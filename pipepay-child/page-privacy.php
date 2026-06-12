<?php
/**
 * Template for the Privacy Policy page (slug: privacy).
 *
 * v1 of this content was published 2026-05-11 alongside the v1.8.11 plugin
 * ship. Pipe Pay is operated by Silver Bazaar, LLC (US). The operator
 * constants below are the source of truth for the rendered policy header.
 *
 * Update protocol: bump the "Last updated" date when material changes ship.
 * Sub-processor list is intentionally a SEPARATE page (/sub-processors/) so
 * it can be updated without re-versioning this document. Track changes in
 * site-repo commits; legal-review-recommended threshold is any change that
 * adds a new data category, sub-processor, or retention period.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$last_updated  = 'May 25, 2026';
$contact_email = 'privacy@pipepay.app';
$operator_name = 'Silver Bazaar, LLC';
$jurisdiction  = 'United States';
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">Legal</span>
        <h1 class="pp-page-title">Privacy Policy</h1>
        <p class="pp-page-hero__sub">How Pipe Pay handles your data. Plain English, no surprises. Last updated <?php echo esc_html( $last_updated ); ?>.</p>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container pp-container--narrow">

        <article class="pp-legal-doc">

            <h2>The short version</h2>
            <p>Pipe Pay sells a WordPress plugin to merchants. We collect the minimum data needed to deliver the plugin, license it to you, and send security/renewal updates. We do not sell personal data to anyone, ever.</p>
            <p>Your merchant's customer (the person who places an order on a store running Pipe Pay) interacts with Pipe Pay's code only on the merchant's own server. We don't see those screenshots or order details. For that data, the merchant is the data controller and their privacy policy applies. See <a href="<?php echo esc_url( home_url( '/sub-processors/' ) ); ?>">Sub-processors</a> and our merchant-facing <a href="<?php echo esc_url( home_url( '/data-handling/' ) ); ?>">Data Handling guide</a> for the technical detail.</p>

            <hr />

            <h2>Who we are</h2>
            <p>Pipe Pay is a software product operated by <strong><?php echo esc_html( $operator_name ); ?></strong>, <?php echo esc_html( $jurisdiction ); ?>. References to "Pipe Pay," "we," "our," and "us" mean this operator. For contact details see <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">the Contact page</a> or email <a href="mailto:<?php echo antispambot( $contact_email ); ?>"><?php echo esc_html( $contact_email ); ?></a>.</p>

            <h2>What we collect, and why</h2>

            <h3>If you buy a Pipe Pay license</h3>
            <p>When you purchase a Pipe Pay plugin license on pipepay.app, we collect through standard WooCommerce checkout:</p>
            <ul>
                <li><strong>Identity:</strong> your name and email address. Required to issue the license, deliver download links, send renewal reminders, and provide support.</li>
                <li><strong>Billing address:</strong> required for invoicing and to comply with sales-tax obligations in the jurisdictions where the buyer is based.</li>
                <li><strong>Payment information:</strong> license payments on pipepay.app run through Pipe Pay itself (we dogfood our own checkout). We do not accept credit-card payments, so no third-party card processor receives your billing data &mdash; you pay us directly through whichever P2P app you choose at checkout (Venmo, Cash App, PayPal, or Zelle). The payment-confirmation screenshot you upload is stored on our server for accounting reconciliation and deleted per the retention schedule below. See <a href="<?php echo esc_url( home_url( '/sub-processors/' ) ); ?>">Sub-processors</a> for the full picture.</li>
            </ul>

            <h3>While you use the plugin (server-side)</h3>
            <p>Once your license is activated, our server receives the following on each daily license check, license activation, and plugin update download:</p>
            <ul>
                <li><strong>License key</strong> (used as your authentication credential to our server).</li>
                <li><strong>Site fingerprint</strong> (a deterministic hash derived from your site URL and a per-install random salt; documented in our <a href="<?php echo esc_url( home_url( '/docs/security/' ) ); ?>">Security docs</a>).</li>
                <li><strong>Site URL</strong> (your WordPress <code>home_url()</code>; used to detect license-cloning between installs).</li>
                <li><strong>Plugin version</strong> (so we know which release you're running and can target update notices appropriately).</li>
                <li><strong>The IP address</strong> that contacted our server (logged for operational purposes; see retention below).</li>
            </ul>
            <p>We never receive the contents of orders placed on your store, customer billing data captured by your WooCommerce, or any payment screenshots uploaded to your store. Those stay on your server.</p>

            <h3>Anti-piracy fingerprinting</h3>
            <p>Each time you download the plugin zip from your <a href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>">My Account</a> page, we embed a small marker file inside the zip identifying the download as yours. The marker contains your customer ID, order ID, plugin version, and a timestamp, signed cryptographically so it can't be forged. Its sole purpose is to identify the original downloader if a copy of the plugin appears on a piracy forum. We don't read the marker for anything else; it travels in the zip and lives only on your filesystem after extract.</p>

            <h3>Site analytics on this marketing website</h3>
            <p>This website (pipepay.app) is served through <strong>Cloudflare</strong> for caching and DDoS protection. Cloudflare may collect technical request data (IP, user agent, geographic region) for those purposes per their own privacy policy. We do not run third-party analytics scripts (no Google Analytics, no Mixpanel, no pixel trackers). The site sets a small number of essential cookies for the WordPress session and WooCommerce cart; we do not set any marketing or advertising cookies.</p>

            <h2>Lawful basis (GDPR)</h2>
            <ul>
                <li><strong>Contract performance:</strong> for license issuance, the activation/revalidation flow, and update delivery. You can't run a licensed copy of Pipe Pay without this data exchange.</li>
                <li><strong>Legitimate interest:</strong> for security logs (which IP made which license check), anti-piracy fingerprinting on zip downloads, and revocation enforcement against confirmed misuse. Balanced against the customer-side need to never block a legitimate purchase from running.</li>
                <li><strong>Legal obligation:</strong> for accounting records and tax reporting.</li>
                <li><strong>Consent:</strong> only for the (very few) optional email subscriptions, which we do not currently operate.</li>
            </ul>

            <h2>Who sees your data</h2>
            <p>We do not sell or rent your data. We use third-party processors only where genuinely necessary — see <a href="<?php echo esc_url( home_url( '/sub-processors/' ) ); ?>">the Sub-processors page</a> for the current list and what each one receives.</p>

            <h2>Retention</h2>
            <ul>
                <li><strong>License records</strong> (your license key, purchase order, activation history): kept for the life of the active license plus a 12-month tail after expiry, then deleted unless you have an open support ticket or a renewal in flight.</li>
                <li><strong>Daily revalidation log</strong> (which license checked in from which site URL and IP): <strong>90 days</strong>, automatically pruned. Used to detect license cloning.</li>
                <li><strong>Revocation log</strong> (when a license was administratively revoked and why): kept indefinitely as a security audit trail. Capped at 200 most-recent entries by design.</li>
                <li><strong>Web server access logs</strong> on pipepay.app (which IP requested which URL): rotated weekly; older logs are deleted.</li>
                <li><strong>Backups</strong>: daily encrypted backups for 30 days then deleted. Deletion requests may take up to 30 days to propagate through backups.</li>
            </ul>

            <h2>Your rights</h2>
            <p>You have the right, at any time, to:</p>
            <ul>
                <li><strong>Access</strong> the personal data we hold about you.</li>
                <li><strong>Correct</strong> inaccurate data.</li>
                <li><strong>Delete</strong> your data (the "right to be forgotten") — see the <strong>Data Deletion</strong> section below.</li>
                <li><strong>Receive a copy</strong> of your data in a portable format.</li>
                <li><strong>Object</strong> to processing, including any future change that introduces marketing emails.</li>
                <li><strong>Lodge a complaint</strong> with a supervisory authority (in the EU, your local data protection authority).</li>
            </ul>
            <p>To exercise any of these rights, email <a href="mailto:<?php echo antispambot( $contact_email ); ?>"><?php echo esc_html( $contact_email ); ?></a> with "Privacy Request" in the subject line and tell us which right you're exercising. We respond within <strong>30 days</strong> (the GDPR standard) and usually much sooner. We may ask for one piece of corroborating information to verify identity before processing a deletion (typically: the email used at purchase plus the last 4 of your license key).</p>

            <h2 id="data-deletion">Data deletion (right to be forgotten)</h2>
            <p>If you ask us to delete your data, the following happens within 30 days of verification:</p>
            <ol>
                <li>Your license record in our API Manager is purged. Auto-update and license revalidation will stop functioning for any installs using that license.</li>
                <li>Your WooCommerce purchase order on pipepay.app is anonymized (the order line items remain for our tax records as required by law, but identifying customer information is replaced with a tombstone marker).</li>
                <li>Your entries in our daily revalidation log are deleted.</li>
                <li>Your entries in any revocation log (if applicable) remain, but are anonymized to the same tombstone marker — we keep revocation events as a security audit trail but they are no longer linkable to you.</li>
                <li>Web server access logs containing your IP address age out per the standard 7-day rotation; we do not selectively delete from them.</li>
            </ol>
            <p>We will tell you when each step is complete. Backups containing your data are not selectively edited; they expire on the standard 30-day rotation, after which no copy of your data remains.</p>
            <p>If you are an end-customer of a store running Pipe Pay (not a Pipe Pay license holder), the data Pipe Pay knows about you is limited to what your merchant chose to send through their AI provider (if they use AI verification). To request deletion of order or screenshot data on the merchant's store, contact the merchant directly — they're the data controller for that data, not us.</p>

            <h2>Children's data</h2>
            <p>Pipe Pay is a business-to-business product. We do not knowingly collect data from anyone under 16. If you believe a minor's data has reached us, email us and we will delete it immediately.</p>

            <h2>Cross-border transfers</h2>
            <p>Pipe Pay is operated from the United States. License-customer data is processed in the United States. Sub-processors are listed with their data-location information on the <a href="<?php echo esc_url( home_url( '/sub-processors/' ) ); ?>">Sub-processors page</a>. We rely on the EU-US Data Privacy Framework (or its successor) and Standard Contractual Clauses for transfers from the EU to the US where applicable.</p>

            <h2>Security</h2>
            <p>License credentials are stored hashed where possible. Communications between your plugin install and our server are TLS-encrypted and additionally signed with Ed25519 so even a compromised CDN cannot tamper with the response. See our <a href="<?php echo esc_url( home_url( '/docs/security/' ) ); ?>">Security docs</a> for the technical detail.</p>

            <h2>Changes to this policy</h2>
            <p>If we materially change how we handle your data, we'll update this page and bump the "Last updated" date. For substantive changes (new sub-processor, new data category, longer retention), we'll email customers with an active license before the change takes effect.</p>

            <h2>Contact</h2>
            <p>Privacy questions, complaints, or data requests: <a href="mailto:<?php echo antispambot( $contact_email ); ?>?subject=<?php echo esc_attr( rawurlencode( 'Privacy Request' ) ); ?>"><?php echo esc_html( $contact_email ); ?></a> with "Privacy Request" in the subject. We answer every email; usually within one business day.</p>

        </article>
    </div>
</section>

<?php get_footer(); ?>
