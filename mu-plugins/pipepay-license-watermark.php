<?php
/**
 * Plugin Name: Pipe Pay - Download Watermark
 * Description: Phase B anti-piracy. Each per-customer download of a Pipe Pay zip from /my-account is watermarked with a small Ed25519-signed marker file naming (customer_id, order_id, download_at, plugin_version). When a leaked zip turns up in the wild, we can decode the marker to identify the leaker - which then becomes the input to the Phase C revoke kill switch.
 * Author:      Pipe Pay
 * Version:     1.0.0
 *
 * Threat model:
 *   This is Phase B of the four-phase anti-piracy plan. It targets the
 *   casual pirate who downloads the zip from /my-account, posts it to a
 *   forum unmodified, and doesn't think to scrub the marker. It does
 *   NOT defeat:
 *     - A determined attacker who unzips, edits, and re-zips
 *     - A determined attacker who knows about this file and strips it
 *     - A buyer who shares their license credentials directly (Phase C
 *       handles that via revalidation)
 *
 *   For our customer base size (~hundreds of merchants, mostly small
 *   stores), the casual-pirate vector is the dominant leak path and
 *   this is enough.
 *
 * Marker location:
 *   `pipe-pay/.pipepay-build` inside the zip (dotfile so it doesn't show
 *   up in casual file listings; no behavior - the plugin never reads
 *   it; survives unzip + WP upgrade flow because the upgrade extracts
 *   into a fresh plugins/pipe-pay/ directory).
 *
 * Marker format (JSON, base64-decoded from the file contents):
 *   {
 *     "v":   1,                       // marker schema version
 *     "iat": 1778336547,              // unix timestamp of download
 *     "cid": 42,                      // Woo customer/user ID
 *     "oid": 1234,                    // order ID the download was tied to
 *     "ver": "1.8.1",                 // plugin version downloaded
 *     "sig": "base64-ed25519-sig"     // signature over the canonical
 *   }
 *
 * Canonical signed:
 *   download-v1|<iat>|<cid>|<oid>|<ver>
 *
 *   Distinct `download-v1|` prefix from activation `v1|` and revalidate
 *   `revalidate-v1|` so a captured signature from one context can't be
 *   replayed in another. No api_key in the canonical - we deliberately
 *   keep the license credential OUT of the marker so a leaked zip on a
 *   customer's own disk doesn't expose their key. The (cid, oid) pair
 *   is enough to look up the customer in our DB.
 *
 * Forensic decoder:
 *   - Admin page: Pipe Pay -> Decode Leaked Zip - upload the suspicious
 *     zip, see the signed verdict (customer, order, when, version,
 *     license key last 4) plus a one-click "Revoke this license" button
 *     that hands off to the Phase C tool.
 *   - wp-cli: `wp pipepay-decode-zip /path/to/leaked.zip` for headless
 *     use (e.g., automated forum-scrape pipelines).
 *
 * Performance:
 *   ZipArchive on a ~140KB plugin zip + adding a ~200-byte marker file
 *   = a few ms of overhead per download. Watermarked copies are written
 *   to /tmp and cleaned up by an hourly wp-cron sweeper.
 */

defined( 'ABSPATH' ) || exit;

const PIPEPAY_WATERMARK_TMP_PREFIX     = 'pipepay-wm-';
const PIPEPAY_WATERMARK_TMP_TTL        = HOUR_IN_SECONDS;
const PIPEPAY_WATERMARK_SWEEP_HOOK     = 'pipepay_watermark_sweep_tmp';
const PIPEPAY_WATERMARK_MARKER_PATH    = 'pipe-pay/.pipepay-build';
const PIPEPAY_WATERMARK_FILENAME_REGEX = '/^pipe-pay-v\d+\.\d+\.\d+\.zip$/';

// ── Watermark hook ───────────────────────────────────────────────────────────
add_filter( 'woocommerce_file_download_path', 'pipepay_watermark_intercept_download', 10, 3 );

/**
 * Intercept a Woo file-download path. If the file is a Pipe Pay zip and
 * we have enough request context to identify the customer, build a
 * per-customer watermarked copy in /tmp and return that path instead.
 * Otherwise return the original path unchanged.
 *
 * @param string     $file_path   the resolved on-disk path WC was about to serve
 * @param WC_Product $product     the WC product carrying the download
 * @param string     $download_id the download key inside the product's _downloadable_files
 * @return string watermarked /tmp path, or the original path on any miss
 */
