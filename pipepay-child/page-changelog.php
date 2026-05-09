<?php
/**
 * Template for the Changelog page (slug: changelog).
 * Content is hardcoded here so future releases get appended in this file.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$releases = array(
    array(
        'date'    => 'May 9, 2026',
        'version' => 'v1.8.1',
        'title'   => 'License integrity (continued)',
        'notes'   => array(
            'Continued license-integrity work. No customer-visible behavior change for active or expired licenses; recommended update.',
            'Active and expired-license installs continue to process orders exactly as before. Pipe Pay&rsquo;s policy of never bricking the gateway runtime when a license expires is unchanged - a lapsed renewal still gets a banner, not an outage.',
        ),
    ),
    array(
        'date'    => 'May 8, 2026',
        'version' => 'v1.7.4',
        'title'   => 'Handle-only payment mode',
        'notes'   => array(
            'Methods can now be configured with just a payment handle, no QR code upload required. The customer payment page renders a clean &ldquo;Open Venmo&rdquo; / &ldquo;Open Cash App&rdquo; / &ldquo;Open PayPal&rdquo; deep-link callout for these methods so customers on mobile can tap straight into the right app.',
            'Zelle handle-only mode shows a tailored callout pointing customers at their bank&rsquo;s Zelle feature, since Zelle doesn&rsquo;t expose a universal deep-link.',
            'Yellow admin notice on the gateway settings page suggests adding a QR for any enabled method that doesn&rsquo;t have one, with a one-click &ldquo;hide for 30 days&rdquo; option for merchants who prefer handle-only operation.',
        ),
    ),
    array(
        'date'    => 'May 8, 2026',
        'version' => 'v1.7.0',
        'title'   => 'License integrity',
        'notes'   => array(
            'Stronger verification of license-server responses. No customer-visible behavior change; recommended update.',
            'New &ldquo;Awaiting Approval&rdquo; order status for manual-review orders, so the orders list immediately tells you which orders need your decision (was previously generic &ldquo;On Hold&rdquo;).',
            'Dedicated review-pending customer email replaces the reused on-hold template, with copy that matches the actual state.',
        ),
    ),
    array(
        'date'    => 'May 7, 2026',
        'version' => 'v1.6.5',
        'title'   => 'Reliability and hardening',
        'notes'   => array(
            'Continued security and reliability improvements throughout the upload, license activation, and image-handling flows.',
            'PHP 8.0 is now the minimum supported version. Stores on PHP 7.4 see an admin notice and the plugin remains inactive until PHP is upgraded.',
            'Recommended update for everyone.',
        ),
    ),
    array(
        'date'    => 'May 7, 2026',
        'version' => 'v1.6.2',
        'title'   => 'Block Checkout payment fix',
        'notes'   => array(
            'Resolved an issue affecting stores on WooCommerce&rsquo;s Block Checkout (the default since WC 8.0). In some configurations, orders weren&rsquo;t advancing past the payment step, leaving customers unable to upload their payment screenshot. The handoff has been corrected so every Block Checkout order now flows through cleanly.',
            'Stores running the Classic shortcode checkout were not affected.',
            'Update recommended for any store using Block Checkout.',
        ),
    ),
    array(
        'date'    => 'May 6, 2026',
        'version' => 'v1.6.1',
        'title'   => 'Stability improvements',
        'notes'   => array(
            'License activation feels faster and more forgiving. If your session times out mid-activation, you&rsquo;ll get a friendly retry prompt instead of an error page.',
            'Tier upgrades (e.g. trial &rarr; paid, single-site &rarr; unlimited) now flow seamlessly: paste your new key on the License page and Pipe Pay re-binds automatically.',
            'Several smaller reliability and resilience improvements throughout the licensing flow.',
        ),
    ),
    array(
        'date'    => 'May 4, 2026',
        'version' => 'v1.6.0',
        'title'   => 'One-field license activation',
        'notes'   => array(
            'Activation only requires your license key now. No more looking up a separate product ID from your account page.',
            'Tier upgrades work without re-installing the plugin: paste the new key on the License page and Pipe Pay handles the rest.',
            'New License page under Pipe Pay &rarr; License in your WordPress admin sidebar.',
        ),
    ),
    array(
        'date'    => 'Apr 30, 2026',
        'version' => 'v1.5.1',
        'title'   => 'Infrastructure update',
        'notes'   => array(
            'Internal infrastructure migration. No customer action required; existing licenses continue to work.',
        ),
    ),
    array(
        'date'    => 'Apr 25, 2026',
        'version' => 'v1.5.0',
        'title'   => 'Automatic updates',
        'notes'   => array(
            'Pipe Pay now appears in the standard &ldquo;Update available&rdquo; notifications in your WordPress plugin list. Click Update Now when a new version ships - no more manual zip downloads or reinstalls.',
            'Activation takes a single field and about thirty seconds. After that, every future release flows in through the same WordPress update mechanism you already use for everything else.',
            'The payment gateway itself works whether or not a license is activated, so a lapsed license never blocks an in-flight customer order.',
        ),
    ),
    array(
        'date'    => 'Apr 19, 2026',
        'version' => 'v1.4.2',
        'title'   => 'Branding &amp; customization tab',
        'notes'   => array(
            'New Branding &amp; Customization tab in the Pipe Pay gateway settings. Make the customer-facing payment page look like part of your store, not like a generic plugin page.',
            'Upload a logo to display above the upload card.',
            'Choose from popular Google Fonts (Inter, Manrope, Roboto, Open Sans, Lato, Poppins, Montserrat, Nunito) or stay with your theme&rsquo;s default.',
            'Override the upload card heading, subhead, and submit button label without editing template files.',
            'Pick a corner-style preset: sharp, soft, or pill.',
            'Custom CSS textarea for power users who want pixel-level control.',
        ),
    ),
    array(
        'date'    => 'Apr 11, 2026',
        'version' => 'v1.4.1',
        'title'   => 'Customer upload reliability',
        'notes'   => array(
            'Resolved an issue where customers behind shared networks (hotel WiFi, mobile carrier proxies, certain CDNs) could occasionally see false &ldquo;rate limit&rdquo; errors when uploading their payment screenshot.',
            'iPhone HEIC screenshots now appear in the file picker by default - no need to toggle &ldquo;all files.&rdquo;',
            'Improved resilience when a third-party AI provider responds slowly. The screenshot still saves and the order still moves forward; the verification verdict updates as soon as the provider responds.',
        ),
    ),
    array(
        'date'    => 'Apr 4, 2026',
        'version' => 'v1.4.0',
        'title'   => 'Security hardening',
        'notes'   => array(
            'Tightened protections against abuse on the screenshot upload endpoint.',
            'Improved auto-cancel timing for orders on slower networks where the upload completed but the verification callback was delayed.',
            'Defense-in-depth on stored payment screenshots: every storage location now ships with a layered access denial.',
        ),
    ),
    array(
        'date'    => 'Mar 23, 2026',
        'version' => 'v1.3.4',
        'title'   => 'iPhone screenshot support',
        'notes'   => array(
            'iPhone screenshots saved as HEIC now upload reliably, even on hosts without specialized image conversion libraries.',
            'Cleaner error messages when a screenshot can&rsquo;t be processed, with concrete next steps for the host admin.',
        ),
    ),
    array(
        'date'    => 'Mar 16, 2026',
        'version' => 'v1.3.3',
        'title'   => 'Block Checkout polish',
        'notes'   => array(
            'Smoother experience for customers on stores using WooCommerce&rsquo;s Block Checkout.',
            'Better compatibility with stores that have strict security headers configured.',
        ),
    ),
    array(
        'date'    => 'Mar 9, 2026',
        'version' => 'v1.3.0',
        'title'   => 'Multi-account rotation',
        'notes'   => array(
            'Configure up to three handles per payment method (Venmo, Cash App, PayPal, Zelle) and rotate between them automatically.',
            'Two rotation strategies: least-recently-used (recommended) or round-robin.',
            'Useful when a single account hits transaction throughput limits during a launch or sale.',
        ),
    ),
    array(
        'date'    => 'Feb 23, 2026',
        'version' => 'v1.2.0',
        'title'   => 'Provider expansion',
        'notes'   => array(
            'OpenRouter added as a supported AI provider.',
            'New &ldquo;Custom&rdquo; provider option lets you point Pipe Pay at any OpenAI-compatible endpoint, including self-hosted models.',
            'Provider settings can be configured per-environment so staging and production can use different keys.',
        ),
    ),
    array(
        'date'    => 'Feb 6, 2026',
        'version' => 'v1.1.0',
        'title'   => 'Test connection &amp; auto-approval cap',
        'notes'   => array(
            'Test AI Connection button verifies your provider key with a real round-trip without placing a fake order.',
            'Configurable auto-approval cap. Orders above the cap always land in the manual review queue regardless of AI confidence.',
            'Admin Proofs queue gained a History tab so resolved proofs no longer crowd the Pending view.',
        ),
    ),
    array(
        'date'    => 'Jan 21, 2026',
        'version' => 'v1.0.0',
        'title'   => 'Initial release',
        'notes'   => array(
            'Venmo, Cash App, PayPal Friends &amp; Family, and Zelle as supported payment methods.',
            'AI verification via Claude or OpenAI, with high / medium / low confidence grading.',
            'Custom WooCommerce order status: Awaiting Proof.',
            'Admin Proofs review queue with manual approve, reject, and re-run analysis actions.',
            'Per-order reminder emails at 5, 20, and 45 minutes; 60-minute auto-cancel.',
            'Sticky bottom upload bar with browser tab-close warning.',
            'HPOS compatibility from day one.',
        ),
    ),
);
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">Ship log</span>
        <h1 class="pp-page-title">Changelog</h1>
        <p class="pp-page-hero__sub">Every shipped release of Pipe Pay, newest first. Bug fix releases between numbered versions are not separately documented unless they introduce a behavior change worth noting.</p>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container pp-container--narrow">
        <ol class="pp-changelog">
            <?php foreach ( $releases as $r ) : ?>
                <li class="pp-changelog__item">
                    <div class="pp-changelog__meta">
                        <span class="pp-changelog__version"><?php echo esc_html( $r['version'] ); ?></span>
                        <span class="pp-changelog__date"><?php echo esc_html( $r['date'] ); ?></span>
                    </div>
                    <div class="pp-changelog__body">
                        <h2 class="pp-changelog__title"><?php echo esc_html( $r['title'] ); ?></h2>
                        <ul class="pp-changelog__notes">
                            <?php foreach ( $r['notes'] as $n ) : ?>
                                <li><?php echo wp_kses_post( $n ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>

        <p class="pp-changelog__rss">
            Tracking only major changes? <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Subscribe to release notes</a> and we'll email you when a new version ships.
        </p>
    </div>
</section>

<?php get_footer(); ?>
