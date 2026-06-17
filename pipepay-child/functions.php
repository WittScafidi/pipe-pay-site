<?php
/**
 * Pipe Pay child theme - bootstrap.
 * Enqueues parent + child styles, loads Inter from Google Fonts, registers basic theme support.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Single source of truth for the version displayed in the site footer + release bar.
 * Bump this with each plugin release; templates read it via PIPEPAY_SITE_VERSION
 * to avoid the previous pattern of hardcoded version strings drifting per file.
 */
if ( ! defined( 'PIPEPAY_SITE_VERSION' ) ) {
    // Public-facing version. Reflects the most recent version with a published
    // changelog entry, NOT the actual plugin version on dogfood (which may be
    // running unpublicized patch releases per the changelog's own disclaimer:
    // "Bug fix releases between numbered versions are not separately documented
    // unless they introduce a behavior change worth noting"). Customers
    // auto-updating past this number get the patches without a public release
    // narrative for each one.
    define( 'PIPEPAY_SITE_VERSION', '1.9.9' );
}

add_action( 'wp_enqueue_scripts', function() {
    // Parent theme stylesheet
    wp_enqueue_style(
        'generatepress-parent',
        get_template_directory_uri() . '/style.css',
        array(),
        wp_get_theme( 'generatepress' )->get( 'Version' )
    );

    // Preconnect for snappier font fetch
    add_action( 'wp_head', function() {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }, 0 );

    // Manrope sans from Google Fonts. Geist Mono is self-hosted via @font-face
    // in style.css because the Google Fonts build of Geist strips the OpenType
    // feature table, which we need for the "zero" 0 rule (round zeros instead
    // of slashed). The variable-weight font file lives in pipepay-child/fonts/.
    wp_enqueue_style(
        'pipepay-fonts',
        'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap',
        array(),
        null
    );

    // Child stylesheet is auto-enqueued by GeneratePress (handle "generate-child",
    // cache-busted via filemtime) - see generatepress/inc/general.php. Don't
    // double-enqueue here or stale CDN-cached copies of an old ?ver= can win.

    // WC overrides live in their own file and load ONLY on WC pages. Saves
    // ~30KB on every marketing page (was previously appended to style.css
    // and shipped to every visitor). The WC bundle itself is also dequeued
    // on non-WC pages by the priority-99 hook below - this stylesheet is
    // OUR brand-match overrides, not WC's own CSS.
    if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
        $rel = '/woocommerce.css';
        $abs = get_stylesheet_directory() . $rel;
        wp_enqueue_style(
            'pipepay-woocommerce',
            get_stylesheet_directory_uri() . $rel,
            array( 'generate-child' ),
            file_exists( $abs ) ? filemtime( $abs ) : null
        );
    }
}, 20 );

// Apply the site-wide style scope on every page so the same brand styling
// (Manrope, royal-blue accent, header, footer, etc.) applies to every page,
// not only the homepage. The class name is historical; treat it as "pipepay site".
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'pipepay-home';
    if ( is_front_page() ) {
        $classes[] = 'is-front-page';
    }
    return $classes;
} );

// Allow the homepage template to skip GeneratePress's default markup wrappers.
add_action( 'after_setup_theme', function() {
    add_theme_support( 'title-tag' );
} );

// Set a sane <title> on every page. Use pre_get_document_title to bypass
// WordPress's wptexturize step (which converts hyphens to en dashes,
// violating the brief's "no en/em dashes" rule).
//
// Front-page title reads as B2B SaaS positioning ("for WooCommerce stores")
// rather than payment-action vocabulary ("accept Venmo, Cash App, ...") -
// the latter trips phishing classifiers because it textually matches
// phishing-template hero copy. Same product, different shelf placement.
add_filter( 'pre_get_document_title', function( $title ) {
    if ( is_front_page() ) {
        return 'Pipe Pay - P2P Payment Verification for WooCommerce Stores';
    }
    if ( is_singular() ) {
        return single_post_title( '', false ) . ' - Pipe Pay';
    }
    return $title;
} );

