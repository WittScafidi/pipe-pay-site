<?php
/**
 * Template for the Docs landing page (slug: docs).
 * Sub-pages will live under /docs/{topic} once the actual articles are written.
 * For now this lists the topic areas with one-line summaries.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$sections = array(
    array(
        'slug'    => 'getting-started',
        'kicker'  => 'Getting started',
        'title'   => 'Install and run your first test order',
        'desc'    => 'Drop the plugin in, paste your license key, add a P2P handle, place a test order on your own store, and watch the full upload + AI flow.',
        'topics'  => array( 'Plugin installation', 'License key activation', 'First test order', 'P2P account setup' ),
    ),
    array(
        'slug'    => 'ai-verification',
        'kicker'  => 'AI verification',
        'title'   => 'Configure your AI provider',
        'desc'    => 'Pipe Pay supports Claude, OpenAI, OpenRouter, or any OpenAI-compatible endpoint. Bring your own key, set an auto-approval cap, and test the connection before a real order hits.',
        'topics'  => array( 'Provider selection', 'API keys', 'Auto-approval cap', 'Manual review mode', 'Test AI Connection' ),
    ),
    array(
        'slug'    => 'admin-guide',
        'kicker'  => 'Admin guide',
        'title'   => 'Run the Proofs review queue',
        'desc'    => 'How to triage flagged proofs, approve or reject at volume, re-run AI analysis on demand, and read the confidence signals the AI is surfacing.',
        'topics'  => array( 'Proofs queue', 'Confidence levels', 'Approve and reject flow', 'Re-running AI analysis', 'History tab' ),
    ),
    array(
        'slug'    => 'configuration',
        'kicker'  => 'Configuration',
        'title'   => 'Per-method settings, multi-account rotation, branding',
        'desc'    => 'Set up handles for Venmo (personal or business), Cash App, PayPal Friends &amp; Family, and Zelle. Add multiple accounts per method with rotation. Upload QR codes and set per-method accent colors.',
        'topics'  => array( 'Per-method handles', 'Multi-account rotation', 'QR codes', 'Customer page branding', 'Method enable / disable' ),
    ),
    array(
        'slug'    => 'order-lifecycle',
        'kicker'  => 'Order lifecycle',
        'title'   => 'Awaiting Proof, reminders, auto-cancel',
        'desc'    => 'How orders move through Pipe Pay\'s custom statuses: Awaiting Proof to Processing on auto-approval, On Hold for manual review, Cancelled when the customer ghosts. Reminder email cadence and timing.',
        'topics'  => array( 'Order statuses', 'Reminder emails', 'Auto-cancel timing', 'Stock restoration', 'Customer cancellation email' ),
    ),
    array(
        'slug'    => 'refunds',
        'kicker'  => 'Refunds',
        'title'   => 'Issue a refund inside or outside Pipe Pay',
        'desc'    => 'Money movement happens in your P2P app, not in Pipe Pay. With Venmo and Cash App business profiles you can use the in-app refund button. With personal Venmo, Cash App, PayPal F&amp;F, and Zelle you send a new payment in reverse and mark the order refunded.',
        'topics'  => array( 'Business-profile refunds', 'Personal-account refunds', 'Marking refunded in WooCommerce', 'Customer notification' ),
    ),
    array(
        'slug'    => 'security',
        'kicker'  => 'Security',
        'title'   => 'How proofs are stored and viewed',
        'desc'    => 'Payment proofs live outside the web-accessible directory. Viewing routes through an authenticated proxy gated by manage_woocommerce. Filenames are 32-character hex random. Optional separate-volume storage via wp-config constant.',
        'topics'  => array( 'Storage location', 'Capability gating', 'Random filenames', 'Custom storage volume', 'Triple denial-file layer' ),
    ),
    array(
        'slug'    => 'license-management',
        'kicker'  => 'License management',
        'title'   => 'Activate, transfer, and renew',
        'desc'    => 'How license activation, deactivation, and renewal work. What happens when a license lapses (the plugin stops processing new payments at the next license check; existing data is intact).',
        'topics'  => array( 'Activation', 'Deactivation', 'Site limits per tier', 'Renewal', 'License expiration behavior' ),
    ),
    array(
        'slug'    => 'troubleshooting',
        'kicker'  => 'Troubleshooting',
        'title'   => 'Common issues and where Pipe Pay\'s logs live',
        'desc'    => 'When uploads fail, when AI verification stalls, when an order seems stuck. What to check first, where Pipe Pay logs live, and how to tell whether the issue is on our side or somewhere else in your stack.',
        'topics'  => array( 'Upload failures', 'AI verification stalls', 'Stuck orders', 'Pipe Pay logs', 'Pipe Pay vs your stack' ),
    ),
);
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">Documentation</span>
        <h1 class="pp-page-title">Pipe Pay docs</h1>
        <p class="pp-page-hero__sub">Everything we know about running Pipe Pay in production. The articles are still being written; the topic areas below are the table of contents we are filling in.</p>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container">
        <div class="pp-docs-grid">
            <?php foreach ( $sections as $s ) : ?>
                <a class="pp-doc-card" href="<?php echo esc_url( home_url( '/docs/' . $s['slug'] . '/' ) ); ?>">
                    <span class="pp-doc-card__kicker"><?php echo esc_html( $s['kicker'] ); ?></span>
                    <h3 class="pp-doc-card__title"><?php echo esc_html( $s['title'] ); ?></h3>
                    <p class="pp-doc-card__desc"><?php echo wp_kses_post( $s['desc'] ); ?></p>
                    <ul class="pp-doc-card__topics">
                        <?php foreach ( $s['topics'] as $t ) : ?>
                            <li><?php echo esc_html( $t ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <span class="pp-doc-card__cta">Read &rarr;</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="pp-section pp-section--snug pp-section--alt">
    <div class="pp-container pp-container--narrow">
        <div class="pp-docs-cta">
            <h2>Can't find what you need?</h2>
            <p>The docs are still under construction. If you are mid-trial and stuck, email me directly and I will answer.</p>
            <a class="pp-btn pp-btn--primary" href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact support</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
