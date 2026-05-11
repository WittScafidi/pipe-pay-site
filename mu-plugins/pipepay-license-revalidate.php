<?php
/**
 * Plugin Name: Pipe Pay - License Revalidator
 * Description: Daily server-side license-state check for the Pipe Pay plugin (v1.8.1+). Returns Ed25519-signed (state) responses where state ∈ {active, expired, revoked}. Revoked is the runtime-disable kill switch with drain semantics; expired is banner-only. v1.1.0 (plugin v1.8.5+): also emits a signed at-rest state envelope in the response body so the plugin can verify the persisted state on every read, defeating direct-option-write bypass.
 * Author:      Pipe Pay
 * Version:     1.1.0
 *
 * Endpoint: POST https://pipepay.app/wp-json/pipepay-license/v1/revalidate
 * Body:     api_key, instance, site_url, plugin_version
 *
 * Why this exists:
 *   The plugin's daily wp-cron job (pipepay_license_revalidate, added in
 *   plugin v1.8.1) calls this endpoint to ask "is this license still in
 *   good standing?" and persists a three-state verdict. Revoked is the
 *   one CLAUDE.md runtime-disable carve-out:
 *     active   - normal operation
 *     expired  - yellow banner only; gateway keeps processing orders
 *                (don't brick in-flight orders rule)
 *     revoked  - red banner + is_available() false at NEW checkouts;
 *                existing in-flight orders drain through the upload +
 *                AI verification path until 60-min auto-cancel.
 *
 *   The plugin caches the verdict for up to 30 days (fail-open TTL). If
 *   we are unreachable that long the stored state degrades to 'unknown'
 *   and the gateway resumes normal operation. So the worst-case impact
 *   of pipepay.app downtime is 30 days of stale-revoked masking, after
 *   which we lose the kill switch on that install. Acceptable.
 *
 * State determination:
 *   - In `pipepay_revoked_licenses` option           → revoked
 *   - wc_am_api_resource.active = 0 (Kestrel disable)→ revoked
 *   - access_expires > 0 && access_expires < now()   → expired
 *   - otherwise                                       → active
 *
 *   Mapping Kestrel-level active=0 to revoked is intentional: refund-
 *   driven auto-deactivation in Kestrel and manual master-key disable
 *   should both stop new checkouts at the customer site. Customers who
 *   paid for the license, never got refunded, and just let renewal
 *   lapse hit the 'expired' branch via access_expires only.
 *
 * Security posture:
 *   - POST-only, public (license keys are the auth secret)
 *   - HTTPS-required: 400 with `https_required` if request is plain HTTP
 *   - Per-IP rate limit: 60 lookups / hour (mirrors the resolver). The
 *     plugin's cron only fires once a day per install so legitimate
 *     traffic is well below this; rate limiting protects against an
 *     attacker probing for valid license keys.
 *   - Key shape validated BEFORE rate-limit increment (shared NAT
 *     friendliness: don't let a malformed-key bot burn a real customer's
 *     bucket).
 *   - No enumeration oracle: invalid_key + key_not_found return the
 *     same opaque 404. Internal reasons logged for ops, never returned
 *     to the client.
 *   - Response signing (Ed25519): success responses include an
 *     X-Pipepay-Signature header over a canonical string binding
 *     (api_key, instance, state). The plugin verifies before trusting
 *     the verdict. The `revalidate-v1|` prefix is distinct from the
 *     activation `v1|` prefix so a captured activation signature can't
 *     be replayed as a revalidation signature. Key material:
 *       Private: PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY constant in wp-config
 *                (shared with the resolver mu-plugin)
 *       Public:  bundled in the plugin source as PIPEPAY_LICENSE_SIGNING_PUBLIC_KEY
 *                in pipepay-license-verify.php
 *
 * Admin tool:
 *   This file also registers a "License Revocation" submenu under
 *   Pipe Pay (the existing pipepay-proofs parent menu, available via
 *   the Pipe Pay plugin running on the dogfood install). Capability:
 *   manage_options. From there an admin can add a license key to the
 *   revoke list with a required reason; the entry is stored in option
 *   `pipepay_revoked_licenses` and audit-logged in
 *   `pipepay_license_revocation_log` (capped at 200 entries).
 *
 *   Unrevoke is also available - use the same form to remove a key
 *   from the list. Both events are logged.
 */

