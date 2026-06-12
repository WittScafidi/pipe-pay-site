<?php
/**
 * Template for /pricing.
 * Pricing cards, what Pipe Pay isn't, FAQ, final CTA.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$checkout_url    = home_url( '/checkout/?add-to-cart=38' );

// Trial CTAs from a tier card carry an `intent` param so the customer's tier
// preference is captured at signup and surfaces again at trial conversion.
// `intent` is validated to {34, 35, 36} by the woocommerce_add_cart_item_data
// filter in functions.php - mismatched values are dropped silently.
$trial_intent_single = home_url( '/checkout/?add-to-cart=38&intent=34' );
$trial_intent_five   = home_url( '/checkout/?add-to-cart=38&intent=35' );
$trial_intent_unlim  = home_url( '/checkout/?add-to-cart=38&intent=36' );
// Direct-purchase URLs - skip the trial and add the paid tier straight to the
// cart. page-checkout.php is cart-aware and renders the "Complete your
// purchase" hero when these products are present.
$buy_single      = home_url( '/checkout/?add-to-cart=34' );
$buy_five        = home_url( '/checkout/?add-to-cart=35' );
$buy_unlim       = home_url( '/checkout/?add-to-cart=36' );
$refund_url      = home_url( '/refund-policy' );

// Monthly subscription buy URLs. All purchases funnel through the WC checkout
// page, which embeds the Stripe card form inline (monthly = card-only; annual
// offers a card-vs-payment-app chooser). See page-checkout.php.
$monthly_buy_single = home_url( '/checkout/?add-to-cart=526' );
$monthly_buy_five   = home_url( '/checkout/?add-to-cart=527' );
$monthly_buy_unlim  = home_url( '/checkout/?add-to-cart=528' );
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">Pricing</span>
        <h1 class="pp-page-title">Three tiers. Pay monthly or annual.</h1>
        <p class="pp-page-hero__sub">Pick the license size that matches the number of WooCommerce stores you run. Annual saves up to 35% and includes a 7-day free trial. Monthly is cancel-anytime, no trial — pay only for what you use.</p>
    </div>
</section>

<!-- ============== PRICING CARDS ============== -->
<section id="tiers" class="pp-section pp-section--tight pp-pricing">
    <div class="pp-container">
        <div class="pp-billing-toggle" role="group" aria-label="Choose billing period">
            <button type="button" class="pp-billing-toggle__btn pp-billing-toggle__btn--active" aria-pressed="true" data-billing="annual">Annual <span class="pp-billing-toggle__save">save up to 35%</span></button>
            <button type="button" class="pp-billing-toggle__btn" aria-pressed="false" data-billing="monthly">Monthly</button>
        </div>
        <p class="pp-billing-toggle__note" data-billing-show="annual">Annual includes a 7-day free trial. Buying now? Pay by card (auto-renews) or a payment app (renew manually) &mdash; choose at checkout.</p>
        <p class="pp-billing-toggle__note" data-billing-show="monthly" hidden>Monthly is cancel-anytime in your Stripe billing portal. No trial; pay only for what you use.</p>

        <div class="pp-pricing-grid">
            <div class="pp-pricing-card">
                <svg class="pp-tier-illustration" viewBox="0 0 120 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><rect x="22" y="12" width="76" height="56" rx="7" fill="#fff" stroke="#1336a8" stroke-width="2"/><circle cx="30" cy="22" r="2" fill="#1336a8"/><circle cx="38" cy="22" r="2" fill="#1336a8" opacity="0.45"/><circle cx="46" cy="22" r="2" fill="#1336a8" opacity="0.22"/><line x1="22" y1="30" x2="98" y2="30" stroke="#1336a8" stroke-width="1" opacity="0.18"/><circle cx="60" cy="48" r="11" fill="#1336a8"/><path d="M54.5 48 l4 4 l7.5 -8" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <h3>Single Site</h3>
                <p class="pp-price-detail">For one WooCommerce store.</p>
                <div data-billing-show="annual">
                    <div class="pp-price">$299<small></small></div>
                    <div class="pp-price-period">per year</div>
                </div>
                <div data-billing-show="monthly" hidden>
                    <div class="pp-price">$35<small></small></div>
                    <div class="pp-price-period">per month, cancel anytime</div>
                </div>
                <ul class="pp-pricing-features">
                    <li>1 site activation</li>
                    <li>Plugin updates included</li>
                    <li>Email support included</li>
                    <li data-billing-show="annual">7-day free trial, no card required</li>
                    <li data-billing-show="monthly" hidden>Cancel anytime in your billing portal</li>
                </ul>
                <a class="pp-btn pp-btn--secondary" data-billing-show="annual" href="<?php echo esc_url( $trial_intent_single ); ?>">Start 7-day trial</a>
                <a class="pp-btn pp-btn--ghost" data-billing-show="annual" href="<?php echo esc_url( $buy_single ); ?>">Buy now - skip the trial</a>
                <a class="pp-btn pp-btn--secondary" data-billing-show="monthly" href="<?php echo esc_url( $monthly_buy_single ); ?>" hidden>Subscribe monthly &mdash; $35/mo</a>
            </div>
            <div class="pp-pricing-card pp-pricing-card--featured">
                <span class="pp-pricing-ribbon">Most Popular</span>
                <svg class="pp-tier-illustration" viewBox="0 0 120 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><g fill="#fff" stroke="#1336a8" stroke-width="1.6"><rect x="15" y="12" width="26" height="22" rx="3"/><rect x="47" y="12" width="26" height="22" rx="3"/><rect x="79" y="12" width="26" height="22" rx="3"/><rect x="31" y="42" width="26" height="22" rx="3"/><rect x="63" y="42" width="26" height="22" rx="3"/></g><g fill="#1336a8"><circle cx="20" cy="17" r="1.3"/><circle cx="52" cy="17" r="1.3"/><circle cx="84" cy="17" r="1.3"/><circle cx="36" cy="47" r="1.3"/><circle cx="68" cy="47" r="1.3"/></g><g stroke="#1336a8" stroke-width="1.4" stroke-linecap="round" opacity="0.45"><line x1="19" y1="26" x2="37" y2="26"/><line x1="51" y1="26" x2="69" y2="26"/><line x1="83" y1="26" x2="101" y2="26"/><line x1="35" y1="56" x2="53" y2="56"/><line x1="67" y1="56" x2="85" y2="56"/></g></svg>
                <h3>5 Sites</h3>
                <p class="pp-price-detail">For agencies or multi-store owners.</p>
                <div data-billing-show="annual">
                    <div class="pp-price">$499<small></small></div>
                    <div class="pp-price-period">per year</div>
                </div>
                <div data-billing-show="monthly" hidden>
                    <div class="pp-price">$65<small></small></div>
                    <div class="pp-price-period">per month, cancel anytime</div>
                </div>
                <ul class="pp-pricing-features">
                    <li>Up to 5 site activations</li>
                    <li>Plugin updates included</li>
                    <li>Email support included</li>
                    <li>Remove &ldquo;Powered by Pipe Pay&rdquo; from your customer payment page</li>
                    <li data-billing-show="annual">7-day free trial, no card required</li>
                    <li data-billing-show="monthly" hidden>Cancel anytime in your billing portal</li>
                </ul>
                <a class="pp-btn pp-btn--primary" data-billing-show="annual" href="<?php echo esc_url( $trial_intent_five ); ?>">Start 7-day trial</a>
                <a class="pp-btn pp-btn--ghost" data-billing-show="annual" href="<?php echo esc_url( $buy_five ); ?>">Buy now - skip the trial</a>
                <a class="pp-btn pp-btn--primary" data-billing-show="monthly" href="<?php echo esc_url( $monthly_buy_five ); ?>" hidden>Subscribe monthly &mdash; $65/mo</a>
            </div>
            <div class="pp-pricing-card">
                <svg class="pp-tier-illustration" viewBox="0 0 120 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M22 40 C22 22, 48 22, 60 40 C72 58, 98 58, 98 40 C98 22, 72 22, 60 40 C48 58, 22 58, 22 40 Z" fill="none" stroke="#1336a8" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="29" y="35" width="12" height="10" rx="2" fill="#1336a8"/><rect x="79" y="35" width="12" height="10" rx="2" fill="#1336a8"/></svg>
                <h3>Unlimited Sites</h3>
                <p class="pp-price-detail">No activation cap. Run it everywhere.</p>
                <div data-billing-show="annual">
                    <div class="pp-price">$999<small></small></div>
                    <div class="pp-price-period">per year</div>
                </div>
                <div data-billing-show="monthly" hidden>
                    <div class="pp-price">$129<small></small></div>
                    <div class="pp-price-period">per month, cancel anytime</div>
                </div>
                <ul class="pp-pricing-features">
                    <li>Unlimited site activations</li>
                    <li>Plugin updates included</li>
                    <li>Email support included</li>
                    <li>Remove &ldquo;Powered by Pipe Pay&rdquo; from your customer payment page</li>
                    <li data-billing-show="annual">7-day free trial, no card required</li>
                    <li data-billing-show="monthly" hidden>Cancel anytime in your billing portal</li>
                </ul>
                <a class="pp-btn pp-btn--secondary" data-billing-show="annual" href="<?php echo esc_url( $trial_intent_unlim ); ?>">Start 7-day trial</a>
                <a class="pp-btn pp-btn--ghost" data-billing-show="annual" href="<?php echo esc_url( $buy_unlim ); ?>">Buy now - skip the trial</a>
                <a class="pp-btn pp-btn--secondary" data-billing-show="monthly" href="<?php echo esc_url( $monthly_buy_unlim ); ?>" hidden>Subscribe monthly &mdash; $129/mo</a>
            </div>
        </div>
        <p class="pp-pricing-fineprint" data-billing-show="annual">Each annual license includes 1 year of plugin updates and support. Renew annually to keep receiving WooCommerce-compatibility patches, security updates, and support &mdash; without renewal, your install falls behind each WP and WC release and eventually needs an update you can no longer get. Cancel anytime before the trial ends and you won't be charged. Once your trial converts to a paid license, all sales are final, no refunds. The 7-day trial is your evaluation window.</p>
        <p class="pp-pricing-fineprint" data-billing-show="monthly" hidden>Monthly subscriptions include plugin updates and support for as long as the subscription is active. Cancel anytime in your billing portal &mdash; your license stays active until the end of the current billing period, then expires. Annual saves up to 35% if you're committing to a full year; monthly is best for testing the waters or short-term needs. Monthly charges are non-refundable; cancel before the next billing date to avoid the next charge.</p>
    </div>
</section>

<!-- ============== IS THIS FOR ME? ============== -->
<section class="pp-section pp-fit-check">
    <div class="pp-container">
        <div class="pp-section-head">
            <h2>Is Pipe Pay for you?</h2>
            <p class="pp-subhead">The honest version. Worth $0 to find out inside the seven-day trial.</p>
        </div>
        <div class="pp-fit-grid">
            <div class="pp-fit-col pp-fit-col--yes">
                <h3 class="pp-fit-title pp-fit-title--yes">Yes, if any of these are you</h3>
                <ul class="pp-fit-list">
                    <li><svg class="pp-fit-icon pp-fit-icon--yes" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><polyline points="7 12.5 11 16.5 17 9" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Your processor doesn't fit your business - or you've outgrown what they'll cover.</span></li>
                    <li><svg class="pp-fit-icon pp-fit-icon--yes" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><polyline points="7 12.5 11 16.5 17 9" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg><span>You've had funds held longer than your cash flow can absorb, or the alternatives want a personal guarantee that you're not comfortable signing.</span></li>
                    <li><svg class="pp-fit-icon pp-fit-icon--yes" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><polyline points="7 12.5 11 16.5 17 9" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg><span>You're testing a new product idea and don't want to file an LLC, get an EIN, and hand your SSN to a processor before you know if it works.</span></li>
                    <li><svg class="pp-fit-icon pp-fit-icon--yes" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><polyline points="7 12.5 11 16.5 17 9" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg><span>You're paying meaningful Stripe or Square fees ($5K+ per year) and want them back.</span></li>
                    <li><svg class="pp-fit-icon pp-fit-icon--yes" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><polyline points="7 12.5 11 16.5 17 9" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg><span>You already accept Venmo, Cash App, PayPal F&amp;F, or Zelle and match orders to payments by hand.</span></li>
                </ul>
            </div>
            <div class="pp-fit-col pp-fit-col--no">
                <h3 class="pp-fit-title pp-fit-title--no">Probably not, if any of these are you</h3>
                <ul class="pp-fit-list">
                    <li><svg class="pp-fit-icon pp-fit-icon--no" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><line x1="8" y1="8" x2="16" y2="16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg><span>You need to accept credit cards directly. Pipe Pay does not process cards and never will.</span></li>
                    <li><svg class="pp-fit-icon pp-fit-icon--no" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><line x1="8" y1="8" x2="16" y2="16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg><span>You sell subscriptions and need recurring billing. Single-payment orders only in this version.</span></li>
                    <li><svg class="pp-fit-icon pp-fit-icon--no" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><line x1="8" y1="8" x2="16" y2="16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg><span>Your customers refuse to use P2P apps and you can't move them off card.</span></li>
                    <li><svg class="pp-fit-icon pp-fit-icon--no" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><line x1="8" y1="8" x2="16" y2="16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg><span>You need formal chargeback insurance. P2P rails reverse less often than card networks, but they don't carry an insurance product.</span></li>
                    <li><svg class="pp-fit-icon pp-fit-icon--no" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><line x1="8" y1="8" x2="16" y2="16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/><line x1="16" y1="8" x2="8" y2="16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg><span>You're on Shopify, BigCommerce, Magento, or Squarespace. Pipe Pay is a WooCommerce plugin; there are no ports to other platforms.</span></li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ============== WHAT IT ISN'T ============== -->
<section class="pp-section pp-section--alt pp-section--wide pp-isnt">
    <div class="pp-container">
        <div class="pp-isnt-grid">
            <aside class="pp-isnt-aside">
                <p class="pp-isnt-pullquote">We'd rather you have an honest picture than a sale.</p>
                <p class="pp-isnt-h2">What Pipe Pay isn't.</p>
            </aside>
            <ul class="pp-list">
                <li><strong>Not a card processor.</strong> No Visa, Mastercard, or Amex acceptance. Customers pay through their own P2P apps.</li>
                <li><strong>Not a Venmo or Cash App account creator.</strong> You configure your own existing accounts. We don't open accounts on your behalf.</li>
                <li><strong>Not a refund engine.</strong> Refunds happen merchant-to-customer outside Pipe Pay. With Venmo and Cash App business profiles you can use the in-app refund button. With personal Venmo, Cash App, PayPal Friends &amp; Family, and Zelle there is no refund function: you simply send a new payment in the reverse direction.</li>
                <li><strong>Not a regulatory workaround.</strong> Pipe Pay does not change what you can legally sell. You are responsible for what your store offers. We don't screen products, which, for a payment processor, is a feature.</li>
                <li><strong>Not for WooCommerce Subscriptions.</strong> Pipe Pay supports single-payment orders only in this version. Recurring billing is not supported.</li>
            </ul>
        </div>
    </div>
</section>

<!-- ============== FAQ ============== -->
<section class="pp-section pp-faq-section">
    <div class="pp-container">
        <div class="pp-section-head">
            <h2>Questions worth answering before you trial.</h2>
        </div>
        <div class="pp-faq">
            <?php
            $faq = array(
                array(
                    'q' => 'How is this different from manually accepting Venmo?',
                    'a' => 'The payment part is identical. The figuring-out-who-paid part isn\'t. Pipe Pay captures the screenshot at checkout, verifies it with AI, and only surfaces the orders that actually need your attention. The manual workflow you\'re doing now scales linearly with order volume; this one doesn\'t.',
                ),
                array(
                    'q' => 'Can I pay monthly instead of annual?',
                    'a' => 'Yes. Monthly billing is $35/mo for Single Site, $65/mo for 5 Sites, or $129/mo for Unlimited. Charges run through Stripe; cancel any time from your billing portal and the license stays active until the end of the current billing period. Annual is cheaper if you\'re committing to a year (you save up to 35%) and includes the 7-day free trial. Monthly is the better fit for testing the waters or covering a short-term season &mdash; no trial, no commitment past the next charge.',
                ),
                array(
                    'q' => 'Can I use my existing Venmo, Cash App, PayPal, and Zelle accounts?',
                    'a' => 'Yes. Pipe Pay connects to the accounts you already have. We don\'t open new accounts and we don\'t move money on your behalf.',
                ),
                array(
                    'q' => 'What happens if a customer pays but never uploads the screenshot?',
                    'a' => 'Three escalating reminder emails fire at 5, 20, and 45 minutes after checkout. If there\'s still no upload at 60 minutes, the order auto-cancels and stock is restored. The customer gets a cancellation email.',
                ),
                array(
                    'q' => 'Does the AI catch all fraud?',
                    'a' => 'Honest answer: no. The AI catches the obvious cases (wrong amount, wrong recipient, signs of image editing) and flags anything ambiguous for your manual review. It is a triage layer that makes manual review tractable, not a guarantee against fraud.',
                ),
                array(
                    'q' => 'How does the chargeback risk compare to card processing?',
                    'a' => 'Materially lower than card processing - for many merchants this is a feature, not a downside. Card networks let customers dispute charges for months after the sale, with a built-in chargeback infrastructure that often lands on the merchant by default. P2P payments don\'t carry that machinery. Zelle payments are effectively irreversible once received. Venmo and Cash App personal payments can only be reversed via unauthorized-account claims (the customer claiming their account was hacked). PayPal Friends &amp; Family has no built-in dispute pathway, though it can be reversed via unauthorized-access claims through PayPal, or, if the F&amp;F payment was funded by a credit card, a chargeback through the customer\'s card issuer. So the reversal risk is non-zero, but materially smaller than card processing.',
                ),
                array(
                    'q' => 'How do I issue refunds to customers?',
                    'a' => 'Refunds happen outside Pipe Pay. You send the money back through whichever P2P app the customer originally paid with. With a Venmo or Cash App business profile you can use the in-app refund button (typically 5 to 10 business days to settle). For personal Venmo, Cash App, PayPal Friends &amp; Family, and Zelle there is no refund button: you simply send a new payment in the reverse direction. Pipe Pay marks the order refunded in WooCommerce; the actual money movement is on you.',
                ),
                array(
                    'q' => 'Do you offer refunds on the plugin license?',
                    'a' => 'No. The 7-day free trial is the evaluation period. No card is charged until day 8, and you can cancel any time before then. Once the trial converts to a paid license, all sales are final. Full details on the <a href="' . esc_url( $refund_url ) . '">refund policy page</a>.',
                ),
                array(
                    'q' => 'What if I don\'t want to use AI verification?',
                    'a' => 'The plugin works in fully manual review mode. Every uploaded screenshot lands in your admin queue; you approve or reject each one yourself. No API key required.',
                ),
                array(
                    'q' => 'What happens if my license expires?',
                    'a' => 'When your license ends &mdash; an annual term not renewed, or a subscription cancelled at the end of its billing period &mdash; plugin updates and support pause immediately. Annual licenses renewed manually (payment-app purchases) then get a <strong>30-day grace period</strong> during which the gateway keeps accepting orders; after that, Pipe Pay stops appearing at your checkout until you renew. Card-paid subscriptions (monthly or annual) stop at the end of the period you paid for. Orders already in progress always finish normally, and your orders, settings, and history stay intact &mdash; renewing at any time restores checkout, updates, and support, picking up exactly where you left off.',
                ),
                array(
                    'q' => 'Does it work with WooCommerce Subscriptions?',
                    'a' => 'No. Pipe Pay supports single-payment orders only in the current version.',
                ),
                array(
                    'q' => 'What versions of WordPress and WooCommerce does it require?',
                    'a' => 'WordPress 6.0+, WooCommerce 8.0+, PHP 7.4+. HPOS compatible. Works with both classic shortcode checkout and the block checkout.',
                ),
                array(
                    'q' => 'How do updates work?',
                    'a' => 'Through WordPress\'s standard "Update Available" notification, the same way any plugin from wordpress.org updates. Drop your license key into the plugin settings; updates flow automatically as long as the license is active.',
                ),
            );
            foreach ( $faq as $item ) {
                echo '<details>';
                echo '<summary>' . esc_html( $item['q'] ) . '</summary>';
                echo '<div class="pp-faq-body">' . wp_kses_post( $item['a'] ) . '</div>';
                echo '</details>';
            }
            ?>
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
        <p class="pp-cta-skip pp-cta-skip--inverse"><a href="#tiers">or skip the trial - pick a tier and buy now &uarr;</a></p>
    </div>
</section>

<?php include __DIR__ . '/partials/billing-toggle-assets.php'; ?>

<?php get_footer(); ?>