// Per-page SEO meta. Fires on every template (was previously home-only). Each
// page gets a description (template-specific where useful, post-excerpt fallback),
// a canonical URL, OG tags, and a Twitter card. Add an og:image asset to enable
// summary_large_image; until then we use the smaller summary card.
add_action( 'wp_head', function() {
    // Per-template descriptions for the templates we have. Slug-keyed.
    // Front-page handled separately below.
    $slug_descriptions = array(
        'about'          => 'About Pipe Pay - an independent WooCommerce plugin for store owners who accept Venmo, Cash App, PayPal, or Zelle directly from customers. We are a verification tool, not a payment processor or money transmitter; we do not hold or transmit funds.',
        'how-it-works'   => 'How Pipe Pay works for WooCommerce store owners: customer pays via their preferred P2P app, uploads a screenshot at checkout, AI verifies it against the order, you only see the ones flagged for review.',
        'pricing'        => 'Pipe Pay annual subscription pricing for WooCommerce stores: $297 / $497 / $997 per year tiers, all with a 7-day free trial. Per-site licensing. Honest yes/no qualification before you buy.',
        'docs'           => 'Pipe Pay documentation for WooCommerce store owners: installation, AI verification setup, admin guide, configuration, order lifecycle, refunds, security, license management, troubleshooting.',
        'contact'        => 'Contact Pipe Pay support. Independent B2B SaaS for WooCommerce merchants. We respond to all merchant inquiries within one business day.',
        'changelog'      => 'Pipe Pay plugin release notes, newest first. WooCommerce-compatibility patches, AI verification improvements, admin queue updates.',
        'refund-policy'  => 'Refund policy for Pipe Pay annual licenses. The 7-day free trial is the evaluation window; once your trial converts to a paid license, all sales are final.',
        'privacy'        => 'Privacy policy for the Pipe Pay merchant-facing website. What data we collect from store owners, how we use it, how long we keep it, your rights.',
        'terms'          => 'Terms of Service for Pipe Pay. The agreement governing use of the WordPress plugin and the pipepay.app merchant-facing site.',
    );

    // Site name + brand keywords - used on every page meta block. The keyword
    // mix deliberately includes B2B SaaS / WooCommerce terms ahead of payment-
    // brand names to balance phishing-classifier signals.
    $site_name     = 'Pipe Pay';
    $site_keywords = 'WooCommerce plugin, B2B SaaS, merchant tool, P2P payment verification, WordPress plugin, payment screenshot verification, merchant admin queue, annual subscription, per-site licensing, Venmo verification, Cash App verification, PayPal verification, Zelle verification';

    if ( is_front_page() ) {
        $title       = 'Pipe Pay - P2P Payment Verification for WooCommerce Stores';
        $description = 'WooCommerce plugin for store owners who accept Venmo, Cash App, PayPal, or Zelle from customers. AI-verified, queue-managed, merchant-controlled. A verification tool installed on your own WordPress store - not a payment processor.';
        $canonical   = home_url( '/' );
    } elseif ( is_home() ) {
        // Blog posts index (the page set as "Posts page" in Settings → Reading).
        $title       = 'Blog - Pipe Pay';
        $description = 'The Pipe Pay blog: practical guides for WooCommerce store owners accepting Venmo, Cash App, PayPal, and Zelle, and for merchants underserved by the major card processors.';
        $canonical   = get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/blog/' );
    } elseif ( is_singular() ) {
        $slug        = get_post_field( 'post_name', get_the_ID() );
        $title       = single_post_title( '', false ) . ' - Pipe Pay';
        $description = $slug_descriptions[ $slug ] ?? '';
        if ( ! $description && has_excerpt() ) {
            $description = wp_strip_all_tags( get_the_excerpt() );
        }
        if ( ! $description ) {
            $description = 'Pipe Pay - a WooCommerce verification plugin for store owners accepting Venmo, Cash App, PayPal, or Zelle. Not a payment processor.';
        }
        $canonical   = wp_get_canonical_url() ?: get_permalink();
    } elseif ( is_archive() || is_search() ) {
        if ( is_search() ) {
            $title       = 'Search - Pipe Pay';
            $description = 'Search the Pipe Pay blog and site.';
            $canonical   = home_url( '/blog/' );
        } else {
            $title       = wp_strip_all_tags( get_the_archive_title() ) . ' - Pipe Pay';
            $description = wp_strip_all_tags( get_the_archive_description() );
            if ( ! $description ) {
                $description = 'Pipe Pay blog archive: guides for WooCommerce store owners accepting Venmo, Cash App, PayPal, and Zelle.';
            }
            $qo          = get_queried_object();
            $canonical   = ( $qo instanceof WP_Term ) ? get_term_link( $qo ) : home_url( '/blog/' );
            if ( is_wp_error( $canonical ) ) { $canonical = home_url( '/blog/' ); }
        }
    } else {
        return; // 404 / other. Skip the meta block.
    }

    // Canonical: only emit on the front page. WordPress's built-in rel_canonical()
    // already handles singular pages; emitting our own would duplicate.
    if ( is_front_page() ) {
        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
    }
    echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
    echo '<meta name="keywords" content="' . esc_attr( $site_keywords ) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";

    // Schema.org SoftwareApplication on the front page. Explicitly classes the
    // site as B2B software ("BusinessApplication") rather than letting crawlers
    // guess from page text - phishing classifiers train on text and miss this
    // structural signal entirely. Disambiguates Pipe Pay from a payment service.
    if ( is_front_page() ) {
        $schema = array(
            '@context'            => 'https://schema.org',
            '@type'               => 'SoftwareApplication',
            'name'                => 'Pipe Pay',
            'url'                 => home_url( '/' ),
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'WooCommerce Plugin',
            'operatingSystem'     => 'WordPress 6.0+, WooCommerce 8.0+',
            'description'         => $description,
            'offers'              => array(
                '@type'         => 'AggregateOffer',
                'lowPrice'      => '299',
                'highPrice'     => '999',
                'priceCurrency' => 'USD',
                'offerCount'    => 3,
            ),
            'publisher'           => array(
                '@type' => 'Organization',
                'name'  => 'Pipe Pay',
                'url'   => home_url( '/' ),
            ),
        );
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }
}, 1 );

