<?php
/**
 * Trial day 3 - still not activated. Short unblock note, neutral voice.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

$docs_url = home_url( '/docs/getting-started/' );

if ( $plain_text ) :
    $first_name = sanitize_text_field( $first_name );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "You are halfway through your Pipe Pay trial and the license has not\n";
    echo "been activated yet. Most stores finish setup in about 10 minutes,\n";
    echo "so if something got in the way, it is probably a small thing.\n\n";
    echo "The setup guide covers every step with screenshots:\n";
    echo esc_url_raw( $docs_url ) . "\n\n";
    echo "If anything is unclear or broken, email support@pipepay.app with\n";
    echo "what you are seeing and we will sort it out.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'You are halfway through your Pipe Pay trial and the license has not been activated yet. Most stores finish setup in about 10 minutes, so if something got in the way, it is probably a small thing.' );
pp_email_button( $docs_url, 'Open the setup guide' );
pp_email_paragraph( 'If anything is unclear or broken, email <a href="mailto:support@pipepay.app" style="color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;">support@pipepay.app</a> with what you are seeing and we will sort it out.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
