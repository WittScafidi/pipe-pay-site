<?php
/**
 * Plugin Name: Pipe Pay - License Analytics (clone detection)
 * Description: Logs every revalidation call to pipepay.app and surfaces (license, instance) tuples that report from multiple distinct site_urls — the canonical clone signal. Admin page at WP Admin → Pipe Pay → License Anomalies. Hands off to the existing License Revocation tool.
 * Author:      Pipe Pay
 * Version:     1.0.0
 *
 * Threat model:
 *   v1.8.5's Phase D per-install nonce gives FRESH installs a unique
 *   fingerprint, but a customer who DB-clones a v1.8.4-or-earlier
 *   install (filesystem + DB dump) inherits the cached
 *   `pipepay_license_instance` option and the nonce, so the clone's
 *   fingerprint matches the original's. Both then make daily
 *   revalidate calls with the same (license, instance) tuple. The
 *   plugin alone cannot detect this - only this server can, by
 *   correlating incoming requests across installs.
 *
 *   This mu-plugin logs each revalidate call and flags any tuple
 *   where the SAME (license, instance) reports from MORE THAN ONE
 *   distinct `site_url` value within a rolling 30-day window. That's
 *   the load-bearing signal: legitimate customers don't change their
 *   site URL day to day, and even a CDN-fronted site reports a stable
 *   `site_url` from inside WordPress (it's home_url()).
 *
 * Privacy posture:
 *   - The raw api_key is NEVER logged. We use the api_resource_id
 *     from wc_am_api_resource (Kestrel's internal stable license ID)
 *     as the per-license correlation key.
 *   - Client IPs ARE logged for ops correlation but not used as a
 *     detection signal (CDN/proxy fronting makes IP unreliable).
 *   - The log table has a 90-day retention cron that prunes older
 *     entries automatically.
 *
 * Detection rules:
 *   PRIMARY:    same (api_resource_id, instance) seen from >1
 *               distinct site_url in last 30 days
 *   SECONDARY:  same (api_resource_id, instance) seen from >1
 *               distinct /24 client_ip in last 30 days (advisory only;
 *               surface in detail panel, not as a flag)
 *
 * Not in scope:
 *   - Real-time blocking. The plugin can't act on this; the operator
 *     must decide whether to hand a flagged license to the revocation
 *     tool.
 *   - Cross-customer screenshot fraud detection. That's a separate
 *     plugin-side feature (pHash on uploads).
 */

defined( 'ABSPATH' ) || exit;

const PIPEPAY_LICENSE_ANALYTICS_TABLE         = 'pipepay_revalidate_log';
const PIPEPAY_LICENSE_ANALYTICS_DB_VERSION    = '1.0.0';
const PIPEPAY_LICENSE_ANALYTICS_DB_OPT        = 'pipepay_revalidate_log_db_version';
const PIPEPAY_LICENSE_ANALYTICS_RETENTION_DAYS = 90;
const PIPEPAY_LICENSE_ANALYTICS_DETECTION_DAYS = 30;
const PIPEPAY_LICENSE_ANALYTICS_PRUNE_HOOK    = 'pipepay_revalidate_log_prune';

// ── Schema (idempotent via dbDelta version gate) ───────────────────────────
add_action( 'plugins_loaded', 'pipepay_license_analytics_maybe_install_schema', 5 );

function pipepay_license_analytics_maybe_install_schema(): void {
    if ( get_option( PIPEPAY_LICENSE_ANALYTICS_DB_OPT ) === PIPEPAY_LICENSE_ANALYTICS_DB_VERSION ) {
        return;
    }
    global $wpdb;
    $table   = $wpdb->prefix . PIPEPAY_LICENSE_ANALYTICS_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        api_resource_id BIGINT UNSIGNED NOT NULL,
        instance VARCHAR(128) NOT NULL,
        site_url VARCHAR(255) NOT NULL DEFAULT '',
        client_ip VARCHAR(45) NOT NULL DEFAULT '',
        plugin_version VARCHAR(32) NOT NULL DEFAULT '',
        state VARCHAR(16) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY idx_resource_instance (api_resource_id, instance),
        KEY idx_created (created_at)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( PIPEPAY_LICENSE_ANALYTICS_DB_OPT, PIPEPAY_LICENSE_ANALYTICS_DB_VERSION, false );
}

// ── Log writer (action listener) ────────────────────────────────────────────
add_action( 'pipepay_revalidate_logged', 'pipepay_license_analytics_record', 10, 6 );

/**
 * Write one revalidate call to the log table. Fired by the revalidate
 * mu-plugin after each successful resolution; never with raw api_key.
 */
