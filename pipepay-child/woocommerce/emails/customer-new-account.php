<?php
/**
 * Customer new account email – Pipe Pay wrapper.
 *
 * THIN SHIM. Delegates to `wp-content/email-templates/new-account.php`
 * (source of truth at `pipe-pay-site/Email Templates/new-account.php`).
 *
 * Triggered by `WC_Email_Customer_New_Account` when WC auto-creates an account
 * during checkout. With guest checkout disabled, this fires on every new
 * customer.
 *
 * @package pipepay-child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tpl = WP_CONTENT_DIR . '/email-templates/new-account.php';
if ( file_exists( $tpl ) ) {
    include $tpl;
    return;
}

if ( function_exists( 'pipepay_log' ) ) {
    pipepay_log( 'error', 'New-account email template missing on disk', [ 'expected_path' => $tpl ] );
}

// Fallback: WC default copy so the customer at least gets their credentials.
?>
<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>
<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $user_login ) ); ?></p>
<p><?php printf( esc_html__( 'Thanks for creating an account on %1$s. Your username is %2$s.', 'woocommerce' ), esc_html( wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) ), '<strong>' . esc_html( $user_login ) . '</strong>' ); ?></p>
<?php if ( ! empty( $set_password_url ) ) : ?>
    <p><?php printf( esc_html__( 'You can access your account area to manage orders here: %s.', 'woocommerce' ), esc_url( wc_get_page_permalink( 'myaccount' ) ) ); ?></p>
    <p><a href="<?php echo esc_url( $set_password_url ); ?>"><?php esc_html_e( 'Click here to set your new password.', 'woocommerce' ); ?></a></p>
<?php endif; ?>
<?php if ( ! empty( $additional_content ) ) { echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); } ?>
<?php do_action( 'woocommerce_email_footer', $email ); ?>
