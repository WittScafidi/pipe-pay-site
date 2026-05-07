<?php
/**
 * Pipe Pay child theme — bootstrap.
 * Enqueues parent + child styles, loads Inter from Google Fonts, registers basic theme support.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

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

    // Manrope sans + Geist Mono (Google Fonts)
    wp_enqueue_style(
        'pipepay-fonts',
        'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap',
        array(),
        null
    );

    // Child stylesheet is auto-enqueued by GeneratePress (handle "generate-child",
    // cache-busted via filemtime) — see generatepress/inc/general.php. Don't
    // double-enqueue here or stale CDN-cached copies of an old ?ver= can win.
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
add_filter( 'pre_get_document_title', function( $title ) {
    if ( is_front_page() ) {
        return 'Pipe Pay - Accept Venmo, Cash App, PayPal, and Zelle in WooCommerce';
    }
    if ( is_singular() ) {
        return single_post_title( '', false ) . ' - Pipe Pay';
    }
    return $title;
} );

// Inject a meta description on the homepage.
add_action( 'wp_head', function() {
    if ( is_front_page() ) {
        echo '<meta name="description" content="A WooCommerce plugin that captures customer P2P payment screenshots and verifies them with AI, so the only orders you touch are the ones the AI flagged.">' . "\n";
        echo '<meta property="og:title" content="Pipe Pay - Accept Venmo, Cash App, PayPal, and Zelle in WooCommerce">' . "\n";
        echo '<meta property="og:description" content="A workflow layer for WooCommerce stores that already accept P2P payments. AI-verified screenshots, configurable auto-approval, no manual reconciliation.">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:url" content="https://pipepay.app/">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
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
        function close() {
            btn.classList.remove('is-open');
            nav.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            btn.setAttribute('aria-label', 'Open menu');
        }
        function open() {
            btn.classList.add('is-open');
            nav.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
            btn.setAttribute('aria-label', 'Close menu');
        }
        btn.addEventListener('click', function () {
            if (nav.classList.contains('is-open')) close(); else open();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) close();
        });
        nav.addEventListener('click', function (e) {
            if (e.target.tagName === 'A') close();
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