// Override the default title separator (en dash) with a plain hyphen,
// matching the brief's "no em/en dashes" rule.
add_filter( 'document_title_separator', function() {
    return '-';
} );

// Bing Webmaster Tools site verification (msvalidate.01). Claims the site in
// Bing Webmaster Tools, which is the lever for clearing the Microsoft Defender
// SmartScreen phishing flag on pipepay.app. Emitted in <head> on every page
// (incl. the homepage, which calls wp_head() in front-page.php). A BingSiteAuth.xml
// file with the same auth code is also deployed at the web root as a fallback.
add_action( 'wp_head', function() {
    echo '<meta name="msvalidate.01" content="08A0F9C24903860AD33D5908E3DE3B44" />' . "\n";
}, 1 );

// Brand favicon (the logo). Uses an SVG icon with the brand color baked in
// (the inline header logo uses currentColor; a favicon has no CSS context so
// we ship a self-colored copy). Apple touch icon points to the same SVG -
// modern Safari renders it; older iOS that wants PNG just falls back to no
// touch icon, which is harmless.
add_action( 'wp_head', function() {
    $favicon = get_stylesheet_directory_uri() . '/favicon.svg';
    echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $favicon ) . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url( $favicon ) . '">' . "\n";
}, 2 );

// Override Site Icon if WP admin has one set - our favicon takes precedence.
add_filter( 'get_site_icon_url', function( $url ) {
    return get_stylesheet_directory_uri() . '/favicon.svg';
} );

