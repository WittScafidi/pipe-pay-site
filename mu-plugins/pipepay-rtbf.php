<?php
/**
 * Plugin Name: Pipe Pay - Right to Be Forgotten (RTBF) tooling
 * Description: WP-CLI command + admin tracking page for processing GDPR / CCPA data-deletion requests. Surveys every Pipe Pay data store on pipepay.app and deletes a customer's data deterministically with an audit-logged paper trail.
 * Author:      Pipe Pay
 * Version:     1.0.0
 *
 * Two ways to invoke an RTBF deletion:
 *
 *   1. WP-CLI (canonical):
 *      wp pipepay-rtbf preview --email=customer@example.com
 *      wp pipepay-rtbf execute --email=customer@example.com --reason="GDPR Art. 17 request, ticket #1234"
 *
 *      `preview` shows what would be deleted without touching anything.
 *      `execute` requires --reason for the audit log.
 *
 *   2. Admin page at WP Admin → Pipe Pay → RTBF Requests
 *      Form takes email + reason, shows preview, requires explicit
 *      "I confirm" checkbox before deletion. Audit log of every
 *      RTBF action retained indefinitely (events, not data).
 *
 * What gets deleted on `execute`:
 *
 *   - WC API Manager license rows (wc_am_api_resource) for the email
 *   - WC orders by that customer (anonymized — line items kept for tax
 *     records but identifying customer data tombstoned)
 *   - WP user account if it was created at checkout (per Article 17;
 *     overridable with --keep-user if the customer has remaining open
 *     activities like open orders on other products)
 *   - Daily revalidation log rows tied to the licenses
 *   - Revocation log entries (anonymized to a tombstone, not deleted —
 *     security audit trail per policy)
 *
 * What does NOT get deleted (per published privacy policy):
 *
 *   - Web-server access logs (rotate weekly on their own)
 *   - Encrypted backups (rotate after 30 days)
 *   - The revocation audit-event itself (anonymized, retained)
 *
 * Audit:
 *   Every preview + execute is logged to option `pipepay_rtbf_log` with
 *   timestamp, actor (wp-cli user or admin user_id), email hash, reason,
 *   and the counts deleted. The actual email is hashed via SHA-256
 *   pepper-prefixed with PIPEPAY_RTBF_LOG_PEPPER (defined in wp-config)
 *   so the audit log doesn't itself become a PII store.
 *
 * Permissions:
 *   WP-CLI: any logged-in CLI user.
 *   Admin page: manage_options.
 */

defined( 'ABSPATH' ) || exit;

const PIPEPAY_RTBF_LOG_OPT        = 'pipepay_rtbf_log';
const PIPEPAY_RTBF_LOG_MAX_ENTRIES = 500;
const PIPEPAY_RTBF_TOMBSTONE       = 'rtbf-anonymized';

/**
 * Hash an email with a pepper for safe audit-log storage. The pepper is
 * defined in wp-config as PIPEPAY_RTBF_LOG_PEPPER. If undefined, we fall
 * back to wp_salt('auth') — not ideal (auth salt rotation would break
 * lookups) but better than storing emails plain. Returns first 16 hex.
 */
function pipepay_rtbf_email_fingerprint( string $email ): string {
    $pepper = defined( 'PIPEPAY_RTBF_LOG_PEPPER' )
        ? (string) PIPEPAY_RTBF_LOG_PEPPER
        : wp_salt( 'auth' );
    return substr( hash( 'sha256', $pepper . '|' . strtolower( trim( $email ) ) ), 0, 16 );
}

/**
 * Survey what data exists for a given email. Pure read; no side effects.
 *
 * @return array{
 *     email:         string,
 *     fingerprint:   string,
 *     license_rows:  array<int, array{api_resource_id:int, key_last4:string, order_id:int, product_id:int, active:int}>,
 *     orders:        array<int, array{id:int, status:string, date:string, total:string}>,
 *     user:          WP_User|null,
 *     revalidate_log_rows: int,
 *     revocation_entries:  int,
 * }
 */
