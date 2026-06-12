<?php
/**
 * Plugin Name: Pipe Pay - Custom Email Classes
 * Description: Registers WC_Email subclasses for Pipe Pay's renewal cadence. Each class wraps a body template in `wp-content/email-templates/`. Triggered by the cron in `pipepay-license-renewals.php`.
 *
 * NOTE: the order-based "payment-pending" email (awaiting-proof) was removed
 * 2026-06-05 so the dogfood matches the customer plugin exactly - both now rely
 * solely on the plugin's own 5/20/45-min reminder cadence (pipepay_handle_reminder).
 *
 * To bring that email back, do it as "Option A" in the PLUGIN (so all customers
 * get it, not just the dogfood): add a WC_Email subclass in
 * pipe-pay/includes/emails/ fired on awaiting-proof, with an "Upload payment
 * screenshot" button -> pipepay_get_pay_url($order) and NO method list (the
 * /pipe-pay/ page is the source of truth). Do NOT re-add it here as a site email.
 * See Email Templates/README.md for the full Option-A spec.
 * Author:      Pipe Pay
 * Version:     1.0.0
 *
 * Registered email IDs (visible at WC -> Settings -> Emails):
 *   pipepay_trial_ending_soon    - T-2 days before trial expiry
 *   pipepay_trial_ended          - T+0 trial ended
 *   pipepay_renewal_30           - T-30 paid license
 *   pipepay_renewal_7            - T-7 paid license
 *   pipepay_renewal_expiry       - T-0 paid license expires today
 *   pipepay_renewal_grace        - T+7 paid license, in grace period
 *   pipepay_renewal_final        - T+30 paid license, final reminder
 *
 * Each class extends WC_Email so it appears in the WC admin email settings
 * (you can enable/disable each one, edit subject/heading per email, etc.),
 * and so the email body inherits WC's branded chrome (header logo + footer).
 *
 * Body templates are sourced from wp-content/email-templates/ which is
 * deployed from `pipe-pay-site/Email Templates/` in the repo. See
 * `Email Templates/README.md` for the template contract.
 */

defined( 'ABSPATH' ) || exit;

const PIPEPAY_EMAIL_TEMPLATES_DIR = WP_CONTENT_DIR . '/email-templates/';

// Register classes after WC has loaded. WC fires woocommerce_email_classes
// when building the mailer; we hook there to add ours.
add_filter( 'woocommerce_email_classes', 'pipepay_register_custom_emails' );

function pipepay_register_custom_emails( array $emails ): array {
    if ( ! class_exists( 'WC_Email' ) ) {
        return $emails;
    }

    require_once __DIR__ . '/pipepay-lib/email-classes.php';

    $emails['WC_Email_PipePay_Trial_Ending_Soon']   = new WC_Email_PipePay_Trial_Ending_Soon();
    $emails['WC_Email_PipePay_Trial_Ended']         = new WC_Email_PipePay_Trial_Ended();
    $emails['WC_Email_PipePay_Renewal_30']          = new WC_Email_PipePay_Renewal_30();
    $emails['WC_Email_PipePay_Renewal_7']           = new WC_Email_PipePay_Renewal_7();
    $emails['WC_Email_PipePay_Renewal_Expiry']      = new WC_Email_PipePay_Renewal_Expiry();
    $emails['WC_Email_PipePay_Renewal_Grace']       = new WC_Email_PipePay_Renewal_Grace();
    $emails['WC_Email_PipePay_Renewal_Final']       = new WC_Email_PipePay_Renewal_Final();

    return $emails;
}
