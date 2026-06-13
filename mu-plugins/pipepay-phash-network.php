<?php
/**
 * Plugin Name: Pipe Pay Screenshot Hash Network
 * Description: Cross-store duplicate-screenshot detection endpoint. Stores 64-bit
 * perceptual hashes of customer payment screenshots submitted by licensed Pipe Pay
 * installs and answers "has this exact hash been seen on a DIFFERENT store?".
 * Version: 1.0.0
 *
 * PRIVACY / RTBF POSTURE (load-bearing - do not relax):
 * - The image NEVER reaches this server. A 64-bit dHash is a one-way fingerprint;
 *   no amount, handle, face, or text can be reconstructed from it.
 * - The table stores (hash, store_bucket, first_seen) and NOTHING else. No IPs,
 *   no site URLs, no license keys, no PII. store_bucket is a one-way HMAC of
 *   api_key|instance with a server-side pepper - it exists only so a store's own
 *   resubmissions don't self-flag and so per-store rate limits work. It cannot be
 *   reversed to a merchant. Nothing here is personal data under GDPR/CCPA, so the
 *   RTBF runbook does not need to touch this table.
 * - Security posture mirrors pipepay-license-revalidate.php: HTTPS required,
 *   shape validation BEFORE rate-limit accounting, opaque 404 for invalid keys,
 *   per-IP and per-bucket rate limits, Ed25519-signed responses (prefix phash-v1|,
 *   distinct from v1| / revalidate-v1| / download-v1| so captured signatures
 *   cannot be replayed across contexts).
 */

defined( 'ABSPATH' ) || exit;

define( 'PIPEPAY_PHASH_DB_VERSION', 1 );
define( 'PIPEPAY_PHASH_IP_LIMIT', 120 );        // submits per hour per IP
define( 'PIPEPAY_PHASH_BUCKET_LIMIT', 2000 );   // submits per day per store bucket
define( 'PIPEPAY_PHASH_BULK_MAX', 200 );        // hashes per bulk (backfill) request

/**
 * Idempotent table install, gated by a db-version option (same pattern as
 * pipepay-license-analytics.php).
 */
function pipepay_phash_install_table() {
	if ( (int) get_option( 'pipepay_phash_db_version' ) >= PIPEPAY_PHASH_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = $wpdb->prefix . 'pipepay_screenshot_hashes';
	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		hash CHAR(16) NOT NULL,
		store_bucket CHAR(32) NOT NULL,
		first_seen DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY hash_bucket (hash, store_bucket),
		KEY hash (hash)
	) {$wpdb->get_charset_collate()};" );
	update_option( 'pipepay_phash_db_version', PIPEPAY_PHASH_DB_VERSION );
}
add_action( 'plugins_loaded', 'pipepay_phash_install_table' );

/** Valid lowercase 16-hex dHash, excluding the two degenerate values. */
function pipepay_phash_is_storable_hash( $hash ) {
	return is_string( $hash )
		&& 1 === preg_match( '/^[0-9a-f]{16}$/', $hash )
		&& '0000000000000000' !== $hash
		&& 'ffffffffffffffff' !== $hash;
}

/** One-way per-store bucket. Pepper survives in wp-config if defined; falls back to auth salt. */
function pipepay_phash_bucket( $api_key, $instance ) {
	$pepper = defined( 'PIPEPAY_PHASH_BUCKET_PEPPER' ) ? PIPEPAY_PHASH_BUCKET_PEPPER : wp_salt( 'auth' );
	return substr( hash_hmac( 'sha256', $api_key . '|' . $instance, $pepper ), 0, 32 );
}

/** True when the key belongs to a currently-entitled license (any product). */
function pipepay_phash_license_ok( $api_key ) {
	// Revoke-list check: mirrors pipepay-license-revalidate.php's mechanism.
	// Use the sibling helper when available; otherwise replicate its option lookup.
	if ( function_exists( 'pipepay_license_revalidate_is_revoked' ) ) {
		if ( pipepay_license_revalidate_is_revoked( $api_key ) ) {
			return false;
		}
	} else {
		$list = get_option( 'pipepay_revoked_licenses', array() );
		if ( is_array( $list ) && array_key_exists( $api_key, $list ) ) {
			return false;
		}
	}

	global $wpdb;
	$row = $wpdb->get_var( $wpdb->prepare(
		"SELECT api_resource_id FROM {$wpdb->prefix}wc_am_api_resource
		 WHERE master_api_key = %s AND active = 1
		   AND ( access_expires = 0 OR access_expires > UNIX_TIMESTAMP() )
		 ORDER BY api_resource_id ASC LIMIT 1",
		$api_key
	) );
	return ! empty( $row );
}

