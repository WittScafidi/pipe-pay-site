<?php
/**
 * Trial day 3 - activated. Value rundown + first pricing mention (annual +
 * monthly, intent-aware). No discounts.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

if ( $plain_text ) :
    $first_name        = sanitize_text_field( $first_name );
    $renewal_url       = esc_url_raw( $renewal_url );
    $intent_tier_name  = sanitize_text_field( $intent_tier_name );
    $intent_tier_price = sanitize_text_field( $intent_tier_price );
    $intent_monthly    = sanitize_text_field( $intent_monthly_price );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "You are halfway through your trial. Here is what Pipe Pay is doing\n";
    echo "on every order while it runs:\n\n";
    echo "  - Checks each screenshot: right amount, right recipient handle,\n";
    echo "    payment completed (not pending or cancelled), no signs of editing\n";
    echo "  - Compares a privacy-safe fingerprint against the whole network of\n";
    echo "    Pipe Pay stores, so a fake screenshot used at another shop gets\n";
    echo "    flagged at yours\n";
    echo "  - Sends payment reminders and auto-cancels unpaid orders so your\n";
    echo "    order list stays clean\n\n";
    if ( $intent_tier_name ) {
        echo "When you started, you picked the {$intent_tier_name} tier. Keeping it\n";
        echo "is {$intent_tier_price} per year, or {$intent_monthly} per month, cancel anytime.\n\n";
    } else {
        $pp_prices = pp_email_tier_prices();
        echo "Keeping Pipe Pay after the trial: {$pp_prices['annual']} per year for\n";
        echo "Single Site / 5 Sites / Unlimited, or {$pp_prices['monthly']} per month,\n";
        echo "cancel anytime.\n\n";
    }
    echo "Convert in one click (details pre-filled):\n{$renewal_url}\n\n";
    echo "Questions? Email support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'You are halfway through your trial. Here is what Pipe Pay is doing on every order while it runs:' );
?>
<ul style="margin:0 0 24px 22px;padding:0;font-size:16px;line-height:1.6;color:<?php echo esc_attr( pp_email_text_color() ); ?>;">
    <li style="margin:0 0 6px 0;">Checks each screenshot: right amount, right recipient handle, payment <strong>completed</strong> (not pending or cancelled), no signs of editing</li>
    <li style="margin:0 0 6px 0;">Compares a privacy-safe fingerprint against the whole network of Pipe Pay stores, so a fake screenshot used at another shop gets flagged at yours</li>
    <li style="margin:0 0 6px 0;">Sends payment reminders and auto-cancels unpaid orders so your order list stays clean</li>
</ul>
<?php
if ( $intent_tier_name ) {
    pp_email_paragraph( 'When you started, you picked the <strong>' . esc_html( $intent_tier_name ) . '</strong> tier. Keeping it is <strong>' . esc_html( $intent_tier_price ) . ' per year</strong>, or ' . esc_html( $intent_monthly_price ) . ' per month, cancel anytime.' );
} else {
    $pp_prices = pp_email_tier_prices();
    pp_email_paragraph( 'Keeping Pipe Pay after the trial: ' . esc_html( $pp_prices['annual'] ) . ' per year for Single Site / 5 Sites / Unlimited, or ' . esc_html( $pp_prices['monthly'] ) . ' per month, cancel anytime.' );
}
pp_email_button( $renewal_url, 'Keep it running' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