defined( 'ABSPATH' ) || exit;

const PIPEPAY_LICENSE_REVALIDATE_RATE_LIMIT  = 60;
const PIPEPAY_LICENSE_REVALIDATE_RATE_WINDOW = HOUR_IN_SECONDS;

const PIPEPAY_REVOKED_LICENSES_OPT      = 'pipepay_revoked_licenses';
const PIPEPAY_REVOCATION_LOG_OPT        = 'pipepay_license_revocation_log';
const PIPEPAY_REVOCATION_LOG_MAX_ENTRIES = 200;

// ── REST route registration ──────────────────────────────────────────────────
add_action( 'rest_api_init', function () {
    register_rest_route( 'pipepay-license/v1', '/revalidate', array(
        'methods'             => 'POST',
        'callback'            => 'pipepay_license_revalidate_handler',
        'permission_callback' => '__return_true',
        'args'                => array(
            'api_key' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'instance' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'site_url' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ),
            'plugin_version' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ) );
} );

/**
 * Handle a revalidation request from a Pipe Pay v1.8.1+ install.
 *
 * Internal status codes (logged but never exposed to the client beyond the
 * opaque 4xx/5xx codes below):
 *   - validated_short    : key shape failed pre-rate-limit
 *   - key_not_found      : key passed shape validation but no DB row
 *   - db_error           : $wpdb->last_error after query
 *   - resolved_active    : success path, state=active
 *   - resolved_expired   : success path, state=expired
 *   - resolved_revoked   : success path, state=revoked (deliberate or
 *                          via Kestrel master active=0)
 *   - rate_limited       : per-IP counter exceeded
 *   - https_required     : request was plain HTTP
 *
 * Client-facing responses:
 *   - 200 success                : { success: true, state: "active|expired|revoked" }
 *                                 + headers X-Pipepay-Signature, X-Pipepay-Signature-IssuedAt
 *   - 404 invalid_key (collapsed): { success: false, code: invalid_key, ... }
 *   - 429 rate_limited           : { success: false, code: rate_limited, ... }
 *   - 400 https_required         : { success: false, code: https_required, ... }
 *   - 503 service_unavailable    : { success: false, code: service_unavailable, ... }
 */
function pipepay_license_revalidate_handler( WP_REST_Request $request ): WP_REST_Response {
    $ip = pipepay_license_revalidate_client_ip();

    // ── HTTPS guard ──────────────────────────────────────────────────────────
    if ( ! is_ssl() ) {
        pipepay_license_revalidate_log( $ip, '', 400, 'https_required' );
        return new WP_REST_Response( array(
            'success' => false,
            'code'    => 'https_required',
            'message' => 'HTTPS required.',
        ), 400 );
    }

    $api_key  = trim( (string) $request->get_param( 'api_key' ) );
    $instance = trim( (string) $request->get_param( 'instance' ) );

    // ── Shape validation (BEFORE rate limit) ─────────────────────────────────
    if ( ! pipepay_license_revalidate_valid_key_shape( $api_key )
        || ! pipepay_license_revalidate_valid_instance_shape( $instance ) ) {
        pipepay_license_revalidate_log( $ip, $api_key, 404, 'validated_short' );
        return pipepay_license_revalidate_invalid_key();
    }

    // ── Per-IP rate limit ────────────────────────────────────────────────────
    $rl_key = 'pipepay_license_revalidate_' . md5( $ip );
    $count  = pipepay_license_revalidate_increment( $rl_key );

    if ( $count > PIPEPAY_LICENSE_REVALIDATE_RATE_LIMIT ) {
        pipepay_license_revalidate_log( $ip, $api_key, 429, 'rate_limited' );
        return new WP_REST_Response( array(
            'success' => false,
            'code'    => 'rate_limited',
            'message' => 'Too many requests. Please wait and try again.',
        ), 429 );
    }

    // ── DB lookup ────────────────────────────────────────────────────────────
    global $wpdb;
    $table = $wpdb->prefix . 'wc_am_api_resource';

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT master_api_key, product_id, access_expires, active
         FROM {$table}
         WHERE master_api_key = %s
         ORDER BY api_resource_id ASC
         LIMIT 1",
        $api_key
    ), ARRAY_A );
    // phpcs:enable

    if ( ! empty( $wpdb->last_error ) ) {
        pipepay_license_revalidate_log( $ip, $api_key, 503, 'db_error: ' . $wpdb->last_error );
        return new WP_REST_Response( array(
            'success' => false,
            'code'    => 'service_unavailable',
            'message' => 'License lookup is temporarily unavailable. Please try again shortly.',
        ), 503 );
    }

    if ( ! $row ) {
        pipepay_license_revalidate_log( $ip, $api_key, 404, 'key_not_found' );
        return pipepay_license_revalidate_invalid_key();
    }

    // ── State determination ──────────────────────────────────────────────────
    $state = pipepay_license_revalidate_compute_state( $api_key, $row );

    pipepay_license_revalidate_log( $ip, $api_key, 200, 'resolved_' . $state );

    $payload = array( 'success' => true, 'state' => $state );

    return pipepay_license_revalidate_sign_payload( $api_key, $instance, $state, $payload );
}

