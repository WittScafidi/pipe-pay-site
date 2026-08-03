<?php
/**
 * Trial day 1 - license not yet activated. Install walkthrough.
 * Scope: standard cadence contract (see PipePay_Cadence_Email_Base).
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

$docs_url = home_url( '/docs/getting-started/' );

if ( $plain_text ) :
    $first_name  = sanitize_text_field( $first_name );
    $license_key = sanitize_text_field( (string) $license_key );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your Pipe Pay trial started yesterday. The one step that turns the\n";
    echo "gateway on is activating your license. Most stores are done in about\n";
    echo "10 minutes:\n\n";
    echo "  1. Download the plugin zip from your account: " . esc_url_raw( home_url( '/my-account/downloads/' ) ) . "\n";
    echo "  2. Upload it in WordPress -> Plugins -> Add New -> Upload Plugin\n";
    echo "  3. Paste your license key into Pipe Pay -> License and click Activate\n\n";
    if ( $license_key ) {
        echo "Your license key:\n{$license_key}\n\n";
    }
    echo "Step-by-step guide with screenshots: " . esc_url_raw( $docs_url ) . "\n\n";
    echo "Stuck on anything? Email support@pipepay.app and we will walk you through it.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Your Pipe Pay trial started yesterday. The one step that turns the gateway on is activating your license. Most stores are done in about 10 minutes:' );
?>
<ol style="margin:0 0 24px 22px;padding:0;font-size:16px;line-height:1.6;color:<?php echo esc_attr( pp_email_text_color() ); ?>;">
    <li style="margin:0 0 6px 0;">Download the plugin zip from your <a href="<?php echo esc_url( home_url( '/my-account/downloads/' ) ); ?>" style="color:<?php echo esc_attr( pp_email_brand_color() ); ?>;text-decoration:underline;">account downloads</a></li>
    <li style="margin:0 0 6px 0;">Upload it in WordPress &rarr; <strong>Plugins &rarr; Add New &rarr; Upload Plugin</strong></li>
    <li style="margin:0 0 6px 0;">Paste your license key into <strong>Pipe Pay &rarr; License</strong> and click Activate</li>
</ol>
<?php
pp_email_license_card( $license_key );
pp_email_button( $docs_url, 'Open the setup guide' );
pp_email_paragraph( 'Stuck on anything? Email <a href="mailto:support@pipepay.app" style="color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;">support@pipepay.app</a> and we will walk you through it.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
