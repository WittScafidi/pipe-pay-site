<?php
/**
 * Template for the My Account page (slug: my-account).
 * Page content is the [woocommerce_my_account] shortcode. Hero copy adapts
 * to logged-in vs logged-out state. Uses a wide container (not narrow) and
 * no prose wrapper so the WC dashboard grid (sidebar + content) renders
 * with full width.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

if ( is_user_logged_in() ) {
    $kicker = 'Account';
    $title  = 'Your account';
    $sub    = '';
} else {
    $kicker = 'Account';
    $title  = 'Sign in to Pipe Pay';
    $sub    = 'Sign in to manage your licenses, view orders, and download the latest plugin build.';
}
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker"><?php echo esc_html( $kicker ); ?></span>
        <h1 class="pp-page-title"><?php echo esc_html( $title ); ?></h1>
        <?php if ( $sub ) : ?>
            <p class="pp-page-hero__sub"><?php echo esc_html( $sub ); ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="pp-section pp-section--tight pp-myaccount-page">
    <div class="pp-container">
        <?php
        while ( have_posts() ) :
            the_post();
            the_content();
        endwhile;
        ?>
    </div>
</section>

<?php get_footer(); ?>
