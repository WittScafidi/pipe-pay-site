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
 */
function pipepay_phash_over_limit( $bucket_key, $limit, $window_seconds ) {
	$key   = 'pipepay_phash_rl_' . md5( $bucket_key );
	$group = 'pipepay_phash_rl';
	$found = false;
	$cur   = wp_cache_get( $key, $group, false, $found );
	if ( ! $found ) {
		wp_cache_add( $key, 1, $group, $window_seconds );
		$cur = 1;
	} else {
		$next = wp_cache_incr( $key, 1, $group );
		$cur  = is_numeric( $next ) ? (int) $next : ( (int) $cur + 1 );
		wp_cache_set( $key, $cur, $group, $window_seconds );
	}
	$stored = (int) get_transient( $key );
	if ( $stored >= $cur ) {
		$cur = $stored + 1;
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
 */
function pipepay_phash_gate( WP_REST_Request $request ) {
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

	if ( pipepay_phash_over_limit( 'bucket|' . $bucket, PIPEPAY_PHASH_BUCKET_LIMIT, DAY_IN_SECONDS ) ) {
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

	$hash = isset( $request['hash'] ) ? (string) $request['hash'] : '';
	if ( ! pipepay_phash_is_storable_hash( $hash ) ) {
		return new WP_REST_Response( array( 'success' => false, 'code' => 'invalid_hash' ), 400 );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'pipepay_screenshot_hashes';

	// Seen on a DIFFERENT store? (check before our own insert is equivalent to
	// after, because we exclude our own bucket either way)
	$seen = (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT 1 FROM {$table} WHERE hash = %s AND store_bucket != %s LIMIT 1",
		$hash, $gate['bucket']
	) );

	$wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO {$table} (hash, store_bucket, first_seen) VALUES (%s, %s, UTC_TIMESTAMP())",
		$hash, $gate['bucket']
	) );

	if ( '' !== (string) $wpdb->last_error ) {
		$ip = pipepay_phash_client_ip();
		pipepay_phash_log( $ip, isset( $request['api_key'] ) ? (string) $request['api_key'] : '', 503, 'db_error: ' . $wpdb->last_error );
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
	$gate = pipepay_phash_gate( $request );
	if ( $gate instanceof WP_REST_Response ) {
		return $gate;
	}

	$hashes = $request['hashes'];
	if ( ! is_array( $hashes ) || empty( $hashes ) || count( $hashes ) > PIPEPAY_PHASH_BULK_MAX ) {
		return new WP_REST_Response( array( 'success' => false, 'code' => 'invalid_hashes' ), 400 );
	}

	global $wpdb;
	$table  = $wpdb->prefix . 'pipepay_screenshot_hashes';
	$stored = 0;

	foreach ( array_unique( array_map( 'strval', $hashes ) ) as $hash ) {
		if ( ! pipepay_phash_is_storable_hash( $hash ) ) {
			continue; // skip silently: historical meta may contain degenerates
		}
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (hash, store_bucket, first_seen) VALUES (%s, %s, UTC_TIMESTAMP())",
			$hash, $gate['bucket']
		) );
		if ( $wpdb->rows_affected > 0 ) {
			$stored++;
		}
	}

	return new WP_REST_Response( array( 'success' => true, 'stored' => $stored ), 200 );
}