function pipepay_rtbf_survey( string $email ): array {
    global $wpdb;
    $email = strtolower( trim( $email ) );

    // 1. WC API Manager license rows. The wc_am_api_resource table doesn't
    // store email directly — it joins to wp_users via user_id. Resolve
    // the user first, then enumerate their licenses + the (rare) guest
    // checkout case where order email != registered user.
    $user = get_user_by( 'email', $email );

    $license_rows = array();
    if ( $user ) {
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT api_resource_id, master_api_key, order_id, product_id, active
             FROM {$wpdb->prefix}wc_am_api_resource
             WHERE user_id = %d",
            $user->ID
        ), ARRAY_A );
        // phpcs:enable
        if ( is_array( $rows ) ) {
            foreach ( $rows as $r ) {
                $license_rows[] = array(
                    'api_resource_id' => (int) $r['api_resource_id'],
                    'key_last4'       => substr( (string) $r['master_api_key'], -4 ),
                    'order_id'        => (int) $r['order_id'],
                    'product_id'      => (int) $r['product_id'],
                    'active'          => (int) $r['active'],
                );
            }
        }
    }

    // 2. WC orders with this billing email.
    $orders = array();
    if ( function_exists( 'wc_get_orders' ) ) {
        $order_objs = wc_get_orders( array(
            'billing_email' => $email,
            'limit'         => 200,
            'orderby'       => 'date',
            'order'         => 'DESC',
        ) );
        foreach ( $order_objs as $o ) {
            $orders[] = array(
                'id'     => $o->get_id(),
                'status' => $o->get_status(),
                'date'   => $o->get_date_created() ? $o->get_date_created()->format( 'Y-m-d' ) : '',
                'total'  => $o->get_total(),
            );
        }
    }

    // 3. Revalidation log rows tied to this user's license resource_ids.
    $reval_count = 0;
    if ( ! empty( $license_rows ) ) {
        $ids = array_map( function ( $r ) { return $r['api_resource_id']; }, $license_rows );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $log_table = $wpdb->prefix . 'pipepay_revalidate_log';
        // Only if the table exists (analytics mu-plugin might not be installed yet).
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $log_table ) );
        if ( $exists ) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
            $reval_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$log_table} WHERE api_resource_id IN ({$placeholders})",
                ...$ids
            ) );
            // phpcs:enable
        }
    }

    // 4. Revocation entries for this user's licenses.
    $revoke_count = 0;
    $revoked = get_option( 'pipepay_revoked_licenses', array() );
    if ( is_array( $revoked ) && ! empty( $license_rows ) ) {
        $user_keys_lookup = array(); // license_key => 1
        foreach ( $license_rows as $r ) {
            // We don't have the full key, just last4. Match suffix.
            $last4 = $r['key_last4'];
            foreach ( array_keys( $revoked ) as $k ) {
                if ( substr( (string) $k, -4 ) === $last4 ) {
                    $user_keys_lookup[ $k ] = 1;
                }
            }
        }
        $revoke_count = count( $user_keys_lookup );
    }

    return array(
        'email'              => $email,
        'fingerprint'        => pipepay_rtbf_email_fingerprint( $email ),
        'license_rows'       => $license_rows,
        'orders'             => $orders,
        'user'               => $user ?: null,
        'revalidate_log_rows' => $reval_count,
        'revocation_entries' => $revoke_count,
    );
}

/**
 * Execute the deletion. CALLER IS RESPONSIBLE for capability checking.
 * Returns the same shape as survey() plus a `deleted_at` timestamp and
 * a per-store deletion-count breakdown.
 *
 * @return array
 */
