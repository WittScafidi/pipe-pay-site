<?php
/**
 * Pipe Pay paid-renewal email (T+30, final notice).
 *
 * Triggered: daily cron 30 days after `access_expires`. Last automated email
 * we send for this license. After this we go quiet unless the customer renews.
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
    echo "Final reminder.\n\n";
    echo "Your Pipe Pay {$tier_name} license expired 30 days ago and the\n";
    echo "grace period has ended. This is the last automated email we'll send.\n\n";
    echo "What this means:\n";
    echo "  - The gateway has stopped offering Pipe Pay at checkout on your\n";
    echo "    store. Orders already in progress finish normally.\n";
    echo "  - Auto-updates and support remain paused until you renew\n";
    echo "  - Your orders, settings, and history are untouched - renewing\n";
    echo "    picks up exactly where you left off\n\n";
    echo "Renew anytime in one click:\n{$renewal_url}\n\n";
    echo "After renewing, checkout restores on its own within 24 hours - or\n";
    echo "immediately if you open WP Admin -> Pipe Pay -> License and click\n";
    echo "Activate.\n\n";
    echo "Or contact support@pipepay.app if you have questions about renewing\n";
    echo "or want to switch to a different tier.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Final reminder.' );
pp_email_paragraph(
    'Your Pipe Pay <strong>' . esc_html( $tier_name ) . '</strong> license expired 30 days ago and the grace period has ended. This is the last automated email we\'ll send.'
);

pp_email_paragraph( 'What this means:', 12 );
?>
<ul style="margin:0 0 24px 22px;padding:0;font-size:16px;line-height:1.6;color:<?php echo esc_attr( pp_email_text_color() ); ?>;">
    <li style="margin:0 0 6px 0;"><strong>The gateway has stopped offering Pipe Pay at checkout</strong> on your store. Orders already in progress finish normally.</li>
    <li style="margin:0 0 6px 0;">Auto-updates and support remain paused until you renew</li>
    <li style="margin:0 0 6px 0;">Your orders, settings, and history are untouched &mdash; renewing picks up exactly where you left off</li>
</ul>
<?php

pp_email_button( $renewal_url, 'Renew now &rarr;' );
pp_email_paragraph( 'After renewing, checkout restores on its own within 24 hours &mdash; or immediately if you open WP&nbsp;Admin &rarr; Pipe&nbsp;Pay &rarr; License and click Activate.' );
pp_email_paragraph( 'Or email <a href="mailto:support@pipepay.app" style="color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;">support@pipepay.app</a> with any questions about renewing or switching tiers.' );

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