function pipepay_watermark_intercept_download( $file_path, $product, $download_id ) {
    if ( ! is_string( $file_path ) || '' === $file_path || ! file_exists( $file_path ) ) {
        return $file_path;
    }
    $basename = basename( $file_path );
    if ( ! preg_match( PIPEPAY_WATERMARK_FILENAME_REGEX, $basename ) ) {
        return $file_path;
    }

    // Pull customer + order context from the secure download URL params.
    // WC's download URL is always shaped like
    //   ?download_file=<pid>&order=<order_key>&email=<email>&key=<file_key>
    // so $_GET['order'] gives us the order_key, which we then resolve.
    $order_key = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : '';
    if ( '' === $order_key ) {
        return $file_path;
    }
    $order_id = wc_get_order_id_by_order_key( $order_key );
    if ( ! $order_id ) {
        return $file_path;
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return $file_path;
    }
    $customer_id = (int) $order->get_customer_id();

    // Plugin version we're handing out: read from product postmeta we
    // bumped on each release. If absent, fall back to scraping the
    // filename so we never silently sign with a wrong version.
    $plugin_version = (string) get_post_meta( $product->get_id(), '_product_version', true );
    if ( '' === $plugin_version ) {
        if ( preg_match( '/pipe-pay-v(\d+\.\d+\.\d+)\.zip$/', $basename, $m ) ) {
            $plugin_version = $m[1];
        } else {
            return $file_path; // can't determine version - bail rather than sign nonsense
        }
    }

    $marker_json = pipepay_watermark_mint_marker( $customer_id, $order_id, $plugin_version );
    if ( null === $marker_json ) {
        // Signing failed (sodium missing, key missing, etc). Fail-open:
        // serve the un-watermarked zip rather than blocking the download.
        // Customer should never be punished by our infra failures.
        return $file_path;
    }

    $watermarked_path = pipepay_watermark_build_zip( $file_path, $marker_json, $customer_id );
    if ( null === $watermarked_path ) {
        return $file_path; // ZipArchive failed; serve unmodified
    }
    return $watermarked_path;
}

/**
 * Mint a signed marker JSON for a given (customer, order, version) tuple.
 * Returns the JSON string ready to be written into the zip, or null if
 * signing isn't available.
 */
function pipepay_watermark_mint_marker( int $customer_id, int $order_id, string $plugin_version ): ?string {
    if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
        error_log( '[pipepay-license-watermark] sodium extension missing; download will not be watermarked' );
        return null;
    }
    if ( ! defined( 'PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY' ) ) {
        error_log( '[pipepay-license-watermark] PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY missing; download will not be watermarked' );
        return null;
    }
    $secret_key = base64_decode( PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY, true );
    if ( $secret_key === false || strlen( $secret_key ) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ) {
        error_log( '[pipepay-license-watermark] PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY malformed' );
        return null;
    }

    $iat       = time();
    $canonical = sprintf( 'download-v1|%d|%d|%d|%s', $iat, $customer_id, $order_id, $plugin_version );

    try {
        $sig = sodium_crypto_sign_detached( $canonical, $secret_key );
    } catch ( \Throwable $e ) {
        error_log( '[pipepay-license-watermark] signing threw: ' . $e->getMessage() );
        return null;
    } finally {
        sodium_memzero( $secret_key );
    }

    $marker = array(
        'v'   => 1,
        'iat' => $iat,
        'cid' => $customer_id,
        'oid' => $order_id,
        'ver' => $plugin_version,
        'sig' => base64_encode( $sig ),
    );
    return wp_json_encode( $marker, JSON_UNESCAPED_SLASHES );
}

/**
 * Copy the source zip to a unique /tmp file, inject the marker at
 * pipe-pay/.pipepay-build, and return the new path. Returns null on any
 * ZipArchive failure (caller should fall back to the unwatermarked source).
 */