// WooCommerce loads ~6 JS files + 4 CSS files on EVERY page, including marketing
// pages that have no cart/shop functionality. The buttons on home/pricing are
// plain anchor links to /checkout/?add-to-cart=N, not AJAX add-to-cart, so the
// WC frontend bundle is dead weight there. Dequeue on non-WC pages.
add_action( 'wp_enqueue_scripts', function() {
    if ( is_admin() ) { return; }
    $is_wc_page = function_exists( 'is_woocommerce' ) && (
        is_woocommerce() || is_cart() || is_checkout() || is_account_page()
    );
    if ( $is_wc_page ) { return; }

    // Scripts
    // NOTE: wc-order-attribution + sourcebuster-js (and their js-cookie dep) are
    // deliberately NOT dequeued here. They are tiny (~6KB total) and MUST run on
    // marketing pages (home/pricing) to capture first-touch UTM/referrer source.
    // Dequeuing them broke source attribution entirely: every order logged as
    // (direct)/typein because sourcebuster first ran on /checkout/ and saw only
    // the internal /pricing/ referrer. The heavy WC bundle below stays dequeued.
    foreach ( array(
        'wc-add-to-cart',
        'wc-cart-fragments',
        'woocommerce',
        'jquery-blockui',
    ) as $h ) {
        wp_dequeue_script( $h );
        wp_deregister_script( $h );
    }

    // Styles (the wc-blocks bundle alone is several hundred KB)
    foreach ( array(
        'woocommerce-general',
        'woocommerce-layout',
        'woocommerce-smallscreen',
        'wc-blocks-style',
        'wc-block-style',
    ) as $h ) {
        wp_dequeue_style( $h );
        wp_deregister_style( $h );
    }
}, 99 );

// Order-attribution cookie lifetime. WooCommerce core defaults the Sourcebuster
// first-touch cookie to 0.00001 months (~26 seconds), which discards source
// attribution for anyone who lands now and converts later. Sourcebuster computes
// expiry as (lifetime * 30 days), so 1 == 30 days. This keeps a visitor's
// original source (e.g. a Reddit click) attached through the 7-day trial window
// and typical multi-visit consideration before they convert.
add_filter( 'wc_order_attribution_cookie_lifetime_months', function() {
    return 1; // 30 days
} );

// Mobile hamburger toggle. Inline because the script is tiny and a separate
// HTTP request costs more than the inline bytes.
add_action( 'wp_footer', function() {
    ?>
    <script>
    (function () {
        var btn = document.querySelector('.pp-nav-toggle');
        var nav = document.getElementById('pp-primary-nav');
        if (!btn || !nav) return;

        // Elements made inert while the drawer is open. We avoid adding inert
        // to the <header> itself because the close button lives inside it.
        var inertTargets = [
            document.getElementById('content'),
            document.querySelector('.pp-footer'),
            document.querySelector('.pp-release-bar')
        ].filter(Boolean);

        function setInert(on) {
            inertTargets.forEach(function (el) {
                if (on) { el.setAttribute('inert', ''); }
                else    { el.removeAttribute('inert'); }
            });
        }

        function closeMenu(returnFocus) {
            btn.classList.remove('is-open');
            nav.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            btn.setAttribute('aria-label', 'Open menu');
            setInert(false);
            if (returnFocus) { btn.focus(); }
        }
        function openMenu() {
            btn.classList.add('is-open');
            nav.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
            btn.setAttribute('aria-label', 'Close menu');
            setInert(true);
            // Move focus to the first focusable item inside the drawer.
            var firstLink = nav.querySelector('a');
            if (firstLink) { firstLink.focus(); }
        }
        btn.addEventListener('click', function () {
            // returnFocus=false on toggle-close because btn is already focused.
            if (nav.classList.contains('is-open')) closeMenu(false); else openMenu();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) closeMenu(true);
        });
        nav.addEventListener('click', function (e) {
            // Link click = drawer dismisses + browser navigates. Don't return focus.
            if (e.target.tagName === 'A') closeMenu(false);
        });
    })();
    </script>
    <?php
}, 99 );