/**
 * Mint the response-header signature + body envelope signature with one
 * shared `issued_at` and return a fully-built WP_REST_Response. If
 * signing isn't available (missing constant, malformed key, sodium
 * exception), return the body-only payload as a 200 - the plugin will
 * refuse to update state because its envelope verifier rejects
 * missing/malformed envelopes, so the worst case is "no state update
 * this cycle." (v1.8.6: extracted out of the handler so the success
 * path has a single tail-call instead of three control-flow tributaries
 * all converging on the same fallback.)
 *
 * Two signatures with distinct prefixes:
 *
 *   1. Response-signature header (`revalidate-v1|...`): authenticates
 *      this specific HTTP response so an MITM can't forge a verdict.
 *      Verified by the plugin BEFORE state is touched.
 *
 *   2. State-envelope payload (`state-envelope-v1|...`, v1.8.5+):
 *      authenticates the state value AT REST in the customer's WP
 *      options. The plugin stores this whole envelope; its reader
 *      verifies the signature on every read. An attacker with
 *      shell-level option-write access can no longer flip the state
 *      by direct option-write because they can't forge this signature.
 *
 * Distinct prefixes mean a captured signature in either context cannot
 * be replayed in the other.
 *
 * @param array<string, mixed> $payload existing payload to enrich with envelope + headers
 */
function pipepay_license_revalidate_sign_payload( string $api_key, string $instance, string $state, array $payload ): WP_REST_Response {
    if ( ! defined( 'PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY' ) || ! function_exists( 'sodium_crypto_sign_detached' ) ) {
        if ( ! defined( 'PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY' ) ) {
            error_log( '[pipepay-license-revalidate] PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY undefined' );
        }
        return new WP_REST_Response( $payload, 200 );
    }
    $secret_key = base64_decode( PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY, true );
    if ( $secret_key === false || strlen( $secret_key ) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ) {
        error_log( '[pipepay-license-revalidate] PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY is missing or wrong length' );
        return new WP_REST_Response( $payload, 200 );
    }

    $issued_at = time();
    try {
        $resp_canonical = sprintf( 'revalidate-v1|%d|%s|%s|%s', $issued_at, $api_key, $instance, $state );
        $resp_sig       = sodium_crypto_sign_detached( $resp_canonical, $secret_key );

        $env_canonical = sprintf( 'state-envelope-v1|%d|%s|%s|%s', $issued_at, $api_key, $instance, $state );
        $env_sig       = sodium_crypto_sign_detached( $env_canonical, $secret_key );
    } catch ( \Throwable $e ) {
        error_log( '[pipepay-license-revalidate] signing failed: ' . $e->getMessage() );
        sodium_memzero( $secret_key );
        return new WP_REST_Response( $payload, 200 );
    }
    sodium_memzero( $secret_key );

    $payload['envelope'] = array(
        'v'     => 1,
        'iat'   => $issued_at,
        'state' => $state,
        'sig'   => base64_encode( $env_sig ),
    );

    $response = new WP_REST_Response( $payload, 200 );
    $response->header( 'X-Pipepay-Signature',          base64_encode( $resp_sig ) );
    $response->header( 'X-Pipepay-Signature-IssuedAt', (string) $issued_at );
    $response->header( 'X-Pipepay-Signature-Version',  'revalidate-v1' );
    return $response;
}

