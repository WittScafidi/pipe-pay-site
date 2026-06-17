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
// Monthly subscription buy URLs. All purchases funnel through the WC checkout
// page, which embeds the Stripe card form inline (monthly = card-only; annual
// offers a card-vs-payment-app chooser). See page-checkout.php.
$monthly_buy_single = home_url( '/checkout/?add-to-cart=526' );
$monthly_buy_five   = home_url( '/checkout/?add-to-cart=527' );
$monthly_buy_unlim  = home_url( '/checkout/?add-to-cart=528' );
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

<?php /* Release bar removed 2026-06-04 - stale per-release callout was
        harder to keep in sync than it was worth. Latest version info is
        still discoverable via the /changelog/ page link in the footer
        and the in-product update notices. */ ?>

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
            <a href="<?php echo esc_url( home_url( '/docs' ) ); ?>">Docs</a>
            <a href="<?php echo esc_url( home_url( '/blog' ) ); ?>">Blog</a>
            <a href="<?php echo esc_url( home_url( '/changelog' ) ); ?>">Changelog</a>
            <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact</a>
            <a href="<?php echo esc_url( home_url( '/my-account' ) ); ?>"><?php echo is_user_logged_in() ? 'Account' : 'Sign in'; ?></a>
            <?php
            /* Conditional Cart link - see header.php for rationale. */
            if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->get_cart_contents_count() > 0 ) :
                $pp_cart_count = WC()->cart->get_cart_contents_count();
            ?>
            <a class="pp-nav-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">Cart <span class="pp-nav-cart__count"><?php echo esc_html( $pp_cart_count ); ?></span></a>
            <?php endif; ?>
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
                <span class="pp-hero-h1__line">P2P payment verification</span>
                <span class="pp-hero-h1__line">for WooCommerce stores,</span>
                <span class="pp-hero-h1__line">without chasing payments by hand.</span>
            </h1>
            <p class="pp-hero-sub">A WordPress plugin that captures customer payment screenshots from the P2P app they used and verifies them with AI, so the only orders you touch are the ones flagged for your review. Works with Venmo, Cash App, PayPal, and Zelle.</p>
            <div class="pp-hero-ctas">
                <a class="pp-btn pp-btn--inverse pp-btn--lg" href="<?php echo esc_url( $checkout_url ); ?>">Start 7-day free trial &rarr;</a>
                <a class="pp-btn pp-btn--ghost-light pp-btn--lg" href="#how">How it works &darr;</a>
            </div>
        </div>

        <div class="pp-hero-mock-col">
            <p class="pp-hero-mock-label">Real screenshot from the Pipe Pay plugin</p>
            <div class="pp-hero-mock pp-hero-mock--photo">
                <!-- Real screenshot of the Pipe Pay payment page (same shot as flow step 2;
                     iOS status bar cropped, pipepay.app Safari bar kept). The caption above
                     ("Real screenshot from the Pipe Pay plugin") replaces the old DEMO badge:
                     it frames this as product marketing rather than a live payment page, which
                     keeps the phishing-classifier signal (see the v1.8.1 Cloudflare history)
                     while reading as a positive authenticity cue. -->
                <img src="<?php echo esc_url( content_url( '/uploads/pipe-pay/pipe-pay-flow-2-pay.png' ) ); ?>"
                     alt="The Pipe Pay payment page on a phone: amount due, the order number to include in the payment note, and Venmo, Cash App, PayPal and Zelle buttons."
                     width="660" height="1332" decoding="async" fetchpriority="high">
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
                <ul class="pp-persona__list">
                    <li><strong>Customers pay you directly</strong> through Venmo, Cash App, PayPal, or Zelle - apps they already use.</li>
                    <li><strong>The plugin handles the rest:</strong> captures the screenshot, verifies the amount + recipient, moves the order through WC admin.</li>
                    <li><strong>Run multiple P2P apps in parallel</strong> so one account's weekly receive limit doesn't cap your daily volume.</li>
                </ul>
            </article>

            <article class="pp-persona">
                <span class="pp-persona__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg></span>
                <span class="pp-persona__tag">Validating an idea</span>
                <p class="pp-persona__quote">"I don't have an LLC yet. I want to know if this thing works before I file paperwork, get an EIN, and hand my SSN to a payment processor."</p>
                <ul class="pp-persona__list">
                    <li><strong>No business entity required.</strong></li>
                    <li><strong>No merchant account application.</strong></li>
                    <li><strong>No tax ID handed to a processor</strong> for a two-week verification.</li>
                    <li><strong>Use the Venmo and Cash App accounts you already have.</strong> Install on a basic WP site; live the same afternoon. Incorporate once revenue justifies it.</li>
                </ul>
            </article>

            <article class="pp-persona">
                <span class="pp-persona__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></svg></span>
                <span class="pp-persona__tag">Tired of paying fees</span>
                <p class="pp-persona__quote">"I'm bleeding fourteen and a half thousand a year in Stripe fees on $500K of orders. For what, a dashboard?"</p>
                <ul class="pp-persona__list">
                    <li><strong>$297/year, flat.</strong> Zero per-transaction fees on the plugin side.</li>
                    <li><strong>Personal Venmo / Cash App / PayPal F&amp;F:</strong> 0% fees.</li>
                    <li><strong>Business profiles:</strong> 1.9-2.75% - still under Stripe.</li>
                    <li><strong>Breaks even at ~$10,200 in annual card volume.</strong> Every dollar saved from there is yours.</li>
                </ul>
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
            <ol class="pp-flow" aria-label="End-to-end customer flow demo">
                <!-- Steps 1-4 are real screenshots of the live Pipe Pay flow, captured from
                     one order (#600, $297 throughout) so the amounts stay consistent across
                     the strip. iOS status bar cropped off; the pipepay.app Safari bar is kept
                     on purpose. All four use the pp-flow__phone--photo treatment so the row is
                     uniform; object-fit:contain keeps each full screen + URL bar visible.
                     Source PNGs (cropped + web-sized) live in /uploads/pipe-pay/. -->
                <li class="pp-flow__step">
                    <div class="pp-flow__phone pp-flow__phone--photo">
                        <div class="pp-flow__screen pp-flow__screen--photo">
                            <img src="<?php echo esc_url( content_url( '/uploads/pipe-pay/pipe-pay-flow-1-checkout.png' ) ); ?>"
                                 alt="Pipe Pay selected as the payment method at a WooCommerce checkout, with the order summary, total, and Place Order button."
                                 width="660" height="1332" loading="lazy" decoding="async">
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>01.</b>Choose Pipe Pay at checkout</div>
                </li>

                <li class="pp-flow__step">
                    <div class="pp-flow__phone pp-flow__phone--photo">
                        <div class="pp-flow__screen pp-flow__screen--photo">
                            <img src="<?php echo esc_url( content_url( '/uploads/pipe-pay/pipe-pay-flow-2-pay.png' ) ); ?>"
                                 alt="The Pipe Pay payment page showing the amount due, the order number to include in the payment note, and Venmo, Cash App, PayPal and Zelle buttons."
                                 width="660" height="1332" loading="lazy" decoding="async">
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>02.</b>Pay through your P2P app</div>
                </li>

                <li class="pp-flow__step">
                    <div class="pp-flow__phone pp-flow__phone--photo">
                        <div class="pp-flow__screen pp-flow__screen--photo">
                            <img src="<?php echo esc_url( content_url( '/uploads/pipe-pay/pipe-pay-flow-3-upload.png' ) ); ?>"
                                 alt="The customer uploading their payment screenshot on the Pipe Pay page, with the image attached and submitting."
                                 width="660" height="1332" loading="lazy" decoding="async">
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>03.</b>Upload screenshot</div>
                </li>

                <li class="pp-flow__step">
                    <div class="pp-flow__phone pp-flow__phone--photo">
                        <div class="pp-flow__screen pp-flow__screen--photo">
                            <img src="<?php echo esc_url( content_url( '/uploads/pipe-pay/pipe-pay-flow-4-confirmation.png' ) ); ?>"
                                 alt="The Pipe Pay confirmation screen reading Screenshot received, with a View order confirmation button."
                                 width="660" height="1332" loading="lazy" decoding="async">
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>04.</b>Order received</div>
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
            <h2>Simple pricing. Pay annual or monthly.</h2>
            <p class="pp-subhead" style="margin-left:auto;margin-right:auto;">Every tier includes the same features. The license you pick controls the number of sites, nothing else. Annual saves up to 35% and includes a 7-day free trial. Monthly is cancel-anytime.</p>
        </div>

        <div class="pp-billing-toggle" role="group" aria-label="Choose billing period">
            <button type="button" class="pp-billing-toggle__btn pp-billing-toggle__btn--active" aria-pressed="true" data-billing="annual">Annual <span class="pp-billing-toggle__save">save up to 35%</span></button>
            <button type="button" class="pp-billing-toggle__btn" aria-pressed="false" data-billing="monthly">Monthly</button>
        </div>
        <p class="pp-billing-toggle__note" data-billing-show="annual">Annual includes a 7-day free trial. Buying now? Pay by card (auto-renews) or a payment app (renew manually) - choose at checkout.</p>
        <p class="pp-billing-toggle__note" data-billing-show="monthly" hidden>Monthly is cancel-anytime in your Stripe billing portal. No trial; pay only for what you use.</p>

        <div class="pp-pricing-grid">
            <div class="pp-pricing-card">
                <svg class="pp-tier-illustration" viewBox="0 0 120 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><rect x="22" y="12" width="76" height="56" rx="7" fill="#fff" stroke="#1336a8" stroke-width="2"/><circle cx="30" cy="22" r="2" fill="#1336a8"/><circle cx="38" cy="22" r="2" fill="#1336a8" opacity="0.45"/><circle cx="46" cy="22" r="2" fill="#1336a8" opacity="0.22"/><line x1="22" y1="30" x2="98" y2="30" stroke="#1336a8" stroke-width="1" opacity="0.18"/><circle cx="60" cy="48" r="11" fill="#1336a8"/><path d="M54.5 48 l4 4 l7.5 -8" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <h3>Single Site</h3>
                <p class="pp-price-detail">For one WooCommerce store.</p>
                <div data-billing-show="annual">
                    <div class="pp-price">$297<small></small></div>
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
                <a class="pp-btn pp-btn--secondary" data-billing-show="monthly" href="<?php echo esc_url( $monthly_buy_single ); ?>" hidden>Subscribe monthly - $35/mo</a>
            </div>
            <div class="pp-pricing-card pp-pricing-card--featured">
                <span class="pp-pricing-ribbon">Most Popular</span>
                <svg class="pp-tier-illustration" viewBox="0 0 120 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><g fill="#fff" stroke="#1336a8" stroke-width="1.6"><rect x="15" y="12" width="26" height="22" rx="3"/><rect x="47" y="12" width="26" height="22" rx="3"/><rect x="79" y="12" width="26" height="22" rx="3"/><rect x="31" y="42" width="26" height="22" rx="3"/><rect x="63" y="42" width="26" height="22" rx="3"/></g><g fill="#1336a8"><circle cx="20" cy="17" r="1.3"/><circle cx="52" cy="17" r="1.3"/><circle cx="84" cy="17" r="1.3"/><circle cx="36" cy="47" r="1.3"/><circle cx="68" cy="47" r="1.3"/></g><g stroke="#1336a8" stroke-width="1.4" stroke-linecap="round" opacity="0.45"><line x1="19" y1="26" x2="37" y2="26"/><line x1="51" y1="26" x2="69" y2="26"/><line x1="83" y1="26" x2="101" y2="26"/><line x1="35" y1="56" x2="53" y2="56"/><line x1="67" y1="56" x2="85" y2="56"/></g></svg>
                <h3>5 Sites</h3>
                <p class="pp-price-detail">For agencies or multi-store owners.</p>
                <div data-billing-show="annual">
                    <div class="pp-price">$497<small></small></div>
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
                <a class="pp-btn pp-btn--primary" data-billing-show="monthly" href="<?php echo esc_url( $monthly_buy_five ); ?>" hidden>Subscribe monthly - $65/mo</a>
            </div>
            <div class="pp-pricing-card">
                <svg class="pp-tier-illustration" viewBox="0 0 120 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M22 40 C22 22, 48 22, 60 40 C72 58, 98 58, 98 40 C98 22, 72 22, 60 40 C48 58, 22 58, 22 40 Z" fill="none" stroke="#1336a8" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="29" y="35" width="12" height="10" rx="2" fill="#1336a8"/><rect x="79" y="35" width="12" height="10" rx="2" fill="#1336a8"/></svg>
                <h3>Unlimited Sites</h3>
                <p class="pp-price-detail">No activation cap. Run it everywhere.</p>
                <div data-billing-show="annual">
                    <div class="pp-price">$997<small></small></div>
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
                <a class="pp-btn pp-btn--secondary" data-billing-show="monthly" href="<?php echo esc_url( $monthly_buy_unlim ); ?>" hidden>Subscribe monthly - $129/mo</a>
            </div>
        </div>
        <p class="pp-pricing-fineprint" data-billing-show="annual">Each annual license includes 1 year of plugin updates and support. Renew annually to keep receiving WooCommerce-compatibility patches, security updates, and support - without renewal, your install falls behind each WP and WC release and eventually needs an update you can no longer get. Cancel anytime before the trial ends and you won't be charged. Once your trial converts to a paid license, all sales are final, no refunds. The 7-day trial is your evaluation window.</p>
        <p class="pp-pricing-fineprint" data-billing-show="monthly" hidden>Monthly subscriptions include plugin updates and support for as long as the subscription is active. Cancel anytime in your billing portal - your license stays active until the end of the current billing period, then expires. Annual saves up to 35% if you're committing to a full year; monthly is best for testing the waters or short-term needs. Monthly charges are non-refundable; cancel before the next billing date to avoid the next charge.</p>
    </div>

    <?php include __DIR__ . '/partials/billing-toggle-assets.php'; ?>
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
                <span class="pp-shiplog__date">May 29, 2026</span>
                <span class="pp-shiplog__ver">v1.9.9</span>
                <span class="pp-shiplog__note"><strong>Reliability and polish.</strong> Tighter safeguards around order total handling for $0 and refunded orders. Mobile customers tapping &ldquo;Open Venmo&rdquo; on a business profile now get the amount and order number pre-filled, matching what desktop QR-scanners already got. Visual polish on the customer payment page. Recommended update for everyone.</span>
            </li>
            <li>
                <span class="pp-shiplog__date">May 29, 2026</span>
                <span class="pp-shiplog__ver">v1.9.7</span>
                <span class="pp-shiplog__note"><strong>Venmo Business: scan-to-pay with order memo.</strong> Venmo Business profiles now show a per-order QR code on the customer payment page. Customers scan with their phone camera and Venmo opens with your handle, the order amount, and the order number pre-filled in the memo field. No more &ldquo;what was the order number again?&rdquo; follow-ups in your DMs. The on-page hint reminds customers to scan with their phone camera, not Venmo&rsquo;s in-app scanner. Personal Venmo profiles keep using the QR you upload yourself in the gateway settings.</span>
            </li>
            <li>
                <span class="pp-shiplog__date">May 29, 2026</span>
                <span class="pp-shiplog__ver">v1.9.6</span>
                <span class="pp-shiplog__note"><strong>Instant activation for $0 orders.</strong> Free trial signups and any other zero-dollar orders (coupon-comped, 100%-off promos) now complete on the spot. The customer gets the standard order confirmation right away - no screenshot upload step, no waiting room, no proof-of-payment review for orders where nothing is owed. Paid orders continue through the normal payment-verification path unchanged.</span>
            </li>
            <li>
                <span class="pp-shiplog__date">May 8, 2026</span>
                <span class="pp-shiplog__ver">v1.7.4</span>
                <span class="pp-shiplog__note"><strong>Handle-only payment mode.</strong> Methods can now be configured with just a payment handle, no QR code upload required. The customer payment page renders a clean &ldquo;Open Venmo&rdquo; / &ldquo;Open Cash App&rdquo; / &ldquo;Open PayPal&rdquo; deep-link callout for these methods. Zelle gets a tailored bank-app instruction since Zelle has no universal deep link. A yellow admin notice on the gateway settings page suggests adding a QR for any method that doesn&rsquo;t have one, with a one-click hide-for-30-days option for merchants who prefer handle-only.</span>
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
                <a href="<?php echo esc_url( home_url( '/about' ) ); ?>">About</a>
                <a href="#pricing">Pricing</a>
                <a href="<?php echo esc_url( $docs_url ); ?>">Docs</a>
                <a href="<?php echo esc_url( $changelog_url ); ?>">Changelog</a>
                <a href="<?php echo esc_url( $contact_url ); ?>">Contact</a>
                <a href="<?php echo esc_url( $refund_url ); ?>">Refunds</a>
                <a href="<?php echo esc_url( $privacy_url ); ?>">Privacy</a>
                <a href="<?php echo esc_url( home_url( '/sub-processors' ) ); ?>">Sub-processors</a>
                <a href="<?php echo esc_url( home_url( '/data-handling' ) ); ?>">Data handling</a>
                <a href="<?php echo esc_url( $terms_url ); ?>">Terms</a>
            </nav>
            <a class="pp-footer-changelog" href="<?php echo esc_url( $changelog_url ); ?>">
                <span>Release notes</span>
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
        <p class="pp-footer-disclaimer">
            Pipe Pay is an independent software tool for WooCommerce merchants. We are not affiliated with, endorsed by, or sponsored by Cash App, Block Inc., Zelle, Early Warning Services, Venmo, PayPal Holdings, Chime, or any payment service mentioned on this site. All product names, logos, and brands referenced are property of their respective owners and are used for identification purposes only.
        </p>
        <div class="pp-footer-ledger">
            <strong>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Silver Bazaar, LLC</strong>
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
