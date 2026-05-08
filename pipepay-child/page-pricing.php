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
// filter in functions.php — mismatched values are dropped silently.
$trial_intent_single = home_url( '/checkout/?add-to-cart=38&intent=34' );
$trial_intent_five   = home_url( '/checkout/?add-to-cart=38&intent=35' );
$trial_intent_unlim  = home_url( '/checkout/?add-to-cart=38&intent=36' );
$refund_url      = home_url( '/refund-policy' );
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">Pricing</span>
        <h1 class="pp-page-title">Three tiers, all with a 7-day free trial.</h1>
        <p class="pp-page-hero__sub">Pick the license size that matches the number of WooCommerce stores you run. Every tier ships the same features. Cancel any time before day eight and you won't be charged.</p>
    </div>
</section>

<!-- ============== PRICING CARDS ============== -->
<section class="pp-section pp-section--tight pp-pricing">
    <div class="pp-container">
        <div class="pp-pricing-grid">
            <div class="pp-pricing-card">
                <h3>Single Site</h3>
                <p class="pp-price-detail">For one WooCommerce store.</p>
                <div class="pp-price">$299<small></small></div>
                <div class="pp-price-period">per year</div>
                <ul class="pp-pricing-features">
                    <li>1 site activation</li>
                    <li>1 year of plugin updates</li>
                    <li>1 year of email support</li>
                    <li>7-day free trial, no card required</li>
                </ul>
                <a class="pp-btn pp-btn--secondary" href="<?php echo esc_url( $trial_intent_single ); ?>">Start 7-day trial</a>
            </div>
            <div class="pp-pricing-card pp-pricing-card--featured">
                <span class="pp-pricing-ribbon">Most Popular</span>
                <h3>5 Sites</h3>
                <p class="pp-price-detail">For agencies or multi-store owners.</p>
                <div class="pp-price">$599<small></small></div>
                <div class="pp-price-period">per year</div>
                <ul class="pp-pricing-features">
                    <li>Up to 5 site activations</li>
                    <li>1 year of plugin updates</li>
                    <li>1 year of email support</li>
                    <li>7-day free trial, no card required</li>
                </ul>
                <a class="pp-btn pp-btn--primary" href="<?php echo esc_url( $trial_intent_five ); ?>">Start 7-day trial</a>
            </div>
            <div class="pp-pricing-card">
                <h3>Unlimited Sites</h3>
                <p class="pp-price-detail">No activation cap. Run it everywhere.</p>
                <div class="pp-price">$1,199<small></small></div>
                <div class="pp-price-period">per year</div>
                <ul class="pp-pricing-features">
                    <li>Unlimited site activations</li>
                    <li>1 year of plugin updates</li>
                    <li>1 year of email support</li>
                    <li>7-day free trial, no card required</li>
                </ul>
                <a class="pp-btn pp-btn--secondary" href="<?php echo esc_url( $trial_intent_unlim ); ?>">Start 7-day trial</a>
            </div>
        </div>
        <p class="pp-pricing-fineprint">License entitles you to 1 year of updates and support. The plugin requires an active license to process payments; if your license lapses, the plugin stops accepting new orders until renewed. Cancel anytime before the trial ends and you won't be charged. Once your trial converts to a paid license, all sales are final, no refunds. The 7-day trial is your evaluation window.</p>
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
                    <li>Stripe, Square, or Adyen won't underwrite your vertical, or terminated you for being in it.</li>
                    <li>You've had funds frozen for 180 days, been put on the MATCH list, or been hit with a personal guarantee on a high-risk processor.</li>
                    <li>You're testing a new product idea and don't want to file an LLC, get an EIN, and hand your SSN to a processor before you know if it works.</li>
                    <li>You're paying meaningful Stripe or Square fees ($5K+ per year) and want them back.</li>
                    <li>You already accept Venmo, Cash App, PayPal F&amp;F, or Zelle and reconcile orders by hand against your transaction history.</li>
                </ul>
            </div>
            <div class="pp-fit-col pp-fit-col--no">
                <h3 class="pp-fit-title pp-fit-title--no">Probably not, if any of these are you</h3>
                <ul class="pp-fit-list">
                    <li>You need to accept credit cards directly. Pipe Pay does not process cards and never will.</li>
                    <li>You sell subscriptions and need recurring billing. Single-payment orders only in this version.</li>
                    <li>Your customers refuse to use P2P apps and you can't move them off card.</li>
                    <li>You need chargeback insurance for a vertical with structurally high chargeback rates. P2P rails reverse less often than card networks but they have no insurance product.</li>
                    <li>You're on Shopify, BigCommerce, Magento, or Squarespace. Pipe Pay is a WooCommerce plugin; there are no ports to other platforms.</li>
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
                    'a' => 'The payment part is identical. The reconciliation part isn\'t. Pipe Pay captures the screenshot at checkout, verifies it with AI, and only surfaces the orders that actually need your attention. The manual workflow you\'re doing now scales linearly with order volume; this one doesn\'t.',
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
                    'a' => 'Materially lower, and for most merchants in restricted verticals it\'s a feature. Card networks let customers dispute charges for months after the sale, with a built-in chargeback infrastructure that often lands on the merchant by default. P2P payments don\'t carry that machinery. Zelle payments are effectively irreversible once received. Venmo and Cash App personal payments can only be reversed via unauthorized-account claims (the customer claiming their account was hacked). PayPal Friends &amp; Family has no built-in dispute pathway, though it can be reversed via unauthorized-access claims through PayPal, or, if the F&amp;F payment was funded by a credit card, a chargeback through the customer\'s card issuer. So the reversal risk is non-zero, but materially smaller than card processing.',
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
                    'a' => 'The plugin stops processing new payments at the next license check. Existing orders, settings, and historical data all remain intact. Renew and the plugin starts accepting orders again immediately.',
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
        <h2>Ready to stop reconciling by hand?</h2>
        <p>Start your 7-day free trial. No card required.</p>
        <a class="pp-btn pp-btn--inverse pp-btn--lg" href="<?php echo esc_url( $checkout_url ); ?>">Start 7-day free trial &rarr;</a>
    </div>
</section>

<?php get_footer(); ?>
