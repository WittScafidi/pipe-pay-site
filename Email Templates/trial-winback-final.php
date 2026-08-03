<?php
/**
 * Trial win-back final (T+7). Last automated email for the trial. No pressure
 * copy, then silence.
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
    echo "This is the last email about your Pipe Pay trial.\n\n";
    echo "Your license stays saved in your account, and converting later uses\n";
    echo "the same one-click link, whenever the timing is right:\n";
    echo "{$renewal_url}\n\n";
    echo "Heads up on timing: 30 days after the trial ended, the gateway\n";
    echo "stops offering Pipe Pay at new checkouts until a paid tier is\n";
    echo "active. Converting reopens it right away.\n\n";
    echo "Questions? Email support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'This is the last email about your Pipe Pay trial.' );
pp_email_paragraph( 'Your license stays saved in your account, and converting later uses the same one-click link, whenever the timing is right.' );
pp_email_button( $renewal_url, 'Pick a tier' );
pp_email_paragraph( 'Heads up on timing: 30 days after the trial ended, the gateway stops offering Pipe Pay at new checkouts until a paid tier is active. Converting reopens it right away.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
