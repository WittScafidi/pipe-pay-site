<?php
/**
 * Pipe Pay free-trial activation email.
 *
 * Triggered: when a $0 trial order (product 38) hits completed status. Wired
 * via theme wrapper `pipepay-child/woocommerce/emails/customer-completed-order.php`
 * which branches on `pp_order_is_trial( $order )`.
 *
 * Expected scope (set by the wrapper):
 *   $order              WC_Order
 *   $email_heading      string
 *   $email              WC_Email
 *   $sent_to_admin      bool
 *   $plain_text         bool
 *   $additional_content string
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

// -----------------------------------------------------------------------------
// Data fetch
// -----------------------------------------------------------------------------

global $wpdb;

$license_key = $wpdb->get_var( $wpdb->prepare(
    "SELECT master_api_key FROM {$wpdb->prefix}wc_am_api_resource WHERE order_id = %d AND product_id = 38 LIMIT 1",
    $order->get_id()
) );

$expires_raw = $wpdb->get_var( $wpdb->prepare(
    "SELECT access_expires FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions WHERE order_id = %d AND product_id = 38 LIMIT 1",
    $order->get_id()
) );

if ( $expires_raw && '0000-00-00 00:00:00' !== $expires_raw ) {
    $expires_dt = wc_string_to_datetime( $expires_raw );
} else {
    $expires_dt = clone $order->get_date_created();
    $expires_dt->modify( '+7 days' );
}
$expires_label = wc_format_datetime( $expires_dt, 'F j, Y' );

$download_url = '';
foreach ( $order->get_items() as $item ) {
    if ( (int) $item->get_product_id() !== 38 ) {
        continue;
    }
    $product = $item->get_product();
    if ( ! $product ) {
        continue;
    }
    $downloads = $product->get_downloads();
    if ( ! empty( $downloads ) ) {
        $download_id  = key( $downloads );
        $download_url = $order->get_download_url( 38, $download_id );
    }
    break;
}

$first_name = $order->get_billing_first_name();

// -----------------------------------------------------------------------------
// Plain text
// -----------------------------------------------------------------------------

if ( $plain_text ) :
    // Defensive sanitization at the plain-text boundary - strips control chars
    // (incl. \r\n) from anything that came from a customer billing field or
    // external source. esc_url_raw() validates the renewal/download URLs.
    $first_name    = sanitize_text_field( $first_name );
    $license_key   = sanitize_text_field( (string) $license_key );
    $expires_label = sanitize_text_field( $expires_label );
    $download_url  = esc_url_raw( (string) $download_url );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your free 7-day Pipe Pay trial is live - no card required.\n\n";
    if ( $license_key ) {
        echo "Your license key:\n{$license_key}\n\n";
    } else {
        echo "Your license key will arrive in a separate email within a few minutes.\n\n";
    }
    echo "Trial expires: {$expires_label}\n\n";
    echo "Three steps to get started:\n";
    echo "  1. Download the plugin: " . ( $download_url ?: 'see your account dashboard' ) . "\n";
    echo "  2. Upload it in WordPress -> Plugins -> Add New -> Upload\n";
    echo "  3. Paste the license key into Pipe Pay -> License to activate\n\n";
    echo "We'll send a one-click renewal link before your trial expires so you\n";
    echo "can convert to a paid tier without re-entering your details. No charge\n";
    echo "unless you choose to continue.\n\n";
    echo "Questions? Reply to this email or contact support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

// -----------------------------------------------------------------------------
// HTML
// -----------------------------------------------------------------------------

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Your free 7-day Pipe Pay trial is live &mdash; no card required.' );
pp_email_license_card( $license_key );

pp_email_paragraph( 'Three steps to get started:', 12 );
?>
<ol style="margin:0 0 24px 22px;padding:0;font-size:16px;line-height:1.6;color:<?php echo esc_attr( pp_email_text_color() ); ?>;">
    <li style="margin:0 0 6px 0;">
        <?php if ( $download_url ) : ?>
            <a href="<?php echo esc_url( $download_url ); ?>" style="color:<?php echo esc_attr( pp_email_brand_color() ); ?>;text-decoration:underline;">Download the Pipe Pay plugin</a> (zip)
        <?php else : ?>
            Download the plugin from your <a href="<?php echo esc_url( home_url( '/my-account/downloads/' ) ); ?>" style="color:<?php echo esc_attr( pp_email_brand_color() ); ?>;text-decoration:underline;">account downloads</a>
        <?php endif; ?>
    </li>
    <li style="margin:0 0 6px 0;">Upload it in WordPress &rarr; <strong>Plugins &rarr; Add New &rarr; Upload Plugin</strong></li>
    <li style="margin:0 0 6px 0;">Paste your license key into <strong>Pipe Pay &rarr; License</strong> to activate</li>
</ol>
<?php

pp_email_paragraph(
    'Your trial expires <strong>' . esc_html( $expires_label ) . '</strong>. We\'ll email a one-click renewal link before then so you can convert to a paid tier without re-entering your details. No charge unless you choose to continue.'
);
pp_email_signoff();

do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
