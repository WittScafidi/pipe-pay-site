<?php
/**
 * Customer completed order email – Pipe Pay wrapper.
 *
 * THIS IS A THIN SHIM. The actual trial template body lives at
 * `wp-content/email-templates/free-trial.php` (source of truth at
 * `pipe-pay-site/Email Templates/free-trial.php` in the repo).
 *
 * For trial orders we delegate to that file. For paid orders we fall back to
 * WC's default template (rendered inline below to avoid the recursion that
 * would happen if we called `wc_get_template()` for the same path).
 *
 * Override of WC core template version 10.4.0.
 *
 * @package pipepay-child
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- Trial branch: delegate to source-of-truth template -----------------------

if ( function_exists( 'pp_order_is_trial' ) && pp_order_is_trial( $order ) ) {
    $trial_template = WP_CONTENT_DIR . '/email-templates/free-trial.php';
    if ( file_exists( $trial_template ) ) {
        include $trial_template;
        return;
    }
    if ( function_exists( 'pipepay_log' ) ) {
        pipepay_log( 'error', 'Trial email template missing on disk', [ 'expected_path' => $trial_template, 'order_id' => $order->get_id() ] );
    }
}

// --- Paid tier branch: delegate to paid-completed template --------------------

if ( function_exists( 'pp_order_is_paid_tier' ) && pp_order_is_paid_tier( $order ) ) {
    $paid_template = WP_CONTENT_DIR . '/email-templates/paid-completed.php';
    if ( file_exists( $paid_template ) ) {
        include $paid_template;
        return;
    }
    if ( function_exists( 'pipepay_log' ) ) {
        pipepay_log( 'error', 'Paid-tier email template missing on disk', [ 'expected_path' => $paid_template, 'order_id' => $order->get_id() ] );
    }
}

// --- Paid branch: WC default --------------------------------------------------

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>
<p>
<?php
if ( ! empty( $order->get_billing_first_name() ) ) {
	/* translators: %s: Customer first name */
	printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) );
} else {
	printf( esc_html__( 'Hi,', 'woocommerce' ) );
}
?>
</p>
<p><?php esc_html_e( 'We have finished processing your order.', 'woocommerce' ); ?></p>
<?php if ( $email_improvements_enabled ) : ?>
	<p><?php esc_html_e( 'Here\'s a reminder of what you\'ve ordered:', 'woocommerce' ); ?></p>
<?php endif; ?>
<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
    echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td class="email-additional-content">' : '';
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
    echo $email_improvements_enabled ? '</td></tr></table>' : '';
}

do_action( 'woocommerce_email_footer', $email );