function pipepay_watermark_build_zip( string $source_path, string $marker_json, int $customer_id ): ?string {
    if ( ! class_exists( 'ZipArchive' ) ) {
        error_log( '[pipepay-license-watermark] ZipArchive missing; download will not be watermarked' );
        return null;
    }

    // Unique tmp filename. Includes customer_id for log-correlation but
    // also a random suffix so concurrent downloads from the same
    // customer don't race on the same file.
    $rand = function_exists( 'wp_generate_password' )
        ? wp_generate_password( 16, false, false )
        : bin2hex( random_bytes( 8 ) );
    $tmp_path = sys_get_temp_dir() . '/' . PIPEPAY_WATERMARK_TMP_PREFIX . $customer_id . '-' . $rand . '.zip';

    if ( ! @copy( $source_path, $tmp_path ) ) {
        error_log( '[pipepay-license-watermark] failed to copy source zip to ' . $tmp_path );
        return null;
    }

    $zip = new ZipArchive();
    $opened = $zip->open( $tmp_path );
    if ( true !== $opened ) {
        @unlink( $tmp_path );
        error_log( '[pipepay-license-watermark] ZipArchive open failed: code ' . $opened );
        return null;
    }
    // addFromString replaces an existing entry of the same path, so
    // re-watermarking a previously-watermarked source zip is safe.
    if ( ! $zip->addFromString( PIPEPAY_WATERMARK_MARKER_PATH, $marker_json ) ) {
        $zip->close();
        @unlink( $tmp_path );
        error_log( '[pipepay-license-watermark] addFromString failed' );
        return null;
    }
    if ( ! $zip->close() ) {
        @unlink( $tmp_path );
        error_log( '[pipepay-license-watermark] ZipArchive close failed' );
        return null;
    }

    return $tmp_path;
}

// ── Tmp file sweeper (hourly wp-cron) ────────────────────────────────────────
// Cleans up /tmp/pipepay-wm-* files older than the TTL. Belt-and-braces
// in case a download is interrupted mid-stream and the shutdown handler
// doesn't fire. Most OS /tmp dirs are also auto-cleared on reboot, so
// this is just a freshness guarantee under normal uptime.
add_action( PIPEPAY_WATERMARK_SWEEP_HOOK, 'pipepay_watermark_sweep_tmp_handler' );
add_action( 'admin_init', function () {
    if ( ! wp_next_scheduled( PIPEPAY_WATERMARK_SWEEP_HOOK ) ) {
        wp_schedule_event( time() + 600, 'hourly', PIPEPAY_WATERMARK_SWEEP_HOOK );
    }
} );

function pipepay_watermark_sweep_tmp_handler(): void {
    $tmp = sys_get_temp_dir();
    if ( ! is_dir( $tmp ) ) {
        return;
    }
    $now    = time();
    $cutoff = $now - PIPEPAY_WATERMARK_TMP_TTL;
    $glob   = glob( rtrim( $tmp, '/' ) . '/' . PIPEPAY_WATERMARK_TMP_PREFIX . '*.zip' );
    if ( ! is_array( $glob ) ) {
        return;
    }
    foreach ( $glob as $path ) {
        $mtime = @filemtime( $path );
        if ( $mtime !== false && $mtime < $cutoff ) {
            @unlink( $path );
        }
    }
}

// Also try to clean up THIS download's tmp file right after WC finishes
// streaming. This hits the common case fast; the cron sweeper is the
// safety net for interrupted streams.
add_action( 'shutdown', function () {
    if ( ! defined( 'PIPEPAY_WATERMARK_LAST_TMP' ) ) {
        return;
    }
    $path = PIPEPAY_WATERMARK_LAST_TMP;
    if ( is_string( $path ) && '' !== $path && file_exists( $path )
        && strpos( basename( $path ), PIPEPAY_WATERMARK_TMP_PREFIX ) === 0 ) {
        @unlink( $path );
    }
} );

// Track the most recent tmp-file path we returned so the shutdown hook
// can clean it up. Single-request scope; downloads are one-per-request.
add_filter( 'woocommerce_file_download_path', function ( $path ) {
    if ( is_string( $path ) && '' !== $path
        && strpos( basename( $path ), PIPEPAY_WATERMARK_TMP_PREFIX ) === 0
        && ! defined( 'PIPEPAY_WATERMARK_LAST_TMP' ) ) {
        define( 'PIPEPAY_WATERMARK_LAST_TMP', $path );
    }
    return $path;
}, 11, 1 ); // priority 11 so we run AFTER the watermark filter at 10

// ─────────────────────────────────────────────────────────────────────────────
// Forensic decoder: admin page + helper
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'pipepay_watermark_decoder_register_menu', 21 );

/**
 * Sit the decoder under the Pipe Pay menu next to License Revocation, or
 * fall back to Tools if the gateway plugin isn't installed on the
 * pipepay.app instance for some reason.
 */
