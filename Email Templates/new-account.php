<?php
/**
 * Pipe Pay new-account welcome email.
 *
 * Triggered: when WC auto-creates a customer account during checkout (now the
 * default path after we disabled guest checkout). Wired via theme wrapper
 * `pipepay-child/woocommerce/emails/customer-new-account.php`.
 *
 * Expected scope (set by WC_Email_Customer_New_Account + the wrapper):
 *   $user_login          string  email-based username
 *   $user_pass           string  generated password (empty when password_url available)
 *   $blogname            string  site title
 *   $set_password_url    string  password-set URL when generate_password is enabled
 *   $email               WC_Email
 *   $email_heading       string
 *   $sent_to_admin       bool
 *   $plain_text          bool
 *   $additional_content  string
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

$first_name = '';
$user       = get_user_by( 'login', $user_login );
if ( $user ) {
    $first_name = get_user_meta( $user->ID, 'first_name', true );
    if ( ! $first_name && function_exists( 'wc_get_customer_default_location' ) ) {
        // Fall back to most-recent order billing name
        $orders = wc_get_orders( [
            'customer_id' => $user->ID,
            'limit'       => 1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ] );
        if ( $orders ) {
            $first_name = $orders[0]->get_billing_first_name();
        }
    }
}

$account_url = home_url( '/my-account/' );

// -----------------------------------------------------------------------------
// Plain text
// -----------------------------------------------------------------------------

if ( $plain_text ) :
    $first_name       = sanitize_text_field( $first_name );
    $user_login       = sanitize_text_field( (string) $user_login );
    $user_pass        = sanitize_text_field( (string) $user_pass );
    $set_password_url = esc_url_raw( (string) ( $set_password_url ?? '' ) );
    $account_url      = esc_url_raw( $account_url );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your Pipe Pay account is ready.\n\n";
    if ( ! empty( $set_password_url ) ) {
        echo "Set your password using the link below to access your dashboard:\n";
        echo "{$set_password_url}\n\n";
    } elseif ( ! empty( $user_pass ) ) {
        echo "Your sign-in details:\n";
        echo "  Username: {$user_login}\n";
        echo "  Password: {$user_pass}\n\n";
    }
    echo "Your account: {$account_url}\n\n";
    echo "From your dashboard you can:\n";
    echo "  - View your orders and license keys\n";
    echo "  - Download the latest Pipe Pay plugin build\n";
    echo "  - Manage billing and account details\n\n";
    echo "Questions? Reply to this email or contact support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

// -----------------------------------------------------------------------------
// HTML
// -----------------------------------------------------------------------------

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Your Pipe Pay account is ready. Set your password using the link below to access your dashboard, view orders, and download the plugin.' );

if ( ! empty( $set_password_url ) ) {
    pp_email_button( $set_password_url, 'Set your password' );
} elseif ( ! empty( $user_pass ) ) {
    // Fallback: show credentials in a card (only when WC isn't generating a reset link)
    ?>
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 24px 0;">
        <tr>
            <td style="background:<?php echo esc_attr( pp_email_card_bg() ); ?>;border:1px solid <?php echo esc_attr( pp_email_card_border() ); ?>;border-radius:8px;padding:18px 20px;">
                <p style="margin:0 0 6px 0;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:<?php echo esc_attr( pp_email_muted_color() ); ?>;">Your sign-in details</p>
                <p style="margin:0;font-family:Menlo, Consolas, monospace;font-size:14px;color:<?php echo esc_attr( pp_email_text_color() ); ?>;line-height:1.6;">
                    Username: <?php echo esc_html( $user_login ); ?><br>
                    Password: <?php echo esc_html( $user_pass ); ?>
                </p>
            </td>
        </tr>
    </table>
    <?php
}

pp_email_paragraph(
    'From your <a href="' . esc_url( $account_url ) . '" style="color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;">account dashboard</a> you can view orders and license keys, download the latest Pipe Pay plugin build, and manage billing.'
);

pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