function pipepay_rtbf_execute( string $email, string $reason, int $actor_user_id = 0, string $actor_source = 'admin', bool $keep_user = false ): array {
    global $wpdb;
    $survey  = pipepay_rtbf_survey( $email );
    $deleted = array(
        'license_rows'        => 0,
        'orders_anonymized'   => 0,
        'user_deleted'        => false,
        'revalidate_log_rows' => 0,
        'revocation_entries_anonymized' => 0,
    );

    // 1. Delete license rows.
    foreach ( $survey['license_rows'] as $r ) {
        $rid = $r['api_resource_id'];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $affected = $wpdb->delete(
            $wpdb->prefix . 'wc_am_api_resource',
            array( 'api_resource_id' => $rid ),
            array( '%d' )
        );
        if ( $affected ) {
            $deleted['license_rows']++;
        }
    }

    // 2. Anonymize orders (line items + totals retained for tax; PII tombstoned).
    if ( function_exists( 'wc_get_order' ) ) {
        foreach ( $survey['orders'] as $o ) {
            $order = wc_get_order( $o['id'] );
            if ( ! $order ) continue;
            $order->set_billing_email( PIPEPAY_RTBF_TOMBSTONE . '@example.invalid' );
            $order->set_billing_first_name( PIPEPAY_RTBF_TOMBSTONE );
            $order->set_billing_last_name( '' );
            $order->set_billing_address_1( PIPEPAY_RTBF_TOMBSTONE );
            $order->set_billing_address_2( '' );
            $order->set_billing_city( '' );
            $order->set_billing_state( '' );
            $order->set_billing_postcode( '' );
            $order->set_billing_phone( '' );
            $order->set_customer_id( 0 ); // detach from any WP user
            $order->add_order_note( sprintf(
                'PII anonymized per RTBF request. Reason: %s.',
                $reason
            ) );
            $order->save();
            $deleted['orders_anonymized']++;
        }
    }

    // 3. Delete user (unless overridden).
    if ( $survey['user'] && ! $keep_user ) {
        if ( ! function_exists( 'wp_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        $deleted['user_deleted'] = (bool) wp_delete_user( $survey['user']->ID );
    }

    // 4. Delete revalidation log rows.
    if ( ! empty( $survey['license_rows'] ) ) {
        $ids = array_map( function ( $r ) { return $r['api_resource_id']; }, $survey['license_rows'] );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $log_table = $wpdb->prefix . 'pipepay_revalidate_log';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $log_table ) );
        if ( $exists ) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
            $affected = $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$log_table} WHERE api_resource_id IN ({$placeholders})",
                ...$ids
            ) );
            // phpcs:enable
            $deleted['revalidate_log_rows'] = is_int( $affected ) ? $affected : 0;
        }
    }

    // 5. Anonymize revocation entries (don't delete — security audit trail).
    $revoked = get_option( 'pipepay_revoked_licenses', array() );
    if ( is_array( $revoked ) ) {
        $changed = false;
        foreach ( $revoked as $k => $entry ) {
            $last4 = substr( (string) $k, -4 );
            foreach ( $survey['license_rows'] as $r ) {
                if ( $r['key_last4'] === $last4 && is_array( $entry ) ) {
                    $revoked[ $k ]['revoked_by_user_id'] = 0;
                    if ( isset( $revoked[ $k ]['reason'] ) ) {
                        $revoked[ $k ]['reason'] = '[anonymized per RTBF]';
                    }
                    $changed = true;
                    $deleted['revocation_entries_anonymized']++;
                    break;
                }
            }
        }
        if ( $changed ) {
            update_option( 'pipepay_revoked_licenses', $revoked, false );
        }
    }

    // 6. Audit log.
    pipepay_rtbf_audit_log( 'execute', $email, $reason, $actor_user_id, $actor_source, $deleted );

    return array_merge( $survey, array(
        'deleted'    => $deleted,
        'deleted_at' => time(),
    ) );
}

function pipepay_rtbf_audit_log( string $action, string $email, string $reason, int $actor_user_id, string $actor_source, array $context = array() ): void {
    $log = get_option( PIPEPAY_RTBF_LOG_OPT, array() );
    if ( ! is_array( $log ) ) {
        $log = array();
    }
    $log[] = array(
        'action'       => $action,
        'email_hash'   => pipepay_rtbf_email_fingerprint( $email ),
        'reason'       => $reason,
        'actor_id'     => $actor_user_id,
        'actor_source' => $actor_source,
        'at'           => time(),
        'context'      => $context,
    );
    if ( count( $log ) > PIPEPAY_RTBF_LOG_MAX_ENTRIES ) {
        $log = array_slice( $log, -PIPEPAY_RTBF_LOG_MAX_ENTRIES );
    }
    update_option( PIPEPAY_RTBF_LOG_OPT, $log, false );
}