// WooCommerce: enforce ONE Pipe Pay product per cart. When a customer
// switches tiers (or clicks trial after browsing a paid tier, or vice
// versa), we drop any other Pipe Pay products and keep only the most
// recently added one.
//
// We hook `woocommerce_add_to_cart` (action that fires AFTER the new item
// has been inserted into the cart) rather than the older
// `woocommerce_add_cart_item_data` filter that runs BEFORE insertion. The
// after-the-fact action is more robust:
//
//   - Filter priority conflicts with other plugins can't cause the dedup
//     to be skipped or run on stale state.
//   - We have full visibility into the resulting cart and can target
//     other-Pipe-Pay items by cart_item_key, preserving the just-added
//     one even if its product_id collides with an existing entry.
//   - Block Cart / Block Checkout's Store API add-item endpoint also
//     fires this action, so the dedup covers both the classic
//     ?add-to-cart=N URL flow and the Store API flow uniformly.
//
// Constraint: we never remove the just-added item (matched by cart_item_key).
add_action( 'woocommerce_add_to_cart', function ( $cart_item_key, $product_id ) {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }
    $pipepay_product_ids = array( 34, 35, 36, 38, 526, 527, 528 );
    if ( ! in_array( (int) $product_id, $pipepay_product_ids, true ) ) {
        return;
    }
    foreach ( WC()->cart->get_cart() as $key => $item ) {
        if ( $key === $cart_item_key ) {
            continue;
        }
        if ( in_array( (int) $item['product_id'], $pipepay_product_ids, true ) ) {
            WC()->cart->remove_cart_item( $key );
        }
    }
}, 10, 2 );

// Pre-insertion guard that runs INSIDE WC_Cart::add_to_cart(), BEFORE the
// `sold_individually` "already in cart" duplicate check fires. Pipe Pay
// tier products are sold_individually, so adding the SAME product twice
// produces a "You cannot add another ..." error notice — even though the
// customer-intent ("I want this tier") is clear and idempotent.
//
// Hook choice matters: `woocommerce_add_cart_item_data` fires INSIDE
// `WC_Cart::add_to_cart()` itself (just before `find_product_in_cart()`),
// which means it runs on EVERY add-to-cart path including:
//   - Classic `?add-to-cart=N` URL → WC_Form_Handler::add_to_cart_action()
//   - Block Cart / Block Checkout → Store API CartAddItem route
//   - Programmatic `WC()->cart->add_to_cart()` calls from other plugins
//
// An earlier attempt used `woocommerce_add_to_cart_validation` which only
// fires from the form-handler path; Block Checkout's Store API endpoint
// bypasses it entirely, so the user kept seeing the duplicate error.
//
// Behavior: when ANY Pipe Pay product is about to be added, clear all
// existing Pipe Pay items from the cart first. By the time WC reaches the
// duplicate check, the cart is empty and the new item adds cleanly with
// no error notice. Cross-tier intent (Single → 5 Sites → Unlimited) keeps
// working because the cart only ever contains the most-recently-clicked
// Pipe Pay product.
//
// Priority 9 so the trial-intent filter at priority 11 (below) still runs
// after this on the cart_item_data array — they don't compete for the
// same operation. Belt-and-braces: the post-insert action above also
// covers any path that bypasses this filter.
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return $cart_item_data;
    }
    $pipepay_product_ids = array( 34, 35, 36, 38, 526, 527, 528 );
    if ( ! in_array( (int) $product_id, $pipepay_product_ids, true ) ) {
        return $cart_item_data;
    }
    foreach ( WC()->cart->get_cart() as $key => $item ) {
        if ( in_array( (int) $item['product_id'], $pipepay_product_ids, true ) ) {
            WC()->cart->remove_cart_item( $key );
        }
    }
    return $cart_item_data;
}, 9, 2 );

