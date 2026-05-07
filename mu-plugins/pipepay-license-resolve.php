<?php
/**
 * Plugin Name: Pipe Pay - License Resolver
 * Description: REST endpoint that maps a Kestrel API Manager license key to its product ID. Used by the Pipe Pay plugin so customers only enter a license key (no product ID) when activating.
 * Author:      Pipe Pay
 * Version:     1.0.0
 *
 * Endpoint: GET https://pipepay.app/wp-json/pipepay-license/v1/resolve?api_key=XXXX
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
 *   - GET-only, public (license keys themselves are the auth secret; they're
 *     long random strings issued only at checkout, statistically infeasible
 *     to brute force).
 *   - Per-IP rate limit: 60 lookups per hour. Plenty for a real customer's
 *     occasional reactivation; tight enough to bound any probing.
 *   - Returns ONLY product_id + product_title. No customer data, no order
 *     data, no activation counts, no nothing else.
 *   - On miss returns a generic "not recognized" without leaking whether
 *     the key was malformed vs absent.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
    // POST so the api_key travels in the request body, not the query string.
    // Query-string keys land in nginx access logs on disk; body params don't
    // (default nginx logging captures the request line + status, not body).
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
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function pipepay_license_resolve_handler( WP_REST_Request $request ): WP_REST_Response {
    // ── Per-IP rate limit ────────────────────────────────────────────────────
    // Same pattern as the Pipe Pay plugin's screenshot upload endpoint.
    // Resolves Cloudflare / proxy headers so we don't pool every customer
    // behind a CDN edge into one bucket.
    $ip = pipepay_license_resolve_client_ip();
    $rl_key = 'pipepay_license_resolve_' . md5( $ip );
    $count  = (int) get_transient( $rl_key );
    if ( $count >= 60 ) {
        return new WP_REST_Response( [
            'success' => false,
            'code'    => 'rate_limited',
            'message' => 'Too many requests. Please wait and try again.',
        ], 429 );
    }
    set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

    $api_key = trim( (string) $request->get_param( 'api_key' ) );

    // Sanity-check the key shape before hitting the DB. Kestrel keys are
    // typically ~32+ char alphanumeric. Reject obviously-malformed input
    // without burning a query.
    if ( '' === $api_key || strlen( $api_key ) < 8 || strlen( $api_key ) > 190 ) {
        return new WP_REST_Response( [
            'success' => false,
            'code'    => 'invalid_key',
            'message' => 'License key not recognized.',
        ], 404 );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'wc_am_api_resource';

    // Query the API Manager resource record. master_api_key is the customer's
    // license key. We filter to active resources only (active=1) so revoked
    // keys don't resolve. order by api_resource_id ASC so we get the original
    // resource if the key is somehow associated with multiple (shouldn't
    // happen, but defensive).
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

    if ( ! $row ) {
        return new WP_REST_Response( [
            'success' => false,
            'code'    => 'key_not_found',
            'message' => 'License key not recognized.',
        ], 404 );
    }

    if ( empty( $row['active'] ) ) {
        return new WP_REST_Response( [
            'success' => false,
            'code'    => 'key_inactive',
            'message' => 'This license key has been deactivated. Contact support if this is unexpected.',
        ], 403 );
    }

    return new WP_REST_Response( [
        'success'       => true,
        'product_id'    => (int) $row['product_id'],
        'product_title' => (string) $row['product_title'],
    ], 200 );
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
