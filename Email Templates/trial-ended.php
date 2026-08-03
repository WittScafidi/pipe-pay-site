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
    $intent_tier_name    = sanitize_text_field( $intent_tier_name );
    $intent_tier_price   = sanitize_text_field( $intent_tier_price );
    $intent_monthly      = sanitize_text_field( $intent_monthly_price );
    $trial_extension_url = esc_url_raw( $trial_extension_url );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your free 7-day Pipe Pay trial ended today.\n\n";
    echo "Your gateway keeps accepting orders during a 30-day grace period,\n";
    echo "then stops offering Pipe Pay at checkout until you pick a paid\n";
    echo "tier. Auto-updates and support\n";
    echo "are paused until you convert to a paid tier.\n\n";
    echo "Pick a tier and renew in one click (no need to re-enter details):\n";
    echo "{$renewal_url}\n\n";
    if ( $intent_tier_name ) {
        echo "You picked the {$intent_tier_name} tier when you started: {$intent_tier_price}\n";
        echo "per year, or {$intent_monthly} per month, cancel anytime.\n\n";
    } else {
        echo "Three paid tiers:\n";
        echo "  - Single Site     - \$297/yr or \$35/mo  - 1 site\n";
        echo "  - 5 Sites         - \$497/yr or \$65/mo  - up to 5 sites\n";
        echo "  - Unlimited Sites - \$997/yr or \$129/mo - any number\n\n";
    }
    if ( $trial_extension_url ) {
        echo "Still setting up? You can extend your trial once, by 7 days:\n";
        echo "{$trial_extension_url}\n\n";
    }
    echo "Prefer a card? Choose the card option at checkout and your license\n";
    echo "switches to automatic yearly renewal.\n\n";
    echo "Questions? Email support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Your free 7-day Pipe Pay trial ended today.' );
pp_email_paragraph( 'Your gateway keeps accepting orders during a <strong>30-day grace period</strong>, then stops offering Pipe Pay at checkout until you pick a paid tier. Auto-updates and support are paused starting today.' );
pp_email_button( $renewal_url, 'Pick a tier &rarr;' );
if ( $intent_tier_name ) {
    pp_email_paragraph( 'You picked the <strong>' . esc_html( $intent_tier_name ) . '</strong> tier when you started: <strong>' . esc_html( $intent_tier_price ) . ' per year</strong>, or ' . esc_html( $intent_monthly_price ) . ' per month, cancel anytime. Converting takes about 30 seconds, your details are pre-filled.' );
} else {
    pp_email_paragraph( 'Three tiers: Single Site $297/yr, 5 Sites $497/yr, Unlimited $997/yr. Monthly billing is also available at $35 / $65 / $129, cancel anytime. Converting takes about 30 seconds, your details are pre-filled.' );
}
if ( $trial_extension_url ) {
    pp_email_paragraph( 'Still setting up? You can extend your trial once, by 7 days:' );
    pp_email_button( $trial_extension_url, 'Extend my trial 7 days' );
}
pp_email_paragraph( 'Prefer a card? <a href=\"https://pipepay.app/pricing/\" style=\"color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;\">Auto-renewing card billing is available on the pricing page</a>. Pick your tier and use the Buy now button.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
