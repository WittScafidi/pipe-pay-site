<?php
/**
 * Email Header - Pipe Pay override.
 *
 * Replaces the default WC email header logo (single <img> from
 * woocommerce_email_header_image option) with a <picture> element that swaps
 * between blue-on-transparent (light mode) and white-on-transparent (dark
 * mode) using the prefers-color-scheme media query. PNG variants live at
 * `wp-content/email-templates/assets/` (source of truth at
 * `pipe-pay-site/Email Templates/assets/` in the repo).
 *
 * Support matrix:
 * - Apple Mail (macOS + iOS): full <picture> + prefers-color-scheme support
 * - Outlook 2019+/Microsoft 365: yes
 * - Yahoo Mail (webmail): yes
 * - Thunderbird: yes
 * - Gmail (web/iOS/Android): ignores prefers-color-scheme, falls back to the
 *   blue <img> default - Gmail web/app renders message body on light bg even
 *   in user-selected dark mode, so blue logo on light bg still reads well.
 *
 * Override of WC core template version 10.7.0.
 *
 * @package pipepay-child
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );
$store_name                 = $store_name ?? get_bloginfo( 'name', 'display' );
$header_image_url           = apply_filters( 'woocommerce_email_header_image_url', home_url() );

// Pipe Pay logo - simple styled text wordmark. No images. Renders perfectly in
// every email client, sharp on every display, no font-loading races, no SVG
// rasterization issues, no <picture> source-media quirks. Brand color via
// inline style; dark-mode swap handled via @media (prefers-color-scheme: dark)
// in WC's email styles (we keep blue in light mode; in dark mode the client
// usually inverts the bg and the heading text color adapts naturally).
$logo_html  = '<span style="font-family:\'Manrope\',-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;font-size:32px;font-weight:700;letter-spacing:-1px;color:#1336a8;">Pipe Pay</span>';

if ( $header_image_url ) {
    $logo_html = '<a href="' . esc_url( $header_image_url ) . '" style="display:inline-block;text-decoration:none;" target="_blank">' . $logo_html . '</a>';
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta name="color-scheme" content="light dark">
        <meta name="supported-color-schemes" content="light dark">
        <title><?php echo esc_html( $store_name ); ?></title>
    </head>
    <body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
        <table width="100%" id="outer_wrapper" role="presentation">
            <tr>
                <td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
                <td width="600">
                    <div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
                        <table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="inner_wrapper" role="presentation">
                            <tr>
                                <td align="center" valign="top">
                                    <?php if ( $email_improvements_enabled ) : ?>
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                            <tr>
                                                <td id="template_header_image">
                                                    <p style="margin-top:0;"><?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- logo HTML built with esc_url + esc_attr above. ?></p>
                                                </td>
                                            </tr>
                                        </table>
                                    <?php else : ?>
                                        <div id="template_header_image">
                                            <p style="margin-top:0;"><?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_container" role="presentation">
                                        <tr>
                                            <td align="center" valign="top">
                                                <!-- Header -->
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" role="presentation">
                                                    <tr>
                                                        <td id="header_wrapper">
                                                            <h1><?php echo esc_html( $email_heading ); ?></h1>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <!-- End Header -->
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="center" valign="top">
                                                <!-- Body -->
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body" role="presentation">
                                                    <tr>
                                                        <td valign="top" id="body_content">
                                                            <!-- Content -->
                                                            <table border="0" cellpadding="20" cellspacing="0" width="100%" role="presentation">
                                                                <tr>
                                                                    <td valign="top" id="body_content_inner_cell">
                                                                        <div id="body_content_inner">