/**
 * Decide which of {active, expired, revoked} applies to a license row.
 *
 * Order matters: revoke list is the highest-priority signal, then Kestrel
 * master active=0, then expiry. Active is the residual case.
 *
 * @param string $api_key license key (already shape-validated)
 * @param array  $row     wc_am_api_resource row with active, access_expires
 */
function pipepay_license_revalidate_compute_state( string $api_key, array $row ): string {
    if ( pipepay_license_revalidate_is_revoked( $api_key ) ) {
        return 'revoked';
    }
    // Kestrel-level master-key disable (e.g. refund, manual admin disable).
    // Map to revoked because new checkouts shouldn't continue to use a
    // license whose master key has been administratively shut off.
    if ( empty( $row['active'] ) ) {
        return 'revoked';
    }
    $expires = (int) ( $row['access_expires'] ?? 0 );
    if ( $expires > 0 && $expires < time() ) {
        return 'expired';
    }
    return 'active';
}

/**
 * Is this license key currently in the revoked list?
 *
 * Storage: option `pipepay_revoked_licenses` is an associative array
 * keyed by license key, where each value is an array
 * { reason, revoked_at, revoked_by_user_id }. The admin page below
 * adds + removes entries.
 */
function pipepay_license_revalidate_is_revoked( string $api_key ): bool {
    $list = get_option( PIPEPAY_REVOKED_LICENSES_OPT, array() );
    if ( ! is_array( $list ) ) {
        return false;
    }
    return array_key_exists( $api_key, $list );
}

/**
 * Strict shape check on api_key. Identical to the resolver's check so a
 * key that resolves can also revalidate.
 */
function pipepay_license_revalidate_valid_key_shape( string $api_key ): bool {
    if ( '' === $api_key ) {
        return false;
    }
    return (bool) preg_match( '/^[A-Za-z0-9_-]{8,190}$/', $api_key );
}

/**
 * Instance fingerprint shape. The plugin emits 32 lowercase hex chars
 * (sha256-truncated) as of v1.8.0+, but we leave a generous range for
 * forward-compat (e.g., a future plugin version that switches hash size).
 */
function pipepay_license_revalidate_valid_instance_shape( string $instance ): bool {
    if ( '' === $instance ) {
        return false;
    }
    return (bool) preg_match( '/^[A-Za-z0-9_-]{8,128}$/', $instance );
}

function pipepay_license_revalidate_invalid_key(): WP_REST_Response {
    return new WP_REST_Response( array(
        'success' => false,
        'code'    => 'invalid_key',
        'message' => 'License key not recognized.',
    ), 404 );
}

/**
 * Atomic per-IP rate-limit counter increment. Mirrors the resolver mu-plugin's
 * implementation. Two separate buckets (revalidate vs resolve) so a customer
 * doing legitimate license activations isn't blocked from revalidations and
 * vice versa.
 */
function pipepay_license_revalidate_increment( string $key ): int {
    $group = 'pipepay_license_rl';
    $found = false;
    $cur   = wp_cache_get( $key, $group, false, $found );
    if ( ! $found ) {
        wp_cache_add( $key, 1, $group, PIPEPAY_LICENSE_REVALIDATE_RATE_WINDOW );
        $cur = 1;
    } else {
        $next = wp_cache_incr( $key, 1, $group );
        $cur  = is_numeric( $next ) ? (int) $next : ( (int) $cur + 1 );
        wp_cache_set( $key, $cur, $group, PIPEPAY_LICENSE_REVALIDATE_RATE_WINDOW );
    }
    $stored = (int) get_transient( $key );
    if ( $stored >= $cur ) {
        $cur = $stored + 1;
    }
    set_transient( $key, $cur, PIPEPAY_LICENSE_REVALIDATE_RATE_WINDOW );
    return (int) $cur;
}

function pipepay_license_revalidate_log( string $ip, string $api_key, int $status, string $reason ): void {
    $last4 = $api_key !== '' && strlen( $api_key ) >= 4 ? substr( $api_key, -4 ) : '----';
    error_log( sprintf(
        '[pipepay-license-revalidate] ip=%s key_last4=%s status=%d reason=%s',
        $ip,
        $last4,
        $status,
        $reason
    ) );
}

