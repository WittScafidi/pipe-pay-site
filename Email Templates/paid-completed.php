<?php
/**
 * Pipe Pay paid-tier license activation email.
 *
 * Triggered: when a paid order (products 34/35/36) hits completed status.
 * Wired via theme wrapper `pipepay-child/woocommerce/emails/customer-completed-order.php`
 * which branches on `pp_order_is_paid_tier( $order )`.
 *
 * Expected scope:
 *   $order, $email_heading, $email, $sent_to_admin, $plain_text, $additional_content
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

// Find the paid tier product on the order (one of 34/35/36)
$paid_product_id    = 0;
$paid_product_title = '';
$paid_activations   = 0;
foreach ( $order->get_items() as $item ) {
    $pid = (int) $item->get_product_id();
    if ( in_array( $pid, [ 34, 35, 36 ], true ) ) {
        $paid_product_id    = $pid;
        $product            = $item->get_product();
        $paid_product_title = $product ? $product->get_name() : 'Pipe Pay';
        // Activations from API Manager product meta
        $paid_activations = (int) get_post_meta( $pid, '_api_activations', true );
        break;
    }
}

$license_key = $wpdb->get_var( $wpdb->prepare(
    "SELECT master_api_key FROM {$wpdb->prefix}wc_am_api_resource WHERE order_id = %d AND product_id = %d LIMIT 1",
    $order->get_id(),
    $paid_product_id
) );

$expires_raw = $wpdb->get_var( $wpdb->prepare(
    "SELECT access_expires FROM {$wpdb->prefix}wc_am_api_resource WHERE order_id = %d AND product_id = %d LIMIT 1",
    $order->get_id(),
    $paid_product_id
) );

// Renewal orders: the renewal-completion hooks (mu-plugins/pipepay-license-renewals.php)
// extend the customer's EXISTING license and remove the key minted for this order, so
// the order-keyed lookups above point at a doomed duplicate. Show the original key and
// its freshly-extended expiry instead (the extension runs at priority 9, before emails).
$is_renewal = false;
foreach ( $order->get_items() as $item ) {
    $renewal_key = (string) $item->get_meta( '_pipepay_renewal_for_license', true );
    if ( '' !== $renewal_key ) {
        $renewal_expires = $wpdb->get_var( $wpdb->prepare(
            "SELECT access_expires FROM {$wpdb->prefix}wc_am_api_resource WHERE master_api_key = %s ORDER BY api_resource_id ASC LIMIT 1",
            $renewal_key
        ) );
        if ( $renewal_expires ) {
            $is_renewal  = true;
            $license_key = $renewal_key;
            $expires_raw = $renewal_expires;
        }
        break;
    }
}

if ( $expires_raw && (int) $expires_raw > 0 ) {
    $expires_dt    = ( new DateTime( '@' . (int) $expires_raw ) )->setTimezone( wp_timezone() );
    $expires_label = wc_format_datetime( $expires_dt, 'F j, Y' );
} else {
    // Fallback: order date + 365 days
    $expires_dt = clone $order->get_date_created();
    $expires_dt->modify( '+365 days' );
    $expires_label = wc_format_datetime( $expires_dt, 'F j, Y' );
}

// Plugin download URL
$download_url = '';
if ( $paid_product_id ) {
    $product = wc_get_product( $paid_product_id );
    if ( $product ) {
        $downloads = $product->get_downloads();
        if ( ! empty( $downloads ) ) {
            $download_id  = key( $downloads );
            $download_url = $order->get_download_url( $paid_product_id, $download_id );
        }
    }
}

$first_name        = $order->get_billing_first_name();
$activations_label = 36 === $paid_product_id ? 'unlimited sites' : ( $paid_activations . ' site' . ( $paid_activations > 1 ? 's' : '' ) );

// -----------------------------------------------------------------------------
// Plain text
// -----------------------------------------------------------------------------

if ( $plain_text ) :
    $first_name         = sanitize_text_field( $first_name );
    $paid_product_title = sanitize_text_field( $paid_product_title );
    $license_key        = sanitize_text_field( (string) $license_key );
    $activations_label  = sanitize_text_field( $activations_label );
    $expires_label      = sanitize_text_field( $expires_label );
    $download_url       = esc_url_raw( (string) $download_url );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    if ( $is_renewal ) {
        echo "Thanks for renewing the {$paid_product_title} license.\n\n";
        echo "Your existing license has been extended - same key, no changes needed on your store.\n\n";
        echo "Your license key (unchanged):\n{$license_key}\n\n";
        echo "Valid for: {$activations_label}\n";
        echo "Renews: {$expires_label}\n\n";
        echo "Updates and support continue uninterrupted. There is nothing to reinstall or re-activate.\n\n";
    } else {
        echo "Thanks for purchasing the {$paid_product_title} license.\n\n";
        if ( $license_key ) {
            echo "Your license key:\n{$license_key}\n\n";
        } else {
            echo "Your license key will arrive in a separate email within a few minutes.\n\n";
        }
        echo "Valid for: {$activations_label}\n";
        echo "Renews: {$expires_label}\n\n";
        echo "Three steps to activate:\n";
        echo "  1. Download the plugin: " . ( $download_url ?: 'see your account dashboard' ) . "\n";
        echo "  2. Upload it in WordPress -> Plugins -> Add New -> Upload\n";
        echo "  3. Paste the license key into Pipe Pay -> License\n\n";
    }
    echo "We'll email a one-click renewal link 30 days before your license expires.\n\n";
    echo "Questions? Email support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

// -----------------------------------------------------------------------------
// HTML
// -----------------------------------------------------------------------------

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
if ( $is_renewal ) {
    pp_email_paragraph( 'Thanks for renewing the <strong>' . esc_html( $paid_product_title ) . '</strong> license. Your existing license has been extended &mdash; same key, no changes needed on your store.' );
} else {
    pp_email_paragraph( 'Thanks for purchasing the <strong>' . esc_html( $paid_product_title ) . '</strong> license. Your activation details are below.' );
}
pp_email_license_card( $license_key );

pp_email_paragraph(
    'Valid for <strong>' . esc_html( $activations_label ) . '</strong> &middot; Renews <strong>' . esc_html( $expires_label ) . '</strong>'
);

if ( $is_renewal ) {
    pp_email_paragraph( 'Updates and support continue uninterrupted. There is nothing to reinstall or re-activate &mdash; your store keeps working exactly as it is.' );
} else {
    pp_email_paragraph( 'Three steps to activate:', 12 );
}
if ( ! $is_renewal ) :
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
    <li style="margin:0 0 6px 0;">Paste your license key into <strong>Pipe Pay &rarr; License</strong></li>
</ol>
<?php
endif;

pp_email_paragraph(
    'We\'ll email a one-click renewal link 30 days before your license expires so you can renew without re-entering your details.'
);
pp_email_signoff();

do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
