<?php
/**
 * Trial day 1 - license activated. Setup checklist toward a first verified order.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

$config_url = home_url( '/docs/configuration/' );

if ( $plain_text ) :
    $first_name = sanitize_text_field( $first_name );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your license is activated. Three things get you to your first\n";
    echo "verified payment:\n\n";
    echo "  1. Add your payment handles (Venmo, Cash App, PayPal, Zelle) under\n";
    echo "     WooCommerce -> Settings -> Payments -> Pipe Pay\n";
    echo "  2. Optional: add an AI provider key so screenshots verify\n";
    echo "     automatically (a few cents per order). Without one, every\n";
    echo "     upload lands in your Proofs queue for one-click manual review.\n";
    echo "  3. Place a \$1 test order on your own store and pay it from your\n";
    echo "     phone. Watching your own screenshot verify is the fastest way\n";
    echo "     to see what your customers will see.\n\n";
    echo "Configuration guide: " . esc_url_raw( $config_url ) . "\n\n";
    echo "Questions? Email support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Your license is activated. Three things get you to your first verified payment:' );
?>
<ol style="margin:0 0 24px 22px;padding:0;font-size:16px;line-height:1.6;color:<?php echo esc_attr( pp_email_text_color() ); ?>;">
    <li style="margin:0 0 6px 0;">Add your payment handles (Venmo, Cash App, PayPal, Zelle) under <strong>WooCommerce &rarr; Settings &rarr; Payments &rarr; Pipe Pay</strong></li>
    <li style="margin:0 0 6px 0;">Optional: add an AI provider key so screenshots verify automatically (a few cents per order). Without one, every upload lands in your Proofs queue for one-click manual review.</li>
    <li style="margin:0 0 6px 0;">Place a $1 test order on your own store and pay it from your phone. Watching your own screenshot verify is the fastest way to see what your customers will see.</li>
</ol>
<?php
pp_email_button( $config_url, 'Open the configuration guide' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
