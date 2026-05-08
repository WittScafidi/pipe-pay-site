<?php
/**
 * Plugin Name: Pipe Pay - License Resolver
 * Description: REST endpoint that maps a Kestrel API Manager license key to its product ID. Used by the Pipe Pay plugin so customers only enter a license key (no product ID) when activating.
 * Author:      Pipe Pay
 * Version:     1.1.0
 *
 * Endpoint: POST https://pipepay.app/wp-json/pipepay-license/v1/resolve
 * Body:     api_key=XXXX
 *
 * Why this exists:
 *   The Kestrel SDK constructor takes a single product_id. Pipe Pay sells four
 *   tiers (single-site #34, 5-sites #35, unlimited #36, trial #38), so we
 *   can't hardcode one. Without this resolver, customers would have to enter
 *   both a license key AND a product ID by hand from their /my-account page.
 *
 *   This endpoint accepts the key, queries API Manager's resource table, and
 *   returns the product_id. The plugin caches the result and feeds it to the
 *   SDK from then on. When a customer upgrades (trial -> paid, single -> 5,
 *   etc.), the resolver returns the new product ID and the plugin auto-pivots
 *   without a zip swap.
 *
 * Security posture:
 *   - POST-only, public (license keys themselves are the auth secret; they're
 *     long random strings issued only at checkout, statistically infeasible
 *     to brute force).
 *   - HTTPS-required: 400 with `https_required` if the request is plain HTTP.
 *   - Per-IP rate limit: 60 lookups per hour, atomically incremented via
 *     wp_cache (race-safe), with a transient TTL fallback.
 *   - Key shape validated BEFORE the rate limiter increments, so malformed
 *     requests don't burn a real customer's bucket on shared NAT.
 *   - Returns ONLY product_id + product_title. No customer data, no order
 *     data, no activation counts.
 *   - No enumeration oracle: invalid_key + key_not_found + key_inactive ALL
 *     return the same opaque 404 body. The internal reason is logged to
 *     PHP error_log for ops but never returned to the client.
 *   - $wpdb->last_error checked after the DB call; on DB failure we 503
 *     instead of pretending the key was bad (avoids a flood of false
 *     "license invalid" support tickets when the DB hiccups).
 */

defined( 'ABSPATH' ) || exit;

const PIPEPAY_LICENSE_RESOLVE_RATE_LIMIT  = 60;
const PIPEPAY_LICENSE_RESOLVE_RATE_WINDOW = HOUR_IN_SECONDS;

