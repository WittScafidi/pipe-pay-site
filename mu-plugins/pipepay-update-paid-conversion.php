<?php
/**
 * Plugin Name: Pipe Pay - Update Endpoint Paid-Conversion Fallback
 * Description: Serves the update package to stores that activated under one
 *              product (usually the 7-Day Trial) and now own a different,
 *              paid one. Those stores keep polling with the old product id
 *              and have no activation row under the paid license, so
 *              Kestrel's standard path (and the instance fallback) return an
 *              empty package URL.
 * Version:     1.1.0
 * Author:      Pipe Pay
 * Author URI:  https://pipepay.app
 *
 * Why this exists
 * ----------------
 * The plugin stores its product id once, at activation. A store that activates
 * on the trial (product 38) and then buys a paid plan never re-activates: the
 * master API key is per user, so daily revalidation resolves to the new paid
 * resource and the store keeps working. But when the trial expires, API Manager
 * deletes the trial resource together with its activation rows, and nothing
 * creates an activation under the paid resource (activations_total stays 0).
 * From then on every update poll gets `new_version` with an empty `package`,
 * and WordPress shows "Update package not available". The same happens to a
 * store bound to a lapsed monthly product whose owner later buys an annual one.
 *
 * Diagnosed 2026-11-18 on purepharmas.com, osl.bio and swiftchems.com.
 *
 * What it does
 * ------------
 * Runs after the standard path and after pipepay-update-instance-fallback.php
 * (priority 20). Only when the package URL is still empty, it serves the paid
 * order's secure download URL if ALL of these hold:
 *
 *   1. The request carries a well-formed api_key, instance, object (host) and
 *      product_id.
 *   2. The key owns a resource that is active=1, not past access_expires, and
 *      for a paid Pipe Pay tier (PIPEPAY_PAID_CONV_PAID_PRODUCT_IDS).
 *   3. The request is a CONVERTED store: its product_id differs from that
 *      resource's, and the key has no active resource for the product the
 *      store is bound to. A store polling with the SAME product id as its
 *      license is not served here - that covers an activation someone
 *      deliberately deleted, which must keep ending updates.
 *   4. The key is not on the revoke list (matched case-insensitively, the way
 *      the database matches keys) and is under the hourly rate limit.
 *   5. The paid product currently ships the same version Kestrel just
 *      advertised, so a store is never handed an older zip mid-rollout.
 *   6. The revalidation log shows this exact instance revalidating as `active`
 *      against that resource, from this host, within the lookback window. That
 *      log is written only by the store's own daily revalidation call.
 *   7. The instance fits inside the license's seat cap: seats used by real
 *      activation rows plus seats claimed here never exceed
 *      activations_purchased_total. Among instances still revalidating, seats
 *      go to the one that appears earliest in the whole log (not just the
 *      lookback window), so the order does not flip with the time of day.
 *
 * It never writes license data.
 *
 * Changelog
 * ---------
 * 1.1.0 (2026-11-18) - review fixes: converted-stores-only rule + paid-tier
 *        allowlist; case-insensitive revoke check; fixed-window rate limit
 *        counted once per request and only for real keys; advertised-version
 *        guard; stable seat order; refusal logging.
 * 1.0.0 (2026-11-18) - initial.
 *
 * @package PipePaySite
 */

defined( 'ABSPATH' ) || exit;

const PIPEPAY_PAID_CONV_LOOKBACK_DAYS    = 30;
const PIPEPAY_PAID_CONV_PER_KEY_LIMIT    = 30;
const PIPEPAY_PAID_CONV_RATE_WINDOW      = 3600;
const PIPEPAY_PAID_CONV_RATE_PREFIX      = 'pipepay_paid_conv_rl_';
const PIPEPAY_PAID_CONV_LOG_PREFIX       = 'pipepay_paid_conv_log_';
// Annual 34/35/36 and monthly 526/527/528. The trial (38) is deliberately absent.
const PIPEPAY_PAID_CONV_PAID_PRODUCT_IDS = array( 34, 35, 36, 526, 527, 528 );

add_filter( 'wc_api_manager_update_data', 'pipepay_paid_conversion_json', 30, 1 );
add_filter( 'wc_api_manager_api_update_response', 'pipepay_paid_conversion_serial', 30, 1 );

/**
 * JSON update response (the shape the Pipe Pay plugin reads).
 *
 * @param mixed $data Kestrel's update payload.
 * @return mixed
 */
