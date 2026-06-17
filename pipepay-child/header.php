<?php
/**
 * Header used by all non-homepage pages.
 * front-page.php inlines its own header markup and bypasses get_header().
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="pp-skip" href="#content">Skip to content</a>

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
            /* Conditional Cart link - only renders when WC is active AND there's
             * something in the cart. Lets users reach /cart to remove items
             * (otherwise the "View cart" hint in WC duplicate-add error notices
             * is a dead-end because the nav has no cart surface). */
            if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->get_cart_contents_count() > 0 ) :
                $pp_cart_count = WC()->cart->get_cart_contents_count();
            ?>
            <a class="pp-nav-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">Cart <span class="pp-nav-cart__count"><?php echo esc_html( $pp_cart_count ); ?></span></a>
            <?php endif; ?>
            <a class="pp-btn pp-btn--primary" href="<?php echo esc_url( home_url( '/checkout/?add-to-cart=38' ) ); ?>">Start free trial</a>
        </nav>
    </div>
</header>

<main id="content" class="pp-main">
