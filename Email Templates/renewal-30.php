<?php
/**
 * Pipe Pay paid-renewal email (T-30 days).
 *
 * Triggered: daily cron when a paid license (products 34/35/36) is exactly
 * 30 days from `access_expires`.
 *
 * Expected scope:
 *   $first_name, $tier_name, $expires_label, $renewal_url, $email,
 *   $email_heading, $sent_to_admin, $plain_text, $additional_content
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
    echo "Just a heads-up: your Pipe Pay {$tier_name} license expires on\n";
    echo "{$expires_label} (30 days from now). It does not renew on its own -\n";
    echo "renew before that date to keep everything running:\n\n";
    echo "Renew now in one click so you keep auto-updates, support, and the\n";
    echo "fraud-detection improvements we ship monthly:\n\n";
    echo "{$renewal_url}\n\n";
    echo "Your billing details are pre-filled - renewal takes about 30 seconds.\n";
    echo "Or just wait - we'll send another reminder a week before your\n";
    echo "expiry date if you haven't renewed by then.\n\n";
    echo "Prefer a card? Auto-renewing card billing is available at\n";
    echo "https://pipepay.app/pricing/ - pick your tier and use the Buy now button.\n\n";
    echo "Questions? Reply to this email or contact support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph(
    'Just a heads-up: your Pipe Pay <strong>' . esc_html( $tier_name ) . '</strong> license expires on <strong>' . esc_html( $expires_label ) . '</strong> &mdash; 30 days from now. It does not renew on its own; renew before that date to keep everything running.'
);
pp_email_paragraph( 'Renew now in one click to keep auto-updates, support, and the fraud-detection improvements we ship monthly.' );
pp_email_button( $renewal_url, 'Renew now &rarr;' );
pp_email_paragraph( 'Your billing details are pre-filled — renewal takes about 30 seconds. Or just wait; we\'ll send another reminder a week before your expiry date.' );
pp_email_paragraph( 'Prefer a card? <a href=\"https://pipepay.app/pricing/\" style=\"color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;\">Auto-renewing card billing is available on the pricing page</a> &mdash; pick your tier and use the Buy now button.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
