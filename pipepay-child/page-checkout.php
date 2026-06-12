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

// Card-subscription wiring: annual tiers (34/35/36) get a card-vs-payment-app
// chooser; monthly tiers (526/527/528) are card-only and embed immediately.
// Price IDs come from the same wp-config constants the bridge plugin uses.
$pp_embed_mode = '';
$pp_embed_price_id = '';
$pp_embed_price_label = '';
$pp_annual_map = array(
    34 => array( defined( 'PIPEPAY_STRIPE_PRICE_SINGLE_YR' ) ? PIPEPAY_STRIPE_PRICE_SINGLE_YR : '', '$299/yr' ),
    35 => array( defined( 'PIPEPAY_STRIPE_PRICE_FIVE_YR' ) ? PIPEPAY_STRIPE_PRICE_FIVE_YR : '', '$499/yr' ),
    36 => array( defined( 'PIPEPAY_STRIPE_PRICE_UNLIM_YR' ) ? PIPEPAY_STRIPE_PRICE_UNLIM_YR : '', '$999/yr' ),
);
$pp_monthly_map = array(
    526 => array( defined( 'PIPEPAY_STRIPE_PRICE_SINGLE' ) ? PIPEPAY_STRIPE_PRICE_SINGLE : '', '$35/mo' ),
    527 => array( defined( 'PIPEPAY_STRIPE_PRICE_FIVE' ) ? PIPEPAY_STRIPE_PRICE_FIVE : '', '$65/mo' ),
    528 => array( defined( 'PIPEPAY_STRIPE_PRICE_UNLIM' ) ? PIPEPAY_STRIPE_PRICE_UNLIM : '', '$129/mo' ),
);

if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
    $has_trial = false;
    foreach ( WC()->cart->get_cart() as $item ) {
        $pid = (int) $item['product_id'];
        if ( 38 === $pid ) {
            $has_trial = true;
        } elseif ( isset( $pp_annual_map[ $pid ] ) && $pp_annual_map[ $pid ][0] ) {
            $pp_embed_mode        = 'choice';
            $pp_embed_price_id    = $pp_annual_map[ $pid ][0];
            $pp_embed_price_label = $pp_annual_map[ $pid ][1];
        } elseif ( isset( $pp_monthly_map[ $pid ] ) && $pp_monthly_map[ $pid ][0] ) {
            $pp_embed_mode        = 'auto';
            $pp_embed_price_id    = $pp_monthly_map[ $pid ][0];
            $pp_embed_price_label = $pp_monthly_map[ $pid ][1];
        }
    }
    if ( $has_trial ) {
        $kicker = 'Trial';
        $title  = 'Start your 7-day free trial.';
        $sub    = 'Drop in your details. No card required to start. We will email you a trial license and a download link for the plugin.';
    } elseif ( 'choice' === $pp_embed_mode ) {
        $sub = 'Pay by card and your license auto-renews each year, or pay with a payment app and renew manually. Choose below.';
    } elseif ( 'auto' === $pp_embed_mode ) {
        $kicker = 'Subscribe';
        $title  = 'Start your monthly subscription.';
        $sub    = 'Billed ' . $pp_embed_price_label . ' by card. Cancel anytime from your billing portal.';
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
        if ( $pp_embed_mode ) {
            include __DIR__ . '/partials/checkout-card-embed.php';
        }
        if ( 'auto' !== $pp_embed_mode ) :
            // Monthly is card-only: the WC form (whose only gateway would be
            // Pipe Pay) is not rendered at all. Annual wraps it so the chooser
            // can reveal it; trial/other carts render it normally.
            if ( 'choice' === $pp_embed_mode ) {
                echo '<div id="pp-wc-checkout-form" hidden>';
            }
            while ( have_posts() ) :
                the_post();
                the_content();
            endwhile;
            if ( 'choice' === $pp_embed_mode ) {
                echo '</div>';
            }
        endif;
        ?>
        <p class="pp-checkout-consent">By placing this order you agree to our <a href="<?php echo esc_url( home_url( '/terms' ) ); ?>">Terms of Service</a> and <a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>">Privacy Policy</a>. License sales are subject to our <a href="<?php echo esc_url( home_url( '/refund-policy' ) ); ?>">Refund Policy</a>.</p>
    </div>
</section>

<?php get_footer(); ?>
