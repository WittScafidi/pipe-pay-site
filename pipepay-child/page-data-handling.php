<?php
/**
 * Template for the "Data Handling for merchants" page (slug: data-handling).
 *
 * Audience: a Pipe Pay license-holder who needs to update their OWN privacy
 * policy to disclose what Pipe Pay does on their store. The page gives them
 * a clear technical inventory AND a paste-able template.
 *
 * Audited 2026-05-11 against actual plugin code in pipe-pay v1.8.11.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$last_updated = 'June 14, 2026';
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">For merchants</span>
        <h1 class="pp-page-title">Data Handling Guide</h1>
        <p class="pp-page-hero__sub">What Pipe Pay does with your customers' data, on your store. Suitable for inclusion in your own privacy policy. Last updated <?php echo esc_html( $last_updated ); ?>.</p>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container pp-container--narrow">
        <article class="pp-legal-doc">

            <h2>Who this is for</h2>
            <p>You bought a Pipe Pay license. You're running it on your WooCommerce store. Your store's privacy policy needs to disclose what Pipe Pay does with the personal data of your customers. This page gives you the technical truth plus a ready-to-paste section you can drop into your own privacy policy.</p>

            <h2>The 30-second summary</h2>
            <p>Pipe Pay's plugin runs entirely on your WordPress server. Your customer's payment screenshot stays on your server (outside the web-accessible directory, only viewable by you in WP admin). The only data that leaves your server is:</p>
            <ol>
                <li><strong>To Pipe Pay's license server (pipepay.app):</strong> your license key, a stable site fingerprint, your site URL, and the plugin version — once per day for the license check. No customer data, no order data, no screenshots.</li>
                <li><strong>To your chosen AI provider</strong> (Claude / OpenAI / OpenRouter / a custom endpoint you configured): the screenshot image bytes and a structured prompt asking for a verification verdict. This uses <em>your</em> API key, set up by you in the plugin settings. We are not in the middle.</li>
                <li><strong>To Pipe Pay's cross-store fraud network (pipepay.app):</strong> a 64-bit one-way fingerprint of the screenshot (never the image, never any text from it) so Pipe Pay can tell you whether the same screenshot was already used at a <em>different</em> Pipe Pay store. On by default; you can turn it off in the gateway settings (<em>Cross-store fraud network</em>). No image, no order data, no personal data.</li>
            </ol>
            <p>Everything else — billing address, customer email, order line items, the screenshot itself — stays on your WordPress server, governed by your own WP/WC setup and your own privacy policy.</p>

            <h2>Detailed data inventory</h2>

            <h3>1. Data Pipe Pay stores on your server</h3>
            <table class="pp-data-table">
                <thead>
                    <tr><th>Data</th><th>Where stored</th><th>How long</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Customer-uploaded payment screenshot</td>
                        <td>Filesystem, outside web-accessible directory (typically <code>wp-content/private-pipepay-proofs/</code>)</td>
                        <td>90 days after the order is completed, then automatically deleted by Pipe Pay's daily cron (configurable via <code>PIPEPAY_PROOF_RETENTION_DAYS</code>)</td>
                    </tr>
                    <tr>
                        <td>AI verification verdict + reasoning</td>
                        <td>WC order meta on the order</td>
                        <td>Lifetime of the order in your WC database</td>
                    </tr>
                    <tr>
                        <td>Perceptual fingerprint of the screenshot (64-bit dHash, ~16 hex chars)</td>
                        <td>WC order meta. A copy of the fingerprint (and nothing else) is also sent to Pipe Pay's cross-store fraud network unless you disable it — see section 4.</td>
                        <td>Lifetime of the order. Used to detect re-use of the same screenshot across multiple orders on your store, and (via the network) across other Pipe Pay stores.</td>
                    </tr>
                    <tr>
                        <td>Payment-method handle the customer was instructed to send to</td>
                        <td>WC order meta</td>
                        <td>Lifetime of the order</td>
                    </tr>
                </tbody>
            </table>

            <h3>2. Data sent to Pipe Pay's license server (pipepay.app)</h3>
            <p>Once per day, your Pipe Pay install contacts <code>pipepay.app/wp-json/pipepay-license/v1/revalidate</code> with:</p>
            <ul>
                <li>Your license key (authentication credential)</li>
                <li>A site fingerprint (a 32-char hash of your site URL + a per-install random salt + WordPress's <code>wp_salt('auth')</code>) used to detect license cloning between installs</li>
                <li>Your site's <code>home_url()</code> value</li>
                <li>Your plugin version</li>
            </ul>
            <p>No customer data, no order data, no screenshots. Pipe Pay's server returns a state verdict (<code>active</code>, <code>expired</code>, or <code>revoked</code>) signed cryptographically.</p>

            <h3>3. Data sent to your AI provider</h3>
            <p>When a customer uploads a screenshot, your Pipe Pay install sends to your configured AI provider:</p>
            <ul>
                <li>The screenshot image bytes (base64-encoded)</li>
                <li>A structured prompt asking the provider to read the amount, recipient handle, and payment-app context</li>
                <li>Your configured API key (in HTTP headers)</li>
            </ul>
            <p>The provider's response is parsed locally on your server. Pipe Pay does not see the screenshot bytes or the prompt — they go directly from your WordPress server to the provider over TLS.</p>
            <p>See <a href="<?php echo esc_url( home_url( '/sub-processors/' ) ); ?>">our sub-processors page</a> for the supported providers and their own privacy policies.</p>

            <h3>4. Data sent to Pipe Pay's cross-store fraud network</h3>
            <p>When a customer uploads a screenshot, your Pipe Pay install sends a <strong>64-bit perceptual fingerprint</strong> of that screenshot to <code>pipepay.app/wp-json/pipepay-phash/v1/submit</code>. Pipe Pay checks it against fingerprints submitted by <em>other</em> Pipe Pay stores and tells your install whether the same screenshot has been seen elsewhere; a match routes the order to manual review. This catches fraudsters who reuse one fake payment screenshot across many stores.</p>
            <p>What is sent:</p>
            <ul>
                <li>The 64-bit one-way fingerprint (16 hex characters)</li>
                <li>Your license key (authentication) and your site fingerprint, used only to keep your own store's submissions from flagging themselves and to rate-limit abuse</li>
            </ul>
            <p>What is <strong>not</strong> sent: the screenshot image, any text extracted from it (amount, handle, name), the order, the customer, or any other personal data. A 64-bit fingerprint is one-way — the original image cannot be reconstructed from it, and it contains no readable information. Pipe Pay stores only the fingerprint, a non-reversible token for your store, and a timestamp; it never receives or stores the image.</p>
            <p><strong>Opt-out:</strong> this is on by default. To disable it, go to <em>WooCommerce → Settings → Payments → Pipe Pay → Manage</em> and uncheck <em>Cross-store fraud network</em>. With it off, no fingerprint leaves your server and the only fraud signal is within-your-own-store re-use (section 1).</p>

            <hr />

            <h2>Paste-able privacy-policy section</h2>
            <p>Copy this into your own privacy policy. Customize the <code>[YOUR PROVIDER]</code> placeholders to reflect which AI provider you chose in Pipe Pay's settings. If you didn't set up an AI provider (manual-review mode), delete the AI provider section entirely.</p>

            <div class="pp-paste-block">
<pre><code><strong>Payment processing via Pipe Pay</strong>

We use Pipe Pay, a WordPress plugin developed by Pipe Pay
(pipepay.app), to handle customer payments via peer-to-peer apps
(Venmo, Cash App, PayPal, Zelle).

What data Pipe Pay handles on our server:
- The screenshot you upload when paying. Stored on our server,
  encrypted-at-rest where supported, outside the web-accessible
  directory. Automatically deleted 90 days after order completion.
- The amount, recipient handle, and payment-app context extracted
  from the screenshot for verification.

What Pipe Pay sends to its license server (pipepay.app):
- Once-per-day license check carrying our license key, our site URL,
  a site fingerprint, and the plugin version. NO customer data,
  order data, or screenshots are sent.
- A 64-bit one-way fingerprint of each payment screenshot, used to
  check whether the same screenshot was used at another store
  (cross-store fraud detection). The image itself, and any text in
  it, are NEVER sent. (If your store has this turned off, delete
  this line.)

What Pipe Pay sends to our AI provider, [YOUR PROVIDER]:
- The payment screenshot you uploaded and a prompt asking it to
  extract amount and recipient. We use [YOUR PROVIDER]'s API
  directly with our own API key; their privacy policy applies to
  this data. Currently configured: [YOUR PROVIDER].

For questions about how we handle your data, contact us at
[YOUR CONTACT EMAIL]. For questions about Pipe Pay's own data
handling, see pipepay.app/privacy.</code></pre>
            </div>

            <h2>Requests for deletion (right to be forgotten)</h2>
            <p>If a customer of your store asks you to delete their data:</p>
            <ol>
                <li>Delete the order in WooCommerce (or anonymize it per your usual procedure).</li>
                <li>Pipe Pay's order-meta data (verification verdict, dHash fingerprint, payment-method handle) deletes automatically with the order.</li>
                <li>The screenshot file is deleted when the order is deleted, AND would have aged out anyway at 90 days.</li>
                <li>The 64-bit fingerprint submitted to Pipe Pay's cross-store fraud network is not linked to the customer (it is just an anonymous hash) and is not personal data, so there is nothing customer-specific to delete there.</li>
                <li>If the order was previously analyzed by an AI provider, that provider may have temporary logs; consult <a href="<?php echo esc_url( home_url( '/sub-processors/' ) ); ?>">their privacy policy</a> for retention details. Pipe Pay does not have a path to delete data from the AI provider's side — that has to go through the provider directly with the customer's API key.</li>
            </ol>
            <p>To delete your own license data from Pipe Pay's license server (e.g., you've stopped using Pipe Pay and want us to purge your records), email <a href="mailto:privacy@pipepay.app?subject=Privacy%20Request%20-%20License%20Deletion">privacy@pipepay.app</a> with "Privacy Request - License Deletion" in the subject and we'll process it within 30 days. See <a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">our privacy policy</a> for what specifically gets purged.</p>

            <h2>Questions</h2>
            <p>Email <a href="mailto:privacy@pipepay.app">privacy@pipepay.app</a>. Subject line: "Pipe Pay data handling — [your question]". You'll get a real answer within one business day.</p>

        </article>
    </div>
</section>

<?php get_footer(); ?>
