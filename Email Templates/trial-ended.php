<?php
/**
 * Pipe Pay trial-ended email (T+0).
 *
 * Triggered: daily cron when a trial license hits `access_expires` today.
 *
 * Expected scope:
 *   $first_name, $renewal_url, $email, $email_heading, $sent_to_admin,
 *   $plain_text, $additional_content
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

if ( $plain_text ) :
    $first_name  = sanitize_text_field( $first_name );
    $renewal_url = esc_url_raw( $renewal_url );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your free 7-day Pipe Pay trial ended today.\n\n";
    echo "Your gateway keeps accepting orders during a 30-day grace period,\n";
    echo "then stops offering Pipe Pay at checkout until you pick a paid\n";
    echo "tier. Auto-updates and support\n";
    echo "are paused until you convert to a paid tier.\n\n";
    echo "Pick a tier and renew in one click (no need to re-enter details):\n";
    echo "{$renewal_url}\n\n";
    echo "Three paid tiers:\n";
    echo "  - Single Site     - \$299/yr  - 1 site\n";
    echo "  - 5 Sites         - \$599/yr  - up to 5 sites\n";
    echo "  - Unlimited Sites - \$1,199/yr - any number\n\n";
    echo "Questions? Reply to this email or contact support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Your free 7-day Pipe Pay trial ended today.' );
pp_email_paragraph( 'Your gateway keeps accepting orders during a <strong>30-day grace period</strong>, then stops offering Pipe Pay at checkout until you pick a paid tier. Auto-updates and support are paused starting today.' );
pp_email_button( $renewal_url, 'Pick a tier &rarr;' );
pp_email_paragraph( 'Three tiers: Single Site $299/yr, 5 Sites $599/yr, Unlimited $1,199/yr. Renewal takes about 30 seconds — your billing details are pre-filled.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
