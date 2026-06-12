<?php
/**
 * Class definitions for Pipe Pay custom WC emails.
 * Loaded from `pipepay-email-classes.php` after WC has booted.
 *
 * @package pipe-pay-site
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email' ) ) {
    return;
}

/**
 * Base class for renewal-cadence + trial-cadence emails.
 *
 * Subclasses set:
 *   $id, $title, $description, $subject, $heading   (in __construct)
 *   $template_basename                              (property)
 *
 * Trigger signature:
 *   $email->trigger( [
 *     'license_key'   => string,
 *     'order_id'      => int,
 *     'recipient'     => string (email),
 *     'first_name'    => string,
 *     'tier_name'     => string (for paid renewals),
 *     'expires_at'    => int (unix timestamp),
 *     'expires_label' => string (formatted date),
 *     'renewal_url'   => string (HMAC-signed /renew/ URL),
 *   ] )
 */
abstract class PipePay_Cadence_Email_Base extends WC_Email {

    /** @var string Filename in wp-content/email-templates/, e.g. 'renewal-30.php' */
    protected $template_basename = '';

    /** @var array Context passed by the cron handler at trigger time. */
    protected $license_data = [];

    public function __construct() {
        $this->customer_email = true;
        // Default placeholders WC uses in subject/heading editing UI.
        $this->placeholders = array_merge( $this->placeholders ?? [], [
            '{site_title}'    => $this->get_blogname(),
            '{tier_name}'     => '',
            '{expires_label}' => '',
        ] );
        // Run parent setup AFTER our id/title etc. are set in the subclass.
        parent::__construct();
    }

    /**
     * Fire the email. Returns true if sent successfully.
     */
    public function trigger( array $license_data ): bool {
        if ( ! $this->is_enabled() ) {
            return false;
        }
        if ( empty( $license_data['recipient'] ) ) {
            return false;
        }

        $this->license_data = $license_data;
        $this->recipient    = $license_data['recipient'];

        // Update placeholders so subject/heading interpolate correctly.
        $this->placeholders['{tier_name}']     = $license_data['tier_name']     ?? '';
        $this->placeholders['{expires_label}'] = $license_data['expires_label'] ?? '';

        $this->setup_locale();

        return $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );
    }

    /**
     * Override the standard WC template-lookup path. We render directly from
     * our `wp-content/email-templates/` source-of-truth folder so the body
     * doesn't have to live inside the theme or plugin.
     */
    public function get_content_html() {
        return $this->render_template( /* plain_text */ false );
    }

    public function get_content_plain() {
        return $this->render_template( /* plain_text */ true );
    }

    private function render_template( bool $plain_text ): string {
        $path = PIPEPAY_EMAIL_TEMPLATES_DIR . $this->template_basename;
        if ( ! file_exists( $path ) ) {
            if ( function_exists( 'pipepay_log' ) ) {
                pipepay_log( 'error', 'Cadence email template missing', [ 'expected_path' => $path, 'email_id' => $this->id ] );
            }
            return '';
        }

        // Variables the templates expect in scope.
        $email              = $this;
        $email_heading      = $this->get_heading();
        $sent_to_admin      = false;
        $additional_content = $this->get_additional_content();

        $first_name    = $this->license_data['first_name']    ?? '';
        $tier_name     = $this->license_data['tier_name']     ?? '';
        $expires_label = $this->license_data['expires_label'] ?? '';
        $renewal_url   = $this->license_data['renewal_url']   ?? '';

        ob_start();
        include $path;
        return ob_get_clean();
    }

    /**
     * Force-enabled by default so the cron can fire. Admins can disable per
     * email in WC -> Settings -> Emails if they want to silence a stage.
     */
    public function get_default_enabled() { return 'yes'; }
}

// -----------------------------------------------------------------------------
// Concrete cadence emails
// -----------------------------------------------------------------------------

