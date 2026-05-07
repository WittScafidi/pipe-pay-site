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
$tier_single_url = home_url( '/checkout/?add-to-cart=34' );
$tier_five_url   = home_url( '/checkout/?add-to-cart=35' );
$tier_unlim_url  = home_url( '/checkout/?add-to-cart=36' );

$docs_url        = 'https://pipepay.app/docs';
$contact_url     = 'https://pipepay.app/contact';
$refund_url      = 'https://pipepay.app/refund-policy';
$privacy_url     = 'https://pipepay.app/privacy';
$terms_url       = 'https://pipepay.app/terms';
$changelog_url   = 'https://pipepay.app/changelog';

// Inline SVG logo: pipe body + slim flange ridge on the inner end,
// connected by a 6-line network around a central $ coin.
$logo_svg = <<<'SVG'
<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <rect x="2"  y="26" width="8" height="12" fill="currentColor"/>
  <rect x="9"  y="22" width="3" height="20" fill="currentColor"/>
  <rect x="54" y="26" width="8" height="12" fill="currentColor"/>
  <rect x="52" y="22" width="3" height="20" fill="currentColor"/>
  <path d="M 12 32 L 32 14" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 12 32 L 22 32" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 12 32 L 32 50" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 52 32 L 32 14" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 52 32 L 42 32" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 52 32 L 32 50" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <circle cx="12" cy="32" r="1.8" fill="currentColor"/>
  <circle cx="52" cy="32" r="1.8" fill="currentColor"/>
  <circle cx="32" cy="32" r="10.5" fill="currentColor"/>
  <circle cx="32" cy="32" r="8.8"  fill="#fff"/>
  <text x="32" y="38" text-anchor="middle" font-family="Manrope, Inter, sans-serif" font-size="17" font-weight="800" fill="currentColor">$</text>
</svg>
SVG;
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

<div class="pp-scroll-progress" aria-hidden="true"></div>

<div class="pp-release-bar" role="status" aria-label="Latest release">
    <div class="pp-container">
        <span class="pp-release-bar__pulse" aria-hidden="true"></span>
        <span class="pp-release-bar__label">Shipped</span>
        <span class="pp-release-bar__version">v1.6.2</span>
        <span class="pp-release-bar__msg">critical Block Checkout payment fix &middot; update strongly recommended</span>
        <a class="pp-release-bar__link" href="<?php echo esc_url( $changelog_url ); ?>">read the changelog &rarr;</a>
    </div>
</div>

<header class="pp-header">
    <div class="pp-header-inner">
        <a class="pp-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Pipe Pay home">
            <?php echo $logo_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
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
            <a class="pp-btn pp-btn--primary" href="<?php echo esc_url( $checkout_url ); ?>">Start free trial</a>
        </nav>
    </div>
</header>

<main>

