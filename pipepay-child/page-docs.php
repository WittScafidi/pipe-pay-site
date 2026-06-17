<?php
/**
 * Template for the Docs landing page (slug: docs).
 * Sub-pages will live under /docs/{topic} once the actual articles are written.
 * For now this lists the topic areas with one-line summaries.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$icon_attrs    = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"';
$icon_rocket   = '<svg ' . $icon_attrs . '><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>';
$icon_sparkles = '<svg ' . $icon_attrs . '><path d="m12 3-1.9 5.8L4.3 10.7l5.8 1.9L12 18.4l1.9-5.8 5.8-1.9-5.8-1.9z"/><path d="M5 22v-4"/><path d="M19 22v-4"/><path d="M3 20h4"/><path d="M17 20h4"/></svg>';
$icon_clipboard= '<svg ' . $icon_attrs . '><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>';
$icon_sliders  = '<svg ' . $icon_attrs . '><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>';
$icon_refresh  = '<svg ' . $icon_attrs . '><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>';
$icon_undo     = '<svg ' . $icon_attrs . '><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>';
$icon_shield   = '<svg ' . $icon_attrs . '><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
$icon_key      = '<svg ' . $icon_attrs . '><circle cx="7.5" cy="15.5" r="5.5"/><path d="M21 2l-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>';
$icon_wrench   = '<svg ' . $icon_attrs . '><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>';

$sections = array(
    array(
        'slug'    => 'getting-started',
        'icon'    => $icon_rocket,
        'kicker'  => 'Getting started',
        'title'   => 'Install and run your first test order',
        'desc'    => 'Drop the plugin in, paste your license key, add a P2P handle, place a test order on your own store, and watch the full upload + AI flow.',
        'topics'  => array( 'Plugin installation', 'License key activation', 'First test order', 'P2P account setup' ),
    ),
    array(
        'slug'    => 'ai-verification',
        'icon'    => $icon_sparkles,
        'kicker'  => 'AI verification',
        'title'   => 'Configure your AI provider',
        'desc'    => 'Pipe Pay supports Claude, OpenAI, OpenRouter, or any OpenAI-compatible endpoint. Bring your own key, set an auto-approval cap, and test the connection before a real order hits.',
        'topics'  => array( 'Provider selection', 'API keys', 'Auto-approval cap', 'Manual review mode', 'Test AI Connection' ),
    ),
    array(
        'slug'    => 'admin-guide',
        'icon'    => $icon_clipboard,
        'kicker'  => 'Admin guide',
        'title'   => 'Run the Proofs review queue',
        'desc'    => 'How to triage flagged proofs, approve or reject at volume, re-run AI analysis on demand, and read the confidence signals the AI is surfacing.',
        'topics'  => array( 'Proofs queue', 'Confidence levels', 'Approve and reject flow', 'Re-running AI analysis', 'History tab' ),
    ),
    array(
        'slug'    => 'configuration',
        'icon'    => $icon_sliders,
        'kicker'  => 'Configuration',
        'title'   => 'Per-method settings, multi-account rotation, branding',
        'desc'    => 'Set up handles for Venmo (personal or business), Cash App, PayPal Friends &amp; Family, and Zelle. Add multiple accounts per method with rotation. Upload QR codes and set per-method accent colors.',
        'topics'  => array( 'Per-method handles', 'Multi-account rotation', 'QR codes', 'Customer page branding', 'Method enable / disable' ),
    ),
    array(
        'slug'    => 'order-lifecycle',
        'icon'    => $icon_refresh,
        'kicker'  => 'Order lifecycle',
        'title'   => 'Awaiting Proof, reminders, auto-cancel',
        'desc'    => 'How orders move through Pipe Pay\'s custom statuses: Awaiting Proof to Processing on auto-approval, Awaiting Approval for manual review, Cancelled when the customer ghosts. Reminder email cadence and timing.',
        'topics'  => array( 'Order statuses', 'Reminder emails', 'Auto-cancel timing', 'Stock restoration', 'Customer cancellation email' ),
    ),
    array(
        'slug'    => 'refunds',
        'icon'    => $icon_undo,
        'kicker'  => 'Refunds',
        'title'   => 'Issue a refund inside or outside Pipe Pay',
        'desc'    => 'Money movement happens in your P2P app, not in Pipe Pay. With Venmo and Cash App business profiles you can use the in-app refund button. With personal Venmo, Cash App, PayPal F&amp;F, and Zelle you send a new payment in reverse and mark the order refunded.',
        'topics'  => array( 'Business-profile refunds', 'Personal-account refunds', 'Marking refunded in WooCommerce', 'Customer notification' ),
    ),
    array(
        'slug'    => 'security',
        'icon'    => $icon_shield,
        'kicker'  => 'Security',
        'title'   => 'How proofs are stored and viewed',
        'desc'    => 'Payment proofs live outside the web-accessible directory. Viewing routes through an authenticated proxy gated by manage_woocommerce. Filenames are 32-character hex random. Optional separate-volume storage via wp-config constant.',
        'topics'  => array( 'Storage location', 'Capability gating', 'Random filenames', 'Custom storage volume', 'Triple denial-file layer' ),
    ),
    array(
        'slug'    => 'license-management',
        'icon'    => $icon_key,
        'kicker'  => 'License management',
        'title'   => 'Activate, transfer, and renew',
        'desc'    => 'How license activation, deactivation, and renewal work. Why annual renewal matters: keeps WooCommerce-compatibility patches, security updates, and support flowing so your install stays current with each WP and WC release.',
        'topics'  => array( 'Activation', 'Deactivation', 'Site limits per tier', 'Renewal', 'License expiration behavior' ),
    ),
    array(
        'slug'    => 'troubleshooting',
        'icon'    => $icon_wrench,
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
        <p class="pp-page-hero__sub">Everything we know about running Pipe Pay in production, from installation and AI verification to the review queue, refunds, security, and troubleshooting. Pick a topic below to dive in.</p>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container">
        <div class="pp-docs-grid">
            <?php foreach ( $sections as $s ) : ?>
                <a class="pp-doc-card" href="<?php echo esc_url( home_url( '/docs/' . $s['slug'] . '/' ) ); ?>">
                    <span class="pp-doc-card__icon" aria-hidden="true"><?php echo $s['icon']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
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
            <p>If you are mid-trial and stuck on something the docs don't cover, email me directly and I will answer.</p>
            <a class="pp-btn pp-btn--primary" href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact support</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
