<?php
/**
 * Trial win-back (T+3). Only sent when the user has not converted (engine
 * skip_if_converted). Leads with the monthly lane. No discounts.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

$pricing_url = home_url( '/pricing/' );

if ( $plain_text ) :
    $first_name     = sanitize_text_field( $first_name );
    $renewal_url    = esc_url_raw( $renewal_url );
    $intent_monthly = sanitize_text_field( $intent_monthly_price );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your Pipe Pay trial ended a few days ago. Your gateway is still\n";
    echo "accepting orders during the 30-day grace window, but updates and\n";
    echo "support are paused, and at the end of the window Pipe Pay stops\n";
    echo "appearing at checkout.\n\n";
    echo "If the annual license was the sticking point, monthly billing might\n";
    echo "fit better: " . ( $intent_monthly ?: '$35' ) . " per month, cancel anytime, same plugin,\n";
    echo "same updates. Pick monthly on the pricing page:\n";
    echo esc_url_raw( $pricing_url ) . "\n\n";
    echo "Prefer annual? Your one-click link still works:\n{$renewal_url}\n\n";
    echo "And if Pipe Pay was not the right fit, one question helps us more\n";
    echo "than anything: what stopped you? Email support@pipepay.app, even one\n";
    echo "sentence is useful.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Your Pipe Pay trial ended a few days ago. Your gateway is still accepting orders during the 30-day grace window, but updates and support are paused, and at the end of the window Pipe Pay stops appearing at checkout.' );
pp_email_paragraph( 'If the annual license was the sticking point, monthly billing might fit better: <strong>' . esc_html( $intent_monthly_price ?: '$35' ) . ' per month</strong>, cancel anytime, same plugin, same updates.' );
pp_email_button( $pricing_url, 'See monthly pricing' );
pp_email_paragraph( 'Prefer annual? <a href="' . esc_url( $renewal_url ) . '" style="color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;">Your one-click conversion link</a> still works.' );
pp_email_paragraph( 'And if Pipe Pay was not the right fit, one question helps us more than anything: what stopped you? Email <a href="mailto:support@pipepay.app" style="color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;">support@pipepay.app</a>, even one sentence is useful.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