<!-- ============== 1. HERO ============== -->
<section class="pp-hero" id="top">
    <div class="pp-container">
        <h1 class="pp-hero-h1">
            <span class="pp-hero-h1__line">Accept Venmo, Cash App,</span>
            <span class="pp-hero-h1__line">PayPal, and Zelle in WooCommerce,</span>
            <span class="pp-hero-h1__line">without manual reconciliation.</span>
        </h1>
        <p class="pp-hero-sub">A WooCommerce plugin that captures customer P2P payment screenshots and verifies them with AI, so the only orders you touch are the ones the AI actually flagged.</p>
        <div class="pp-hero-ctas">
            <a class="pp-btn pp-btn--inverse pp-btn--lg" href="<?php echo esc_url( $checkout_url ); ?>">Start 7-day free trial &rarr;</a>
            <a class="pp-btn pp-btn--ghost-light pp-btn--lg" href="#how">How it works &darr;</a>
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
                        <span class="pp-pay__handle-value">@northrange-supply</span>
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
                        <li><span class="pp-pay__step-num">2</span>Send $87.50 to @northrange-supply</li>
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
                <span class="pp-persona__tag">High-risk vertical</span>
                <p class="pp-persona__quote">"Stripe terminated my account. PayPal froze $40K for 180 days. Every high-risk processor that'll take me wants four to eight percent plus a personal guarantee."</p>
                <p class="pp-persona__body">No underwriting to fail. No MCC to scrutinize. No rolling reserve. Your customers pay you directly through Venmo, Cash App, PayPal, or Zelle. There is no merchant relationship for a processor to terminate, because there is no processor. Run multiple P2P apps in rotation to limit single-account exposure, and you stay open.</p>
            </article>

            <article class="pp-persona">
                <span class="pp-persona__tag">Validating an idea</span>
                <p class="pp-persona__quote">"I don't have an LLC yet. I want to know if this thing works before I file paperwork, get an EIN, and hand my SSN to a payment processor."</p>
                <p class="pp-persona__body">Pipe Pay is the fastest legal path to a working checkout. No business entity required. No merchant account application. No tax ID handed to a processor for a two-week verification. Use the Venmo and Cash App accounts you already have, install on a basic WordPress site, and you're live the same afternoon. Validate the idea on real customers; incorporate once there's revenue to justify it.</p>
            </article>

            <article class="pp-persona">
                <span class="pp-persona__tag">Tired of paying fees</span>
                <p class="pp-persona__quote">"I'm bleeding fourteen and a half thousand a year in Stripe fees on $500K of orders. For what, a dashboard?"</p>
                <p class="pp-persona__body">$299 a year, flat. Zero per-transaction fees on the plugin side. Personal Venmo, Cash App, and PayPal Friends &amp; Family take 0%. Business profiles take 1.9% to 2.75% &mdash; still under Stripe. Pipe Pay pays for itself the moment you cross about $10,300 in annual card volume. Every dollar saved on fees from there forward is yours.</p>
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
                <span class="pp-step-num">1</span>
                <h3>Customer chooses Pipe Pay at checkout.</h3>
                <p>The plugin places the order in a custom <code>Awaiting Proof</code> status. No order confirmation email yet, the confirmation is the customer's incentive to upload.</p>
            </div>
            <div class="pp-how-card">
                <span class="pp-step-num">2</span>
                <h3>Customer pays via their P2P app.</h3>
                <p>The payment page shows a QR code, the merchant handle, and an <em>Open Venmo / Cash App / PayPal / Zelle</em> button that takes the customer straight to the right account.</p>
            </div>
            <div class="pp-how-card">
                <span class="pp-step-num">3</span>
                <h3>Customer uploads a screenshot.</h3>
                <p>A sticky bar at the bottom of the page stays visible until upload completes. If the customer tries to close the tab early, the browser warns them.</p>
            </div>
            <div class="pp-how-card">
                <span class="pp-step-num">4</span>
                <h3>AI verifies and the order processes.</h3>
                <p>High-confidence verifications auto-transition to Processing and trigger your normal order emails. Medium and low confidence flow into the Proofs review queue.</p>
            </div>
        </div>

        <div class="pp-how-mock-wrap">
            <ol class="pp-flow" aria-label="End-to-end customer flow">
                <li class="pp-flow__step">
                    <div class="pp-flow__phone">
                        <div class="pp-flow__screen">
                            <div class="pp-flow__row pp-flow__row--head">Payment method</div>
                            <div class="pp-flow__row pp-flow__row--option-selected">
                                <span class="pp-flow__radio"></span>
                                <span>Pipe Pay <small>Venmo, Cash App, PayPal, Zelle</small></span>
                            </div>
                            <div class="pp-flow__row">
                                <span class="pp-flow__radio pp-flow__radio--off"></span>
                                <span>Credit card</span>
                            </div>
                            <div class="pp-flow__row pp-flow__row--cta">Place order</div>
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>01.</b>Choose Pipe Pay</div>
                </li>

                <li class="pp-flow__step">
                    <div class="pp-flow__phone">
                        <div class="pp-flow__screen pp-flow__screen--pay">
                            <div class="pp-flow__amount">$87.50</div>
                            <div class="pp-flow__handle">@northrange-supply</div>
                            <div class="pp-flow__qr-mini" aria-hidden="true"></div>
                            <div class="pp-flow__btn">Open Venmo &rarr;</div>
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>02.</b>Pay through P2P app</div>
                </li>

                <li class="pp-flow__step">
                    <div class="pp-flow__phone">
                        <div class="pp-flow__screen">
                            <div class="pp-flow__upload-status">Uploading screenshot</div>
                            <div class="pp-flow__upload-card">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.5-3.5L11 18"/></svg>
                                <span>venmo-confirmation.png</span>
                                <small>2.4 MB</small>
                            </div>
                            <div class="pp-flow__progress"><span></span></div>
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>03.</b>Upload screenshot</div>
                </li>

                <li class="pp-flow__step">
                    <div class="pp-flow__phone">
                        <div class="pp-flow__screen pp-flow__screen--done">
                            <div class="pp-flow__check" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                            </div>
                            <div class="pp-flow__done-title">Order confirmed</div>
                            <div class="pp-flow__done-sub">AI verified your payment</div>
                            <div class="pp-flow__badge">Processing</div>
                        </div>
                    </div>
                    <div class="pp-flow__caption"><b>04.</b>AI verifies, order processes</div>
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
                <blockquote>Stripe shut us down twice in a year. Switched to Pipe Pay six months ago and we haven't had a single payment-related issue since. The AI verification is legit.</blockquote>
                <div class="pp-attr"><strong>Priya S.</strong></div>
            </div>
            <div class="pp-testimonial">
                <blockquote>I'm not in a restricted vertical, I just hate paying 2.9% on every order. Pipe Pay paid for itself in the first month and now I'm running about 80% of my volume through it.</blockquote>
                <div class="pp-attr"><strong>Dan W.</strong></div>
            </div>
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
                <a class="pp-btn pp-btn--secondary" href="<?php echo esc_url( $tier_single_url ); ?>">Start 7-day trial</a>
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
                <a class="pp-btn pp-btn--primary" href="<?php echo esc_url( $tier_five_url ); ?>">Start 7-day trial</a>
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
                <a class="pp-btn pp-btn--secondary" href="<?php echo esc_url( $tier_unlim_url ); ?>">Start 7-day trial</a>
            </div>
        </div>
        <p class="pp-pricing-fineprint">License entitles you to 1 year of updates and support. The plugin requires an active license to process payments; if your license lapses, the plugin stops accepting new orders until renewed. Cancel anytime before the trial ends and you won't be charged. Once your trial converts to a paid license, all sales are final, no refunds. The 7-day trial is your evaluation window.</p>
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
                <span class="pp-shiplog__date">May 7, 2026</span>
                <span class="pp-shiplog__ver">v1.6.2</span>
                <span class="pp-shiplog__note"><strong>Block Checkout payment fix (critical).</strong> Resolved an issue preventing some Block Checkout orders from advancing through payment, blocking customer screenshot uploads. Update strongly recommended for any store on Block Checkout.</span>
            </li>
            <li>
                <span class="pp-shiplog__date">May 6, 2026</span>
                <span class="pp-shiplog__ver">v1.6.1</span>
                <span class="pp-shiplog__note"><strong>Stability improvements.</strong> Friendlier handling of expired sessions during license activation, smoother tier upgrades, and a handful of smaller reliability improvements.</span>
            </li>
            <li>
                <span class="pp-shiplog__date">May 4, 2026</span>
                <span class="pp-shiplog__ver">v1.6.0</span>
                <span class="pp-shiplog__note"><strong>One-field license activation.</strong> Activate with just your license key &mdash; no product ID lookup. Tier upgrades (trial &rarr; paid, single &rarr; unlimited) flow seamlessly without re-installing the plugin.</span>
            </li>
            <li>
                <span class="pp-shiplog__date">Apr 25, 2026</span>
                <span class="pp-shiplog__ver">v1.5.0</span>
                <span class="pp-shiplog__note"><strong>Automatic updates.</strong> Pipe Pay now appears in the standard &ldquo;Update available&rdquo; notifications in your WordPress plugin list. Click Update Now when a new version ships &mdash; no more manual zip downloads.</span>
            </li>
        </ul>
    </div>
</section>

<!-- ============== 15. FINAL CTA ============== -->
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

</main>

<!-- ============== Footer ============== -->
<footer class="pp-footer">
    <div class="pp-container">
        <div class="pp-footer-row">
            <a class="pp-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <?php echo $logo_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
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
            <form class="pp-footer-signup" action="<?php echo esc_url( $contact_url ); ?>" method="post">
                <label for="pp-footer-email">Release notes:</label>
                <input id="pp-footer-email" type="email" name="email" placeholder="you@yourstore.com" required>
                <button type="submit">Subscribe</button>
            </form>
        </div>
        <div class="pp-footer-ledger">
            <strong>&copy; <?php echo esc_html( date( 'Y' ) ); ?> Pipe Pay</strong>
            <span class="pp-footer-ledger__sep">/</span>
            <span>Independent software</span>
            <span class="pp-footer-ledger__sep">/</span>
            <span>v1.6.2</span>
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