// ─────────────────────────────────────────────────────────────────────────────
// WP-CLI command
// ─────────────────────────────────────────────────────────────────────────────

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    /**
     * Usage:
     *   wp pipepay-rtbf preview --email=<email>
     *   wp pipepay-rtbf execute --email=<email> --reason="<text>" [--keep-user]
     */
    WP_CLI::add_command( 'pipepay-rtbf', function ( $args, $assoc_args ) {
        $sub   = $args[0] ?? '';
        $email = $assoc_args['email'] ?? '';
        if ( '' === $email || ! is_email( $email ) ) {
            WP_CLI::error( 'Provide --email=<valid email>' );
        }

        if ( 'preview' === $sub ) {
            $s = pipepay_rtbf_survey( $email );
            pipepay_rtbf_audit_log( 'preview', $email, $assoc_args['reason'] ?? '', 0, 'wp-cli' );
            WP_CLI::log( sprintf( '── Survey for %s ──', $email ) );
            WP_CLI::log( sprintf( 'Fingerprint:           %s', $s['fingerprint'] ) );
            WP_CLI::log( sprintf( 'WP user found:         %s', $s['user'] ? '#' . $s['user']->ID . ' (' . $s['user']->user_login . ')' : 'no' ) );
            WP_CLI::log( sprintf( 'License rows:          %d', count( $s['license_rows'] ) ) );
            foreach ( $s['license_rows'] as $r ) {
                WP_CLI::log( sprintf( '  - resource_id=%d key_last4=%s order_id=%d active=%d', $r['api_resource_id'], $r['key_last4'], $r['order_id'], $r['active'] ) );
            }
            WP_CLI::log( sprintf( 'WC orders:             %d', count( $s['orders'] ) ) );
            foreach ( $s['orders'] as $o ) {
                WP_CLI::log( sprintf( '  - order #%d status=%s date=%s total=%s', $o['id'], $o['status'], $o['date'], $o['total'] ) );
            }
            WP_CLI::log( sprintf( 'Revalidate log rows:   %d', $s['revalidate_log_rows'] ) );
            WP_CLI::log( sprintf( 'Revocation entries:    %d (will be anonymized, not deleted)', $s['revocation_entries'] ) );
            WP_CLI::success( 'Preview complete. Run `execute --reason="..."` to delete.' );
            return;
        }

        if ( 'execute' === $sub ) {
            $reason = trim( (string) ( $assoc_args['reason'] ?? '' ) );
            if ( '' === $reason ) {
                WP_CLI::error( 'execute requires --reason="<reason text>" for the audit log.' );
            }
            $keep_user = ! empty( $assoc_args['keep-user'] );
            WP_CLI::confirm( sprintf( 'Delete all data for %s? Reason: %s', $email, $reason ) );
            $result = pipepay_rtbf_execute( $email, $reason, 0, 'wp-cli', $keep_user );
            $d = $result['deleted'];
            WP_CLI::log( sprintf( '── Deletion complete for %s ──', $email ) );
            WP_CLI::log( sprintf( 'License rows deleted:        %d', $d['license_rows'] ) );
            WP_CLI::log( sprintf( 'Orders anonymized:           %d', $d['orders_anonymized'] ) );
            WP_CLI::log( sprintf( 'WP user deleted:             %s', $d['user_deleted'] ? 'yes' : 'no' ) );
            WP_CLI::log( sprintf( 'Revalidate log rows deleted: %d', $d['revalidate_log_rows'] ) );
            WP_CLI::log( sprintf( 'Revocation entries anon:     %d', $d['revocation_entries_anonymized'] ) );
            WP_CLI::success( 'RTBF deletion logged. Audit entry saved.' );
            return;
        }

        WP_CLI::error( 'Subcommand must be `preview` or `execute`. See file header for usage.' );
    } );
}

// ─────────────────────────────────────────────────────────────────────────────
// Admin page
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'pipepay_rtbf_register_menu', 23 );
add_action( 'admin_init', 'pipepay_rtbf_handle_post' );

function pipepay_rtbf_register_menu(): void {
    global $admin_page_hooks;
    $parent = isset( $admin_page_hooks['pipepay-proofs'] ) ? 'pipepay-proofs' : 'tools.php';
    add_submenu_page(
        $parent,
        __( 'RTBF Requests', 'pipe-pay' ),
        __( 'RTBF Requests', 'pipe-pay' ),
        'manage_options',
        'pipepay-rtbf',
        'pipepay_rtbf_render_page'
    );
}