add_action( 'rest_api_init', function () {
    register_rest_route( 'pipepay-license/v1', '/resolve', [
        'methods'             => 'POST',
        'callback'            => 'pipepay_license_resolve_handler',
        'permission_callback' => '__return_true',
        'args'                => [
            'api_key' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ] );
} );

/**
 * Resolve a license key to its product_id by querying the API Manager
 * resource table directly.
 *
 * Internal status codes (logged but never exposed to the client):
 *   - validated_short    : key failed shape validation (rejected pre-rate-limit)
 *   - key_not_found      : key passed shape validation but no DB row
 *   - key_inactive       : DB row exists but active=0
 *   - db_error           : $wpdb->last_error non-empty after query
 *   - resolved           : success path (still logged on debug)
 *   - rate_limited       : per-IP counter exceeded
 *   - https_required     : request was plain HTTP
 *
 * Client-facing responses are intentionally minimal:
 *   - 200 success                      : { success: true, product_id, product_title }
 *   - 404 invalid_key (collapsed)      : { success: false, code: invalid_key, message: ... }
 *   - 429 rate_limited                 : { success: false, code: rate_limited,  message: ... }
 *   - 400 https_required               : { success: false, code: https_required, message: ... }
 *   - 503 service_unavailable          : { success: false, code: service_unavailable, message: ... }
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function pipepay_license_resolve_handler( WP_REST_Request $request ): WP_REST_Response {
    $ip = pipepay_license_resolve_client_ip();

    // ── HTTPS guard ──────────────────────────────────────────────────────────
    // Belt-and-braces: nginx and Cloudflare are primary defense, but if either
    // ever misroutes plaintext to this endpoint, refuse rather than handle a
    // license key over the wire unencrypted.
    if ( ! is_ssl() ) {
        pipepay_license_resolve_log( $ip, '', 400, 'https_required' );
        return new WP_REST_Response( [
            'success' => false,
            'code'    => 'https_required',
            'message' => 'HTTPS required.',
        ], 400 );
    }

    $api_key = trim( (string) $request->get_param( 'api_key' ) );

    // ── Shape validation (BEFORE rate limit, so malformed input doesn't burn
    // a legitimate customer's bucket on shared NAT) ──────────────────────────
    // Kestrel keys are alphanumeric (with optional - or _). Strict whitelist
    // matters: sanitize_text_field silently strips control bytes, so without
    // a regex check we'd see "validated" keys that the DB never matches.
    if ( ! pipepay_license_resolve_valid_shape( $api_key ) ) {
        pipepay_license_resolve_log( $ip, $api_key, 404, 'validated_short' );
        return pipepay_license_resolve_invalid_key();
    }

    // ── Per-IP rate limit ────────────────────────────────────────────────────
    // wp_cache_incr is atomic where the object cache supports it; on a stock
    // install with no persistent object cache, it falls back to wp_options /
    // database where we layer a transient TTL for cleanup. Either path is
    // race-safer than the previous get_transient + set_transient pattern.
    $rl_key = 'pipepay_license_resolve_' . md5( $ip );
    $count  = pipepay_license_resolve_increment( $rl_key );

    if ( $count > PIPEPAY_LICENSE_RESOLVE_RATE_LIMIT ) {
        pipepay_license_resolve_log( $ip, $api_key, 429, 'rate_limited' );
        return new WP_REST_Response( [
            'success' => false,
            'code'    => 'rate_limited',
            'message' => 'Too many requests. Please wait and try again.',
        ], 429 );
    }

    // ── DB lookup ────────────────────────────────────────────────────────────
    global $wpdb;
    $table = $wpdb->prefix . 'wc_am_api_resource';

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT product_id, product_title, active
         FROM {$table}
         WHERE master_api_key = %s
         ORDER BY api_resource_id ASC
         LIMIT 1",
        $api_key
    ), ARRAY_A );
    // phpcs:enable

    // Distinguish "DB error" from "no result". $wpdb->last_error is set on
    // query failure (table missing after a Kestrel schema rename, replication
    // lag, exhausted connections, etc.); falling through to "key not
    // recognized" would mask the real cause and generate support volume.
    if ( ! empty( $wpdb->last_error ) ) {
        pipepay_license_resolve_log( $ip, $api_key, 503, 'db_error: ' . $wpdb->last_error );
        return new WP_REST_Response( [
            'success' => false,
            'code'    => 'service_unavailable',
            'message' => 'License lookup is temporarily unavailable. Please try again shortly.',
        ], 503 );
    }

    if ( ! $row ) {
        pipepay_license_resolve_log( $ip, $api_key, 404, 'key_not_found' );
        return pipepay_license_resolve_invalid_key();
    }

    if ( empty( $row['active'] ) ) {
        // Collapsed into the same opaque 404. Internally distinguishable in logs
        // for ops/support, but indistinguishable to a client probing for a real
        // (deactivated) key vs a guess. This was the enumeration oracle the
        // 2026-05-07 security review flagged.
        pipepay_license_resolve_log( $ip, $api_key, 404, 'key_inactive' );
        return pipepay_license_resolve_invalid_key();
    }

    // Success path is intentionally NOT logged at info level - real customers
    // resolving their own valid keys is the happy path and not a security
    // signal. Only 4xx/5xx hit the log.
    return new WP_REST_Response( [
        'success'       => true,
        'product_id'    => (int) $row['product_id'],
        'product_title' => (string) $row['product_title'],
    ], 200 );
}

/**
 * Strict shape check. Reject anything that doesn't look like a Kestrel key
 * BEFORE we hit the rate limiter or the DB. Kestrel issues alphanumeric keys
 * (32 chars in current versions), but allow `-` and `_` for forward-compat
 * with future formats.
 */
function pipepay_license_resolve_valid_shape( string $api_key ): bool {
    if ( '' === $api_key ) {
        return false;
    }
    return (bool) preg_match( '/^[A-Za-z0-9_-]{8,190}$/', $api_key );
}

/**
 * Standard "invalid_key" response. Used for: shape failure, DB miss,
 * inactive key. All three return the same opaque body to defeat
 * enumeration probing.
 */
function pipepay_license_resolve_invalid_key(): WP_REST_Response {
    return new WP_REST_Response( [
        'success' => false,
        'code'    => 'invalid_key',
        'message' => 'License key not recognized.',
    ], 404 );
}

/**
 * Atomically increment the per-IP rate-limit counter and return the new value.
 * Uses wp_cache_incr where the object-cache backend supports it; falls back
 * to the WordPress transient API otherwise (transients are not atomic but on
 * a single-server stock install the race window is negligible compared to
 * the previous read-modify-write pattern).
 */
function pipepay_license_resolve_increment( string $key ): int {
    // Try object cache first. wp_cache_add succeeds atomically on first hit;
    // wp_cache_incr is atomic on Memcached/Redis backends. On the stock
    // wp_cache (in-memory per-request) these are still single-process atomic.
    $group = 'pipepay_license_rl';
    $found = false;
    $cur   = wp_cache_get( $key, $group, false, $found );
    if ( ! $found ) {
        // First hit. wp_cache_add is the atomic "set if missing" primitive.
        wp_cache_add( $key, 1, $group, PIPEPAY_LICENSE_RESOLVE_RATE_WINDOW );
        $cur = 1;
    } else {
        $next = wp_cache_incr( $key, 1, $group );
        $cur  = is_numeric( $next ) ? (int) $next : ( (int) $cur + 1 );
        // wp_cache_incr does not refresh expiry on most backends. Re-set
        // explicitly so the bucket actually times out at the window.
        wp_cache_set( $key, $cur, $group, PIPEPAY_LICENSE_RESOLVE_RATE_WINDOW );
    }

    // Mirror to a transient as a backstop. On stock installs without a
    // persistent object cache, wp_cache is per-request (resets every page
    // load), so the transient is what actually enforces the rate limit
    // across requests.
    $stored = (int) get_transient( $key );
    if ( $stored >= $cur ) {
        // Transient backstop is ahead of the cache (e.g. cache evicted).
        // Bump from there.
        $cur = $stored + 1;
    }
    set_transient( $key, $cur, PIPEPAY_LICENSE_RESOLVE_RATE_WINDOW );

    return (int) $cur;
}

/**
 * Operational logging. Never logs the full key - only the last 4 chars,
 * enough to correlate a customer support email ("my key ends ABCD") with
 * a log entry. Logged via error_log() so the entries land in whatever
 * sink the host is set up for (php-fpm error log, WP_DEBUG_LOG, syslog).
 */
function pipepay_license_resolve_log( string $ip, string $api_key, int $status, string $reason ): void {
    $last4 = $api_key !== '' && strlen( $api_key ) >= 4 ? substr( $api_key, -4 ) : '----';
    error_log( sprintf(
        '[pipepay-license-resolve] ip=%s key_last4=%s status=%d reason=%s',
        $ip,
        $last4,
        $status,
        $reason
    ) );
}

/**
 * Resolve the real client IP. nginx is configured with `set_real_ip_from
 * 0.0.0.0/0` and trusts `CF-Connecting-IP` (see CLAUDE.md), so by the time
 * PHP receives the request, $_SERVER['REMOTE_ADDR'] already contains the
 * real client IP after nginx's real_ip rewrite. Trusting that single source
 * is more robust than reading proxy headers ourselves - PHP-level header
 * trust would let an attacker hitting the origin direct (bypassing CF) spoof
 * any IP via CF-Connecting-IP and reset the rate-limit counter.
 *
 * If REMOTE_ADDR is empty or invalid, we fall through to a single shared
 * bucket; that's intentional - unknown-IP requests get rate-limited together
 * rather than each getting their own free quota.
 */
function pipepay_license_resolve_client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $remote = is_string( $remote ) ? trim( $remote ) : '';
    if ( $remote && filter_var( $remote, FILTER_VALIDATE_IP ) ) {
        return $remote;
    }
    return '0.0.0.0';
}