function pipepay_license_revalidate_client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $remote = is_string( $remote ) ? trim( $remote ) : '';
    if ( $remote && filter_var( $remote, FILTER_VALIDATE_IP ) ) {
        return $remote;
    }
    return '0.0.0.0';
}

// ─────────────────────────────────────────────────────────────────────────────
// Admin tool: License Revocation
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'pipepay_license_revocation_register_menu', 20 );
add_action( 'admin_init', 'pipepay_license_revocation_handle_post' );

/**
 * Register the License Revocation submenu page.
 *
 * Parent menu: pipepay-proofs (the Pipe Pay plugin's top-level menu on the
 * dogfood install). Capability: manage_options - this is an admin-only
 * tool, not a shop-manager one. A shop manager managing the storefront
 * has no business revoking customer licenses.
 *
 * If the Pipe Pay plugin is not installed (e.g. on a pipepay.app instance
 * that doesn't run the gateway), fall back to a top-level Tools submenu.
 */
function pipepay_license_revocation_register_menu(): void {
    global $admin_page_hooks;
    $parent = isset( $admin_page_hooks['pipepay-proofs'] ) ? 'pipepay-proofs' : 'tools.php';

    add_submenu_page(
        $parent,
        __( 'License Revocation', 'pipe-pay' ),
        __( 'License Revocation', 'pipe-pay' ),
        'manage_options',
        'pipepay-license-revocation',
        'pipepay_license_revocation_render_page'
    );
}

/**
 * Form POST handler: add or remove a license from the revoked list.
 *
 * Both actions are nonce-protected and require manage_options. Both append
 * to the audit log so we have a record of every revoke / unrevoke action,
 * who did it, when, and why.
 */
function pipepay_license_revocation_handle_post(): void {
    if ( empty( $_POST['pipepay_revocation_action'] ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $action = sanitize_key( $_POST['pipepay_revocation_action'] );
    $nonce  = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'pipepay_revocation_' . $action ) ) {
        wp_die( 'Security check failed. Please refresh the page and try again.', '', array( 'response' => 403 ) );
    }

    $key    = trim( sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) );
    $reason = trim( sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ) );

    if ( '' === $key ) {
        pipepay_license_revocation_set_notice( 'error', 'License key is required.' );
        wp_safe_redirect( admin_url( 'admin.php?page=pipepay-license-revocation' ) );
        exit;
    }
    if ( ! pipepay_license_revalidate_valid_key_shape( $key ) ) {
        pipepay_license_revocation_set_notice( 'error', 'License key shape is invalid.' );
        wp_safe_redirect( admin_url( 'admin.php?page=pipepay-license-revocation' ) );
        exit;
    }

    $list = get_option( PIPEPAY_REVOKED_LICENSES_OPT, array() );
    if ( ! is_array( $list ) ) {
        $list = array();
    }

    if ( 'revoke' === $action ) {
        if ( '' === $reason ) {
            pipepay_license_revocation_set_notice( 'error', 'A reason is required when revoking a license.' );
            wp_safe_redirect( admin_url( 'admin.php?page=pipepay-license-revocation' ) );
            exit;
        }
        $list[ $key ] = array(
            'reason'              => $reason,
            'revoked_at'          => time(),
            'revoked_by_user_id'  => get_current_user_id(),
        );
        update_option( PIPEPAY_REVOKED_LICENSES_OPT, $list, false );
        pipepay_license_revocation_audit_append( 'revoke', $key, $reason );
        pipepay_license_revocation_set_notice( 'success', sprintf(
            'License key ending %s has been revoked. Affected installs will pick up the verdict on their next daily revalidation (within 24h).',
            esc_html( substr( $key, -4 ) )
        ) );
    } elseif ( 'unrevoke' === $action ) {
        if ( ! array_key_exists( $key, $list ) ) {
            pipepay_license_revocation_set_notice( 'error', 'That license is not in the revoke list.' );
            wp_safe_redirect( admin_url( 'admin.php?page=pipepay-license-revocation' ) );
            exit;
        }
        unset( $list[ $key ] );
        update_option( PIPEPAY_REVOKED_LICENSES_OPT, $list, false );
        pipepay_license_revocation_audit_append( 'unrevoke', $key, $reason );
        pipepay_license_revocation_set_notice( 'success', sprintf(
            'License key ending %s has been removed from the revoke list. Affected installs will return to active state on their next daily revalidation.',
            esc_html( substr( $key, -4 ) )
        ) );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=pipepay-license-revocation' ) );
    exit;
}

