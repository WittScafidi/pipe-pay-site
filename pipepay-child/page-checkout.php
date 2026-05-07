<?php
/**
 * Template for the Checkout page (slug: checkout).
 * Page content is the [woocommerce_checkout] / Checkout block. Hero copy
 * adjusts based on whether the cart contains the trial product or a paid tier.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$kicker = 'Checkout';
$title  = 'Complete your purchase';
$sub    = 'Your order is placed in pending status. After we receive your P2P payment we will activate your license and email the key.';

if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
    $has_trial = false;
    foreach ( WC()->cart->get_cart() as $item ) {
        if ( (int) $item['product_id'] === 38 ) {
            $has_trial = true;
            break;
        }
    }
    if ( $has_trial ) {
        $kicker = 'Trial';
        $title  = 'Start your 7-day free trial.';
        $sub    = 'Drop in your details. No card required to start. We will email you a trial license and a download link for the plugin.';
    }
}
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker"><?php echo esc_html( $kicker ); ?></span>
        <h1 class="pp-page-title"><?php echo esc_html( $title ); ?></h1>
        <p class="pp-page-hero__sub"><?php echo esc_html( $sub ); ?></p>
    </div>
</section>

<section class="pp-section pp-section--tight pp-checkout-page">
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