function pipepay_paid_conversion_json( $data ) {
	if ( ! is_array( $data ) || ! empty( $data['package']['package'] ) || empty( $data['package']['new_version'] ) ) {
		return $data;
	}
	$url = pipepay_paid_conversion_resolve_url( (string) $data['package']['new_version'] );
	if ( '' !== $url ) {
		$data['package']['package'] = $url;
	}
	return $data;
}

/**
 * Serialized update response (legacy SDK shape).
 *
 * @param mixed $response Kestrel's update object.
 * @return mixed
 */
function pipepay_paid_conversion_serial( $response ) {
	if ( ! is_object( $response ) || ! empty( $response->package ) || empty( $response->new_version ) ) {
		return $response;
	}
	$url = pipepay_paid_conversion_resolve_url( (string) $response->new_version );
	if ( '' !== $url ) {
		$response->package = $url;
	}
	return $response;
}

/**
 * Lowercased host of a URL or bare host string.
 */
function pipepay_paid_conversion_host( string $value ): string {
	$value = strtolower( trim( $value ) );
	if ( '' === $value ) {
		return '';
	}
	if ( false === strpos( $value, '://' ) ) {
		$value = 'https://' . $value;
	}
	$host = wp_parse_url( $value, PHP_URL_HOST );
	return is_string( $host ) ? $host : '';
}

/**
 * Is this key on the revoke list? Case-insensitive on purpose.
 *
 * wc_am_api_resource.master_api_key uses a case-insensitive collation, so
 * "CK_ABC" finds the row for "ck_abc". An exact-case list lookup would let a
 * revoked key through with one letter flipped.
 *
 * @param string $api_key Key from the request.
 * @param mixed  $list    The pipepay_revoked_licenses option (assoc array keyed by key).
 */
function pipepay_paid_conversion_key_is_revoked( string $api_key, $list ): bool {
	if ( ! is_array( $list ) || '' === $api_key ) {
		return false;
	}
	$needle = strtolower( $api_key );
	foreach ( array_keys( $list ) as $revoked_key ) {
		if ( strtolower( (string) $revoked_key ) === $needle ) {
			return true;
		}
	}
	return false;
}

/**
 * Is this request from a converted store that this fallback should serve?
 *
 * @param int   $request_pid  product_id the store sent (what it activated under).
 * @param int   $resource_pid product_id of the candidate license resource.
 * @param int[] $active_pids  product ids of every active, unexpired resource the key owns.
 * @param int[] $paid_ids     paid Pipe Pay tier product ids.
 */
function pipepay_paid_conversion_is_converted( int $request_pid, int $resource_pid, array $active_pids, array $paid_ids ): bool {
	if ( $request_pid <= 0 || ! in_array( $resource_pid, $paid_ids, true ) ) {
		return false;
	}
	if ( $request_pid === $resource_pid ) {
		return false;
	}
	// The product the store is bound to is still live: Kestrel's own path owns that case.
	return ! in_array( $request_pid, array_map( 'intval', $active_pids ), true );
}

/**
 * One step of a fixed-window rate limit. Pure.
 *
 * The window starts at the first hit and ends PIPEPAY_PAID_CONV_RATE_WINDOW
 * seconds later no matter how many hits arrive, so steady polling can never
 * hold a key in the refused state.
 *
 * @param mixed $state  Previous state ({start, count}) or anything else for "none".
 * @param int   $now    Current unix time.
 * @param int   $limit  Hits allowed per window.
 * @param int   $window Window length in seconds.
 * @return array{allowed: bool, state: array{start: int, count: int}}
 */
function pipepay_paid_conversion_rate_step( $state, int $now, int $limit, int $window ): array {
	$valid = is_array( $state ) && isset( $state['start'], $state['count'] );
	if ( ! $valid || ( $now - (int) $state['start'] ) >= $window ) {
		return array(
			'allowed' => true,
			'state'   => array(
				'start' => $now,
				'count' => 1,
			),
		);
	}
	$count = (int) $state['count'] + 1;
	return array(
		'allowed' => $count <= $limit,
		'state'   => array(
			'start' => (int) $state['start'],
			'count' => $count,
		),
	);
}

/**
 * Which instances may use the license's free seats.
 *
 * Pure function so the seat rule can be tested without a database.
 *
 * @param string[] $log_instances  Instances still revalidating as active, earliest first.
 * @param string[] $activated      Instances that already hold a real activation row.
 * @param int      $seat_cap       activations_purchased_total for the resource.
 * @return string[] Instances allowed through this fallback.
 */
