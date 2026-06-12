<?php
/**
 * Template for the About page (slug: about).
 *
 * Trust-page infrastructure required for phishing-classifier remediation
 * (Cloudflare flagged pipepay.app twice; Porkbun ToS flagged once for
 * Cash App mentions). Goal of this page: establish that Pipe Pay is a
 * legitimate B2B SaaS product, identify the founder, and disambiguate
 * the product from a consumer-facing payment service.
 *
 * Voice convention (see CLAUDE.md > Competitive defense > public-copy
 * voice note): "underserved by major processors" rather than "high-risk
 * vertical" - the strategic positioning stays intact in private docs,
 * but the public face stays neutral.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">About</span>
        <h1 class="pp-page-title">An independent WooCommerce plugin for merchants underserved by major processors.</h1>
        <p class="pp-page-hero__sub">Pipe Pay is a verification tool that installs on your own WordPress store. It is not a payment processor, money transmitter, or financial institution, and does not hold, route, or transmit customer funds. We help WooCommerce merchants verify that customers have paid them through external P2P apps - the payment itself happens between the customer and the merchant in the P2P app, exactly as it would without us.</p>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container pp-container--narrow">
        <div class="pp-prose">

            <h2>What Pipe Pay is.</h2>
            <p>Pipe Pay is a $299/year WordPress plugin for WooCommerce store owners. Installed on the merchant's own WordPress site, it does three things: (1) renders a payment page that shows the customer where to send their Venmo, Cash App, PayPal, or Zelle payment; (2) captures the payment-confirmation screenshot the customer uploads; (3) optionally runs that screenshot through an AI verification check using an API key the merchant provides, flagging anything that doesn't match the order amount or the merchant's configured handle. The merchant approves or rejects each payment from their WordPress admin dashboard.</p>
            <p>That is the entire product. We do not handle money. We do not connect to bank accounts. We do not act as an intermediary between buyer and seller for the funds. The customer pays the merchant directly through the P2P app of their choice, the same way they would if they were paying a friend back for dinner. Pipe Pay is the workflow layer that makes that legible to a WooCommerce store - matching the payment to the order, surfacing problems, keeping a record.</p>

            <h2>Who Pipe Pay is for.</h2>
            <p>WooCommerce merchants whose business doesn't fit the major card processors' approval criteria - either because the business category is underserved by Stripe / Square / Adyen, or because the merchant has been through enough processor terminations to want a different model entirely. We also serve merchants who simply prefer to keep the 2.9% + 30 cents on each transaction instead of paying it to a card network, and merchants validating a new product idea who don't want to file an LLC and a payment-processor application before they have proof of demand.</p>
            <p>Pipe Pay is explicitly not for: merchants who need to accept credit cards directly (we don't process cards), merchants on Shopify or BigCommerce (we are a WooCommerce plugin only), merchants who need WooCommerce Subscriptions (single-payment orders only in the current version), and merchants whose customers will not pay through a P2P app.</p>

            <h2>Built by a WooCommerce merchant who needed it.</h2>
            <p>Pipe Pay was built because we ran a WooCommerce store and got tired of reconciling 50+ Venmo, Cash App, and PayPal payments per day against the WooCommerce order list by hand. Every plugin we tried for the workflow assumed a low-friction Stripe-compatible merchant who had just landed on the wrong page; none of them did the actual reconciliation workflow correctly. So we built one, hardened it on our own stores, and are now selling it to other merchants in the same position.</p>
            <p>Pipe Pay is operated by Silver Bazaar, LLC, a US-formed limited liability company. "Pipe Pay" is the product name; Silver Bazaar, LLC is the legal entity behind it that appears in the terms of service, privacy policy, and footer copyright. The plugin source itself is closed-commercial; the customer-facing template files and license-resolver mu-plugin source are visible to any merchant who unzips the customer-download.</p>

            <h2>What Pipe Pay is not.</h2>
            <p>Pipe Pay is a third-party verification tool. We are not a payment processor, money transmitter, or financial institution. We are not affiliated with, endorsed by, or sponsored by Cash App, Block Inc., Zelle, Early Warning Services, Venmo, PayPal Holdings, Chime, or any other payment service we reference. We do not have a relationship with any of those companies. All product names, logos, and brands used on this site are property of their respective owners and are used for identification purposes only - so a merchant evaluating Pipe Pay knows which P2P apps the plugin supports.</p>
            <p>Pipe Pay does not handle, transmit, or hold customer funds at any point in the workflow. Funds move directly from the customer to the merchant inside whichever P2P app the customer chooses. The customer is in a contractual relationship with the P2P provider, not with Pipe Pay, for the payment itself.</p>

            <h2>Where to go next.</h2>
            <ul>
                <li><a href="<?php echo esc_url( home_url( '/how-it-works' ) ); ?>">How it works</a> - the full breakdown of the verification workflow.</li>
                <li><a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>">Pricing</a> - three license tiers, all with a 7-day free trial.</li>
                <li><a href="<?php echo esc_url( home_url( '/docs' ) ); ?>">Docs</a> - installation, AI verification, admin queue, configuration.</li>
                <li><a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact</a> - reach support; we respond within one business day.</li>
                <li><a href="<?php echo esc_url( home_url( '/terms' ) ); ?>">Terms of Service</a> and <a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>">Privacy Policy</a> - the agreement that governs your use of the plugin.</li>
            </ul>

        </div>
    </div>
</section>

<?php get_footer(); ?>