function pipepay_license_analytics_record(
    int $api_resource_id,
    string $instance,
    string $site_url,
    string $client_ip,
    string $plugin_version,
    string $state
): void {
    if ( $api_resource_id <= 0 || '' === $instance ) {
        return; // defensive: nothing useful to log
    }
    global $wpdb;
    $table = $wpdb->prefix . PIPEPAY_LICENSE_ANALYTICS_TABLE;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->insert(
        $table,
        array(
            'api_resource_id' => $api_resource_id,
            'instance'        => $instance,
            'site_url'        => $site_url,
            'client_ip'       => $client_ip,
            'plugin_version'  => $plugin_version,
            'state'           => $state,
            'created_at'      => current_time( 'mysql', true ),
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
    );
}

// ── Retention pruner (daily cron) ───────────────────────────────────────────
add_action( PIPEPAY_LICENSE_ANALYTICS_PRUNE_HOOK, 'pipepay_license_analytics_prune' );

add_action( 'admin_init', function () {
    if ( ! wp_next_scheduled( PIPEPAY_LICENSE_ANALYTICS_PRUNE_HOOK ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', PIPEPAY_LICENSE_ANALYTICS_PRUNE_HOOK );
    }
} );

function pipepay_license_analytics_prune(): void {
    global $wpdb;
    $table  = $wpdb->prefix . PIPEPAY_LICENSE_ANALYTICS_TABLE;
    $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( PIPEPAY_LICENSE_ANALYTICS_RETENTION_DAYS * DAY_IN_SECONDS ) );
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
}

// ── Detection query ─────────────────────────────────────────────────────────

/**
 * Find (api_resource_id, instance) tuples where the same fingerprint is
 * reporting from MORE THAN ONE distinct site_url within the detection
 * window. That's the clone signal.
 *
 * @return array<int, array{api_resource_id:int, instance:string, site_urls:array<string>, distinct_site_count:int, distinct_ip_count:int, total_calls:int, last_seen:string}>
 */
function pipepay_license_analytics_find_anomalies( int $window_days = PIPEPAY_LICENSE_ANALYTICS_DETECTION_DAYS ): array {
    global $wpdb;
    $table  = $wpdb->prefix . PIPEPAY_LICENSE_ANALYTICS_TABLE;
    $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $window_days * DAY_IN_SECONDS ) );
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT
            api_resource_id,
            instance,
            GROUP_CONCAT(DISTINCT site_url ORDER BY site_url SEPARATOR '\\n') AS site_urls,
            COUNT(DISTINCT site_url) AS distinct_site_count,
            COUNT(DISTINCT client_ip) AS distinct_ip_count,
            COUNT(*) AS total_calls,
            MAX(created_at) AS last_seen
         FROM {$table}
         WHERE created_at >= %s
         GROUP BY api_resource_id, instance
         HAVING distinct_site_count > 1
         ORDER BY last_seen DESC",
        $cutoff
    ), ARRAY_A );
    // phpcs:enable
    if ( ! is_array( $rows ) ) {
        return array();
    }
    foreach ( $rows as &$r ) {
        $r['api_resource_id']     = (int) $r['api_resource_id'];
        $r['distinct_site_count'] = (int) $r['distinct_site_count'];
        $r['distinct_ip_count']   = (int) $r['distinct_ip_count'];
        $r['total_calls']         = (int) $r['total_calls'];
        $r['site_urls']           = $r['site_urls'] ? explode( "\n", $r['site_urls'] ) : array();
    }
    return $rows;
}

/**
 * Resolve api_resource_id → license metadata (last 4, order_id, customer)
 * for surfacing in the admin page. Read-only.
 *
 * @return array{key_last4:string, order_id:int, customer_id:int, product_title:string}|null
 */
function pipepay_license_analytics_resolve_license( int $api_resource_id ): ?array {
    global $wpdb;
    $table = $wpdb->prefix . 'wc_am_api_resource';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT master_api_key, order_id, user_id, product_title FROM {$table} WHERE api_resource_id = %d LIMIT 1",
        $api_resource_id
    ), ARRAY_A );
    if ( ! $row ) {
        return null;
    }
    return array(
        'key_last4'     => substr( (string) $row['master_api_key'], -4 ),
        'order_id'      => (int) ( $row['order_id'] ?? 0 ),
        'customer_id'   => (int) ( $row['user_id'] ?? 0 ),
        'product_title' => (string) ( $row['product_title'] ?? '' ),
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Admin page
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'pipepay_license_analytics_register_menu', 22 );

function pipepay_license_analytics_register_menu(): void {
    global $admin_page_hooks;
    $parent = isset( $admin_page_hooks['pipepay-proofs'] ) ? 'pipepay-proofs' : 'tools.php';
    add_submenu_page(
        $parent,
        __( 'License Anomalies', 'pipe-pay' ),
        __( 'License Anomalies', 'pipe-pay' ),
        'manage_options',
        'pipepay-license-anomalies',
        'pipepay_license_analytics_render_page'
    );
}

