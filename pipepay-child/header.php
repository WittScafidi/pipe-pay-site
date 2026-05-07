<?php
/**
 * Header used by all non-homepage pages.
 * front-page.php inlines its own header markup and bypasses get_header().
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
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
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

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
            <a class="pp-btn pp-btn--primary" href="<?php echo esc_url( home_url( '/checkout/?add-to-cart=38' ) ); ?>">Start free trial</a>
        </nav>
    </div>
</header>

<main class="pp-main">
