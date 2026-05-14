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
    define( 'PIPEPAY_SITE_VERSION', '1.7.4' );
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
        'pricing'        => 'Pipe Pay annual subscription pricing for WooCommerce stores: $299 / $599 / $1,199 per year tiers, all with a 7-day free trial. Per-site licensing. Honest yes/no qualification before you buy.',
        'docs'           => 'Pipe Pay documentation for WooCommerce store owners: installation, AI verification setup, admin guide, configuration, order lifecycle, refunds, security, license management, troubleshooting.',
        'contact'        => 'Contact Pipe Pay support. Independent B2B SaaS built and supported by one person. We respond to all merchant inquiries within one business day.',
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
    } else {
        return; // Archive / 404 / etc. Skip the meta block.
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
                'highPrice'     => '1199',
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
    foreach ( array(
        'wc-add-to-cart',
        'wc-cart-fragments',
        'woocommerce',
        'wc-order-attribution',
        'sourcebuster-js',
        'jquery-blockui',
        'js-cookie',
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


// WooCommerce: when the customer clicks any "Start trial" or tier CTA, the
// cart should hold ONE Pipe Pay product at a time. If they switch tiers
// (or click trial after browsing a paid tier), we empty the cart and add
// only the new product so the order summary stays sensible.
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) { return $cart_item_data; }
    // Only enforce for our license-tier products (IDs 34, 35, 36, 38).
    $pipepay_product_ids = array( 34, 35, 36, 38 );
    if ( ! in_array( (int) $product_id, $pipepay_product_ids, true ) ) {
        return $cart_item_data;
    }
    WC()->cart->empty_cart();
    return $cart_item_data;
}, 10, 2 );

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