function pipepay_license_analytics_render_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions.', '', array( 'response' => 403 ) );
    }
    $window     = PIPEPAY_LICENSE_ANALYTICS_DETECTION_DAYS;
    $anomalies  = pipepay_license_analytics_find_anomalies( $window );
    $revoke_url = admin_url( 'admin.php?page=pipepay-license-revocation' );

    // Top-level stats: total log rows + distinct licenses seen in window.
    global $wpdb;
    $table  = $wpdb->prefix . PIPEPAY_LICENSE_ANALYTICS_TABLE;
    $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $window * DAY_IN_SECONDS ) );
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
    $total_calls  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $cutoff ) );
    $distinct_lic = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT api_resource_id) FROM {$table} WHERE created_at >= %s", $cutoff ) );
    // phpcs:enable
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'License Anomalies', 'pipe-pay' ); ?></h1>
        <p>
            Tuples of <code>(license, instance)</code> seen reporting from
            <strong>more than one distinct site URL</strong> within the last
            <?php echo (int) $window; ?> days. This is the load-bearing
            clone signal — legitimate customers don't change <code>home_url()</code>
            day to day, so a same-fingerprint-different-URL pair almost always
            means a DB clone (filesystem + DB dump of a paying customer's site).
        </p>
        <p style="color:#555;">
            <strong>Window:</strong> last <?php echo (int) $window; ?> days
            &middot; <strong>Calls observed:</strong> <?php echo number_format_i18n( $total_calls ); ?>
            &middot; <strong>Distinct licenses:</strong> <?php echo number_format_i18n( $distinct_lic ); ?>
            &middot; <strong>Log retention:</strong> <?php echo (int) PIPEPAY_LICENSE_ANALYTICS_RETENTION_DAYS; ?> days
        </p>

        <?php if ( empty( $anomalies ) ) : ?>
            <div class="notice notice-success inline" style="margin-top:20px;">
                <p><strong>No anomalies detected.</strong> Every (license, instance) tuple in the detection window is reporting from a single site URL.</p>
            </div>
        <?php else : ?>
            <h2 style="margin-top:24px;"><?php echo count( $anomalies ); ?> flagged <?php echo count( $anomalies ) === 1 ? 'tuple' : 'tuples'; ?></h2>
            <table class="widefat striped" style="margin-top:8px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'License', 'pipe-pay' ); ?></th>
                        <th><?php esc_html_e( 'Instance', 'pipe-pay' ); ?></th>
                        <th><?php esc_html_e( 'Distinct site URLs', 'pipe-pay' ); ?></th>
                        <th><?php esc_html_e( 'Calls / IPs', 'pipe-pay' ); ?></th>
                        <th><?php esc_html_e( 'Last seen', 'pipe-pay' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'pipe-pay' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $anomalies as $a ) :
                    $lic = pipepay_license_analytics_resolve_license( $a['api_resource_id'] );
                ?>
                    <tr>
                        <td>
                            <?php if ( $lic ) : ?>
                                <code>········<?php echo esc_html( $lic['key_last4'] ); ?></code><br>
                                <small style="color:#666;"><?php echo esc_html( $lic['product_title'] ); ?></small>
                                <?php if ( $lic['order_id'] > 0 ) : ?>
                                    <br><small><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $lic['order_id'] . '&action=edit' ) ); ?>">order #<?php echo (int) $lic['order_id']; ?></a></small>
                                <?php endif; ?>
                            <?php else : ?>
                                <em>resource <?php echo (int) $a['api_resource_id']; ?> (no longer in api_resource table)</em>
                            <?php endif; ?>
                        </td>
                        <td><code style="font-size:11px;"><?php echo esc_html( substr( $a['instance'], 0, 16 ) ); ?>…</code></td>
                        <td>
                            <strong style="color:#b32d2e;"><?php echo (int) $a['distinct_site_count']; ?>×</strong><br>
                            <?php foreach ( $a['site_urls'] as $url ) : ?>
                                <small style="color:#444;"><?php echo esc_html( $url ?: '(empty)' ); ?></small><br>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php echo number_format_i18n( $a['total_calls'] ); ?> calls<br>
                            <small style="color:#666;"><?php echo (int) $a['distinct_ip_count']; ?> distinct IPs</small>
                        </td>
                        <td>
                            <?php echo esc_html( wp_date( 'Y-m-d H:i', strtotime( $a['last_seen'] . ' UTC' ) ) ); ?>
                        </td>
                        <td>
                            <?php if ( $lic && $lic['order_id'] > 0 ) : ?>
                                <a href="<?php echo esc_url( $revoke_url ); ?>" class="button button-small">
                                    Go to Revocation Tool →
                                </a>
                                <br><small style="color:#888;">Paste key ending <code><?php echo esc_html( $lic['key_last4'] ); ?></code></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:16px;color:#666;font-size:13px;">
                <strong>How to act:</strong> Look at the distinct site URLs.
                If one is clearly a legitimate domain change (e.g. the
                merchant moved <code>oldname.com</code> → <code>newname.com</code>),
                no action needed. If two unrelated stores share the
                fingerprint (one of them is a clone), use the revocation
                tool to revoke the license. The next daily revalidate
                tick on both sites will then disable Pipe Pay at new
                checkouts on both, with drain semantics letting in-flight
                orders finish.
            </p>
        <?php endif; ?>
    </div>
    <?php
}