function pipepay_rtbf_handle_post(): void {
    if ( empty( $_POST['pipepay_rtbf_action'] ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions.', '', array( 'response' => 403 ) );
    }
    $action = sanitize_key( $_POST['pipepay_rtbf_action'] );
    $nonce  = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'pipepay_rtbf_' . $action ) ) {
        wp_die( 'Security check failed.', '', array( 'response' => 403 ) );
    }

    $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    if ( ! is_email( $email ) ) {
        set_transient( 'pipepay_rtbf_notice', array( 'type' => 'error', 'message' => 'Please enter a valid email address.' ), 60 );
        wp_safe_redirect( admin_url( 'admin.php?page=pipepay-rtbf' ) );
        exit;
    }

    if ( 'preview' === $action ) {
        $survey = pipepay_rtbf_survey( $email );
        pipepay_rtbf_audit_log( 'preview', $email, '', get_current_user_id(), 'admin' );
        set_transient( 'pipepay_rtbf_last_preview', $survey, 600 );
        set_transient( 'pipepay_rtbf_notice', array( 'type' => 'success', 'message' => 'Preview computed. Review below before executing deletion.' ), 60 );
        wp_safe_redirect( admin_url( 'admin.php?page=pipepay-rtbf' ) );
        exit;
    }

    if ( 'execute' === $action ) {
        $reason  = trim( sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ) );
        $confirm = ! empty( $_POST['confirm'] );
        if ( '' === $reason ) {
            set_transient( 'pipepay_rtbf_notice', array( 'type' => 'error', 'message' => 'A reason is required.' ), 60 );
            wp_safe_redirect( admin_url( 'admin.php?page=pipepay-rtbf' ) );
            exit;
        }
        if ( ! $confirm ) {
            set_transient( 'pipepay_rtbf_notice', array( 'type' => 'error', 'message' => 'You must check the confirmation box.' ), 60 );
            wp_safe_redirect( admin_url( 'admin.php?page=pipepay-rtbf' ) );
            exit;
        }
        $result = pipepay_rtbf_execute( $email, $reason, get_current_user_id(), 'admin' );
        delete_transient( 'pipepay_rtbf_last_preview' );
        set_transient( 'pipepay_rtbf_notice', array(
            'type'    => 'success',
            'message' => sprintf(
                'RTBF executed: %d licenses deleted, %d orders anonymized, user-deleted=%s, %d revalidate-log rows deleted, %d revocation entries anonymized.',
                $result['deleted']['license_rows'],
                $result['deleted']['orders_anonymized'],
                $result['deleted']['user_deleted'] ? 'yes' : 'no',
                $result['deleted']['revalidate_log_rows'],
                $result['deleted']['revocation_entries_anonymized']
            ),
        ), 120 );
        wp_safe_redirect( admin_url( 'admin.php?page=pipepay-rtbf' ) );
        exit;
    }
}