// WooCommerce: capture tier intent on trial signup. When a customer clicks
// "Start 7-day trial" from a specific pricing card, the URL carries
// ?intent=34|35|36 alongside the trial product (38). We persist that as a
// cart-item meta so the trial order can pre-fill the customer's preferred
// paid tier at conversion time. Header / hero / final-CTA trial buttons
// don't carry intent - those customers pick their tier at conversion.
//
// Strict allow-list: only {34, 35, 36} are accepted. Any other value is
// silently dropped, and the order goes through with no intent stored.
// Priority 11 so we run AFTER the cart-deduplication filter at priority 10.
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {
    if ( 38 !== (int) $product_id ) {
        return $cart_item_data;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $intent = isset( $_GET['intent'] ) ? (int) $_GET['intent'] : 0;
    if ( in_array( $intent, array( 34, 35, 36 ), true ) ) {
        $cart_item_data['_pipepay_intended_tier_pid'] = $intent;
    }
    return $cart_item_data;
}, 11, 2 );

// WooCommerce: persist tier intent from the cart item to the order line item
// so it survives session expiry and is queryable later. Stored as an order
// item meta on the trial product line. Read at trial conversion (Stage 3,
// lives in the renewal mu-plugin) to pre-fill the customer's preferred
// paid tier on the conversion checkout page.
add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values, $order ) {
    if ( ! empty( $values['_pipepay_intended_tier_pid'] ) ) {
        $item->add_meta_data(
            '_pipepay_intended_tier_pid',
            (int) $values['_pipepay_intended_tier_pid'],
            true
        );
    }
}, 10, 4 );

/**
 * Helper: is this order a Pipe Pay trial (contains product 38)?
 *
 * Used by the trial-specific email overrides + auto-complete logic below
 * and by the customer-completed-order.php template override.
 */
function pp_order_is_trial( $order ) {
    if ( ! $order instanceof WC_Order ) {
        $order = wc_get_order( $order );
    }
    if ( ! $order ) {
        return false;
    }
    foreach ( $order->get_items() as $item ) {
        if ( (int) $item->get_product_id() === 38 ) {
            return true;
        }
    }
    return false;
}

/**
 * Helper: is this order a Pipe Pay paid tier (products 34, 35, or 36)?
 *
 * Used by `customer-completed-order.php` to delegate to paid-completed.php
 * and by the paid-tier subject/heading filters below.
 */
function pp_order_is_paid_tier( $order ) {
    if ( ! $order instanceof WC_Order ) {
        $order = wc_get_order( $order );
    }
    if ( ! $order ) {
        return false;
    }
    foreach ( $order->get_items() as $item ) {
        if ( in_array( (int) $item->get_product_id(), [ 34, 35, 36 ], true ) ) {
            return true;
        }
    }
    return false;
}

// Trial orders ($0, no payment to verify) should skip the "processing"
// status entirely and go straight to "completed" so the customer gets their
// license + download link immediately. Hook fires when WC transitions an
// order to processing; we bump it to completed in the same request.
add_action( 'woocommerce_order_status_processing', function( $order_id, $order ) {
    if ( ! $order ) {
        $order = wc_get_order( $order_id );
    }
    if ( ! pp_order_is_trial( $order ) ) {
        return;
    }
    $order->update_status( 'completed', __( 'Trial: auto-completed (no payment required).', 'pipe-pay' ) );
}, 10, 2 );

// Suppress the "Your order has been received" customer_processing_order
// email for trials. They auto-complete to "completed" in the same request
// (above), so sending both processing + completed emails is noise. Setting
// the recipient to empty makes WC skip the send entirely.
add_filter( 'woocommerce_email_recipient_customer_processing_order', function( $recipient, $order ) {
    if ( pp_order_is_trial( $order ) ) {
        return '';
    }
    return $recipient;
}, 10, 2 );

// Subject line for the customer_completed_order email - branched per tier.
// Trials: "Your Pipe Pay 7-day trial is live". Paid: "Your Pipe Pay license is ready".
// Default WC ("Your Pipe Pay order is now complete") isn't precise for either.
add_filter( 'woocommerce_email_subject_customer_completed_order', function( $subject, $order ) {
    if ( pp_order_is_trial( $order ) ) {
        return 'Your Pipe Pay 7-day trial is live';
    }
    if ( pp_order_is_paid_tier( $order ) ) {
        return 'Your Pipe Pay license is ready';
    }
    return $subject;
}, 10, 2 );