function pipepay_license_revocation_set_notice( string $type, string $message ): void {
    set_transient( 'pipepay_revocation_notice', array( 'type' => $type, 'message' => $message ), 60 );
}

function pipepay_license_revocation_audit_append( string $action, string $key, string $reason ): void {
    $log = get_option( PIPEPAY_REVOCATION_LOG_OPT, array() );
    if ( ! is_array( $log ) ) {
        $log = array();
    }
    $log[] = array(
        'action'    => $action,
        'key_last4' => substr( $key, -4 ),
        'reason'    => $reason,
        'user_id'   => get_current_user_id(),
        'user_login'=> wp_get_current_user()->user_login,
        'at'        => time(),
        'ip'        => pipepay_license_revalidate_client_ip(),
    );
    if ( count( $log ) > PIPEPAY_REVOCATION_LOG_MAX_ENTRIES ) {
        $log = array_slice( $log, -PIPEPAY_REVOCATION_LOG_MAX_ENTRIES );
    }
    update_option( PIPEPAY_REVOCATION_LOG_OPT, $log, false );
}

/**
 * Render the License Revocation admin page. Lists current revocations,
 * exposes a revoke form, an unrevoke form, and the audit log (most recent
 * 50 entries).
 */
function pipepay_license_revocation_render_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions.', '', array( 'response' => 403 ) );
    }

    $notice = get_transient( 'pipepay_revocation_notice' );
    if ( $notice ) {
        delete_transient( 'pipepay_revocation_notice' );
    }

    $list = get_option( PIPEPAY_REVOKED_LICENSES_OPT, array() );
    if ( ! is_array( $list ) ) {
        $list = array();
    }
    $log = get_option( PIPEPAY_REVOCATION_LOG_OPT, array() );
    if ( ! is_array( $log ) ) {
        $log = array();
    }
    $log = array_slice( $log, -50 );
    $log = array_reverse( $log );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'License Revocation', 'pipe-pay' ); ?></h1>

        <?php if ( $notice && is_array( $notice ) ) : ?>
            <div class="notice notice-<?php echo esc_attr( $notice['type'] ?? 'info' ); ?> is-dismissible">
                <p><?php echo wp_kses_post( $notice['message'] ?? '' ); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width:760px;padding:20px 24px;margin-top:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e( 'Revoke a license', 'pipe-pay' ); ?></h2>
            <p>
                Adding a license key here marks it as revoked.
                Affected installs running Pipe Pay v1.8.1+ will pick up the
                verdict on their next daily revalidation (within 24 hours)
                and disable Pipe Pay at <strong>new</strong> checkouts.
                Existing in-flight orders drain through the upload + AI
                verification path until 60-min auto-cancel.
            </p>
            <p>
                <strong>Use sparingly.</strong> This is the runtime kill
                switch reserved for chargebacks, license sharing, ToS
                violations, and similar deliberate misconduct. Routine
                expiry on lapsed renewals is handled automatically and
                does not require revocation.
            </p>
            <form method="post">
                <?php wp_nonce_field( 'pipepay_revocation_revoke' ); ?>
                <input type="hidden" name="pipepay_revocation_action" value="revoke" />
                <p>
                    <label for="pipepay_revoke_key" style="display:block;margin-bottom:6px;font-weight:600;">
                        <?php esc_html_e( 'License key', 'pipe-pay' ); ?>
                    </label>
                    <input
                        type="text"
                        id="pipepay_revoke_key"
                        name="license_key"
                        class="regular-text code"
                        style="width:100%;font-family:Menlo,Consolas,Monaco,monospace;"
                        placeholder="ck_..."
                        autocomplete="off"
                        required
                    />
                </p>
                <p>
                    <label for="pipepay_revoke_reason" style="display:block;margin-bottom:6px;font-weight:600;">
                        <?php esc_html_e( 'Reason (required)', 'pipe-pay' ); ?>
                    </label>
                    <textarea
                        id="pipepay_revoke_reason"
                        name="reason"
                        rows="3"
                        style="width:100%;"
                        placeholder="e.g. chargeback on order #1234; license being shared across multiple unrelated stores; ToS violation - reselling activations"
                        required
                    ></textarea>
                </p>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Revoke this license', 'pipe-pay' ); ?></button>
                </p>
            </form>
        </div>

        <div class="card" style="max-width:760px;padding:20px 24px;margin-top:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e( 'Currently revoked', 'pipe-pay' ); ?> <span style="color:#777;font-weight:400;">(<?php echo count( $list ); ?>)</span></h2>
            <?php if ( empty( $list ) ) : ?>
                <p style="color:#777;"><?php esc_html_e( 'No licenses are currently revoked.', 'pipe-pay' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Key (last 4)', 'pipe-pay' ); ?></th>
                            <th><?php esc_html_e( 'Reason', 'pipe-pay' ); ?></th>
                            <th><?php esc_html_e( 'Revoked at', 'pipe-pay' ); ?></th>
                            <th><?php esc_html_e( 'By', 'pipe-pay' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'pipe-pay' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $list as $key => $entry ) :
                        $entry        = is_array( $entry ) ? $entry : array();
                        $reason       = (string) ( $entry['reason'] ?? '' );
                        $revoked_at   = (int) ( $entry['revoked_at'] ?? 0 );
                        $revoker_id   = (int) ( $entry['revoked_by_user_id'] ?? 0 );
                        $revoker      = $revoker_id ? get_userdata( $revoker_id ) : null;
                        $revoker_name = $revoker ? $revoker->user_login : '(deleted user)';
                    ?>
                        <tr>
                            <td><code><?php echo esc_html( substr( (string) $key, -4 ) ); ?></code></td>
                            <td><?php echo esc_html( $reason ); ?></td>
                            <td>
                                <?php echo esc_html( $revoked_at ? wp_date( 'Y-m-d H:i:s', $revoked_at ) : '—' ); ?>
                            </td>
                            <td><?php echo esc_html( $revoker_name ); ?></td>
                            <td>
                                <form method="post" style="margin:0;">
                                    <?php wp_nonce_field( 'pipepay_revocation_unrevoke' ); ?>
                                    <input type="hidden" name="pipepay_revocation_action" value="unrevoke" />
                                    <input type="hidden" name="license_key" value="<?php echo esc_attr( (string) $key ); ?>" />
                                    <input type="hidden" name="reason" value="manual unrevoke from admin" />
                                    <button type="submit" class="button button-small" onclick="return confirm('Unrevoke this license?');">
                                        <?php esc_html_e( 'Unrevoke', 'pipe-pay' ); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width:760px;padding:20px 24px;margin-top:20px;">
            <h2 style="margin-top:0;"><?php esc_html_e( 'Recent audit log', 'pipe-pay' ); ?> <span style="color:#777;font-weight:400;">(<?php echo count( $log ); ?> shown, capped at <?php echo PIPEPAY_REVOCATION_LOG_MAX_ENTRIES; ?> total)</span></h2>
            <?php if ( empty( $log ) ) : ?>
                <p style="color:#777;"><?php esc_html_e( 'No revoke / unrevoke events yet.', 'pipe-pay' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'When', 'pipe-pay' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'pipe-pay' ); ?></th>
                            <th><?php esc_html_e( 'Key (last 4)', 'pipe-pay' ); ?></th>
                            <th><?php esc_html_e( 'Reason', 'pipe-pay' ); ?></th>
                            <th><?php esc_html_e( 'By', 'pipe-pay' ); ?></th>
                            <th><?php esc_html_e( 'IP', 'pipe-pay' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $log as $entry ) : ?>
                        <tr>
                            <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) ( $entry['at'] ?? 0 ) ) ); ?></td>
                            <td>
                                <?php
                                $a = (string) ( $entry['action'] ?? '' );
                                $color = 'revoke' === $a ? '#b32d2e' : ( 'unrevoke' === $a ? '#0e7950' : '#555' );
                                printf( '<span style="color:%s;font-weight:600;">%s</span>', esc_attr( $color ), esc_html( $a ) );
                                ?>
                            </td>
                            <td><code><?php echo esc_html( (string) ( $entry['key_last4'] ?? '----' ) ); ?></code></td>
                            <td><?php echo esc_html( (string) ( $entry['reason'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $entry['user_login'] ?? '(unknown)' ) ); ?></td>
                            <td><code><?php echo esc_html( (string) ( $entry['ip'] ?? '' ) ); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