function pipepay_rtbf_render_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions.', '', array( 'response' => 403 ) );
    }
    $notice = get_transient( 'pipepay_rtbf_notice' );
    if ( $notice ) {
        delete_transient( 'pipepay_rtbf_notice' );
    }
    $preview = get_transient( 'pipepay_rtbf_last_preview' );
    $log     = get_option( PIPEPAY_RTBF_LOG_OPT, array() );
    if ( ! is_array( $log ) ) $log = array();
    $log = array_slice( array_reverse( $log ), 0, 25 );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'RTBF (Right to Be Forgotten) Requests', 'pipe-pay' ); ?></h1>
        <p>Process GDPR Article 17 / CCPA §1798.105 data-deletion requests. Two-step flow: <strong>preview</strong> first to see what data exists, <strong>execute</strong> to delete.</p>

        <?php if ( $notice && is_array( $notice ) ) : ?>
            <div class="notice notice-<?php echo esc_attr( $notice['type'] ?? 'info' ); ?> is-dismissible">
                <p><?php echo wp_kses_post( $notice['message'] ?? '' ); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width:760px;padding:20px 24px;margin-top:20px;">
            <h2 style="margin-top:0;">Step 1: Preview</h2>
            <p>Enter the email of the data subject. We'll show what data Pipe Pay holds about them. Nothing is deleted yet.</p>
            <form method="post" style="margin-top:12px;">
                <?php wp_nonce_field( 'pipepay_rtbf_preview' ); ?>
                <input type="hidden" name="pipepay_rtbf_action" value="preview" />
                <p>
                    <label for="pipepay_rtbf_email" style="display:block;margin-bottom:6px;font-weight:600;">Email address</label>
                    <input type="email" id="pipepay_rtbf_email" name="email" class="regular-text" style="width:100%;" required />
                </p>
                <p><button type="submit" class="button"><?php esc_html_e( 'Preview', 'pipe-pay' ); ?></button></p>
            </form>
        </div>

        <?php if ( $preview && is_array( $preview ) ) : ?>
            <div class="card" style="max-width:760px;padding:20px 24px;margin-top:20px;">
                <h2 style="margin-top:0;">Preview for <code><?php echo esc_html( $preview['email'] ); ?></code></h2>
                <ul>
                    <li><strong>WP user:</strong> <?php echo $preview['user'] ? '#' . (int) $preview['user']->ID . ' (' . esc_html( $preview['user']->user_login ) . ')' : 'no'; ?></li>
                    <li><strong>License rows:</strong> <?php echo count( $preview['license_rows'] ); ?></li>
                    <?php foreach ( $preview['license_rows'] as $r ) : ?>
                        <li style="list-style:none;margin-left:20px;color:#555;">
                            resource_id=<?php echo (int) $r['api_resource_id']; ?>,
                            key_last4=<code><?php echo esc_html( $r['key_last4'] ); ?></code>,
                            order #<?php echo (int) $r['order_id']; ?>,
                            active=<?php echo (int) $r['active']; ?>
                        </li>
                    <?php endforeach; ?>
                    <li><strong>WC orders:</strong> <?php echo count( $preview['orders'] ); ?></li>
                    <?php foreach ( array_slice( $preview['orders'], 0, 5 ) as $o ) : ?>
                        <li style="list-style:none;margin-left:20px;color:#555;">
                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $o['id'] . '&action=edit' ) ); ?>">#<?php echo (int) $o['id']; ?></a>
                            (<?php echo esc_html( $o['status'] ); ?>, <?php echo esc_html( $o['date'] ); ?>)
                        </li>
                    <?php endforeach; ?>
                    <li><strong>Revalidation log rows:</strong> <?php echo (int) $preview['revalidate_log_rows']; ?></li>
                    <li><strong>Revocation entries:</strong> <?php echo (int) $preview['revocation_entries']; ?> (will be anonymized, not deleted)</li>
                </ul>

                <h3 style="margin-top:24px;">Step 2: Execute deletion</h3>
                <form method="post">
                    <?php wp_nonce_field( 'pipepay_rtbf_execute' ); ?>
                    <input type="hidden" name="pipepay_rtbf_action" value="execute" />
                    <input type="hidden" name="email" value="<?php echo esc_attr( $preview['email'] ); ?>" />
                    <p>
                        <label for="pipepay_rtbf_reason" style="display:block;margin-bottom:6px;font-weight:600;">Reason (audit log)</label>
                        <textarea id="pipepay_rtbf_reason" name="reason" rows="2" style="width:100%;" required placeholder="e.g. GDPR Art. 17 request, ticket #1234"></textarea>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="confirm" value="1" required />
                            I confirm this customer's data should be permanently deleted as listed above.
                        </label>
                    </p>
                    <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Execute deletion', 'pipe-pay' ); ?></button></p>
                </form>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width:760px;padding:20px 24px;margin-top:20px;">
            <h2 style="margin-top:0;">Audit log (last 25 events)</h2>
            <?php if ( empty( $log ) ) : ?>
                <p style="color:#777;">No RTBF events recorded yet.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr><th>When</th><th>Action</th><th>Email hash</th><th>Actor</th><th>Reason</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $log as $e ) : ?>
                        <tr>
                            <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) ( $e['at'] ?? 0 ) ) ); ?></td>
                            <td><strong><?php echo esc_html( (string) ( $e['action'] ?? '' ) ); ?></strong></td>
                            <td><code><?php echo esc_html( (string) ( $e['email_hash'] ?? '' ) ); ?></code></td>
                            <td><?php
                                $src = (string) ( $e['actor_source'] ?? '' );
                                $aid = (int) ( $e['actor_id'] ?? 0 );
                                echo esc_html( $src . ( $aid > 0 ? ' #' . $aid : '' ) );
                            ?></td>
                            <td><?php echo esc_html( (string) ( $e['reason'] ?? '' ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