// Heading rendered at the top of the email body, branched per tier.
add_filter( 'woocommerce_email_heading_customer_completed_order', function( $heading, $order ) {
    if ( pp_order_is_trial( $order ) ) {
        return 'Your trial is live';
    }
    if ( pp_order_is_paid_tier( $order ) ) {
        return 'Your license is ready';
    }
    return $heading;
}, 10, 2 );

// Pipe Pay-specific subject + heading for the WC customer_new_account email,
// which fires when a customer auto-creates an account during checkout.
add_filter( 'woocommerce_email_subject_customer_new_account', function( $subject ) {
    return 'Welcome to Pipe Pay - set your password';
} );
add_filter( 'woocommerce_email_heading_customer_new_account', function( $heading ) {
    return 'Welcome to Pipe Pay';
} );

// Pipe Pay-specific subject + heading for the on-hold email (used by the
// gateway's "payment pending - upload proof" flow).
add_filter( 'woocommerce_email_subject_customer_on_hold_order', function( $subject ) {
    return 'Complete your Pipe Pay order - send payment';
} );
add_filter( 'woocommerce_email_heading_customer_on_hold_order', function( $heading ) {
    return 'Send your P2P payment';
} );

// WooCommerce: force the no-sidebar layout via GeneratePress's filter so shop,
// cart, checkout, my-account, and single product pages get the full container width.
add_filter( 'generate_sidebar_layout', function( $layout ) {
    if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
        return 'no-sidebar';
    }
    return $layout;
} );

// WooCommerce: render a Pipe Pay-styled page hero above the WC main content
// so shop / cart / checkout / my-account look like the rest of the site.
add_action( 'woocommerce_before_main_content', function() {
    $kicker = 'Store';
    $title  = 'Shop';
    $sub    = '';

    if ( is_shop() || is_product_taxonomy() ) {
        $kicker = 'Store';
        $title  = 'Pipe Pay licenses';
        $sub    = 'Pick a license size for the number of WooCommerce stores you run. Every tier includes a 7-day free trial; no card is charged until day eight.';
    } elseif ( is_product() ) {
        $kicker = 'Product';
        $title  = single_post_title( '', false );
    } elseif ( is_cart() ) {
        $kicker = 'Cart';
        $title  = 'Your cart';
    } elseif ( is_checkout() ) {
        $kicker = 'Checkout';
        $title  = 'Complete your purchase';
        $sub    = 'Your order is placed in pending status. After we receive your P2P payment we will activate your license and email the key.';
    } elseif ( is_account_page() ) {
        $kicker = 'Account';
        $title  = 'Your account';
    }
    ?>
    <section class="pp-page-hero">
        <div class="pp-container">
            <span class="pp-page-hero__kicker"><?php echo esc_html( $kicker ); ?></span>
            <h1 class="pp-page-title"><?php echo esc_html( $title ); ?></h1>
            <?php if ( $sub ) : ?><p class="pp-page-hero__sub"><?php echo esc_html( $sub ); ?></p><?php endif; ?>
        </div>
    </section>
    <section class="pp-section pp-section--tight">
        <div class="pp-container">
    <?php
}, 5 );

// Close the section/container we opened in woocommerce_before_main_content.
add_action( 'woocommerce_after_main_content', function() {
    ?>
        </div>
    </section>
    <?php
}, 50 );

// Custom robots.txt: allow everything crawler-relevant, block wp-admin
// (with the /admin-ajax.php exception many WP themes need), and reference
// the WordPress core sitemap index.
add_filter( 'robots_txt', function( $output, $public ) {
    if ( ! $public ) { return $output; }
    $sitemap_url = home_url( '/wp-sitemap.xml' );
    return "User-agent: *\n"
         . "Allow: /\n"
         . "Disallow: /wp-admin/\n"
         . "Allow: /wp-admin/admin-ajax.php\n"
         . "\n"
         . "Sitemap: {$sitemap_url}\n";
}, 10, 2 );