function pipepay_watermark_decoder_register_menu(): void {
    global $admin_page_hooks;
    $parent = isset( $admin_page_hooks['pipepay-proofs'] ) ? 'pipepay-proofs' : 'tools.php';

    add_submenu_page(
        $parent,
        __( 'Decode Leaked Zip', 'pipe-pay' ),
        __( 'Decode Leaked Zip', 'pipe-pay' ),
        'manage_options',
        'pipepay-decode-zip',
        'pipepay_watermark_decoder_render_page'
    );
}

/**
 * Decode a watermark from a zip file path. Returns either:
 *   - array{ ok: true, marker: array, signature_check: 'ok', customer: WP_User|null, order: WC_Order|null, license_last4: ?string }
 *   - array{ ok: false, error: string }
 *
 * The signature_check is a separate boolean from ok: a marker that
 * decodes structurally but fails the Ed25519 verify is "ok=true" with
 * signature_check='failed' so the operator sees the marker contents
 * AND knows it was tampered with.
 */
function pipepay_watermark_decode_zip_path( string $zip_path ): array {
    if ( ! file_exists( $zip_path ) ) {
        return array( 'ok' => false, 'error' => 'File does not exist: ' . $zip_path );
    }
    if ( ! class_exists( 'ZipArchive' ) ) {
        return array( 'ok' => false, 'error' => 'ZipArchive PHP extension is not available on this server.' );
    }
    $zip = new ZipArchive();
    $opened = $zip->open( $zip_path );
    if ( true !== $opened ) {
        return array( 'ok' => false, 'error' => 'Could not open zip (code ' . (int) $opened . '). File may be corrupt or not a zip.' );
    }
    $marker_str = $zip->getFromName( PIPEPAY_WATERMARK_MARKER_PATH );
    $zip->close();
    if ( false === $marker_str || '' === $marker_str ) {
        return array(
            'ok'    => false,
            'error' => 'No watermark marker found at ' . PIPEPAY_WATERMARK_MARKER_PATH . '. ' .
                       'This zip is either pre-Phase-B (released before 2026-05-09), ' .
                       'has been deliberately scrubbed, or was rebuilt from extracted files.',
        );
    }

    $marker = json_decode( $marker_str, true );
    if ( ! is_array( $marker ) ) {
        return array( 'ok' => false, 'error' => 'Marker is not valid JSON.' );
    }
    foreach ( array( 'v', 'iat', 'cid', 'oid', 'ver', 'sig' ) as $required_key ) {
        if ( ! array_key_exists( $required_key, $marker ) ) {
            return array( 'ok' => false, 'error' => 'Marker is missing required key: ' . $required_key );
        }
    }
    if ( 1 !== (int) $marker['v'] ) {
        return array( 'ok' => false, 'error' => 'Unsupported marker version: ' . $marker['v'] );
    }

    $sig_check = pipepay_watermark_verify_marker(
        (int) $marker['iat'],
        (int) $marker['cid'],
        (int) $marker['oid'],
        (string) $marker['ver'],
        (string) $marker['sig']
    );

    $customer = get_userdata( (int) $marker['cid'] ) ?: null;
    $order    = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $marker['oid'] ) : null;
    if ( ! ( $order instanceof WC_Order ) ) {
        $order = null;
    }

    // Best-effort: pick the first license tied to this order out of the
    // Kestrel resource table so the operator can hand it straight to the
    // revoke tool. Only the last 4 chars are surfaced - the full key
    // never appears in this UI.
    $license_last4 = null;
    if ( $order ) {
        global $wpdb;
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
        $key = $wpdb->get_var( $wpdb->prepare(
            "SELECT master_api_key FROM {$wpdb->prefix}wc_am_api_resource WHERE order_id = %d ORDER BY api_resource_id ASC LIMIT 1",
            $order->get_id()
        ) );
        // phpcs:enable
        if ( is_string( $key ) && '' !== $key ) {
            $license_last4 = substr( $key, -4 );
        }
    }

    return array(
        'ok'              => true,
        'marker'          => $marker,
        'signature_check' => $sig_check,
        'customer'        => $customer,
        'order'           => $order,
        'license_last4'   => $license_last4,
    );
}

/**
 * Verify the Ed25519 signature on a watermark marker. Returns 'ok' or a
 * short error code string ('sodium_extension_missing', 'malformed_signature',
 * 'bundled_public_key_corrupt', 'signature_mismatch').
 */