function pipepay_paid_conversion_allowed_instances( array $log_instances, array $activated, int $seat_cap ): array {
	$activated = array_values( array_unique( array_filter( array_map( 'strval', $activated ), 'strlen' ) ) );
	$free      = $seat_cap - count( $activated );
	if ( $free <= 0 ) {
		return array();
	}
	$candidates = array();
	foreach ( $log_instances as $instance ) {
		$instance = (string) $instance;
		if ( '' === $instance || in_array( $instance, $activated, true ) || in_array( $instance, $candidates, true ) ) {
			continue;
		}
		$candidates[] = $instance;
	}
	return array_slice( $candidates, 0, $free );
}

/**
 * Decide whether this request is a converted store and, if so, return the
 * secure download URL for its paid order.
 *
 * API Manager applies the update filter twice per request (once to fill its
 * cache, once for the response), so the answer is memoized per request: the
 * queries, the rate-limit hit and the URL minting happen once.
 *
 * @param string $advertised_version new_version Kestrel is about to return.
 */
function pipepay_paid_conversion_resolve_url( string $advertised_version ): string {
	static $memo = array();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public license API, authenticated by api_key.
	$api_key    = isset( $_GET['api_key'] ) ? sanitize_text_field( wp_unslash( $_GET['api_key'] ) ) : '';
	$instance   = isset( $_GET['instance'] ) ? sanitize_text_field( wp_unslash( $_GET['instance'] ) ) : '';
	$object     = isset( $_GET['object'] ) ? sanitize_text_field( wp_unslash( $_GET['object'] ) ) : '';
	$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
	// phpcs:enable

	$memo_key = md5( strtolower( $api_key ) . '|' . $instance . '|' . $object . '|' . $product_id . '|' . $advertised_version );
	if ( ! array_key_exists( $memo_key, $memo ) ) {
		$memo[ $memo_key ] = pipepay_paid_conversion_resolve_url_uncached( $api_key, $instance, $object, $product_id, $advertised_version );
	}
	return $memo[ $memo_key ];
}

/**
 * The actual gate. See the file header for the numbered conditions.
 */