// Strip trailing ".00" from Pipe Pay license-product price HTML so
// WC's price-display output (cart-block "New in store" cross-sells, mini-cart,
// product widgets, /shop, etc.) matches the whole-dollar formatting used on
// the marketing pages (homepage pricing cards + /pricing). Without this the
// same product reads as "\\" on one surface and "\\.00" on the next,
// which makes the cart's cross-sell block feel disconnected from the rest of
// the site even though the layout is otherwise correct.
//
// Scoped to the four license SKUs (Single, 5, Unlimited, Trial). Does NOT
// affect actual checkout totals or order receipts — those use
// wc_format_price_range() / order->get_formatted_order_total() internally,
// not the price-html filter, and we want those to show ".00" so the legal
// total reads unambiguously to the customer + tax authorities.
add_filter( 'woocommerce_get_price_html', function( $price_html, $product ) {
    if ( ! $product instanceof WC_Product ) {
        return $price_html;
    }
    $pipepay_ids = array( 34, 35, 36, 38, 526, 527, 528 );
    if ( ! in_array( (int) $product->get_id(), $pipepay_ids, true ) ) {
        return $price_html;
    }
    return preg_replace( '/(\d)\.00(?!\d)/', '$1', $price_html );
}, 10, 2 );


/**
 * License tiers, card lane vs payment-app lane: paying by CARD happens through
 * Stripe Checkout (the auto-renewing buttons on the pricing cards, via the
 * pipepay-stripe-subs bridge). The WC checkout is the payment-app lane (Venmo /
 * Cash App / PayPal / Zelle, manual renewal) — card gateways are removed here so
 * a card buyer cannot end up with a one-time charge that never auto-renews.
 */
add_filter( 'woocommerce_available_payment_gateways', function ( $gateways ) {
    if ( is_admin() || ! function_exists( 'WC' ) ) {
        return $gateways;
    }

    $has_annual  = false;
    $has_monthly = false;

    $classify = function ( $pid ) use ( &$has_annual, &$has_monthly ) {
        if ( in_array( $pid, array( 34, 35, 36 ), true ) ) {
            $has_annual = true;
        } elseif ( in_array( $pid, array( 526, 527, 528 ), true ) ) {
            $has_monthly = true;
        }
    };

    // Normal checkout: inspect the cart.
    if ( WC()->cart ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $classify( (int) $cart_item['product_id'] );
        }
    }

    // Pay-for-order page (admin-created orders): the cart is empty there, so
    // inspect the order's line items instead.
    if ( ! $has_annual && ! $has_monthly && function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
        $order_id = absint( get_query_var( 'order-pay' ) );
        $order    = $order_id ? wc_get_order( $order_id ) : false;
        if ( $order ) {
            foreach ( $order->get_items() as $item ) {
                $classify( (int) $item->get_product_id() );
            }
        }
    }

    if ( $has_monthly ) {
        // Monthly tiers are card-subscription only (P2P cannot auto-bill):
        // keep just the auto-renewing card gateway.
        foreach ( array_keys( $gateways ) as $gateway_id ) {
            if ( 'pipepay_stripe_sub' !== $gateway_id ) {
                unset( $gateways[ $gateway_id ] );
            }
        }
        return $gateways;
    }
    if ( $has_annual ) {
        // Annual tiers: Pipe Pay (payment apps, manual renewal) + the
        // auto-renewing card gateway. The one-time card gateway is removed
        // so a card payment is always the auto-renewing subscription.
        foreach ( array_keys( $gateways ) as $gateway_id ) {
            if ( 0 === strpos( $gateway_id, 'stripe_' ) ) {
                unset( $gateways[ $gateway_id ] );
            }
        }
    }
    return $gateways;
} );
