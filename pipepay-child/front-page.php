<?php
/**
 * Front page for Pipe Pay marketing site.
 * Renders the long-scroll landing page with all sections from the copy brief.
 * Bypasses get_header()/get_footer() (no GeneratePress chrome on homepage).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Generic "Start 7-day free trial" buttons (header, hero, final CTA) drop the
// user straight into the trial signup (free $0 product, no card required).
$checkout_url    = home_url( '/checkout/?add-to-cart=38' );
// Tier-specific buttons in the pricing section go to WC checkout with the
// matching paid tier pre-added to cart.
// Trial CTAs from a tier card carry an `intent` param so the customer's tier
// preference is captured at signup and surfaces again at trial conversion.
// `intent` is validated to {34, 35, 36} by the woocommerce_add_cart_item_data
// filter in functions.php - mismatched values are dropped silently.
$trial_intent_single = home_url( '/checkout/?add-to-cart=38&intent=34' );
$trial_intent_five   = home_url( '/checkout/?add-to-cart=38&intent=35' );
$trial_intent_unlim  = home_url( '/checkout/?add-to-cart=38&intent=36' );
// Buy-now URLs skip the trial and add the paid tier directly. Used by the
// secondary CTA on each pricing card. page-checkout.php detects which product
// is in the cart and switches its hero copy from "Start your trial" to
// "Complete your purchase" automatically.
$buy_single      = home_url( '/checkout/?add-to-cart=34' );
$buy_five        = home_url( '/checkout/?add-to-cart=35' );
$buy_unlim       = home_url( '/checkout/?add-to-cart=36' );
$pricing_url     = home_url( '/pricing' );

$docs_url        = home_url( '/docs' );
$contact_url     = home_url( '/contact' );
$refund_url      = home_url( '/refund-policy' );
$privacy_url     = home_url( '/privacy' );
$terms_url       = home_url( '/terms' );
$changelog_url   = home_url( '/changelog' );

// Logo SVG lives in partials/logo-svg.php (single source of truth across header, footer, and final-CTA inverse variant).
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'pipepay-home' ); ?>>
<?php wp_body_open(); ?>

<a class="pp-skip" href="#content">Skip to content</a>

<div class="pp-scroll-progress" aria-hidden="true"></div>

<div class="pp-release-bar" role="status" aria-label="Latest release">
    <div class="pp-container">
        <span class="pp-release-bar__pulse" aria-hidden="true"></span>
        <span class="pp-release-bar__label">Shipped</span>
        <span class="pp-release-bar__version">v<?php echo esc_html( PIPEPAY_SITE_VERSION ); ?></span>
        <span class="pp-release-bar__msg">Handle-only payment mode &middot; methods now work with a payment handle alone, no QR upload required</span>
        <a class="pp-release-bar__link" href="<?php echo esc_url( $changelog_url ); ?>">read the changelog &rarr;</a>
    </div>
</div>

<header class="pp-header">
    <div class="pp-header-inner">
        <a class="pp-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Pipe Pay home">
            <?php include __DIR__ . '/partials/logo-svg.php'; ?>
            <span>Pipe Pay</span>
        </a>
        <button class="pp-nav-toggle" type="button" aria-expanded="false" aria-controls="pp-primary-nav" aria-label="Open menu">
            <span class="pp-nav-toggle__bar"></span>
            <span class="pp-nav-toggle__bar"></span>
            <span class="pp-nav-toggle__bar"></span>
        </button>
        <nav id="pp-primary-nav" class="pp-nav" aria-label="Primary">
            <a href="<?php echo esc_url( home_url( '/how-it-works' ) ); ?>">How it works</a>
            <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>">Pricing</a>
            <a href="<?php echo esc_url( home_url( '/changelog' ) ); ?>">Changelog</a>
            <a href="<?php echo esc_url( home_url( '/docs' ) ); ?>">Docs</a>
            <a href="<?php echo esc_url( home_url( '/my-account' ) ); ?>"><?php echo is_user_logged_in() ? 'Account' : 'Sign in'; ?></a>
            <a class="pp-btn pp-btn--primary" href="<?php echo esc_url( $checkout_url ); ?>">Start free trial</a>
        </nav>
    </div>
</header>

<main id="content">

<!-- ============== 1. HERO ============== -->
<section class="pp-hero" id="top">
    <div class="pp-container">
        <div class="pp-hero-text">
            <h1 class="pp-hero-h1">
                <span class="pp-hero-h1__line">Accept Venmo, Cash App,</span>
                <span class="pp-hero-h1__line">PayPal, and Zelle in WooCommerce,</span>
                <span class="pp-hero-h1__line">without chasing payments by hand.</span>
            </h1>
            <p class="pp-hero-sub">A WooCommerce plugin that captures customer P2P payment screenshots and verifies them with AI, so the only orders you touch are the ones the AI actually flagged.</p>
            <div class="pp-hero-ctas">
                <a class="pp-btn pp-btn--inverse pp-btn--lg" href="<?php echo esc_url( $checkout_url ); ?>">Start 7-day free trial &rarr;</a>
                <a class="pp-btn pp-btn--ghost-light pp-btn--lg" href="#how">How it works &darr;</a>
            </div>
            <p class="pp-cta-skip pp-cta-skip--inverse"><a href="<?php echo esc_url( $pricing_url ); ?>">or skip the trial - buy a license now &rarr;</a></p>
        </div>

        <div class="pp-hero-mock">
            <div class="pp-mock-browser" aria-hidden="true">
                <div class="pp-mock-chrome">
                    <span class="pp-mock-dot pp-mock-dot--r"></span>
                    <span class="pp-mock-dot pp-mock-dot--y"></span>
                    <span class="pp-mock-dot pp-mock-dot--g"></span>
                    <div class="pp-mock-url">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span>pipepay.app/pay/order-1247</span>
                    </div>
                </div>

                <div class="pp-pay">
                    <header class="pp-pay__head">
                        <h3 class="pp-pay__title">Complete your payment</h3>
                        <p class="pp-pay__order">Order #1247 &middot; <strong>$87.50</strong></p>
                    </header>

                    <div class="pp-pay__qr-wrap">
                        <div class="pp-qr">
                            <span class="pp-qr__finder pp-qr__finder--tl"></span>
                            <span class="pp-qr__finder pp-qr__finder--tr"></span>
                            <span class="pp-qr__finder pp-qr__finder--bl"></span>
                            <?php
                            // Pseudo-random but deterministic QR-ish dot pattern (avoiding the 7x7 finder squares).
                            $qr_cells = array(
                                array(8,1),array(9,2),array(8,3),array(9,4),array(8,5),
                                array(11,1),array(13,1),array(15,1),array(11,3),array(14,3),
                                array(0,8),array(2,8),array(3,8),array(5,8),array(7,8),
                                array(8,8),array(10,8),array(12,8),array(13,8),array(15,8),
                                array(8,9),array(8,11),array(8,13),array(8,15),
                                array(0,9),array(1,10),array(2,11),array(3,12),array(4,13),array(5,14),array(6,15),
                                array(10,9),array(11,10),array(12,11),array(13,12),array(14,13),array(15,14),array(16,15),
                                array(9,11),array(11,11),array(13,11),array(15,11),
                                array(10,13),array(12,13),array(14,13),array(16,13),
                                array(0,11),array(2,11),array(4,11),
                                array(0,13),array(3,13),array(5,13),
                                array(11,15),array(13,15),array(15,15),
                                array(2,9),array(4,9),array(6,9),
                                array(11,5),array(13,5),array(15,5),
                                array(11,7),array(13,7),array(15,7),
                            );
                            foreach ( $qr_cells as $c ) {
                                printf( '<span class="pp-qr__cell" style="--x:%d;--y:%d"></span>', $c[0], $c[1] );
                            }
                            ?>
                        </div>
                    </div>

                    <button type="button" class="pp-pay__handle">
                        <span class="pp-pay__handle-label">Pay</span>
                        <span class="pp-pay__handle-value">@your-handle</span>
                        <svg class="pp-pay__handle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>

                    <a class="pp-pay__cta" href="#" onclick="return false;">
                        Open Venmo
                        <span aria-hidden="true">&rarr;</span>
                    </a>

                    <div class="pp-pay__alts">
                        <button type="button" class="pp-pay__alt"><span class="pp-pay__alt-mark pp-pay__alt-mark--cash">$</span>Cash App</button>
                        <button type="button" class="pp-pay__alt"><span class="pp-pay__alt-mark pp-pay__alt-mark--paypal">P</span>PayPal</button>
                        <button type="button" class="pp-pay__alt"><span class="pp-pay__alt-mark pp-pay__alt-mark--zelle">Z</span>Zelle</button>
                    </div>

                    <ol class="pp-pay__steps">
                        <li><span class="pp-pay__step-num">1</span>Open Venmo</li>
                        <li><span class="pp-pay__step-num">2</span>Send $87.50 to @your-handle</li>
                        <li><span class="pp-pay__step-num">3</span>Screenshot the confirmation</li>
                        <li><span class="pp-pay__step-num">4</span>Upload below</li>
                    </ol>
                </div>

                <footer class="pp-pay__sticky">
                    <div class="pp-pay__sticky-text">
                        <strong>Upload your payment screenshot</strong>
                        <span>to complete this order</span>
                    </div>
                    <div class="pp-pay__dropzone">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <span>Drop screenshot or click</span>
                    </div>
                </footer>
            </div>
        </div>

    </div>
</section>

<!-- ============== 2. WHO IT IS FOR ============== -->
<section class="pp-section pp-section--tight pp-personas">
    <div class="pp-container">
        <div class="pp-section-head pp-section-head--center">
            <h2>You're probably one of three kinds of store.</h2>
            <p class="pp-subhead" style="margin-left:auto;margin-right:auto;">If your story is below, Pipe Pay was built for you.</p>
        </div>
        <div class="pp-personas-grid">
            <article class="pp-persona">
                <span class="pp-persona__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg></span>
                <span class="pp-persona__tag">Underserved by major processors</span>
                <p class="pp-persona__quote">"My processor doesn't fit my business - and the alternatives that do want four to eight percent plus a personal guarantee."</p>
                <p class="pp-persona__body">Your customers pay you directly through Venmo, Cash App, PayPal, or Zelle - the apps they already use. The plugin handles the workflow that comes after: capturing the payment screenshot, verifying the amount and recipient, and moving the order through your existing WooCommerce admin. Use multiple P2P apps so a single account's weekly receive limit doesn't cap your daily volume.</p>
            </article>

            <article class="pp-persona">
                <span class="pp-persona__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg></span>
                <span class="pp-persona__tag">Validating an idea</span>
                <p class="pp-persona__quote">"I don't have an LLC yet. I want to know if this thing works before I file paperwork, get an EIN, and hand my SSN to a payment processor."</p>
                <p class="pp-persona__body">Pipe Pay is the fastest legal path to a working checkout. No business entity required. No merchant account application. No tax ID handed to a processor for a two-week verification. Use the Venmo and Cash App accounts you already have, install on a basic WordPress site, and you're live the same afternoon. Validate the idea on real customers; incorporate once there's revenue to justify it.</p>
            </article>

            <article class="pp-persona">
                <span class="pp-persona__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></svg></span>
                <span class="pp-persona__tag">Tired of paying fees</span>
                <p class="pp-persona__quote">"I'm bleeding fourteen and a half thousand a year in Stripe fees on $500K of orders. For what, a dashboard?"</p>
                <p class="pp-persona__body">$299 a year, flat. Zero per-transaction fees on the plugin side. Personal Venmo, Cash App, and PayPal Friends &amp; Family take 0%. Business profiles take 1.9% to 2.75% - still under Stripe. Pipe Pay pays for itself the moment you cross about $10,300 in annual card volume. Every dollar saved on fees from there forward is yours.</p>
            </article>
        </div>
    </div>
</section>

<!-- ============== 3. HOW IT WORKS ============== -->
<section class="pp-section pp-how" id="how">
    <div class="pp-container">
        <div class="pp-section-head pp-section-head--center">
            <h2>Four steps from cart to confirmed order.</h2>
            <p class="pp-subhead" style="margin-left:auto;margin-right:auto;">The customer experience is a guided checkout. Yours is a Proofs queue that only shows the orders the AI couldn't auto-approve.</p>
        </div>

        <div class="pp-how-grid">
            <div class="pp-how-card">
                <span class="pp-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.5 12.5a2 2 0 0 0 2 1.5h8a2 2 0 0 0 2-1.5L22 7H7"/></svg></span>
                <span class="pp-step-num">1</span>
                <h3>Customer chooses Pipe Pay at checkout.</h3>
                <p>The plugin places the order in a custom <code>Awaiting Proof</code> status. No order confirmation email yet, the confirmation is the customer's incentive to upload.</p>
            </div>
            <div class="pp-how-card">
                <span class="pp-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/></svg></span>
                <span class="pp-step-num">2</span>
                <h3>Customer pays via their P2P app.</h3>
                <p>The payment page shows the merchant handle and an <em>Open Venmo / Cash App / PayPal / Zelle</em> button that takes the customer straight to the right account. When a QR code is configured, it renders alongside for desktop visitors to scan with their phone.</p>
            </div>
            <div class="pp-how-card">
                <span class="pp-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14.9A7 7 0 1 1 15.7 8h1.8a4.5 4.5 0 0 1 2.5 8.2"/><path d="M12 12v9"/><path d="m16 16-4-4-4 4"/></svg></span>
                <span class="pp-step-num">3</span>
                <h3>Customer uploads a screenshot.</h3>
                <p>A sticky bar at the bottom of the page stays visible until upload completes. If the customer tries to close the tab early, the browser warns them.</p>
            </div>
            <div class="pp-how-card">
                <span class="pp-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                <span class="pp-step-num">4</span>
                <h3>AI verifies and the order processes.</h3>
                <p>High-confidence verifications auto-transition to Processing and trigger your normal order emails. Medium and low confidence flow into the Proofs review queue.</p>
            </div>
        </div>

        <div class="pp-how-mock-wrap">
            <ol class="pp-flow" aria-label="End-to-end customer flow">
                <!-- Step 1: WooCommerce checkout, Pipe Pay as the only payment method.
                     Shows order summary + contact + payment method + Place order to look
                     like a real checkout, not just a payment-radio cherry-pick. -->
                <li class="pp-flow__step">
                    <div class="pp-flow__phone">
                        <div class="pp-flow__screen pp-flow__screen--checkout">
                            <div class="pp-flow__row pp-flow__row--head">Order summary</div>
                            <div class="pp-flow__cart-line">
                                <span>Premium widget</span>
                                <span>$87.50</span>
                            </div>
                            <div class="pp-flow__cart-line pp-flow__cart-line--total">
                                <span>Total</span>
                                <span>$87.50</span>
                            </div>
                            <div class="pp-flow__row pp-flow__row--head pp-flow__row--head-spaced">Contact</div>
                            <div class="pp-flow__field">your@email.com</div>
                            <div class="pp-flow__row pp-flow__row--head pp-flow__row--head-spaced">Payment method</div>
                            <div class="pp-flow__row pp-flow__row--option-selected">
                                <span class="pp-flow__radio"></span>
                                <span>Pipe Pay <small>Venmo, Cash App, PayPal, Zelle</small></span>
                            </div>
                            <div class="pp-flow__row pp-flow__row--cta">Place order</div>
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>01.</b>Choose Pipe Pay at checkout</div>
                </li>

                <!-- Step 2: Customer payment page. Mirrors the real plugin layout
                     (templates/pipe-pay-page.php): screenshot drop-zone at the
                     top → order amount + # → QR → method buttons under the QR
                     (pill-shaped, brand colors V/$/P/Z) → numbered instructions
                     for the ACTIVE method → big brand-colored Open button. -->
                <li class="pp-flow__step">
                    <div class="pp-flow__phone">
                        <div class="pp-flow__screen pp-flow__screen--pay">
                            <!-- Screenshot drop-zone in its idle state. The real
                                 plugin places this in the right column on desktop
                                 / sticky bar on mobile; in the marketing mock we
                                 lift it to the very top so visitors see the upload
                                 step is part of the same page. Square box matches
                                 the visual rhythm of a "drop file here" zone. -->
                            <div class="pp-flow__upload-zone">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <span class="pp-flow__upload-zone-label">Choose screenshot</span>
                            </div>

                            <!-- Order line: amount due + order number -->
                            <div class="pp-flow__order-line">
                                <span class="pp-flow__order-amount">$87.50</span>
                                <span class="pp-flow__order-num">#1247</span>
                            </div>

                            <div class="pp-flow__qr-mini" aria-hidden="true"></div>

                            <!-- Method buttons (under the QR per real product) -->
                            <div class="pp-flow__methods" role="tablist" aria-label="Payment method">
                                <span class="pp-flow__method pp-flow__method--active" role="tab" aria-selected="true">
                                    <span class="pp-flow__method-icon" style="background:#008CFF;">V</span>
                                </span>
                                <span class="pp-flow__method" role="tab" aria-selected="false">
                                    <span class="pp-flow__method-icon" style="background:#00D632;">$</span>
                                </span>
                                <span class="pp-flow__method" role="tab" aria-selected="false">
                                    <span class="pp-flow__method-icon" style="background:#003087;">P</span>
                                </span>
                                <span class="pp-flow__method" role="tab" aria-selected="false">
                                    <span class="pp-flow__method-icon" style="background:#6D1ED4;">Z</span>
                                </span>
                            </div>

                            <!-- Numbered instructions for the active method -->
                            <ol class="pp-flow__steps">
                                <li>Send $87.50 to <code>@your-handle</code></li>
                                <li>Add memo <code>#1247</code></li>
                                <li>Screenshot the receipt</li>
                            </ol>

                            <!-- Open Venmo CTA, colored to match Venmo brand -->
                            <a class="pp-flow__open-btn" style="background:#008CFF;" aria-hidden="true">
                                Open Venmo
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            </a>
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>02.</b>Pay through P2P app</div>
                </li>

                <!-- Step 3: same payment-page layout as step 2, but the upload
                     zone at the top is now in its submitting state - filename
                     visible, spinner active. Customer paid, came back, picked
                     their screenshot, and hit submit; payment context stays
                     visible below since they haven't left the page. -->
                <li class="pp-flow__step">
                    <div class="pp-flow__phone">
                        <div class="pp-flow__screen pp-flow__screen--pay">
                            <!-- Upload zone in submitting state - square box,
                                 spinner on top, filename underneath. -->
                            <div class="pp-flow__upload-zone pp-flow__upload-zone--submitting">
                                <span class="pp-flow__spinner" aria-hidden="true"></span>
                                <span class="pp-flow__upload-zone-name">venmo-submission.png</span>
                            </div>

                            <div class="pp-flow__order-line">
                                <span class="pp-flow__order-amount">$87.50</span>
                                <span class="pp-flow__order-num">#1247</span>
                            </div>

                            <div class="pp-flow__qr-mini" aria-hidden="true"></div>

                            <div class="pp-flow__methods" role="tablist" aria-label="Payment method">
                                <span class="pp-flow__method pp-flow__method--active" role="tab" aria-selected="true">
                                    <span class="pp-flow__method-icon" style="background:#008CFF;">V</span>
                                </span>
                                <span class="pp-flow__method" role="tab" aria-selected="false">
                                    <span class="pp-flow__method-icon" style="background:#00D632;">$</span>
                                </span>
                                <span class="pp-flow__method" role="tab" aria-selected="false">
                                    <span class="pp-flow__method-icon" style="background:#003087;">P</span>
                                </span>
                                <span class="pp-flow__method" role="tab" aria-selected="false">
                                    <span class="pp-flow__method-icon" style="background:#6D1ED4;">Z</span>
                                </span>
                            </div>

                            <ol class="pp-flow__steps">
                                <li>Send $87.50 to <code>@your-handle</code></li>
                                <li>Add memo <code>#1247</code></li>
                                <li>Screenshot the receipt</li>
                            </ol>

                            <!-- Open Venmo CTA - same as step 2; the customer
                                 can re-open the app any time if they need to
                                 re-pay or check the transaction. -->
                            <a class="pp-flow__open-btn" style="background:#008CFF;" aria-hidden="true">
                                Open Venmo
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            </a>
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>03.</b>Upload screenshot</div>
                </li>

                <!-- Step 4: Post-upload success state. Copy matches the actual plugin's
                     success-state template (templates/pipe-pay-page.php): heading
                     "Screenshot received" + sub "We have your payment screenshot and
                     will process your order shortly." Deliberately makes NO mention
                     of AI verification - customers shouldn't learn the verification
                     internals. The merchant-facing caption below the phone retains
                     the AI marketing hook because the homepage audience is merchants. -->
                <li class="pp-flow__step">
                    <div class="pp-flow__phone">
                        <div class="pp-flow__screen pp-flow__screen--done">
                            <div class="pp-flow__check" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                            </div>
                            <div class="pp-flow__done-title">Screenshot received</div>
                            <div class="pp-flow__done-sub">We&rsquo;ll process your order shortly.</div>
                            <div class="pp-flow__order-no">Order #1247</div>
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>04.</b>AI handles most</div>
                </li>
            </ol>
        </div>

        <p class="pp-how-more"><a href="<?php echo esc_url( home_url( '/how-it-works' ) ); ?>">Read the full breakdown of how Pipe Pay works &rarr;</a></p>
    </div>
</section>



<!-- ============== 10. COMPATIBILITY ============== -->
<section class="pp-section pp-section--tight pp-compat">
    <div class="pp-container">
        <div class="pp-section-head pp-section-head--center">
            <h2>Works with your stack.</h2>
        </div>
        <div class="pp-compat-grid" style="margin-left:auto;margin-right:auto;">
            <div class="pp-compat-pill"><strong>WordPress</strong><span>6.0+</span></div>
            <div class="pp-compat-pill"><strong>WooCommerce</strong><span>8.0+</span></div>
            <div class="pp-compat-pill"><strong>PHP</strong><span>7.4+</span></div>
            <div class="pp-compat-pill"><strong>Storage</strong><span>HPOS compatible</span></div>
            <div class="pp-compat-pill"><strong>Checkout</strong><span>Classic + block</span></div>
            <div class="pp-compat-pill"><strong>HEIC</strong><span>iPhone screenshots</span></div>
            <div class="pp-compat-pill"><strong>Multisite</strong><span>Friendly</span></div>
            <div class="pp-compat-pill"><strong>Theme</strong><span>Standalone plugin</span></div>
        </div>
    </div>
</section>

<!-- ============== 11. TESTIMONIALS ============== -->
<section class="pp-section pp-section--snug pp-section--alt pp-testimonials">
    <div class="pp-container">
        <div class="pp-section-head pp-section-head--center pp-section-head--wide">
            <h2>From merchants running Pipe Pay today.</h2>
        </div>
        <div class="pp-testimonials-grid" style="margin-left:auto;margin-right:auto;">
            <div class="pp-testimonial">
                <blockquote>I was manually checking 60 Venmo payments a day against my orders. Pipe Pay cut that to maybe five flagged ones I actually need to look at. The other 55 just process themselves.</blockquote>
                <div class="pp-attr"><strong>Marcus R.</strong></div>
            </div>
            <div class="pp-testimonial">
                <blockquote>We needed a path that didn't depend on whether a processor would have us. Switched to Pipe Pay six months ago and haven't had a payment-related issue since. The screenshot verification is legit.</blockquote>
                <div class="pp-attr"><strong>Priya S.</strong></div>
            </div>
            <div class="pp-testimonial">
                <blockquote>I just hate paying 2.9% on every order. Pipe Pay paid for itself in the first month and now I'm running about 80% of my volume through it.</blockquote>
                <div class="pp-attr"><strong>Dan W.</strong></div>
            </div>
        </div>
    </div>
</section>

<!-- ============== 12. LIVE DEMO AT CHECKOUT ==============
     Editorial "specimen plate" of the four P2P methods in their
     authentic brand colors. Frames the dogfooding message
     ("we run our own checkout on Pipe Pay") as a typographic
     centerpiece rather than a slab of brand-blue marketing card. -->
<section class="pp-section pp-section--tight pp-self-checkout" id="live-demo">
    <div class="pp-container">
        <div class="pp-self-checkout__inner">

            <header class="pp-self-checkout__head">
                <h2 class="pp-self-checkout__title">Our checkout runs on <span class="pp-self-checkout__brand">Pipe Pay</span>.</h2>
                <p class="pp-self-checkout__sub"><strong>Live demo at checkout.</strong> We run Pipe Pay as our own store's checkout so merchants can experience it firsthand - and so we keep finding ways to improve it. New updates ship constantly.</p>
            </header>

            <!-- Specimen plate: oversized brand-colored letter-marks for the
                 four P2P methods, framed by editorial hairline rules. The same
                 V/$/P/Z glyph vocabulary used in the How-It-Works phone mocks,
                 here promoted to display typography. -->
            <div class="pp-self-checkout__plate" aria-hidden="true">
                <div class="pp-self-checkout__rule"></div>

                <ol class="pp-self-checkout__methods">
                    <li class="pp-self-checkout__method" style="--m-color:#008CFF;">
                        <span class="pp-self-checkout__glyph">V</span>
                        <span class="pp-self-checkout__name">Venmo</span>
                    </li>
                    <li class="pp-self-checkout__method" style="--m-color:#00D632;">
                        <span class="pp-self-checkout__glyph">$</span>
                        <span class="pp-self-checkout__name">Cash&nbsp;App</span>
                    </li>
                    <li class="pp-self-checkout__method" style="--m-color:#003087;">
                        <span class="pp-self-checkout__glyph">P</span>
                        <span class="pp-self-checkout__name">PayPal&nbsp;F&amp;F</span>
                    </li>
                    <li class="pp-self-checkout__method" style="--m-color:#6D1ED4;">
                        <span class="pp-self-checkout__glyph">Z</span>
                        <span class="pp-self-checkout__name">Zelle</span>
                    </li>
                </ol>

                <div class="pp-self-checkout__rule"></div>
            </div>

            <a class="pp-self-checkout__cta" href="#pricing">
                <span>Pick a tier below</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="m6 13 6 6 6-6"/></svg>
            </a>

        </div>
    </div>
</section>

<!-- ============== 13. PRICING ============== -->
<section class="pp-section pp-section--alt pp-pricing" id="pricing">
    <div class="pp-container">
        <div class="pp-section-head pp-section-head--center">
            <h2>Simple pricing. Annual licenses with a 7-day free trial.</h2>
            <p class="pp-subhead" style="margin-left:auto;margin-right:auto;">Every tier includes the same features. The license you pick controls the number of sites, nothing else.</p>
        </div>
        <div class="pp-pricing-grid">
            <div class="pp-pricing-card">
                <svg class="pp-tier-illustration" viewBox="0 0 120 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><rect x="22" y="12" width="76" height="56" rx="7" fill="#fff" stroke="#1336a8" stroke-width="2"/><circle cx="30" cy="22" r="2" fill="#1336a8"/><circle cx="38" cy="22" r="2" fill="#1336a8" opacity="0.45"/><circle cx="46" cy="22" r="2" fill="#1336a8" opacity="0.22"/><line x1="22" y1="30" x2="98" y2="30" stroke="#1336a8" stroke-width="1" opacity="0.18"/><circle cx="60" cy="48" r="11" fill="#1336a8"/><path d="M54.5 48 l4 4 l7.5 -8" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
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
                <a class="pp-btn pp-btn--ghost" href="<?php echo esc_url( $buy_single ); ?>">Buy now - skip the trial</a>
            </div>
            <div class="pp-pricing-card pp-pricing-card--featured">
                <span class="pp-pricing-ribbon">Most Popular</span>
                <svg class="pp-tier-illustration" viewBox="0 0 120 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><g fill="#fff" stroke="#1336a8" stroke-width="1.6"><rect x="15" y="12" width="26" height="22" rx="3"/><rect x="47" y="12" width="26" height="22" rx="3"/><rect x="79" y="12" width="26" height="22" rx="3"/><rect x="31" y="42" width="26" height="22" rx="3"/><rect x="63" y="42" width="26" height="22" rx="3"/></g><g fill="#1336a8"><circle cx="20" cy="17" r="1.3"/><circle cx="52" cy="17" r="1.3"/><circle cx="84" cy="17" r="1.3"/><circle cx="36" cy="47" r="1.3"/><circle cx="68" cy="47" r="1.3"/></g><g stroke="#1336a8" stroke-width="1.4" stroke-linecap="round" opacity="0.45"><line x1="19" y1="26" x2="37" y2="26"/><line x1="51" y1="26" x2="69" y2="26"/><line x1="83" y1="26" x2="101" y2="26"/><line x1="35" y1="56" x2="53" y2="56"/><line x1="67" y1="56" x2="85" y2="56"/></g></svg>
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
                <a class="pp-btn pp-btn--ghost" href="<?php echo esc_url( $buy_five ); ?>">Buy now - skip the trial</a>
            </div>
            <div class="pp-pricing-card">
                <svg class="pp-tier-illustration" viewBox="0 0 120 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M22 40 C22 22, 48 22, 60 40 C72 58, 98 58, 98 40 C98 22, 72 22, 60 40 C48 58, 22 58, 22 40 Z" fill="none" stroke="#1336a8" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="29" y="35" width="12" height="10" rx="2" fill="#1336a8"/><rect x="79" y="35" width="12" height="10" rx="2" fill="#1336a8"/></svg>
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
                <a class="pp-btn pp-btn--ghost" href="<?php echo esc_url( $buy_unlim ); ?>">Buy now - skip the trial</a>
            </div>
        </div>
        <p class="pp-pricing-fineprint">Each license includes 1 year of plugin updates and support. Renew annually to keep receiving WooCommerce-compatibility patches, security updates, and support - without renewal, your install falls behind each WP and WC release and eventually needs an update you can no longer get. Cancel anytime before the trial ends and you won't be charged. Once your trial converts to a paid license, all sales are final, no refunds. The 7-day trial is your evaluation window.</p>
    </div>
</section>

<!-- ============== Ship log strip ============== -->
<section class="pp-shiplog" aria-label="Recent releases">
    <div class="pp-container">
        <div class="pp-shiplog__head">
            <h3><span>&#9670; Ship log</span> Recent releases worth reading</h3>
            <a class="pp-shiplog__more" href="<?php echo esc_url( $changelog_url ); ?>">All releases &rarr;</a>
        </div>
        <ul class="pp-shiplog__list">
            <li>
                <span class="pp-shiplog__date">May 8, 2026</span>
                <span class="pp-shiplog__ver">v1.7.4</span>
                <span class="pp-shiplog__note"><strong>Handle-only payment mode.</strong> Methods can now be configured with just a payment handle, no QR code upload required. The customer payment page renders a clean &ldquo;Open Venmo&rdquo; / &ldquo;Open Cash App&rdquo; / &ldquo;Open PayPal&rdquo; deep-link callout for these methods. Zelle gets a tailored bank-app instruction since Zelle has no universal deep link. A yellow admin notice on the gateway settings page suggests adding a QR for any method that doesn&rsquo;t have one, with a one-click hide-for-30-days option for merchants who prefer handle-only.</span>
            </li>
            <li>
                <span class="pp-shiplog__date">May 8, 2026</span>
                <span class="pp-shiplog__ver">v1.7.0</span>
                <span class="pp-shiplog__note"><strong>License integrity + Awaiting Approval status.</strong> Stronger verification of license-server responses (no customer-visible behavior change). Manual-review orders now land in a dedicated &ldquo;Awaiting Approval&rdquo; status instead of generic on-hold, so the orders list immediately tells you which orders need your decision. Dedicated review-pending customer email with copy that matches the actual state.</span>
            </li>
            <li>
                <span class="pp-shiplog__date">May 7, 2026</span>
                <span class="pp-shiplog__ver">v1.6.5</span>
                <span class="pp-shiplog__note"><strong>Reliability and hardening.</strong> Continued security and reliability improvements throughout the upload, license activation, and image-handling flows. PHP 8.0 minimum (stores on PHP 7.4 see an admin notice and remain inactive until upgraded). Recommended update for everyone.</span>
            </li>
            <li>
                <span class="pp-shiplog__date">May 4, 2026</span>
                <span class="pp-shiplog__ver">v1.6.0</span>
                <span class="pp-shiplog__note"><strong>One-field license activation.</strong> Activate with just your license key - no product ID lookup. Tier upgrades (trial &rarr; paid, single &rarr; unlimited) flow seamlessly without re-installing the plugin.</span>
            </li>
        </ul>
    </div>
</section>

<!-- ============== 15. FINAL CTA ============== -->
<section class="pp-section pp-section--snug pp-section--blue pp-final-cta">
    <div class="pp-container">
        <div class="pp-final-cta__mark" aria-hidden="true">
            <?php $pp_logo_variant = 'inverse'; include __DIR__ . '/partials/logo-svg.php'; ?>
        </div>
        <h2>Ready to stop chasing payments by hand?</h2>
        <p>Start your 7-day free trial. No card required.</p>
        <a class="pp-btn pp-btn--inverse pp-btn--lg" href="<?php echo esc_url( $checkout_url ); ?>">Start 7-day free trial &rarr;</a>
        <p class="pp-cta-skip pp-cta-skip--inverse"><a href="<?php echo esc_url( $pricing_url ); ?>">or skip the trial - buy a license now &rarr;</a></p>
    </div>
</section>

</main>

<!-- ============== Footer ============== -->
<footer class="pp-footer">
    <div class="pp-container">
        <div class="pp-footer-row">
            <a class="pp-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <?php include __DIR__ . '/partials/logo-svg.php'; ?>
                <span>Pipe Pay</span>
            </a>
            <nav class="pp-footer-links" aria-label="Footer">
                <a href="#pricing">Pricing</a>
                <a href="<?php echo esc_url( $docs_url ); ?>">Docs</a>
                <a href="<?php echo esc_url( $changelog_url ); ?>">Changelog</a>
                <a href="<?php echo esc_url( $contact_url ); ?>">Contact</a>
                <a href="<?php echo esc_url( $refund_url ); ?>">Refunds</a>
                <a href="<?php echo esc_url( $privacy_url ); ?>">Privacy</a>
                <a href="<?php echo esc_url( $terms_url ); ?>">Terms</a>
            </nav>
            <a class="pp-footer-changelog" href="<?php echo esc_url( $changelog_url ); ?>">
                <span>Release notes</span>
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
        <div class="pp-footer-ledger">
            <strong>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Pipe Pay</strong>
            <span class="pp-footer-ledger__sep">/</span>
            <span>Independent software</span>
            <span class="pp-footer-ledger__sep">/</span>
            <span>v<?php echo esc_html( PIPEPAY_SITE_VERSION ); ?></span>
        </div>
    </div>
</footer>

<script>
(function () {
    // Scroll progress indicator
    var bar = document.querySelector('.pp-scroll-progress');
    if (bar) {
        var update = function () {
            var h = document.documentElement;
            var max = h.scrollHeight - h.clientHeight;
            var pct = max > 0 ? (h.scrollTop / max) * 100 : 0;
            bar.style.width = pct + '%';
        };
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        update();
    }

    // Section reveal on scroll
    var supportsIO = 'IntersectionObserver' in window;
    var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (supportsIO && !prefersReduced) {
        var targets = document.querySelectorAll('main > section:not(.pp-hero)');
        targets.forEach(function (el) { el.classList.add('pp-reveal'); });
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.05 });
        targets.forEach(function (el) { io.observe(el); });
    }
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