function pipepay_paid_conversion_resolve_url_uncached( string $api_key, string $instance, string $object, int $product_id, string $advertised_version ): string {
	if ( ! preg_match( '/^[A-Za-z0-9_-]{16,128}$/', $api_key ) || ! preg_match( '/^[A-Za-z0-9_-]{8,128}$/', $instance ) ) {
		return '';
	}
	$host = pipepay_paid_conversion_host( $object );
	if ( '' === $host || $product_id <= 0 || '' === $advertised_version ) {
		return '';
	}
	if ( ! function_exists( 'WC_AM_ORDER_DATA_STORE' ) ) {
		return '';
	}

	global $wpdb;
	$now       = time();
	$resources = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT api_resource_id, product_id, order_id, user_id, access_expires, activations_purchased_total
			 FROM {$wpdb->prefix}wc_am_api_resource
			 WHERE master_api_key = %s AND active = 1 AND ( access_expires = 0 OR access_expires > %d )
			 ORDER BY access_expires DESC, api_resource_id ASC",
			$api_key,
			$now
		)
	);
	// Unknown key: stop before any option or transient is touched, so random
	// keys cannot be used to write rows.
	if ( empty( $resources ) ) {
		return '';
	}

	if ( pipepay_paid_conversion_key_is_revoked( $api_key, get_option( 'pipepay_revoked_licenses', array() ) ) ) {
		return '';
	}

	// Fixed-window limit, one bucket per key regardless of letter case.
	$rl_key = PIPEPAY_PAID_CONV_RATE_PREFIX . md5( strtolower( $api_key ) );
	$step   = pipepay_paid_conversion_rate_step( get_transient( $rl_key ), $now, PIPEPAY_PAID_CONV_PER_KEY_LIMIT, PIPEPAY_PAID_CONV_RATE_WINDOW );
	set_transient( $rl_key, $step['state'], PIPEPAY_PAID_CONV_RATE_WINDOW );
	if ( ! $step['allowed'] ) {
		return '';
	}

	$paid_ids    = array_map( 'intval', (array) apply_filters( 'pipepay_paid_conversion_paid_product_ids', PIPEPAY_PAID_CONV_PAID_PRODUCT_IDS ) );
	$active_pids = array();
	foreach ( $resources as $resource ) {
		$active_pids[] = (int) $resource->product_id;
	}
	$since = gmdate( 'Y-m-d H:i:s', $now - ( PIPEPAY_PAID_CONV_LOOKBACK_DAYS * DAY_IN_SECONDS ) );

	foreach ( $resources as $resource ) {
		$resource_id  = (int) $resource->api_resource_id;
		$resource_pid = (int) $resource->product_id;
		$same_product = ( $resource_pid === $product_id );

		if ( ! $same_product && ! pipepay_paid_conversion_is_converted( $product_id, $resource_pid, $active_pids, $paid_ids ) ) {
			continue;
		}

		// This instance, from this host, must have revalidated as active against this resource.
		$seen_urls = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT site_url FROM {$wpdb->prefix}pipepay_revalidate_log
				 WHERE api_resource_id = %d AND instance = %s AND state = 'active' AND created_at >= %s",
				$resource_id,
				$instance,
				$since
			)
		);
		$host_seen = false;
		foreach ( (array) $seen_urls as $seen_url ) {
			if ( pipepay_paid_conversion_host( (string) $seen_url ) === $host ) {
				$host_seen = true;
				break;
			}
		}
		if ( ! $host_seen ) {
			continue;
		}

		$context = array(
			'resource_id'    => $resource_id,
			'license_pid'    => $resource_pid,
			'request_pid'    => $product_id,
			'object'         => $host,
			'api_key_last4'  => substr( $api_key, -4 ),
			'instance'       => substr( $instance, 0, 8 ) . '...',
			'offered'        => $advertised_version,
		);

		if ( $same_product ) {
			// A live store, bound to the product it owns, that Kestrel and the
			// instance fallback both declined. Not ours to serve (it may be a
			// deliberately deleted activation), but worth one log line a day.
			pipepay_paid_conversion_log_once( 'refused_same_product', $resource_id, $host, 'wc-am: update package withheld - store has no activation row under its own product', $context );
			continue;
		}

		// Never hand out an older zip than the version just advertised.
		$ships = (string) get_post_meta( $resource_pid, '_api_new_version', true );
		if ( $ships !== $advertised_version ) {
			$context['license_product_ships'] = $ships;
			pipepay_paid_conversion_log_once( 'refused_version', $resource_id, $host, 'wc-am: update package withheld - paid product is on a different version than the one advertised', $context );
			continue;
		}

		// Seat cap: real activations first, then log-proven instances, earliest in the log first.
		$activated = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT instance FROM {$wpdb->prefix}wc_am_api_activation WHERE api_resource_id = %d",
				$resource_id
			)
		);
		$log_instances = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT instance FROM {$wpdb->prefix}pipepay_revalidate_log
				 WHERE api_resource_id = %d AND state = 'active'
				 GROUP BY instance HAVING MAX(created_at) >= %s
				 ORDER BY MIN(id) ASC",
				$resource_id,
				$since
			)
		);
		$allowed = pipepay_paid_conversion_allowed_instances(
			(array) $log_instances,
			(array) $activated,
			(int) $resource->activations_purchased_total
		);
		if ( ! in_array( $instance, $allowed, true ) ) {
			pipepay_paid_conversion_log_once( 'refused_seats', $resource_id, $host, 'wc-am: update package withheld - no free seat for this converted store', $context );
			continue;
		}

		$download_url = (string) WC_AM_ORDER_DATA_STORE()->get_secure_order_download_url(
			(int) $resource->user_id,
			(int) $resource->order_id,
			$resource_pid
		);
		if ( '' === $download_url ) {
			continue;
		}

		pipepay_paid_conversion_log_once( 'served', $resource_id, $host, 'wc-am: served package URL to a converted store with no activation row', $context );
		return $download_url;
	}

	return '';
}

/**
 * One log line per (event, resource, host) per day.
 *
 * @param string $event       Short event slug.
 * @param int    $resource_id License resource.
 * @param string $host        Requesting host.
 * @param string $message     Log message.
 * @param array  $context     Structured context (never the full key).
 */
function pipepay_paid_conversion_log_once( string $event, int $resource_id, string $host, string $message, array $context ): void {
	$log_key = PIPEPAY_PAID_CONV_LOG_PREFIX . md5( $event . '|' . $resource_id . '|' . $host );
	if ( get_transient( $log_key ) ) {
		return;
	}
	set_transient( $log_key, time(), DAY_IN_SECONDS );
	if ( function_exists( 'pipepay_log' ) ) {
		pipepay_log( 'info', $message, $context );
	} else {
		error_log( 'PIPEPAY_PAID_CONVERSION ' . $message . ' ' . wp_json_encode( $context ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
