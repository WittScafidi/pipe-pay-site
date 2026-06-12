<?php
/**
 * Shared HTML helpers for Pipe Pay transactional email templates.
 *
 * Each template in `Email Templates/` requires this file and uses these
 * helpers to keep visual elements consistent — license-key cards, CTA buttons,
 * intro paragraphs all render the same way across emails.
 *
 * Inline styles only (no CSS classes) — email clients strip <style> blocks
 * inconsistently. Brand colors hardcoded; if `--pp-blue` ever changes update
 * here in one place.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'pp_email_brand_color' ) ) {
    function pp_email_brand_color() { return '#1336a8'; }
    function pp_email_text_color()  { return '#1a1a1a'; }
    function pp_email_muted_color() { return '#6b7280'; }
    function pp_email_card_bg()     { return '#f7f8fa'; }
    function pp_email_card_border() { return '#e3e6ee'; }
}

/**
 * Standard body paragraph used everywhere.
 */
if ( ! function_exists( 'pp_email_paragraph' ) ) {
    function pp_email_paragraph( $html, $margin_bottom = 16 ) {
        printf(
            '<p style="margin:0 0 %dpx 0;font-size:16px;line-height:1.6;color:%s;">%s</p>',
            (int) $margin_bottom,
            esc_attr( pp_email_text_color() ),
            wp_kses_post( $html )
        );
    }
}

/**
 * License key card (gray box with the key in monospace).
 * Falls back to a "key will arrive separately" notice when no key is provided.
 */
if ( ! function_exists( 'pp_email_license_card' ) ) {
    function pp_email_license_card( $license_key, $label = 'Your license key' ) {
        $card_bg     = pp_email_card_bg();
        $card_border = pp_email_card_border();
        $blue        = pp_email_brand_color();
        $muted       = pp_email_muted_color();
        ?>
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 24px 0;">
            <tr>
                <td style="background:<?php echo esc_attr( $card_bg ); ?>;border:1px solid <?php echo esc_attr( $card_border ); ?>;border-radius:8px;padding:18px 20px;">
                    <p style="margin:0 0 6px 0;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:<?php echo esc_attr( $muted ); ?>;"><?php echo esc_html( $label ); ?></p>
                    <?php if ( $license_key ) : ?>
                        <p style="margin:0;font-family:Menlo, Consolas, 'Geist Mono', monospace;font-size:15px;color:<?php echo esc_attr( $blue ); ?>;word-break:break-all;line-height:1.5;">
                            <?php echo esc_html( $license_key ); ?>
                        </p>
                    <?php else : ?>
                        <p style="margin:0;font-size:14px;color:<?php echo esc_attr( $muted ); ?>;line-height:1.5;">
                            Your license key will arrive in a separate email within a few minutes. If you don't see it, email <a href="mailto:support@pipepay.app" style="color:<?php echo esc_attr( $blue ); ?>;text-decoration:underline;">support@pipepay.app</a>.
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }
}

/**
 * Brand-blue CTA button. Used for renewal links, "set password" prompts, etc.
 * Wraps in a bulletproof table so it renders consistently across clients.
 */
if ( ! function_exists( 'pp_email_button' ) ) {
    function pp_email_button( $url, $label ) {
        $blue = pp_email_brand_color();
        ?>
        <table border="0" cellpadding="0" cellspacing="0" style="margin:0 0 24px 0;">
            <tr>
                <td style="background:<?php echo esc_attr( $blue ); ?>;border-radius:8px;">
                    <a href="<?php echo esc_url( $url ); ?>" style="display:inline-block;padding:14px 28px;font-family:'Manrope',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:16px;font-weight:600;color:#ffffff;text-decoration:none;">
                        <?php echo esc_html( $label ); ?>
                    </a>
                </td>
            </tr>
        </table>
        <?php
    }
}

/**
 * Sign-off line shared across all emails. Single source of truth so brand
 * voice stays consistent.
 */
if ( ! function_exists( 'pp_email_signoff' ) ) {
    function pp_email_signoff() {
        $blue  = pp_email_brand_color();
        $text  = pp_email_text_color();
        ?>
        <p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:<?php echo esc_attr( $text ); ?>;">
            Questions? Reply to this email or contact <a href="mailto:support@pipepay.app" style="color:<?php echo esc_attr( $blue ); ?>;text-decoration:underline;">support@pipepay.app</a>.
        </p>
        <?php
    }
}

/**
 * Greeting line. "Hi {first_name}," or "Hi there," fallback.
 */
if ( ! function_exists( 'pp_email_greeting' ) ) {
    function pp_email_greeting( $first_name = '' ) {
        $name = $first_name ?: 'there';
        $text = pp_email_text_color();
        printf(
            '<p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:%s;">Hi %s,</p>',
            esc_attr( $text ),
            esc_html( $name )
        );
    }
}

/**
 * Plain-text helper: print a simple line with optional trailing newlines.
 * Use in the if($plain_text) branch of each template.
 */
if ( ! function_exists( 'pp_email_text_line' ) ) {
    function pp_email_text_line( $line, $trailing_blanks = 1 ) {
        echo $line . str_repeat( "\n", max( 1, $trailing_blanks + 1 ) );
    }
}
