<?php
/**
 * Pipe Pay paid-renewal email (T-7 days).
 *
 * Triggered: daily cron when a paid license is exactly 7 days from
 * `access_expires`. Slightly firmer tone than T-30.
 *
 * Expected scope: same as renewal-30.php
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

if ( $plain_text ) :
    $first_name    = sanitize_text_field( $first_name );
    $tier_name     = sanitize_text_field( $tier_name );
    $expires_label = sanitize_text_field( $expires_label );
    $renewal_url   = esc_url_raw( $renewal_url );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "One week left on your Pipe Pay {$tier_name} license.\n";
    echo "Expires: {$expires_label}\n\n";
    echo "Renew in one click - your details are pre-filled, takes about 30 seconds:\n\n";
    echo "{$renewal_url}\n\n";
    echo "If it lapses, auto-updates and support pause right away, and 30 days\n";
    echo "after expiry the gateway stops accepting new orders on your store\n";
    echo "until you renew. Renewing at any point restores everything.\n\n";
    echo "Prefer a card? Choose the card option at checkout and your license\n";
    echo "switches to automatic yearly renewal.\n\n";
    echo "Questions? Reply to this email or contact support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph(
    'One week left on your Pipe Pay <strong>' . esc_html( $tier_name ) . '</strong> license. Expires <strong>' . esc_html( $expires_label ) . '</strong>.'
);
pp_email_button( $renewal_url, 'Renew now &rarr;' );
pp_email_paragraph( 'Your details are pre-filled — renewal takes about 30 seconds.' );
pp_email_paragraph( 'If it lapses, auto-updates and support pause right away &mdash; and <strong>30 days after expiry, the gateway stops accepting new orders</strong> on your store until you renew. Renewing at any point restores everything.' );
pp_email_paragraph( 'Prefer a card? <a href=\"https://pipepay.app/pricing/\" style=\"color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;\">Auto-renewing card billing is available on the pricing page</a> &mdash; pick your tier and use the Buy now button.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
