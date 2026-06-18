<?php
/**
 * Pipe Pay paid-renewal email (T-0 = expiry day).
 *
 * Triggered: daily cron when a paid license `access_expires` is today.
 * Clear-warning tone: 30-day grace period, then the gateway stops accepting
 * new orders. The stop date is computed at send time (sent on expiry day,
 * so send-time + 30 days ~= the plugin-side cutoff).
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
    $first_name  = sanitize_text_field( $first_name );
    $tier_name   = sanitize_text_field( $tier_name );
    $renewal_url = esc_url_raw( $renewal_url );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    $stop_label = sanitize_text_field( date_i18n( 'F j, Y', time() + 30 * DAY_IN_SECONDS ) );

    echo "Your Pipe Pay {$tier_name} license expires today.\n\n";
    echo "Your gateway keeps accepting orders during a 30-day grace period.\n";
    echo "Around {$stop_label} it stops offering Pipe Pay at checkout until\n";
    echo "you renew. Auto-updates and support pause starting today.\n\n";
    echo "Renew now and nothing changes:\n\n";
    echo "{$renewal_url}\n\n";
    echo "Orders already in progress always finish normally, and renewing at\n";
    echo "any point - before or after the stop date - restores checkout.\n\n";
    echo "Prefer a card? Choose the card option at checkout and your license\n";
    echo "switches to automatic yearly renewal.\n\n";
    echo "Questions? Email support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph(
    'Your Pipe Pay <strong>' . esc_html( $tier_name ) . '</strong> license expires today.'
);
pp_email_paragraph(
    'Your gateway keeps accepting orders during a 30-day grace period. Around <strong>' . esc_html( date_i18n( 'F j, Y', time() + 30 * DAY_IN_SECONDS ) ) . '</strong> it stops offering Pipe Pay at checkout until you renew. Auto-updates and support pause starting today.'
);
pp_email_button( $renewal_url, 'Renew now &rarr;' );
pp_email_paragraph( 'Orders already in progress always finish normally, and renewing at any point &mdash; before or after the stop date &mdash; restores checkout.' );
pp_email_paragraph( 'Prefer a card? <a href=\"https://pipepay.app/pricing/\" style=\"color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;\">Auto-renewing card billing is available on the pricing page</a> &mdash; pick your tier and use the Buy now button.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
