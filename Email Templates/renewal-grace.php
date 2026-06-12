<?php
/**
 * Pipe Pay paid-renewal email (T+7, in grace period).
 *
 * Triggered: daily cron 7 days after `access_expires`. Customer is in the
 * 30-day grace period; 23 days left before final notice.
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
    echo "Your Pipe Pay {$tier_name} license expired 7 days ago.\n\n";
    $stop_label = sanitize_text_field( date_i18n( 'F j, Y', time() + 23 * DAY_IN_SECONDS ) );

    echo "You're in the 30-day grace period: the gateway is still accepting\n";
    echo "orders, but auto-updates and support have paused - and around\n";
    echo "{$stop_label} the gateway stops offering Pipe Pay at checkout\n";
    echo "until you renew.\n\n";
    echo "About 23 days remain. Renew now and nothing changes:\n\n";
    echo "{$renewal_url}\n\n";
    echo "Your billing details are pre-filled. About 30 seconds to renew.\n\n";
    echo "Prefer a card? Auto-renewing card billing is available at\n";
    echo "https://pipepay.app/pricing/ - pick your tier and use the Buy now button.\n\n";
    echo "Questions? Reply to this email or contact support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph(
    'Your Pipe Pay <strong>' . esc_html( $tier_name ) . '</strong> license expired 7 days ago.'
);
pp_email_paragraph(
    'You\'re in the 30-day grace period: the gateway is still accepting orders, but auto-updates and support have paused &mdash; and around <strong>' . esc_html( date_i18n( 'F j, Y', time() + 23 * DAY_IN_SECONDS ) ) . '</strong> the gateway stops offering Pipe Pay at checkout until you renew.'
);
pp_email_paragraph( 'About 23 days remain. Renew now and nothing changes.' );
pp_email_button( $renewal_url, 'Renew now &rarr;' );
pp_email_paragraph( 'Your billing details are pre-filled. About 30 seconds to renew.' );
pp_email_paragraph( 'Prefer a card? <a href=\"https://pipepay.app/pricing/\" style=\"color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;\">Auto-renewing card billing is available on the pricing page</a> &mdash; pick your tier and use the Buy now button.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
