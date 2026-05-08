<?php
/**
 * Template for /how-it-works.
 * Long-form pitch: the chasing-payments-by-hand pain, the founder story, the
 * traceable workflow, AI deep-dive, security primitives, onboarding shape.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$checkout_url = home_url( '/checkout/?add-to-cart=38' );
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">How it works</span>
        <h1 class="pp-page-title">From customer click to verified order in under a minute.</h1>
        <p class="pp-page-hero__sub">The full breakdown of what Pipe Pay does, why it exists, and the controls that keep it honest. If you're evaluating it, this is the page to read.</p>
    </div>
</section>

<!-- ============== PROBLEM ============== -->
<section class="pp-section pp-section--tight pp-problem">
    <div class="pp-container">
        <div class="pp-prose-cols">
            <div>
                <div class="pp-section-head">
                    <h2>Tracking payments by hand breaks quickly.</h2>
                </div>
                <div class="pp-prose">
                    <p>You already accept Venmo, Cash App, PayPal, or Zelle, because card processors won't onboard you, or because the fees are eating margin you can't spare. The hard part isn't the payment. The hard part is everything that happens after.</p>
                    <p>Customers pay through their P2P app and then either email you a screenshot, send the wrong amount, send the right amount with no order tag, or quietly never pay at all and assume you'll ship anyway. You cross-reference your P2P transaction history against your WooCommerce orders by hand. Each match is finishable on its own. The queue of them is not, because every new order produces a new one. The work is tedious, you hate it, so you put it off, and the queue grows while you avoid it.</p>
                    <p>Ghost orders sit in your queue. Untagged payments sit in your P2P account. You spot a discrepancy and disappear into your transaction history hunting for it. You ship something that was never paid. You fail to ship something that was. Every unmatched line is a thread you keep meaning to come back to.</p>
                    <p>Pipe Pay automates the workflow without changing how your customers pay.</p>
                </div>
            </div>
            <aside class="pp-side-panel">
                <span class="pp-side-panel__label">The pain</span>
                <ul class="pp-stat-list">
                    <li class="pp-stat-card">
                        <span class="pp-stat-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </span>
                        <div>
                            <div class="pp-stat-card__title">20 min lost</div>
                            <div class="pp-stat-card__sub">per discrepancy you chase</div>
                        </div>
                    </li>
                    <li class="pp-stat-card">
                        <span class="pp-stat-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
                        </span>
                        <div>
                            <div class="pp-stat-card__title">Ghost orders pile up</div>
                            <div class="pp-stat-card__sub">placed but never paid</div>
                        </div>
                    </li>
                    <li class="pp-stat-card">
                        <span class="pp-stat-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                        </span>
                        <div>
                            <div class="pp-stat-card__title">Untagged payments stall</div>
                            <div class="pp-stat-card__sub">paid but no matching order</div>
                        </div>
                    </li>
                    <li class="pp-stat-card">
                        <span class="pp-stat-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><polyline points="7 17 11 11 15 14 21 7"/></svg>
                        </span>
                        <div>
                            <div class="pp-stat-card__title">Linear scaling</div>
                            <div class="pp-stat-card__sub">manual workload grows with sales</div>
                        </div>
                    </li>
                </ul>
            </aside>
        </div>
    </div>
</section>

<!-- ============== STORY ============== -->
<section class="pp-section pp-section--alt pp-story">
    <div class="pp-container">
        <article class="pp-story-card">
            <span class="pp-eyebrow pp-story-eyebrow">From the founder</span>
            <h2 class="pp-story-title">Built by someone who needed it.</h2>
            <div class="pp-prose pp-story-prose">
                <p class="pp-story-lead">I run businesses in restricted verticals. Every traditional payment processor I tried either refused to onboard me, terminated me after a few months without explanation, or held my funds while my customers waited.</p>
                <p>I shifted to accepting Venmo, Cash App, PayPal, and Zelle directly. That solved the access problem and immediately created a new one: matching 50+ payments a day by hand to the right WooCommerce orders. I tried other plugins. None of them did the workflow correctly, and most of them assumed you were a low-risk Stripe-compatible merchant who'd just landed on the wrong page.</p>
                <p>I built Pipe Pay because I needed it. The AI verification piece exists because manual review at scale is a tax on growth. The security hardening exists because storing customer payment screenshots casually is unacceptable. The honest-about-its-limits framing exists because I've been burned by overpromising plugins, and I assume you have too.</p>
                <p>If you're in a similar position, this is the tool I wish I'd had two years ago.</p>
            </div>
        </article>
    </div>
</section>

<!-- ============== FEATURES ============== -->
<section class="pp-section pp-features-section">
    <div class="pp-container">
        <div class="pp-section-head pp-section-head--center">
            <h2>Traceable workflow from all angles</h2>
        </div>
        <div class="pp-features-grid">
            <?php
            $svg_sparkles = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8L4.3 10.7l5.8 1.9L12 18.4l1.9-5.8 5.8-1.9-5.8-1.9z"/><path d="M5 22v-4"/><path d="M19 22v-4"/><path d="M3 20h4"/><path d="M17 20h4"/></svg>';
            $svg_refresh  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>';
            $svg_gauge    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/></svg>';
            $svg_zap      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
            $svg_timer    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="2" x2="14" y2="2"/><line x1="12" y1="14" x2="15" y2="11"/><circle cx="12" cy="14" r="8"/></svg>';
            $svg_inbox    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>';
            $svg_phone    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2.5"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>';
            $svg_blocks   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>';

            $features = array(
                array( 'icon' => $svg_sparkles, 'title' => 'AI verification, your choice of provider.', 'body' => 'Plug in Claude, OpenAI, OpenRouter, or any OpenAI-compatible custom endpoint. Pipe Pay never sees the screenshots; they go straight from your store to the provider you configured.' ),
                array( 'icon' => $svg_refresh,  'title' => 'Multiple accounts per method, with rotation.', 'body' => 'Add up to three handles per payment method and rotate between them (LRU or round-robin). Useful when one account hits P2P throughput limits during a launch.' ),
                array( 'icon' => $svg_gauge,    'title' => 'Configurable auto-approval cap.', 'body' => 'Set a dollar threshold for auto-approval. Anything above lands in your manual review queue, no matter how confident the AI is.' ),
                array( 'icon' => $svg_zap,      'title' => 'Test AI Connection button.', 'body' => 'Verify your provider key without placing a real order. One click, real round-trip, plain pass/fail.' ),
                array( 'icon' => $svg_timer,    'title' => 'Per-order reminders + 60-minute auto-cancel.', 'body' => 'Three escalating reminder emails fire at 5, 20, and 45 minutes. If the customer hasn\'t uploaded by 60 minutes, the order auto-cancels and stock is restored.' ),
                array( 'icon' => $svg_inbox,    'title' => 'Admin Proofs review queue.', 'body' => 'Pending and History tabs. Approve, reject, or re-run AI analysis on demand. Built for triage at volume, not one order at a time.' ),
                array( 'icon' => $svg_phone,    'title' => 'Mobile-aware UI.', 'body' => 'QR codes hide on phone-sized screens. HEIC iPhone screenshots auto-convert server-side via Imagick when available, with a graceful fallback.' ),
                array( 'icon' => $svg_blocks,   'title' => 'WordPress 6.0+, WooCommerce 8.0+.', 'body' => 'HPOS compatible. Works with both classic shortcode checkout and the block checkout. Standalone plugin, not theme-dependent.' ),
            );
            foreach ( $features as $f ) {
                echo '<div class="pp-feature">';
                echo '<span class="pp-feature-icon" aria-hidden="true">' . $f['icon'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
                echo '<h4>' . esc_html( $f['title'] ) . '</h4>';
                echo '<p>' . esc_html( $f['body'] ) . '</p>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
</section>

<!-- ============== AI DEEP-DIVE ============== -->
<section class="pp-section pp-section--alt pp-ai-section">
    <div class="pp-container">
        <div class="pp-ai">
            <div class="pp-ai-prose">
                <h2>AI verifies each payment. You only see what needs your attention.</h2>
                <p>The AI checks the things you'd check by hand: that the amount matches the order total, that the recipient handle matches the one you configured, and that the screenshot doesn't show signs of editing. It also flags app/method mismatches and implausible amounts.</p>
                <p>Confidence is graded as <strong>high</strong>, <strong>medium</strong>, or <strong>low</strong>. High-confidence verifications auto-approve and the order moves straight to Processing. Medium and low land in the Proofs queue. If any fraud signal trips, confidence is capped at <em>medium</em> no matter how clean the rest of the screenshot looks.</p>
                <p>You stay in control of the threshold. Set a configurable auto-approval cap. Use the <em>Test AI Connection</em> button to confirm the provider integration works before you go live.</p>
                <p>We don't lock you into one AI vendor. Pipe Pay supports <span class="pp-mono">Claude</span>, <span class="pp-mono">OpenAI</span>, <span class="pp-mono">OpenRouter</span>, and any OpenAI-compatible custom endpoint. Bring your own key, swap providers when pricing or quality changes, or run against a self-hosted model.</p>
            </div>

            <div class="pp-wpadmin">
                <div class="pp-wpadmin__head">
                    <h3 class="pp-wpadmin__title">Proofs review queue<span class="pp-pulse" aria-label="Live"><span class="pp-pulse__dot" aria-hidden="true"></span><span>live</span></span></h3>
                    <nav class="pp-wpadmin__tabs" aria-label="Queue filters">
                        <a href="#" class="pp-wpadmin__tab pp-wpadmin__tab--active" aria-current="page" onclick="return false;">Pending <span>(5)</span></a>
                        <a href="#" class="pp-wpadmin__tab" onclick="return false;">History</a>
                    </nav>
                </div>

                <div class="pp-wpadmin__filters">
                    <label class="pp-wpadmin__search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="search" placeholder="Search by order, customer, or amount" disabled>
                    </label>
                    <label class="pp-wpadmin__select">
                        <span>Status</span>
                        <select disabled>
                            <option>All confidence</option>
                        </select>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </label>
                </div>

                <table class="pp-wpadmin__table">
                    <thead>
                        <tr>
                            <th class="pp-wpadmin__col-thumb"><span class="pp-wpadmin__sr">Screenshot</span></th>
                            <th>Order</th>
                            <th>Amount</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th>Confidence</th>
                            <th class="pp-wpadmin__col-actions"><span class="pp-wpadmin__sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="pp-thumb pp-thumb--venmo" aria-hidden="true"></span></td>
                            <td><a href="#" onclick="return false;">#1247</a></td>
                            <td class="pp-wpadmin__num">$127.50</td>
                            <td><!--email_off-->sarah.miller@example.com<!--/email_off--></td>
                            <td><span class="pp-method pp-method--venmo">Venmo</span></td>
                            <td><span class="pp-conf pp-conf--high">High</span></td>
                            <td class="pp-wpadmin__actions">
                                <button type="button" class="pp-act pp-act--ok">Approve</button>
                                <button type="button" class="pp-act">Reject</button>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="pp-thumb pp-thumb--cash" aria-hidden="true"></span></td>
                            <td><a href="#" onclick="return false;">#1246</a></td>
                            <td class="pp-wpadmin__num">$42.00</td>
                            <td><!--email_off-->jdang91@gmail.com<!--/email_off--></td>
                            <td><span class="pp-method pp-method--cash">Cash App</span></td>
                            <td><span class="pp-conf pp-conf--med">Medium</span></td>
                            <td class="pp-wpadmin__actions">
                                <button type="button" class="pp-act pp-act--ok">Approve</button>
                                <button type="button" class="pp-act">Reject</button>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="pp-thumb pp-thumb--paypal" aria-hidden="true"></span></td>
                            <td><a href="#" onclick="return false;">#1245</a></td>
                            <td class="pp-wpadmin__num">$310.00</td>
                            <td><!--email_off-->mforge@mountainforge.co<!--/email_off--></td>
                            <td><span class="pp-method pp-method--paypal">PayPal F&amp;F</span></td>
                            <td><span class="pp-conf pp-conf--med">Medium</span></td>
                            <td class="pp-wpadmin__actions">
                                <button type="button" class="pp-act pp-act--ok">Approve</button>
                                <button type="button" class="pp-act">Reject</button>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="pp-thumb pp-thumb--zelle" aria-hidden="true"></span></td>
                            <td><a href="#" onclick="return false;">#1244</a></td>
                            <td class="pp-wpadmin__num">$89.99</td>
                            <td><!--email_off-->alex@cleanstack.io<!--/email_off--></td>
                            <td><span class="pp-method pp-method--zelle">Zelle</span></td>
                            <td><span class="pp-conf pp-conf--low">Low</span></td>
                            <td class="pp-wpadmin__actions">
                                <button type="button" class="pp-act pp-act--ok">Approve</button>
                                <button type="button" class="pp-act">Reject</button>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="pp-thumb pp-thumb--venmo" aria-hidden="true"></span></td>
                            <td><a href="#" onclick="return false;">#1243</a></td>
                            <td class="pp-wpadmin__num">$215.50</td>
                            <td><!--email_off-->tworth@example.com<!--/email_off--></td>
                            <td><span class="pp-method pp-method--venmo">Venmo</span></td>
                            <td><span class="pp-conf pp-conf--high">High</span></td>
                            <td class="pp-wpadmin__actions">
                                <button type="button" class="pp-act pp-act--ok">Approve</button>
                                <button type="button" class="pp-act">Reject</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <footer class="pp-wpadmin__foot">
                    <span>5 items</span>
                    <nav class="pp-wpadmin__pager" aria-label="Pagination">
                        <button type="button" disabled>&lsaquo;</button>
                        <span>1 of 1</span>
                        <button type="button" disabled>&rsaquo;</button>
                    </nav>
                </footer>
            </div>
        </div>
    </div>
</section>

<!-- ============== SECURITY ============== -->
<section class="pp-section pp-section--snug pp-security">
    <div class="pp-container">
        <div class="pp-prose-cols">
            <div>
                <div class="pp-section-head">
                    <h2>Customer screenshots stay private.</h2>
                </div>
                <div class="pp-prose">
                    <p>Payment proofs are stored outside the web-accessible directory, with a triple denial-file layer on every storage root. There is no public URL for any screenshot, ever. Viewing a proof in the admin goes through an authenticated proxy endpoint gated by the <code>manage_woocommerce</code> capability.</p>
                    <p>Every uploaded file gets a <span class="pp-num">32</span>-character hex random filename (<span class="pp-num">128</span> bits of entropy), so screenshots can't be guessed or enumerated. A <code>wp-config.php</code> constant lets you store proofs on a separate volume if you want them off the main webroot disk entirely.</p>
                    <p>Auto-expiration is configurable. The default retention is <span class="pp-num">90</span> days; you can set it anywhere from <span class="pp-num">0</span> to <span class="pp-num">10</span> years. Per-IP abuse rate limiting, per-customer success rate limiting, and a per-order lifetime upload cap are all on by default.</p>
                    <div class="pp-callout">
                        <strong>On AI provider data handling</strong>
                        Screenshots are sent to whichever AI provider you configure. Pipe Pay does not see, store, or have access to those screenshots in transit. If you handle particularly sensitive data, review your chosen provider's data retention policy, or point Pipe Pay at a self-hosted or zero-retention OpenAI-compatible endpoint.
                    </div>
                </div>
            </div>
            <aside class="pp-side-panel">
                <span class="pp-side-panel__label">Primitives</span>
                <ul class="pp-stat-list">
                    <li class="pp-stat-card">
                        <span class="pp-stat-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <div>
                            <div class="pp-stat-card__title">Outside webroot</div>
                            <div class="pp-stat-card__sub">no public URL exists for any proof</div>
                        </div>
                    </li>
                    <li class="pp-stat-card">
                        <span class="pp-stat-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>
                        </span>
                        <div>
                            <div class="pp-stat-card__title">128 bits entropy</div>
                            <div class="pp-stat-card__sub">32-char hex random filenames</div>
                        </div>
                    </li>
                    <li class="pp-stat-card">
                        <span class="pp-stat-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </span>
                        <div>
                            <div class="pp-stat-card__title">Capability-gated</div>
                            <div class="pp-stat-card__sub"><code>manage_woocommerce</code> only</div>
                        </div>
                    </li>
                    <li class="pp-stat-card">
                        <span class="pp-stat-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </span>
                        <div>
                            <div class="pp-stat-card__title">90-day retention</div>
                            <div class="pp-stat-card__sub">configurable from 0 to 10 years</div>
                        </div>
                    </li>
                    <li class="pp-stat-card">
                        <span class="pp-stat-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        </span>
                        <div>
                            <div class="pp-stat-card__title">Triple rate limit</div>
                            <div class="pp-stat-card__sub">per-IP, per-customer, per-order</div>
                        </div>
                    </li>
                </ul>
            </aside>
        </div>
    </div>
</section>

<!-- ============== ONBOARDING ============== -->
<section class="pp-section pp-section--tight pp-section--alt pp-onboarding">
    <div class="pp-container">
        <div class="pp-section-head pp-section-head--center">
            <h2>Live in about 10 minutes. No LLC required.</h2>
        </div>
        <div class="pp-prose" style="margin-left:auto;margin-right:auto;text-align:center;">
            <p>Setup is short. Enter your license key. Add the handles for whichever P2P methods you want to accept. Optionally upload your QR codes. Paste in an AI provider API key, or skip it and review uploads manually. Then place a test order on your own store to confirm the full flow.</p>
            <p>You don't need an EIN. You don't need a merchant account. You don't need to hand your tax ID to a processor and wait two weeks for verification. The Venmo and Cash App accounts you already have are enough to start. Validate the idea with real customers and real money first; incorporate once revenue justifies it.</p>
            <p><strong>If you can configure a Stripe gateway, you can configure Pipe Pay in less time.</strong></p>
        </div>
    </div>
</section>

<!-- ============== FINAL CTA ============== -->
<section class="pp-section pp-section--snug pp-section--blue pp-final-cta">
    <div class="pp-container">
        <div class="pp-final-cta__mark" aria-hidden="true">
            <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                <rect x="2"  y="26" width="8" height="12" fill="#fff"/>
                <rect x="9"  y="22" width="3" height="20" fill="#fff"/>
                <rect x="54" y="26" width="8" height="12" fill="#fff"/>
                <rect x="52" y="22" width="3" height="20" fill="#fff"/>
                <path d="M 12 32 L 32 14" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/>
                <path d="M 12 32 L 22 32" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/>
                <path d="M 12 32 L 32 50" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/>
                <path d="M 52 32 L 32 14" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/>
                <path d="M 52 32 L 42 32" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/>
                <path d="M 52 32 L 32 50" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/>
                <circle cx="12" cy="32" r="1.8" fill="#fff"/>
                <circle cx="52" cy="32" r="1.8" fill="#fff"/>
                <circle cx="32" cy="32" r="10.5" fill="#fff"/>
                <circle cx="32" cy="32" r="8.8"  fill="#1336a8"/>
                <text x="32" y="38" text-anchor="middle" font-family="Manrope, Inter, sans-serif" font-size="17" font-weight="800" fill="#fff">$</text>
            </svg>
        </div>
        <h2>Ready to stop chasing payments by hand?</h2>
        <p>Start your 7-day free trial. No card required.</p>
        <a class="pp-btn pp-btn--inverse pp-btn--lg" href="<?php echo esc_url( $checkout_url ); ?>">Start 7-day free trial &rarr;</a>
    </div>
</section>

<?php get_footer(); ?>