class WC_Email_PipePay_Trial_Ending_Soon extends PipePay_Cadence_Email_Base {
    protected $template_basename = 'trial-ending-soon.php';
    public function __construct() {
        $this->id          = 'pipepay_trial_ending_soon';
        $this->title       = 'Pipe Pay - Trial ending soon (T-2)';
        $this->description = 'Sent 2 days before a 7-day trial license expires. Includes a one-click renewal link.';
        $this->subject     = 'Your Pipe Pay trial ends in 2 days';
        $this->heading     = 'Your trial ends in 2 days';
        parent::__construct();
    }
}

class WC_Email_PipePay_Trial_Ended extends PipePay_Cadence_Email_Base {
    protected $template_basename = 'trial-ended.php';
    public function __construct() {
        $this->id          = 'pipepay_trial_ended';
        $this->title       = 'Pipe Pay - Trial ended (T+0)';
        $this->description = 'Sent when a 7-day trial license expires. Explains the 30-day grace period before checkout stops and offers a one-click upgrade.';
        $this->subject     = 'Your Pipe Pay trial ended - convert to keep going';
        $this->heading     = 'Your trial has ended';
        parent::__construct();
    }
}

class WC_Email_PipePay_Renewal_30 extends PipePay_Cadence_Email_Base {
    protected $template_basename = 'renewal-30.php';
    public function __construct() {
        $this->id          = 'pipepay_renewal_30';
        $this->title       = 'Pipe Pay - Renewal heads-up (T-30)';
        $this->description = 'Sent 30 days before a paid license (Single Site / 5 Sites / Unlimited) expires.';
        $this->subject     = 'Your Pipe Pay license renews in 30 days';
        $this->heading     = 'Renewal coming up';
        parent::__construct();
    }
}

class WC_Email_PipePay_Renewal_7 extends PipePay_Cadence_Email_Base {
    protected $template_basename = 'renewal-7.php';
    public function __construct() {
        $this->id          = 'pipepay_renewal_7';
        $this->title       = 'Pipe Pay - Renewal reminder (T-7)';
        $this->description = 'Sent 7 days before a paid license expires.';
        $this->subject     = 'Your Pipe Pay license renews in 7 days';
        $this->heading     = 'One week left on your license';
        parent::__construct();
    }
}

class WC_Email_PipePay_Renewal_Expiry extends PipePay_Cadence_Email_Base {
    protected $template_basename = 'renewal-expiry.php';
    public function __construct() {
        $this->id          = 'pipepay_renewal_expiry';
        $this->title       = 'Pipe Pay - License expires today (T-0)';
        $this->description = 'Sent on the day a paid license expires. Warns that the gateway stops accepting new orders 30 days later and offers one-click renewal.';
        $this->subject     = 'Your Pipe Pay license expires today';
        $this->heading     = 'License expires today';
        parent::__construct();
    }
}

class WC_Email_PipePay_Renewal_Grace extends PipePay_Cadence_Email_Base {
    protected $template_basename = 'renewal-grace.php';
    public function __construct() {
        $this->id          = 'pipepay_renewal_grace';
        $this->title       = 'Pipe Pay - Grace period reminder (T+7)';
        $this->description = 'Sent 7 days after a paid license expired. Customer is in the 30-day grace period.';
        $this->subject     = 'Action needed: renew your Pipe Pay license';
        $this->heading     = 'Grace period: 23 days left';
        parent::__construct();
    }
}

class WC_Email_PipePay_Renewal_Final extends PipePay_Cadence_Email_Base {
    protected $template_basename = 'renewal-final.php';
    public function __construct() {
        $this->id          = 'pipepay_renewal_final';
        $this->title       = 'Pipe Pay - Final renewal reminder (T+30)';
        $this->description = 'Sent 30 days after a paid license expired. Last automated email for this license cycle.';
        $this->subject     = 'Final notice: Pipe Pay license lapsed';
        $this->heading     = 'Final renewal reminder';
        parent::__construct();
    }
}

