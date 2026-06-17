<?php
/**
 * Template for the Sub-processors page (slug: sub-processors).
 *
 * Kept separate from the main Privacy Policy so the list can be updated
 * without re-versioning the whole legal document. Each entry tells the
 * customer EXACTLY what data the sub-processor sees and where it's
 * processed.
 *
 * Update protocol: bump the "Last updated" date on every change. For
 * substantive additions (new sub-processor, new data category to an
 * existing sub-processor) email active license-holders ahead of time
 * per the privacy policy's "Changes to this policy" commitment.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$last_updated = 'June 14, 2026';
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">Legal</span>
        <h1 class="pp-page-title">Sub-processors</h1>
        <p class="pp-page-hero__sub">Third-party services Pipe Pay uses to deliver its plugin and license server. Last updated <?php echo esc_html( $last_updated ); ?>.</p>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container pp-container--narrow">
        <article class="pp-legal-doc">

            <p>Pipe Pay engages the third parties listed below to operate the plugin, deliver license updates, and run pipepay.app. Each one is named here with the data it receives, why it gets that data, and where it processes it.</p>

            <p><strong>Important distinction:</strong> these are the sub-processors for <em>Pipe Pay's own service</em> – the license-server side. Sub-processors for the <em>customer-side</em> data that flows through your Pipe Pay install on your own WordPress server (specifically, the AI provider that verifies payment screenshots) are configured by <em>you</em>, the merchant, with your own API key. We list the supported options below as well, but Pipe Pay never sees that data – your merchant store sends it directly to your chosen provider. See our <a href="<?php echo esc_url( home_url( '/data-handling/' ) ); ?>">Data Handling guide</a> for the technical detail.</p>

            <hr />

            <h2>Pipe Pay's own sub-processors</h2>

            <div class="pp-subproc-block">
                <h3>No third-party payment processor</h3>
                <p class="pp-subproc-meta"><strong>Why this is not a sub-processor:</strong> license payments on pipepay.app run through Pipe Pay itself (we dogfood our own checkout). We do not accept credit-card payments, so no third-party card processor (Stripe, Braintree, Adyen, or otherwise) receives your billing data.</p>
                <p class="pp-subproc-meta"><strong>How payment actually flows:</strong> at checkout you choose a P2P method (Venmo, Cash App, PayPal, or Zelle) and pay us directly through that app. The contractual relationship for the payment itself is between you and the P2P provider you chose, not between you and a card network. Pipe Pay observes and reconciles those payments; it never holds, routes, or transmits the funds.</p>
                <p class="pp-subproc-meta"><strong>If this ever changes:</strong> adding a card processor to pipepay.app would be a substantive change to this list, and active license-holders would be notified by email before the change takes effect.</p>
            </div>

            <div class="pp-subproc-block">
                <h3>Cloudflare</h3>
                <p class="pp-subproc-meta"><strong>Purpose:</strong> CDN, DDoS protection, and bot-management for pipepay.app and for the license-server endpoints your plugin contacts each day.</p>
                <p class="pp-subproc-meta"><strong>Data received:</strong> Request metadata (your IP address, user agent, geographic region, request URL). Cloudflare does not see the contents of license-check responses because those are end-to-end signed by us with Ed25519 before being served through them.</p>
                <p class="pp-subproc-meta"><strong>Processing location:</strong> Cloudflare global network; routes to the closest edge to the request origin.</p>
                <p class="pp-subproc-meta"><strong>Privacy policy:</strong> <a href="https://www.cloudflare.com/privacypolicy/" target="_blank" rel="noopener">cloudflare.com/privacypolicy/</a></p>
            </div>

            <hr />

            <h2>Cross-store fraud network (operated by Pipe Pay)</h2>
            <p>This is the one place where data derived from your customers' payment screenshots reaches Pipe Pay's own servers. It sends a one-way fingerprint only – never the image – and it can be turned off in your gateway settings.</p>

            <div class="pp-subproc-block">
                <h3>Pipe Pay Screenshot Hash Network</h3>
                <p class="pp-subproc-meta"><strong>Operated by:</strong> Pipe Pay itself (pipepay.app) – not a third party.</p>
                <p class="pp-subproc-meta"><strong>Purpose:</strong> Cross-store duplicate-screenshot fraud detection. Your install submits a 64-bit one-way fingerprint of each payment screenshot; Pipe Pay reports whether the same screenshot has been seen at a <em>different</em> Pipe Pay store, and a match routes that order to manual review.</p>
                <p class="pp-subproc-meta"><strong>Data received:</strong> The 64-bit fingerprint, your license key (authentication), and your site fingerprint (so your own store's submissions don't self-flag, and for rate-limiting). NEVER the screenshot image, the extracted amount or handle, the customer, or any order data. The fingerprint is one-way and not linkable to a person, so it is not personal data.</p>
                <p class="pp-subproc-meta"><strong>Opt-out:</strong> On by default. <em>WooCommerce → Settings → Payments → Pipe Pay → Manage → uncheck "Cross-store fraud network."</em> With it off, no fingerprint leaves your server.</p>
                <p class="pp-subproc-meta"><strong>Processing location:</strong> United States.</p>
                <p class="pp-subproc-meta"><strong>Privacy policy:</strong> <a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">pipepay.app/privacy</a></p>
            </div>

            <hr />

            <h2>Sub-processors you (the merchant) control</h2>
            <p>The following services are used by Pipe Pay's plugin code running on <em>your</em> WordPress server, with your own API keys. Pipe Pay never sees this data. We list them here so you know what to disclose in your own privacy policy. For ready-to-paste text, see our <a href="<?php echo esc_url( home_url( '/data-handling/' ) ); ?>">Data Handling guide for merchants</a>.</p>

            <div class="pp-subproc-block">
                <h3>Anthropic (Claude)</h3>
                <p class="pp-subproc-meta"><strong>Purpose:</strong> Optional AI verification of customer-uploaded payment screenshots, if you choose Claude as your AI provider.</p>
                <p class="pp-subproc-meta"><strong>Data received:</strong> The screenshot image bytes and a structured prompt describing what to extract. Sent directly from your WordPress server using your own Anthropic API key. Anthropic's API does not retain customer inputs for training under their standard terms.</p>
                <p class="pp-subproc-meta"><strong>Processing location:</strong> United States.</p>
                <p class="pp-subproc-meta"><strong>Privacy policy:</strong> <a href="https://www.anthropic.com/legal/privacy" target="_blank" rel="noopener">anthropic.com/legal/privacy</a></p>
            </div>

            <div class="pp-subproc-block">
                <h3>OpenAI</h3>
                <p class="pp-subproc-meta"><strong>Purpose:</strong> Optional AI verification, if you choose OpenAI as your AI provider.</p>
                <p class="pp-subproc-meta"><strong>Data received:</strong> Same as above – screenshot bytes and prompt, sent with your OpenAI API key. OpenAI's API does not retain customer inputs for training under their standard terms.</p>
                <p class="pp-subproc-meta"><strong>Processing location:</strong> United States.</p>
                <p class="pp-subproc-meta"><strong>Privacy policy:</strong> <a href="https://openai.com/policies/privacy-policy/" target="_blank" rel="noopener">openai.com/policies/privacy-policy/</a></p>
            </div>

            <div class="pp-subproc-block">
                <h3>OpenRouter</h3>
                <p class="pp-subproc-meta"><strong>Purpose:</strong> Optional AI verification routing, if you choose OpenRouter as your AI provider (which itself routes to one of dozens of supported models).</p>
                <p class="pp-subproc-meta"><strong>Data received:</strong> Same as above. The end model is whichever you configure on OpenRouter – review OpenRouter's policies for downstream sub-processors.</p>
                <p class="pp-subproc-meta"><strong>Processing location:</strong> Varies by selected model.</p>
                <p class="pp-subproc-meta"><strong>Privacy policy:</strong> <a href="https://openrouter.ai/privacy" target="_blank" rel="noopener">openrouter.ai/privacy</a></p>
            </div>

            <div class="pp-subproc-block">
                <h3>Custom endpoint (your choice)</h3>
                <p class="pp-subproc-meta"><strong>Purpose:</strong> If you self-host or use an OpenAI-compatible alternative API not listed above, Pipe Pay will send the screenshot to whatever endpoint you configure.</p>
                <p class="pp-subproc-meta"><strong>Data received:</strong> Whatever you direct it to. Your privacy policy obligations apply to your chosen endpoint.</p>
            </div>

            <hr />

            <h2>When this list changes</h2>
            <p>Substantive additions or changes (a new sub-processor, a new data category to an existing one) are notified to active license-holders by email before the change takes effect. The "Last updated" date at the top of this page tracks every change.</p>
            <p>If you'd like to be notified of changes whether or not you currently hold a license, email <a href="mailto:privacy@pipepay.app?subject=Subscribe%20to%20sub-processor%20updates">privacy@pipepay.app</a> with "Subscribe to sub-processor updates" in the subject.</p>

        </article>
    </div>
</section>

<?php get_footer(); ?>
