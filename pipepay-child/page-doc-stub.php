<?php
/**
 * Template Name: Doc Stub
 *
 * Single template used by every /docs/{slug}/ child page. Articles with a
 * 'body' key render fully; articles without one show a "coming soon" stub
 * plus the topic outline.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$slug = get_post_field( 'post_name', get_the_ID() );

$docs = array(

    'getting-started' => array(
        'kicker' => 'Getting started',
        'title'  => 'Install and run your first test order',
        'sub'    => 'From plugin install to first verified test order in about ten minutes.',
        'body'   => <<<'HTML'
<h2>1. Install the plugin</h2>
<p>Download the plugin zip from the email we sent you after purchase. In WordPress: <code>Plugins -> Add New -> Upload Plugin</code>, pick the zip, click <em>Install Now</em>, then <em>Activate</em>. Pipe Pay registers itself as a WooCommerce payment gateway during activation; if WooCommerce is not active you will see a notice and the gateway will not appear.</p>

<h2>2. Activate your license</h2>
<p>Go to <code>WooCommerce -> Settings -> Payments -> Pipe Pay -> Manage</code>. The first field on the page is the license key. Paste the key from your purchase email and save. The status indicator next to the field flips to <em>Active</em> within a second or two. If it does not, double-check that you are pasting the key for the same site you are activating on; the activation count is enforced per registered site.</p>

<h2>3. Add your P2P handles</h2>
<p>On the same settings page, scroll to the <em>Payment methods</em> section and enable each rail you want to accept. For each one, paste the handle exactly as it appears in your P2P app:</p>
<ul>
    <li><strong>Venmo:</strong> <code>@your-handle</code></li>
    <li><strong>Cash App:</strong> <code>$your-cashtag</code></li>
    <li><strong>PayPal:</strong> the email address associated with your PayPal account</li>
    <li><strong>Zelle:</strong> the email or phone number registered with your bank's Zelle</li>
</ul>
<p>Optionally upload a QR code per method so customers on desktop can scan and pay from their phone. QR codes auto-hide on phone-sized screens (the customer is already on their phone, no need to scan).</p>

<h2>4. Place a test order</h2>
<p>From a private/incognito window, add a cheap product to your cart and complete checkout, picking Pipe Pay as the payment method. You will land on the customer-facing payment page. Send yourself $1 (or whatever the order total is) through the P2P app, screenshot the confirmation, and upload it via the sticky bar at the bottom of the page. If you have an AI provider configured, the order should auto-approve in a few seconds; if not, the order lands in the Proofs queue for you to manually approve.</p>

<h2>Common first-run errors</h2>
<ul>
    <li><strong>"License invalid":</strong> the key has not been activated yet, or the site URL has changed since activation. Re-paste the key.</li>
    <li><strong>"No payment methods enabled":</strong> at least one P2P method needs an enabled toggle and a non-empty handle.</li>
    <li><strong>Upload silently fails:</strong> usually a server-side file-size limit. Check the troubleshooting article for which PHP and nginx settings to bump.</li>
</ul>
HTML,
        'topics' => array(
            'Plugin installation: upload zip, activate.',
            'License key activation: where to paste it, what the active state looks like.',
            'P2P account setup: handles, business profiles, custom QR codes.',
            'First test order: place a $1 order on your own store, run the upload + AI flow end to end.',
            'Common first-run errors and what they mean.',
        ),
    ),

    'ai-verification' => array(
        'kicker' => 'AI verification',
        'title'  => 'Configure your AI provider',
        'sub'    => 'Pick a provider, paste an API key, set an auto-approval cap, and verify the integration before a real order hits.',
        'body'   => <<<'HTML'
<h2>Pick a provider</h2>
<p>Pipe Pay supports four AI providers and treats them as interchangeable. Pick whichever fits your existing accounts and pricing preferences:</p>
<ul>
    <li><strong>Claude</strong> (Anthropic). Strong vision, low hallucination rate on structured tasks. Cheapest at moderate volume.</li>
    <li><strong>OpenAI</strong> (GPT-4o family). Fastest. Slightly more permissive on borderline screenshots.</li>
    <li><strong>OpenRouter.</strong> Gives you access to the above plus open-source models behind one key. Useful if you want to swap models cheaply.</li>
    <li><strong>Custom OpenAI-compatible endpoint.</strong> Point Pipe Pay at a self-hosted Llama, vLLM server, Together, or any OpenAI-compatible API. The data never leaves your infrastructure.</li>
</ul>

<h2>Paste the API key</h2>
<p>In <code>WooCommerce -> Settings -> Payments -> Pipe Pay -> Manage</code>, pick your provider from the dropdown, paste the API key, and save. Pipe Pay never stores your screenshots; they are sent directly to the provider's vision endpoint and the response is parsed locally.</p>
<p>If you run staging and production on the same WordPress site (rare but possible), use a different key per environment so usage caps and rate limits do not blur.</p>

<h2>Set the auto-approval cap</h2>
<p>Below the provider field is the auto-approval cap, expressed in your store's currency. The default is $500. Any verified order at or under that dollar amount will move straight to <em>Processing</em> if the AI returns high confidence. Anything above lands in the Proofs queue regardless of how confident the AI is. We recommend setting the cap to roughly your average order value; review the small stuff at human speed and confirm the big stuff yourself.</p>

<h2>Run without an AI provider (manual review mode)</h2>
<p>If you leave the provider dropdown set to <em>None</em>, every uploaded screenshot lands in the Proofs queue and waits for you. The plugin works fine this way; you just lose the auto-approval lane. This is the right setup if you do under ten orders a day, or if you want to evaluate AI accuracy on your own data before turning it loose.</p>

<h2>Test the connection before a real order arrives</h2>
<p>The <em>Test AI Connection</em> button at the bottom of the settings page runs a real round-trip against your provider with a generated test image. Pass = green checkmark plus the latency. Fail = the provider's actual error message (rate limit, invalid key, model deprecated). Run this every time you change provider, change keys, or rotate model versions.</p>

<h2>When confidence is capped at "medium"</h2>
<p>Even on a clean-looking screenshot, Pipe Pay will cap confidence at <em>medium</em> if any of these signals trip:</p>
<ul>
    <li>Recipient handle does not exactly match your configured handle.</li>
    <li>Amount on the screenshot differs from the order total by any amount.</li>
    <li>Visible signs of pixel-level editing around the amount or recipient fields.</li>
    <li>Screenshot timestamp is more than 30 minutes old or in the future.</li>
    <li>Transaction status reads "pending" or "cancelled."</li>
</ul>
<p>Capped orders skip auto-approval and require your manual review. This is intentional: a screenshot can look pristine and still be wrong.</p>
HTML,
        'topics' => array(
            'Choosing a provider: Claude, OpenAI, OpenRouter, or any OpenAI-compatible custom endpoint.',
            'API keys: where to put them, environment-specific keys for staging vs production.',
            'Auto-approval cap: setting a dollar threshold above which orders always land in manual review.',
            'Manual review mode: running Pipe Pay without any AI provider configured.',
            'Test AI Connection button: what a successful round-trip looks like.',
            'When confidence is capped at "medium" by a fraud signal, even on a clean screenshot.',
        ),
    ),

    'admin-guide' => array(
        'kicker' => 'Admin guide',
        'title'  => 'Run the Proofs review queue',
        'sub'    => 'Triage flagged proofs, approve and reject in bulk, re-run AI analysis, and read the confidence signals the AI is surfacing.',
        'body'   => <<<'HTML'
<h2>The Proofs queue, at a glance</h2>
<p>Open <code>WooCommerce -> Pipe Pay Proofs</code>. The queue has two tabs:</p>
<ul>
    <li><strong>Pending.</strong> Orders waiting on your review. Anything the AI flagged with medium or low confidence, plus anything above your auto-approval cap.</li>
    <li><strong>History.</strong> Everything that has been approved or rejected, oldest first when sorted by date. Useful for spot-checking the AI's auto-approvals after the fact.</li>
</ul>
<p>Each row shows a thumbnail of the screenshot, the order ID, amount, customer email, P2P method, AI confidence, and inline approve/reject buttons.</p>

<h2>Confidence levels</h2>
<ul>
    <li><strong>High</strong> (green). Amount matches, handle matches, no editing signals, completed transaction. Orders at or below your auto-approval cap auto-process; orders above land here for your review.</li>
    <li><strong>Medium</strong> (yellow). One or more signals tripped, but nothing definitively wrong. Read the AI reasoning text, glance at the screenshot, decide.</li>
    <li><strong>Low</strong> (red). The AI is recommending you reject. Examples: amount visibly mismatched, recipient handle missing or wrong, transaction shown as pending or cancelled, clear signs of edited pixels.</li>
</ul>

<h2>Approve and reject</h2>
<p>Click any row to open the full proof view: full-size screenshot, the AI's structured reasoning, the customer's order details, and a comment field. Use this view for anything ambiguous.</p>
<p>For obvious cases, the inline buttons in the queue work without opening the row. Hold <kbd>Shift</kbd> and click multiple checkboxes to select a range; bulk-approve or bulk-reject from the action dropdown above the table.</p>

<h2>Re-run AI on a single proof</h2>
<p>Inside the proof detail view, the <em>Re-run AI</em> button sends the screenshot to your provider again. Useful when the first call timed out, when the provider was rate-limited, or when you have changed providers and want a second opinion. Each re-run is a fresh API call and costs the usual provider fee.</p>

<h2>What the AI's reasoning text contains</h2>
<p>Pipe Pay asks the AI to return structured JSON with a per-check pass/fail and one-sentence rationale. You see all of that in the proof detail view. Example:</p>
<pre><code>amount_matches: true   - Screenshot shows $87.50, order total is $87.50.
handle_matches: true   - Recipient @your-handle matches configured Venmo handle.
edit_artifacts: false  - No anti-aliasing inconsistencies detected around amount field.
timestamp_recent: true - Transaction time is 2 minutes ago.
status_complete: true  - Status reads "Sent" with green checkmark visible.
overall_confidence: high
</code></pre>
<p>Use this as input for your decision; do not treat it as authoritative on its own.</p>

<h2>What the customer sees</h2>
<p>Pipe Pay never sends a rejection email automatically. Medium- or low-confidence verifications do not reject the order; they just hold it in the Proofs queue for you. Three possible outcomes a customer experiences:</p>
<ul>
    <li><strong>Auto-approved</strong> (high confidence, at or below your auto-approval cap): standard WooCommerce order-confirmation email, order moves to <em>Processing</em>.</li>
    <li><strong>You approve from the queue</strong>: same as above, just delayed by however long the order sat in <em>On Hold</em>. Most customers do not notice.</li>
    <li><strong>You reject from the queue</strong>: rejection email goes out with your configurable note (default: a short message asking them to retry or contact you), order moves to <em>Cancelled</em>.</li>
</ul>
<p>The customer never sees the AI's reasoning text or the confidence level; that lives only in your admin.</p>
HTML,
        'topics' => array(
            'Proofs queue layout: Pending vs History tabs.',
            'Confidence levels (high, medium, low) and what each one means in practice.',
            'Approve and reject flow: keyboard shortcuts, bulk actions.',
            'Re-running AI analysis on a single proof.',
            'Reading the AI\'s reasoning text on flagged proofs.',
            'Customer-side experience after approval or rejection.',
        ),
    ),

    'configuration' => array(
        'kicker' => 'Configuration',
        'title'  => 'Per-method settings, multi-account rotation, branding',
        'sub'    => 'Set up handles for each P2P method, add multiple accounts with rotation, upload QR codes, and adjust the customer-facing payment page.',
        'body'   => <<<'HTML'
<h2>Per-method enable</h2>
<p>Each P2P rail (Venmo, Cash App, PayPal, Zelle) has its own enable toggle on the settings page. Enabling a method requires a non-empty handle; saving with an empty handle re-disables the toggle and surfaces a notice. Disable any method you do not actually accept; customers will see only the enabled options on the payment page.</p>

<h2>Personal vs business profiles for Venmo and Cash App</h2>
<p>Venmo and Cash App differentiate personal and business profiles. The handle field accepts either, but the <em>Profile type</em> dropdown next to it changes downstream behavior:</p>
<ul>
    <li><strong>Personal profile.</strong> No fees on the receiving side, no in-app refund button. Acceptable for low volumes; risky over time because Venmo and Cash App both reserve the right to flag personal accounts that look like commercial activity.</li>
    <li><strong>Business profile.</strong> 1.9% receiving fee on Venmo Business and 2.75% on Cash App for Business at the time of writing, plus the in-app refund button. Recommended for any meaningful volume.</li>
</ul>
<p>PayPal handles only the email field; Friends and Family is implied for the rail. Zelle has no profile distinction in the app, so the field is just the email or phone number on file with your bank.</p>

<h2>Multi-account rotation</h2>
<p>For each enabled method you can add up to three handles. Rotation strategies:</p>
<ul>
    <li><strong>LRU (least recently used).</strong> Each new order goes to the handle that has not been used in the longest time. Smooths the load across accounts.</li>
    <li><strong>Round-robin.</strong> Strict cycle: handle 1, 2, 3, 1, 2, 3. Predictable, useful when you want each account to get exactly its share.</li>
</ul>
<p>Use this when you are running a launch and one account is bumping against P2P throughput limits, or when you operate multiple LLCs and split incoming payments across them.</p>

<h2>QR code upload</h2>
<p>One PNG or SVG per method, uploaded into the field next to the handle. Render at 512x512 or larger; we downscale on the customer page. QR codes auto-hide on screens 720px wide or smaller since the customer is already on their phone.</p>
<p>Generate the QR codes inside the P2P app's own profile screen, not third-party tools. Apps periodically rotate their QR formats and a third-party-generated QR can stop working overnight.</p>

<h2>Customer payment page branding</h2>
<p>Three knobs:</p>
<ul>
    <li><strong>Accent color.</strong> Default is Pipe Pay blue (<code>#1336a8</code>). Override with your brand color via the color picker. The customer-facing buttons and the order-amount accent use this color.</li>
    <li><strong>Store logo.</strong> Upload via the standard WordPress media picker. Renders at the top of the customer payment page, scaled to 120px tall, with white space respected.</li>
    <li><strong>Custom message.</strong> Optional one-line note shown above the payment instructions. Use it for things like "Send within 30 minutes to keep your order active" or "Use the order number in the payment note."</li>
</ul>

<h2>Payment-method label on WooCommerce checkout</h2>
<p>The label customers see on WooCommerce's checkout page (not the post-checkout payment page) is editable from the same settings screen. Default is "Pipe Pay - pay with Venmo, Cash App, PayPal, or Zelle." Shorten or rebrand as needed; this is the line that competes for attention against any other gateways you have enabled.</p>
HTML,
        'topics' => array(
            'Per-method enable / disable.',
            'Personal vs business profiles for Venmo and Cash App.',
            'Multi-account rotation: LRU vs round-robin selection.',
            'QR code upload (one per method, hidden on phone-sized screens).',
            'Customer payment page branding: accent color, store logo, custom message.',
            'Payment-method label as it appears on WooCommerce checkout.',
        ),
    ),

    'order-lifecycle' => array(
        'kicker' => 'Order lifecycle',
        'title'  => 'Awaiting Proof, reminders, auto-cancel',
        'sub'    => 'How orders move through Pipe Pay\'s custom statuses, the reminder email cadence, and what auto-cancel does to stock.',
        'body'   => <<<'HTML'
<h2>Status flow</h2>
<p>Pipe Pay registers one custom WooCommerce order status: <code>wc-awaiting-proof</code>, shown as <strong>Awaiting Proof</strong>. It's assigned when the customer places the order, and the order stays in it until the customer uploads a screenshot, the auto-cancel timer fires, or you intervene manually.</p>
<p>When the customer uploads a screenshot and the AI grades it medium or low confidence (or the order amount is above the auto-approval cap), the order moves to <strong>Awaiting Approval</strong> for your manual review in the Proofs queue. From there, orders move to standard WooCommerce statuses: <em>Processing</em> on approval, <em>Cancelled</em> on rejection or auto-cancel.</p>

<h2>Reminder email cadence</h2>
<p>If a customer places an order in <em>Awaiting Proof</em> and then bounces away from the payment page without uploading, Pipe Pay schedules three escalating reminder emails:</p>
<ul>
    <li><strong>5 minutes.</strong> Friendly nudge with the payment page link. Most customers come back here.</li>
    <li><strong>20 minutes.</strong> More direct. Same link, plus a note that the order will be cancelled if the screenshot is not uploaded.</li>
    <li><strong>45 minutes.</strong> Final reminder. Last chance before auto-cancel.</li>
</ul>
<p>All three emails are templated through WooCommerce's email system, so any customizations you have made to your transactional email styling apply automatically.</p>

<h2>Auto-cancel at 60 minutes</h2>
<p>Sixty minutes after order placement, if no screenshot has been uploaded, Pipe Pay:</p>
<ol>
    <li>Flips the order to <em>Cancelled</em>.</li>
    <li>Restores stock for every line item back to your inventory.</li>
    <li>Sends the customer a cancellation email noting that the order can be reattempted from the store.</li>
    <li>Writes a note to the order log: <code>Auto-cancelled: no screenshot uploaded within 60 minutes.</code></li>
</ol>

<h2>Customer-facing cancellation email</h2>
<p>The cancellation email is intentionally non-judgmental. It says the order timed out, the items are back in stock if they want to try again, and links them to the store. There is no scolding or "your account has been flagged" language. Most customers who timed out either got distracted or had a P2P-app issue and will reattempt.</p>

<h2>Adjusting the auto-cancel window and reminder cadence</h2>
<p>Both are configurable from the same screen: <code>WooCommerce -> Settings -> Payments -> Pipe Pay -> Manage</code>, under the <em>Reminders &amp; Expiry</em> section. The auto-cancel window defaults to 60 minutes; the three reminder times default to 5, 20, and 45 minutes after order placement.</p>
<p>We do not recommend setting auto-cancel below 30 minutes; customers occasionally take 20+ minutes to find their phone and screenshot a payment, and you do not want to cancel an order that was about to be paid.</p>
HTML,
        'topics' => array(
            'Awaiting Proof status: when WooCommerce assigns it, what triggers the transition out.',
            'On Hold for manual review: which orders end up here.',
            'Reminder email cadence: 5 / 20 / 45 minutes after checkout.',
            'Auto-cancel at 60 minutes: stock restoration, customer email, order log entry.',
            'Customer-facing cancellation email content.',
            'How to extend or shorten the auto-cancel window.',
        ),
    ),

    'refunds' => array(
        'kicker' => 'Refunds',
        'title'  => 'Issue a refund inside or outside Pipe Pay',
        'sub'    => 'Money movement happens in your P2P app. Pipe Pay marks the order refunded in WooCommerce; the actual reverse payment is on you.',
        'body'   => <<<'HTML'
<h2>The short version</h2>
<p>Pipe Pay does not move money. P2P apps do not expose programmatic refund APIs, so the actual money movement to refund a customer happens inside your Venmo, Cash App, PayPal, or Zelle account. Pipe Pay's role is to mark the order as refunded in WooCommerce so your records line up.</p>

<h2>Venmo and Cash App business profiles</h2>
<p>If you took the payment on a business profile, the in-app interface gives you a refund button on the original transaction. Use it. Settlement timing is typically 5 to 10 business days, slightly faster on Cash App. After you trigger the in-app refund, mark the order refunded in WooCommerce (see below) so the order status reflects reality.</p>

<h2>Personal Venmo, Cash App, PayPal Friends and Family, Zelle</h2>
<p>Personal accounts on these rails do not have a refund button. To refund a customer, you send them a new payment in the reverse direction for the refund amount. Open your P2P app, send the customer the refund amount, screenshot the confirmation for your own records, and mark the WooCommerce order refunded.</p>
<p>For Zelle specifically, the customer's email or phone you sent the original payment from is what you send the refund to. Most banks complete Zelle reversals in seconds.</p>

<h2>Marking an order refunded in WooCommerce</h2>
<p>After the money has moved, open the order in <code>WooCommerce -> Orders</code>. Click <em>Refund</em> in the order actions panel. WooCommerce will prompt for the refund amount; enter it and click <em>Refund manually</em>. (Do not click "Refund via [gateway name]" - that would attempt to call a refund API that does not exist for P2P rails.) The order status moves to <em>Refunded</em>; if it was a partial refund the status stays <em>Processing</em> with a refund line item recorded.</p>

<h2>Customer notification</h2>
<p>WooCommerce sends a refund notification email automatically. Pipe Pay does not modify or replace it. The default email tells the customer that a refund has been processed; it does not contain the actual reverse payment proof. If you want to include screenshot proof of the reverse payment, attach it to a manual reply email instead of relying on the automatic transactional one.</p>

<h2>Partial refunds</h2>
<p>Same flow: send the customer a partial-amount payment in your P2P app, then record a partial refund in WooCommerce. WooCommerce keeps a running total per order so multiple partial refunds add up correctly. The order status only flips to <em>Refunded</em> when the cumulative refund equals the original total.</p>

<h2>Customer-initiated disputes</h2>
<p>A dispute is different from a refund: the customer raises it with the payment platform (Venmo, PayPal, their bank), not with you. P2P rails handle disputes very differently from card payments, and the customer's protection depends on which method they used and whether they sent the payment via a business or personal route.</p>

<h3>Venmo Business and Cash App for Business</h3>
<p>Both have a buyer-protection program for goods marked as purchases (not transfers to friends). The customer can open a dispute in the app within a fixed window (Venmo: 180 days, Cash App: typically 60 days). The platform notifies you, freezes the disputed amount, and asks for evidence within ~10 days. <strong>Your evidence:</strong> the WooCommerce order record (timestamp, line items, customer email), the screenshot they uploaded, the AI verification result and confidence score, and any post-order communication. Pipe Pay keeps all of this together on the order detail page, so a single export of the order page is usually sufficient.</p>

<h3>PayPal Goods &amp; Services</h3>
<p>Buyer protection is the strongest here. The customer can open a dispute through PayPal's Resolution Center for up to 180 days. PayPal mediates. Same evidence package as above. PayPal sides with the buyer in roughly 60% of cases by default, so the more documentation you provide up front the better. Mention specifically in your evidence that Pipe Pay independently verified the payment screenshot at the time of order and store that confidence score.</p>

<h3>Personal Venmo, Cash App, PayPal Friends &amp; Family, Zelle</h3>
<p>Personal rails do not have buyer protection. The customer's only recourse is to ask their bank to reverse the underlying ACH transfer, which banks typically refuse for P2P-to-P2P transfers because no fraud or unauthorized access occurred. Zelle is the most protective from the merchant's side - bank reversals are rare. Personal Venmo and Cash App reversals require the customer to file a fraud claim, which they would have to misrepresent since the transfer was authorized. This is one of the structural reasons P2P rails work for merchants in high-risk verticals.</p>

<h3>If a dispute is filed: what to do</h3>
<ol>
    <li><strong>Don't issue a refund first.</strong> If you refund and then lose the dispute, you pay twice. Respond to the dispute with evidence; if the platform sides with you, the freeze is released. If they side with the buyer, the disputed amount is returned to the customer automatically.</li>
    <li><strong>Pull the order's Pipe Pay verification record.</strong> WP Admin -> WooCommerce -> Orders -> [order]. The right sidebar shows the AI verification result, confidence score, extracted amount and recipient, and the screenshot the customer uploaded. Right-click -> Print -> Save as PDF gives you a single-file evidence package.</li>
    <li><strong>Reply within the platform's evidence window.</strong> Most platforms give you 7-10 days. Late responses are typically auto-resolved against the merchant.</li>
    <li><strong>If you lose the dispute, mark the order refunded in WooCommerce.</strong> Same flow as the refund section above. The funds are already reversed; the WC status just needs to reflect reality.</li>
</ol>

<h3>Reducing dispute risk up front</h3>
<p>Three practical defenses, none of which are Pipe Pay-specific:</p>
<ul>
    <li><strong>Clear payment instructions on the checkout page.</strong> The customer should know before they hit Place Order that they're paying via Venmo / Cash App / etc. and uploading a screenshot. Pipe Pay's post-checkout page handles this, but consider adding a one-sentence reminder in your shipping or fulfillment emails.</li>
    <li><strong>Same-day fulfillment for digital goods, traceable shipping for physical.</strong> "Item never arrived" is the most common dispute reason. Same-day or fast fulfillment cuts this category in half. For physical goods, always use a tracking-enabled carrier even on small orders.</li>
    <li><strong>Reply to support requests fast.</strong> Customers who feel ignored escalate to disputes. Pipe Pay's order confirmation includes your support email; respond within 24 hours and most "almost-disputes" resolve as refunds or replacements without the platform ever being involved.</li>
</ul>
HTML,
        'topics' => array(
            'Venmo and Cash App business profiles: in-app refund button, settlement timing.',
            'Personal Venmo, Cash App, PayPal F&F, and Zelle: send a new payment in reverse.',
            'Marking an order refunded in WooCommerce after the money is sent.',
            'Customer notification email: what Pipe Pay sends, what it does not.',
            'Partial refunds and how to record them.',
            'Customer-initiated disputes via Venmo / Cash App / PayPal: evidence and response procedure.',
            'Why personal P2P rails have no buyer-protection program (and what that means for your dispute risk).',
            'Reducing dispute risk up front: clear payment instructions, fast fulfillment, responsive support.',
        ),
    ),

    'security' => array(
        'kicker' => 'Security',
        'title'  => 'How proofs are stored and viewed',
        'sub'    => 'Payment proofs live outside the web-accessible directory. Viewing routes through an authenticated proxy gated by manage_woocommerce.',
        'body'   => <<<'HTML'
<h2>Storage location</h2>
<p>By default, payment proofs are stored at <code>wp-content/private-pipepay-proofs/</code>. If that path is not writable, Pipe Pay falls back to <code>wp-content/uploads/pipepay-proofs-private/</code>. Either way, the directory and all subdirectories are blocked from public access via three layered files:</p>
<ul>
    <li><code>.htaccess</code> with <code>Deny from all</code> for Apache hosts.</li>
    <li><code>web.config</code> with <code>&lt;deny users="*" /&gt;</code> for IIS.</li>
    <li><code>index.php</code> that just calls <code>exit;</code> as a fallback.</li>
</ul>
<p>None of these files alone is sufficient on every host; together they cover Apache, IIS, and the case where neither configuration file is honored.</p>

<h2>Custom storage volume</h2>
<p>To move proofs off the webroot disk entirely, define a constant in <code>wp-config.php</code>:</p>
<pre><code>define( 'PIPEPAY_PROOFS_PATH', '/mnt/private/pipepay-proofs' );</code></pre>
<p>The path must be writable by the PHP process owner (<code>www-data</code> on most stacks). Pipe Pay creates the directory if it does not exist and writes the same triple denial-file layer in case anything ever exposes the path.</p>

<h2>Random filenames</h2>
<p>Every uploaded file is renamed to a 32-character lowercase hex string before being written to disk - 128 bits of entropy, 1.7 followed by 38 zeros possible names. Filenames cannot be guessed or enumerated, and the original filename (which often contains the customer's name) is discarded.</p>

<h2>Capability gating</h2>
<p>Viewing a proof inside WordPress goes through an authenticated proxy endpoint, not a direct file URL. The endpoint checks <code>current_user_can( 'manage_woocommerce' )</code> on every request. If you want to give a support staffer access to the Proofs queue without making them a full admin, add the <code>shop_manager</code> role to their account; it has <code>manage_woocommerce</code> by default.</p>

<h2>Auto-expiration</h2>
<p>Proofs are deleted on a schedule. Default retention is 90 days from upload, configurable from 0 (delete immediately after AI verification) to 10 years. Set via the dropdown in plugin settings.</p>
<p>The deletion job runs daily via Action Scheduler. It removes both the file from disk and the database row that referenced it. Order metadata (amount, customer, AI confidence, AI reasoning) is retained on the order itself; only the screenshot file goes away.</p>

<h2>Rate limits</h2>
<p>Three rate-limit layers are on by default:</p>
<ul>
    <li><strong>Per IP, per order.</strong> 10 valid uploads per hour, scoped to a single (IP, order) pair. Stops a single attacker spamming uploads against one order.</li>
    <li><strong>Per IP brute-force.</strong> 50 attempts per hour against unknown / invalid order keys before the IP is blocked at the endpoint. Stops enumeration of order keys.</li>
    <li><strong>Per order lifetime.</strong> 5 upload attempts per order. After the cap, the order can no longer accept uploads and the customer is asked to contact you.</li>
</ul>
HTML,
        'topics' => array(
            'Storage location: default path, why it is outside webroot.',
            'Capability gating: manage_woocommerce as the access boundary.',
            'Random filenames: 32-character hex, 128 bits of entropy.',
            'Custom storage volume via wp-config constant.',
            'Triple denial-file layer on every storage root.',
            'Auto-expiration: configuring retention from 0 to 10 years.',
            'Per-IP per-order, per-IP brute-force, and per-order lifetime rate limits.',
        ),
    ),

    'license-management' => array(
        'kicker' => 'License management',
        'title'  => 'Activate, transfer, and renew',
        'sub'    => 'How license activation, deactivation, and renewal work, and what happens when a license lapses.',
        'body'   => <<<'HTML'
<h2>First-time activation</h2>
<p>Paste the license key into the first field of <code>WooCommerce -> Settings -> Payments -> Pipe Pay -> Manage</code> and save. The plugin calls back to <code>https://pipepay.app/?wc-api=wc-am-api</code>, which validates the key, registers your site URL against the activation count, and returns an <em>Active</em> status. If you do not see the active state within a few seconds, your firewall may be blocking outbound HTTPS to <code>pipepay.app</code>.</p>

<h2>Site limits per tier</h2>
<ul>
    <li><strong>Single Site</strong> ($299/year): 1 activation. One site URL at a time.</li>
    <li><strong>5 Sites</strong> ($499/year): up to 5 activations, any combination of staging and production sites.</li>
    <li><strong>Unlimited Sites</strong> ($999/year): no activation cap. Run it on as many sites as you want.</li>
</ul>
<p>The activation count is enforced server-side at <code>pipepay.app</code>. Trying to activate beyond your tier returns a clear error rather than silently allowing it.</p>

<h2>Deactivating a license to free up a slot</h2>
<p>If you are migrating between hosts or retiring a site, deactivate first so the slot is freed for a new activation. Two ways:</p>
<ul>
    <li>From the source site (preferred): <em>Settings -> Pipe Pay -> Deactivate license</em>. Sends a deactivation request to <code>pipepay.app</code>; the slot is freed immediately.</li>
    <li>From your account on <code>pipepay.app</code>: log in, go to <em>My Account -> Licenses</em>, expand the license, and click <em>Deactivate</em> next to the site URL. Useful when the source site is already gone or unreachable.</li>
</ul>

<h2>Renewal</h2>
<p>How renewal works depends on how you paid. Licenses bought by <strong>card</strong> are subscriptions that auto-renew through Stripe - cancel anytime from your billing portal. Licenses bought with a <strong>payment app</strong> (Venmo, Cash App, PayPal, Zelle) never auto-bill: you will receive renewal notices from <code>pipepay.app</code> as your annual term approaches expiration, each with a one-click renewal link that takes you straight to checkout with the same tier preselected, at the same price as the original purchase.</p>

<h2>If the license expires</h2>
<p>Plugin updates and support pause as soon as the license expires. For manually-renewed (payment-app) licenses there is then a <strong>30-day grace period</strong> during which the gateway keeps accepting orders; after that, Pipe Pay stops appearing at your checkout until you renew. Card-paid subscriptions stop offering Pipe Pay at checkout when the paid period ends. In both cases, orders already in progress always finish normally - the payment-proof upload, AI verification, and your approval queue keep working so no customer is ever stranded mid-purchase.</p>
<p>Renewing restores checkout, updates, and support: automatically within 24 hours, or immediately if you open <em>WP Admin &rarr; Pipe Pay &rarr; License</em> and click Activate. Existing data, settings, and historical orders all stay intact during a lapse; there is no data migration or reinstall step.</p>

<h2>Reactivating after a long lapse</h2>
<p>If your license has been expired for months and you renew, the plugin treats it like a fresh activation. Your settings, P2P handles, AI provider key, and historical order data all remain in the database; nothing is wiped during a lapse.</p>
HTML,
        'topics' => array(
            'First-time activation: where to paste the key.',
            'Site limits per tier (1 / 5 / unlimited) and how the count is enforced.',
            'Deactivating a license to free up a site slot.',
            'Renewal: notice email cadence, what the renewal email includes.',
            'Why renewal matters: keeps WooCommerce-compatibility patches, security updates, and support flowing so the install stays current with WP and WC releases.',
            'Reactivating after a lapsed renewal.',
        ),
    ),

    'troubleshooting' => array(
        'kicker' => 'Troubleshooting',
        'title'  => 'Common issues and where Pipe Pay\'s logs live',
        'sub'    => 'When uploads fail, when AI verification stalls, when an order seems stuck. What to check first and how to tell which side of the integration the problem is on.',
        'body'   => <<<'HTML'
<h2>Upload failures</h2>
<p>The most common cause is server-side file-size limits. iPhone HEIC screenshots routinely exceed 5MB. Check three settings:</p>
<ul>
    <li><strong>PHP:</strong> <code>upload_max_filesize</code> and <code>post_max_size</code> in <code>php.ini</code>. We recommend <code>32M</code> for both.</li>
    <li><strong>nginx:</strong> <code>client_max_body_size 32m;</code> in your server block.</li>
    <li><strong>Cloudflare:</strong> the free tier caps uploads at 100MB; you should not hit this with screenshots.</li>
</ul>
<p>If HEIC screenshots specifically fail, the Imagick PHP extension (with the HEIC delegate) is missing on your host. Pipe Pay rejects HEIC uploads with an inline error pointing the customer at common workarounds (re-saving as JPG/PNG on the phone, or switching the iPhone Camera setting from HEIC to JPG). <code>sudo apt install php-imagick</code> on Ubuntu and restart PHP-FPM - many shared hosts will need a support ticket to enable Imagick or its HEIC delegate.</p>

<h2>AI verification stalls</h2>
<p>Symptom: customer uploads screenshot, but the order sits in <em>Awaiting Proof</em> for minutes. Causes, in order of likelihood:</p>
<ul>
    <li><strong>API key invalid or out of credits.</strong> Run <em>Test AI Connection</em> in plugin settings; the failure message comes straight from the provider.</li>
    <li><strong>Provider rate limit.</strong> Particularly common right after a launch. Pipe Pay retries with exponential backoff; the order will eventually verify or land in manual review.</li>
    <li><strong>Provider timeout.</strong> Vision endpoints can take 5 to 30 seconds; we cap at 60. If the provider takes longer, the order goes to manual review and a note is logged.</li>
    <li><strong>Outbound HTTPS blocked.</strong> Some hardened hosts block egress; whitelist the provider's API hostname.</li>
</ul>

<h2>Stuck orders</h2>
<p>Orders can land in awkward states if WooCommerce, the AI call, and the customer browser disagree about what happened. Common stuck states:</p>
<ul>
    <li><strong>Awaiting Proof with screenshot uploaded but no AI result.</strong> AI call failed silently. Open the order, click <em>Re-run AI</em>, or just approve manually after eyeballing the screenshot.</li>
    <li><strong>Order paid in P2P but never placed in WooCommerce.</strong> Customer abandoned checkout before clicking <em>Place Order</em>. Refund them through your P2P app and ask them to redo checkout. Pipe Pay cannot create an order from just a payment.</li>
    <li><strong>Order in Processing but customer says they never paid.</strong> Investigate the AI reasoning text on the original proof; if the AI was wrong, refund and reach out. Use this as a calibration data point and possibly lower your auto-approval cap.</li>
</ul>

<h2>Where the logs live</h2>
<p>Pipe Pay logs to two places:</p>
<ul>
    <li><strong>WooCommerce -> Orders -> [order] -> Order notes.</strong> The primary log surface. Per-order events: status changes, AI confidence and reasoning, manual approve/reject, refund actions. Use this for support correspondence about a specific order.</li>
    <li><strong>PHP <code>error_log</code>.</strong> Unexpected failures (Imagick crashes, AI provider HTTP errors, license-server connectivity issues) land here. Path varies by host: typically <code>/var/log/php-fpm/error.log</code> or <code>wp-content/debug.log</code> if <code>WP_DEBUG_LOG</code> is enabled.</li>
</ul>
<p>The Kestrel licensing SDK additionally writes to <em>WooCommerce -> Status -> Logs</em> on failed license-server calls; those follow WooCommerce's default 30-day rotation.</p>

<h2>Telling whether an issue is in Pipe Pay, WooCommerce, or your AI provider</h2>
<ul>
    <li><strong>If the customer never reaches the payment page:</strong> the issue is in WooCommerce checkout or the gateway registration. Check <em>WooCommerce -> Settings -> Payments</em>; Pipe Pay should be enabled.</li>
    <li><strong>If the customer reaches the payment page but cannot upload:</strong> server-side upload limits (above).</li>
    <li><strong>If upload succeeds but verification stalls:</strong> AI provider side. Run <em>Test AI Connection</em>.</li>
    <li><strong>If verification completes but order does not transition:</strong> a WooCommerce hook conflict. Check the WC log file from above.</li>
</ul>

<h2>What to send if you contact support</h2>
<p>Email <a href="mailto:support@pipepay.app">support@pipepay.app</a> with: WordPress version, WooCommerce version, Pipe Pay version, AI provider name, the affected order ID(s), and copy of the relevant Order Notes from those orders. With those pieces of info we can usually diagnose without further round-trips.</p>
HTML,
        'topics' => array(
            'Upload failures: file-size limits, HEIC/Imagick missing, server permissions.',
            'AI verification stalls: provider timeouts, rate limits, API key issues.',
            'Stuck orders: orphaned Awaiting Proof, missing screenshot, status mismatches.',
            'Where Pipe Pay logs live and how to read them.',
            'Telling whether an issue is in Pipe Pay, in WooCommerce, or in your AI provider.',
            'What to send if you contact support.',
        ),
    ),

);

$d      = isset( $docs[ $slug ] ) ? $docs[ $slug ] : null;
$kicker = $d ? $d['kicker'] : 'Documentation';
$title  = $d ? $d['title']  : get_the_title();
$sub    = $d ? $d['sub']    : '';
$topics = $d && ! empty( $d['topics'] ) ? $d['topics'] : array();
$body   = $d && ! empty( $d['body'] )   ? $d['body']   : '';
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <a class="pp-doc-back" href="<?php echo esc_url( home_url( '/docs' ) ); ?>">&larr; Back to docs</a>
        <span class="pp-page-hero__kicker"><?php echo esc_html( $kicker ); ?></span>
        <h1 class="pp-page-title"><?php echo esc_html( $title ); ?></h1>
        <?php if ( $sub ) : ?>
            <p class="pp-page-hero__sub"><?php echo esc_html( $sub ); ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container pp-container--narrow">
        <?php if ( $body ) : ?>
            <article class="pp-prose pp-prose--doc">
                <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput - static authored content ?>
            </article>

            <p class="pp-doc-stub__back-link">
                <a href="<?php echo esc_url( home_url( '/docs' ) ); ?>">&larr; All docs topics</a>
            </p>
        <?php else : ?>
            <div class="pp-doc-stub">
                <span class="pp-doc-stub__label">Coming soon</span>
                <p class="pp-doc-stub__msg">The full article for this topic is being written. The outline below shows what it will cover. If you are mid-trial and stuck on this topic right now, email me and I will walk you through it directly.</p>
                <a class="pp-btn pp-btn--secondary" href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Email support</a>
            </div>

            <?php if ( ! empty( $topics ) ) : ?>
                <h2 class="pp-doc-outline-title">In the article</h2>
                <ul class="pp-doc-outline">
                    <?php foreach ( $topics as $t ) : ?>
                        <li><?php echo esc_html( $t ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p class="pp-doc-stub__back-link">
                <a href="<?php echo esc_url( home_url( '/docs' ) ); ?>">&larr; All docs topics</a>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