function pipepay_watermark_verify_marker( int $iat, int $cid, int $oid, string $ver, string $sig_b64 ): string {
    if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
        return 'sodium_extension_missing';
    }
    $sig = base64_decode( $sig_b64, true );
    if ( false === $sig || strlen( $sig ) !== SODIUM_CRYPTO_SIGN_BYTES ) {
        return 'malformed_signature';
    }
    if ( ! defined( 'PIPEPAY_LICENSE_SIGNING_PUBLIC_KEY' ) ) {
        // The public key constant is normally defined by the Pipe Pay
        // plugin's pipepay-license-verify.php at plugin load. If we got
        // called from a context where that hasn't loaded yet (e.g.
        // wp-cli without WP fully bootstrapped), fall back to the
        // hardcoded production key. This MUST match the plugin's
        // bundled value - keep them in sync if either rotates.
        define( 'PIPEPAY_LICENSE_SIGNING_PUBLIC_KEY', 'xH6qC5l1BnqqzQCPjnsl3T8G4qkbqtypRfIftr/7fyA=' );
    }
    $public_key = base64_decode( PIPEPAY_LICENSE_SIGNING_PUBLIC_KEY, true );
    if ( false === $public_key || strlen( $public_key ) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ) {
        return 'bundled_public_key_corrupt';
    }
    $canonical = sprintf( 'download-v1|%d|%d|%d|%s', $iat, $cid, $oid, $ver );
    $ok = sodium_crypto_sign_verify_detached( $sig, $canonical, $public_key );
    return $ok ? 'ok' : 'signature_mismatch';
}

/**
 * Render the "Decode Leaked Zip" admin page. Lets the operator upload a
 * suspicious zip, see the decoded marker, and (if the customer comes
 * back resolved) hand off to the License Revocation tool with the key
 * pre-filled.
 */
