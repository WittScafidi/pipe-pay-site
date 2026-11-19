<?php
/**
 * Plugin Name: Pipe Pay - Update Endpoint Paid-Conversion Fallback
 * Description: Serves the update package to stores that activated under the
 *              7-Day Trial and later bought a paid plan. Those stores keep
 *              polling with the trial's product id and have no activation row
 *              under the paid license, so Kestrel's standard path (and the
 *              instance fallback) return an empty package URL.
 * Version:     1.0.0
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
 * and WordPress shows "Update package not available".
 *
 * Diagnosed 2026-11-18 on purepharmas.com, osl.bio and swiftchems.com.
 *
 * What it does
 * ------------
 * Runs after the standard path and after pipepay-update-instance-fallback.php
 * (priority 20). Only when the package URL is still empty, it serves the paid
 * order's secure download URL if ALL of these hold:
 *
 *   1. The request carries a well-formed api_key, instance and object (host).
 *   2. The key is not on the revoke list and is under the hourly rate limit.
 *   3. The key owns a resource that is active=1 and not past access_expires.
 *   4. The revalidation log shows this exact instance revalidating as `active`
 *      against that resource, from this host, within the lookback window. That
 *      log is written only by the store's own daily revalidation call.
 *   5. The instance fits inside the license's seat cap: seats already used by
 *      real activation rows plus seats claimed here never exceed
 *      activations_purchased_total. Seats are claimed oldest-instance-first,
 *      so the outcome is stable from poll to poll.
 *
 * It never writes license data. The product id in the request is ignored on
 * purpose: these stores send the trial's id.
 *
 * @package PipePaySite
 */

defined( 'ABSPATH' ) || exit;

const PIPEPAY_PAID_CONV_LOOKBACK_DAYS  = 30;
const PIPEPAY_PAID_CONV_PER_KEY_LIMIT  = 30;
const PIPEPAY_PAID_CONV_RATE_PREFIX    = 'pipepay_paid_conv_rl_';
const PIPEPAY_PAID_CONV_LOG_PREFIX     = 'pipepay_paid_conv_log_';

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
	$url = pipepay_paid_conversion_resolve_url();
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
	$url = pipepay_paid_conversion_resolve_url();
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
 * Which instances may use the license's free seats.
 *
 * Pure function so the seat rule can be tested without a database.
 *
 * @param string[] $log_instances  Instances seen revalidating as active, oldest first.
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
 * Decide whether this request is a paid-conversion store and, if so, return
 * the secure download URL for its paid order.
 */
function pipepay_paid_conversion_resolve_url(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public license API, authenticated by api_key.
	$api_key  = isset( $_GET['api_key'] ) ? sanitize_text_field( wp_unslash( $_GET['api_key'] ) ) : '';
	$instance = isset( $_GET['instance'] ) ? sanitize_text_field( wp_unslash( $_GET['instance'] ) ) : '';
	$object   = isset( $_GET['object'] ) ? sanitize_text_field( wp_unslash( $_GET['object'] ) ) : '';
	// phpcs:enable

	if ( ! preg_match( '/^[A-Za-z0-9_-]{16,128}$/', $api_key ) || ! preg_match( '/^[A-Za-z0-9_-]{8,128}$/', $instance ) ) {
		return '';
	}
	$host = pipepay_paid_conversion_host( $object );
	if ( '' === $host ) {
		return '';
	}

	$rl_key   = PIPEPAY_PAID_CONV_RATE_PREFIX . md5( $api_key );
	$rl_count = (int) get_transient( $rl_key );
	set_transient( $rl_key, $rl_count + 1, HOUR_IN_SECONDS );
	if ( $rl_count >= PIPEPAY_PAID_CONV_PER_KEY_LIMIT ) {
		return '';
	}

	$revoked = get_option( 'pipepay_revoked_licenses', array() );
	if ( is_array( $revoked ) && array_key_exists( $api_key, $revoked ) ) {
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
	if ( empty( $resources ) ) {
		return '';
	}

	$since = gmdate( 'Y-m-d H:i:s', $now - ( PIPEPAY_PAID_CONV_LOOKBACK_DAYS * DAY_IN_SECONDS ) );

	foreach ( $resources as $resource ) {
		$resource_id = (int) $resource->api_resource_id;

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

		// Seat cap: real activations first, then log-proven instances oldest first.
		$activated = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT instance FROM {$wpdb->prefix}wc_am_api_activation WHERE api_resource_id = %d",
				$resource_id
			)
		);
		$log_instances = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT instance FROM {$wpdb->prefix}pipepay_revalidate_log
				 WHERE api_resource_id = %d AND state = 'active' AND created_at >= %s
				 GROUP BY instance ORDER BY MIN(id) ASC",
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
			continue;
		}

		$download_url = (string) WC_AM_ORDER_DATA_STORE()->get_secure_order_download_url(
			(int) $resource->user_id,
			(int) $resource->order_id,
			(int) $resource->product_id
		);
		if ( '' === $download_url ) {
			continue;
		}

		// One log line per resource + host per day.
		$log_key = PIPEPAY_PAID_CONV_LOG_PREFIX . md5( $resource_id . '|' . $host );
		if ( ! get_transient( $log_key ) ) {
			set_transient( $log_key, $now, DAY_IN_SECONDS );
			$context = array(
				'resource_id'   => $resource_id,
				'product_id'    => (int) $resource->product_id,
				'object'        => $host,
				'api_key_last4' => substr( $api_key, -4 ),
				'instance'      => substr( $instance, 0, 8 ) . '...',
			);
			if ( function_exists( 'pipepay_log' ) ) {
				pipepay_log( 'info', 'wc-am: served package URL to a trial-to-paid store with no activation row', $context );
			} else {
				error_log( 'PIPEPAY_PAID_CONVERSION ' . wp_json_encode( $context ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		return $download_url;
	}

	return '';
}