/**
 * Transient + object-cache rate limiter shared by both routes.
 * Mirrors pipepay-license-resolve.php's race-safe increment pattern.
 * Returns true when the caller is OVER the limit.
 *
 * @param int $cost How many units this call counts against the limit (default 1).
 *                  The bulk route passes count($hashes) so each hash counts once.
 *                  The per-IP limit always uses cost=1 (one request is one request).
 */
function pipepay_phash_over_limit( $bucket_key, $limit, $window_seconds, $cost = 1 ) {
	$cost  = max( 1, (int) $cost );
	$key   = 'pipepay_phash_rl_' . md5( $bucket_key );
	$group = 'pipepay_phash_rl';
	$found = false;
	$cur   = wp_cache_get( $key, $group, false, $found );
	if ( ! $found ) {
		wp_cache_add( $key, $cost, $group, $window_seconds );
		$cur = $cost;
	} else {
		$next = wp_cache_incr( $key, $cost, $group );
		$cur  = is_numeric( $next ) ? (int) $next : ( (int) $cur + $cost );
		wp_cache_set( $key, $cur, $group, $window_seconds );
	}
	$stored = (int) get_transient( $key );
	if ( $stored >= $cur ) {
		$cur = $stored + $cost;
	}
	set_transient( $key, $cur, $window_seconds );
	return (int) $cur > $limit;
}

/**
 * Ed25519-sign a submit verdict. Canonical: phash-v1|<issued>|<hash>|<0|1>
 * Returns array{sig,issued_at} or null when signing infra is unavailable
 * (plugin treats unsigned as network-unavailable and fails open).
 */
function pipepay_phash_sign_verdict( $hash, $seen_elsewhere ) {
	if ( ! defined( 'PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY' ) || ! function_exists( 'sodium_crypto_sign_detached' ) ) {
		if ( ! defined( 'PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY' ) ) {
			error_log( '[pipepay-phash-network] PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY undefined' );
		}
		return null;
	}
	$issued = time();
	$msg    = sprintf( 'phash-v1|%d|%s|%s', $issued, $hash, $seen_elsewhere ? '1' : '0' );
	$sk     = base64_decode( PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY, true );
	if ( false === $sk || strlen( $sk ) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ) {
		error_log( '[pipepay-phash-network] PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY is missing or wrong length' );
		return null;
	}
	try {
		$sig = base64_encode( sodium_crypto_sign_detached( $msg, $sk ) );
	} catch ( \Throwable $e ) {
		error_log( '[pipepay-phash-network] signing failed: ' . $e->getMessage() );
		sodium_memzero( $sk );
		return null;
	}
	sodium_memzero( $sk );
	return array( 'sig' => $sig, 'issued_at' => $issued );
}

/**
 * Resolve the real client IP. nginx is configured with set_real_ip_from 0.0.0.0/0
 * and trusts CF-Connecting-IP, so REMOTE_ADDR already contains the real client IP.
 * Mirrors pipepay-license-resolve.php's _client_ip() helper exactly.
 */
function pipepay_phash_client_ip() {
	$remote = $_SERVER['REMOTE_ADDR'] ?? '';
	$remote = is_string( $remote ) ? trim( $remote ) : '';
	if ( $remote && filter_var( $remote, FILTER_VALIDATE_IP ) ) {
		return $remote;
	}
	return '0.0.0.0';
}

/**
 * Operational logging. Mirrors pipepay-license-resolve.php's log helper:
 * never logs the full key, only the last 4 chars.
 */
function pipepay_phash_log( $ip, $api_key, $status, $reason ) {
	$last4 = $api_key !== '' && strlen( (string) $api_key ) >= 4 ? substr( (string) $api_key, -4 ) : '----';
	error_log( sprintf(
		'[pipepay-phash-network] ip=%s key_last4=%s status=%d reason=%s',
		$ip,
		$last4,
		$status,
		$reason
	) );
}

// ── REST routes ───────────────────────────────────────────────────────────────