function pipepay_watermark_decoder_render_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Insufficient permissions.', '', array( 'response' => 403 ) );
    }

    $result = null;
    if ( isset( $_POST['pipepay_decode_action'] ) && 'decode' === $_POST['pipepay_decode_action'] ) {
        check_admin_referer( 'pipepay_watermark_decode' );
        if ( empty( $_FILES['leaked_zip']['tmp_name'] ) ) {
            $result = array( 'ok' => false, 'error' => 'No file uploaded.' );
        } else {
            $tmp_path = (string) $_FILES['leaked_zip']['tmp_name'];
            $upload_error = (int) ( $_FILES['leaked_zip']['error'] ?? UPLOAD_ERR_NO_FILE );
            if ( UPLOAD_ERR_OK !== $upload_error ) {
                $result = array( 'ok' => false, 'error' => 'Upload error code ' . $upload_error );
            } else {
                $result = pipepay_watermark_decode_zip_path( $tmp_path );
            }
        }
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Decode Leaked Zip', 'pipe-pay' ); ?></h1>
        <p>
            Upload a suspicious Pipe Pay zip (e.g. one found on a piracy forum) and
            this tool will decode the embedded watermark - introduced in plugin
            v1.8.2 - to identify the customer who originally downloaded it.
        </p>
        <p>
            The watermark lives at <code><?php echo esc_html( PIPEPAY_WATERMARK_MARKER_PATH ); ?></code>
            inside the zip and is signed with our Ed25519 license key. A signature
            mismatch means the marker was tampered with (or the zip predates Phase B).
            No marker at all means the zip was deliberately scrubbed or built from
            extracted-then-rezipped files.
        </p>

        <form method="post" enctype="multipart/form-data" style="margin-top:20px;">
            <?php wp_nonce_field( 'pipepay_watermark_decode' ); ?>
            <input type="hidden" name="pipepay_decode_action" value="decode" />
            <p>
                <label for="leaked_zip" style="display:block;margin-bottom:6px;font-weight:600;">
                    <?php esc_html_e( 'Suspicious zip file', 'pipe-pay' ); ?>
                </label>
                <input type="file" id="leaked_zip" name="leaked_zip" accept=".zip" required />
            </p>
            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Decode marker', 'pipe-pay' ); ?></button>
            </p>
        </form>

        <?php if ( $result ) : ?>
            <div class="card" style="max-width:760px;padding:20px 24px;margin-top:24px;">
                <?php if ( ! empty( $result['ok'] ) ) :
                    $marker = $result['marker'];
                    $sig    = $result['signature_check'];
                    $sig_color = 'ok' === $sig ? '#0e7950' : '#b32d2e';
                    $cust   = $result['customer'];
                    $order  = $result['order'];
                ?>
                    <h2 style="margin-top:0;">Decoded marker</h2>
                    <p style="margin-bottom:6px;">
                        <strong>Signature check:</strong>
                        <span style="color:<?php echo esc_attr( $sig_color ); ?>;font-weight:600;">
                            <?php echo esc_html( $sig ); ?>
                        </span>
                    </p>
                    <p style="margin-bottom:6px;">
                        <strong>Downloaded at:</strong>
                        <?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $marker['iat'] ) ); ?> (UTC server time)
                    </p>
                    <p style="margin-bottom:6px;">
                        <strong>Plugin version:</strong> <code><?php echo esc_html( (string) $marker['ver'] ); ?></code>
                    </p>
                    <p style="margin-bottom:6px;">
                        <strong>Customer:</strong>
                        <?php if ( $cust ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $cust->ID ) ); ?>"><?php echo esc_html( $cust->user_login ); ?></a>
                            (<?php echo esc_html( $cust->user_email ); ?>, ID <?php echo esc_html( (string) $cust->ID ); ?>)
                        <?php else : ?>
                            <em>not found</em> (cid <?php echo esc_html( (string) $marker['cid'] ); ?>)
                        <?php endif; ?>
                    </p>
                    <p style="margin-bottom:6px;">
                        <strong>Order:</strong>
                        <?php if ( $order ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ); ?>">#<?php echo esc_html( (string) $order->get_id() ); ?></a>
                            placed <?php echo esc_html( wp_date( 'Y-m-d', (int) $order->get_date_created()->getTimestamp() ) ); ?>
                            (status: <?php echo esc_html( $order->get_status() ); ?>)
                        <?php else : ?>
                            <em>not found</em> (oid <?php echo esc_html( (string) $marker['oid'] ); ?>)
                        <?php endif; ?>
                    </p>
                    <p style="margin-bottom:16px;">
                        <strong>License key:</strong>
                        <?php if ( ! empty( $result['license_last4'] ) ) : ?>
                            <code>········<?php echo esc_html( $result['license_last4'] ); ?></code>
                        <?php else : ?>
                            <em>not found in <code>wc_am_api_resource</code></em>
                        <?php endif; ?>
                    </p>
                    <?php if ( 'ok' === $sig && $cust ) : ?>
                        <p>
                            <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=pipepay-license-revocation' ) ); ?>">
                                Hand off to License Revocation &rarr;
                            </a>
                            (paste the license key on that page; we don't auto-fill to keep the revoke decision an explicit two-step.)
                        </p>
                    <?php endif; ?>
                    <details style="margin-top:16px;">
                        <summary>Raw marker JSON</summary>
                        <pre style="background:#f6f7f7;padding:12px;border:1px solid #ddd;overflow:auto;"><?php echo esc_html( wp_json_encode( $marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
                    </details>
                <?php else : ?>
                    <h2 style="margin-top:0;color:#b32d2e;">Decode failed</h2>
                    <p><?php echo esc_html( (string) ( $result['error'] ?? 'Unknown error.' ) ); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// wp-cli command for headless decoding
// ─────────────────────────────────────────────────────────────────────────────

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    /**
     * `wp pipepay-decode-zip /path/to/leaked.zip`
     *
     * Useful for piping leaked-zip discovery from a forum-scrape or
     * ticket-attachment pipeline straight into a JSON output for triage.
     */
    WP_CLI::add_command( 'pipepay-decode-zip', function ( $args ) {
        $path = $args[0] ?? '';
        if ( '' === $path ) {
            WP_CLI::error( 'Usage: wp pipepay-decode-zip /path/to/leaked.zip' );
        }
        $result = pipepay_watermark_decode_zip_path( $path );
        if ( empty( $result['ok'] ) ) {
            WP_CLI::error( (string) ( $result['error'] ?? 'unknown error' ) );
        }
        $marker = $result['marker'];
        $cust   = $result['customer'];
        $order  = $result['order'];
        $output = array(
            'signature_check' => $result['signature_check'],
            'downloaded_at'   => wp_date( 'c', (int) $marker['iat'] ),
            'plugin_version'  => (string) $marker['ver'],
            'customer'        => $cust ? array(
                'id'       => (int) $cust->ID,
                'login'    => (string) $cust->user_login,
                'email'    => (string) $cust->user_email,
            ) : null,
            'order'           => $order ? array(
                'id'     => (int) $order->get_id(),
                'status' => (string) $order->get_status(),
            ) : null,
            'license_last4'   => (string) ( $result['license_last4'] ?? '' ),
        );
        WP_CLI::print_value( $output, array( 'format' => 'json' ) );
    } );
}
