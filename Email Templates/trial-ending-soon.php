<?php
/**
 * Pipe Pay trial-ending-soon email (T-2 days).
 *
 * Triggered: daily cron in `pipepay-license-renewals.php` mu-plugin when a
 * trial license (product 38) is exactly 2 days from `access_expires`.
 *
 * Expected scope (set by the cron handler):
 *   $first_name        string
 *   $expires_label     string  e.g. "June 4, 2026"
 *   $renewal_url       string  HMAC-signed /renew/?key=...&token=... URL
 *   $email             WC_Email
 *   $email_heading     string
 *   $sent_to_admin     bool
 *   $plain_text        bool
 *   $additional_content string
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

if ( $plain_text ) :
    $first_name    = sanitize_text_field( $first_name );
    $expires_label = sanitize_text_field( $expires_label );
    $renewal_url   = esc_url_raw( $renewal_url );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your free 7-day Pipe Pay trial ends in 2 days ({$expires_label}).\n\n";
    echo "To keep the gateway running with auto-updates and support, click the\n";
    echo "link below to convert to a paid tier:\n\n";
    echo "{$renewal_url}\n\n";
    echo "No card required to start - you'll only be charged after you confirm.\n";
    echo "Your details are pre-filled so renewal takes about 30 seconds.\n\n";
    echo "If the trial ends without an upgrade, the gateway keeps accepting\n";
    echo "orders for a 30-day grace period, then stops offering Pipe Pay at\n";
    echo "checkout until you pick a paid tier. Updates and support pause as\n";
    echo "soon as the trial ends.\n\n";
    echo "Prefer a card? Auto-renewing card billing is available at\n";
    echo "https://pipepay.app/pricing/ - pick your tier and use the Buy now button.\n\n";
    echo "Questions? Reply to this email or contact support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph(
    'Your free 7-day Pipe Pay trial ends in <strong>2 days</strong> (' . esc_html( $expires_label ) . '). To keep the gateway running with auto-updates and support, click below to convert to a paid tier.'
);
pp_email_button( $renewal_url, 'Pick a tier &rarr;' );
pp_email_paragraph( 'No card required to start — you\'ll only be charged after you confirm. Your details are pre-filled so renewal takes about 30 seconds.' );
pp_email_paragraph( 'If the trial ends without an upgrade, the gateway keeps accepting orders for a 30-day grace period, then stops offering Pipe Pay at checkout until you pick a paid tier. Auto-updates and support pause as soon as the trial ends.' );
pp_email_paragraph( 'Prefer a card? <a href=\"https://pipepay.app/pricing/\" style=\"color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;\">Auto-renewing card billing is available on the pricing page</a> &mdash; pick your tier and use the Buy now button.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