add_action( 'rest_api_init', function () {
	register_rest_route( 'pipepay-phash/v1', '/submit', array(
		'methods'             => 'POST',
		'callback'            => 'pipepay_phash_handle_submit',
		'permission_callback' => '__return_true', // auth = license key in body, like the resolver
	) );
	register_rest_route( 'pipepay-phash/v1', '/submit-bulk', array(
		'methods'             => 'POST',
		'callback'            => 'pipepay_phash_handle_submit_bulk',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * Shared front-half: HTTPS, shape, rate limits, license, bucket.
 * Returns array{bucket} on success or WP_REST_Response error to return as-is.
 *
 * @param int $bucket_cost How many units this call charges against the per-store
 *                         bucket limit. Single-submit = 1. Bulk route passes
 *                         count($hashes) so each submitted hash costs one unit,
 *                         keeping the effective daily ceiling at PIPEPAY_PHASH_BUCKET_LIMIT
 *                         hashes regardless of whether they arrive one-at-a-time or in bulk.
 *                         Per-IP limit is always cost=1 (one HTTP request is one request).
 */
function pipepay_phash_gate( WP_REST_Request $request, $bucket_cost = 1 ) {
	$ip = pipepay_phash_client_ip();

	if ( ! is_ssl() ) {
		pipepay_phash_log( $ip, '', 400, 'https_required' );
		return new WP_REST_Response( array( 'success' => false, 'code' => 'https_required' ), 400 );
	}

	$api_key  = isset( $request['api_key'] ) ? (string) $request['api_key'] : '';
	$instance = isset( $request['instance'] ) ? (string) $request['instance'] : '';

	// Shape validation BEFORE rate-limit accounting (resolver lesson: malformed
	// junk must not burn a legitimate store's bucket on shared NAT).
	if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{8,190}$/', $api_key ) || 1 !== preg_match( '/^[A-Za-z0-9]{8,64}$/', $instance ) ) {
		pipepay_phash_log( $ip, $api_key, 404, 'validated_short' );
		return new WP_REST_Response( array( 'success' => false, 'code' => 'invalid_key' ), 404 );
	}

	// Per-IP limit: one HTTP request = one unit regardless of batch size.
	if ( pipepay_phash_over_limit( 'ip|' . $ip, PIPEPAY_PHASH_IP_LIMIT, HOUR_IN_SECONDS ) ) {
		pipepay_phash_log( $ip, $api_key, 429, 'rate_limited_ip' );
		return new WP_REST_Response( array( 'success' => false, 'code' => 'rate_limited' ), 429 );
	}

	if ( ! pipepay_phash_license_ok( $api_key ) ) {
		// Opaque: same body as shape failure (no enumeration oracle).
		pipepay_phash_log( $ip, $api_key, 404, 'key_not_found' );
		return new WP_REST_Response( array( 'success' => false, 'code' => 'invalid_key' ), 404 );
	}

	$bucket = pipepay_phash_bucket( $api_key, $instance );

	// Per-bucket limit: charged at $bucket_cost so bulk calls count each hash
	// against the store's daily quota, not just the request itself.
	if ( pipepay_phash_over_limit( 'bucket|' . $bucket, PIPEPAY_PHASH_BUCKET_LIMIT, DAY_IN_SECONDS, $bucket_cost ) ) {
		pipepay_phash_log( $ip, $api_key, 429, 'rate_limited_bucket' );
		return new WP_REST_Response( array( 'success' => false, 'code' => 'rate_limited' ), 429 );
	}

	return array( 'bucket' => $bucket );
}

/** POST /submit {api_key, instance, hash} -> {success, seen_elsewhere, signature, issued_at} */
function pipepay_phash_handle_submit( WP_REST_Request $request ) {
	$gate = pipepay_phash_gate( $request );
	if ( $gate instanceof WP_REST_Response ) {
		return $gate;
	}

	$ip      = pipepay_phash_client_ip();
	$api_key = isset( $request['api_key'] ) ? (string) $request['api_key'] : '';

	$hash = isset( $request['hash'] ) ? (string) $request['hash'] : '';
	if ( ! pipepay_phash_is_storable_hash( $hash ) ) {
		pipepay_phash_log( $ip, $api_key, 400, 'invalid_hash' );
		return new WP_REST_Response( array( 'success' => false, 'code' => 'invalid_hash' ), 400 );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'pipepay_screenshot_hashes';

	// INSERT first, then SELECT for seen-elsewhere.
	//
	// Why insert-first matters: two different stores submitting the SAME brand-new
	// hash concurrently would BOTH get "not seen elsewhere" if we select first —
	// each sees an empty table before the other's write commits. Inserting first
	// ensures at least one store's row is committed before either runs the SELECT,
	// so the second store will observe the first's row (excluding its own bucket).
	// INSERT IGNORE makes retries idempotent, so the ordering is safe.
	$wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO {$table} (hash, store_bucket, first_seen) VALUES (%s, %s, UTC_TIMESTAMP())",
		$hash, $gate['bucket']
	) );

	// wpdb resets last_error per query, so the INSERT needs its own check —
	// the post-SELECT check below would otherwise mask a failed INSERT.
	if ( '' !== (string) $wpdb->last_error ) {
		pipepay_phash_log( $ip, $api_key, 503, 'db_error: ' . $wpdb->last_error );
		return new WP_REST_Response( array( 'success' => false, 'code' => 'service_unavailable' ), 503 );
	}

	// Seen on a DIFFERENT store's bucket?
	$seen = (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT 1 FROM {$table} WHERE hash = %s AND store_bucket != %s LIMIT 1",
		$hash, $gate['bucket']
	) );

	if ( '' !== (string) $wpdb->last_error ) {
		pipepay_phash_log( $ip, $api_key, 503, 'db_error: ' . $wpdb->last_error );
		return new WP_REST_Response( array( 'success' => false, 'code' => 'service_unavailable' ), 503 );
	}

	$body = array( 'success' => true, 'seen_elsewhere' => $seen );
	$sig  = pipepay_phash_sign_verdict( $hash, $seen );
	if ( null !== $sig ) {
		$body['signature'] = $sig['sig'];
		$body['issued_at'] = $sig['issued_at'];
	}
	return new WP_REST_Response( $body, 200 );
}

/**
 * POST /submit-bulk {api_key, instance, hashes: string[]} -> {success, stored}
 * Write-only seeding path for the plugin's backfill cron. No verdicts, no
 * signature (nothing here drives a runtime decision).
 */
function pipepay_phash_handle_submit_bulk( WP_REST_Request $request ) {
	$ip      = pipepay_phash_client_ip();
	$api_key = isset( $request['api_key'] ) ? (string) $request['api_key'] : '';

	// Validate array shape BEFORE calling the gate so the cost parameter is known
	// and malformed requests don't touch rate-limit accounting.
	$hashes = $request['hashes'];
	if ( ! is_array( $hashes ) || empty( $hashes ) || count( $hashes ) > PIPEPAY_PHASH_BULK_MAX ) {
		pipepay_phash_log( $ip, $api_key, 400, 'invalid_hashes' );
		return new WP_REST_Response( array( 'success' => false, 'code' => 'invalid_hashes' ), 400 );
	}

	// Deduplicate now so the gate charges the actual distinct hash count, not the
	// raw array length. Cap at PIPEPAY_PHASH_BULK_MAX for safety.
	$deduped     = array_values( array_unique( array_map( 'strval', $hashes ) ) );
	$bucket_cost = min( count( $deduped ), PIPEPAY_PHASH_BULK_MAX );

	// Gate: shape already validated above; pass bucket_cost so each hash in this
	// batch counts as one unit against the store's daily PIPEPAY_PHASH_BUCKET_LIMIT.
	$gate = pipepay_phash_gate( $request, $bucket_cost );
	if ( $gate instanceof WP_REST_Response ) {
		return $gate;
	}

	global $wpdb;
	$table  = $wpdb->prefix . 'pipepay_screenshot_hashes';
	$stored = 0;

	foreach ( $deduped as $hash ) {
		if ( ! pipepay_phash_is_storable_hash( $hash ) ) {
			continue; // skip silently: historical meta may contain degenerates
		}
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (hash, store_bucket, first_seen) VALUES (%s, %s, UTC_TIMESTAMP())",
			$hash, $gate['bucket']
		) );
		if ( '' !== (string) $wpdb->last_error ) {
			// 503 on DB error so the backfill client retries the batch.
			// INSERT IGNORE makes retries idempotent — partial inserts are fine.
			pipepay_phash_log( $ip, $api_key, 503, 'db_error' );
			return new WP_REST_Response( array( 'success' => false, 'code' => 'service_unavailable' ), 503 );
		}
		if ( $wpdb->rows_affected > 0 ) {
			$stored++;
		}
	}

	return new WP_REST_Response( array( 'success' => true, 'stored' => $stored ), 200 );
}
