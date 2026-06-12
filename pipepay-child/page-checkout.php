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

// Tier-aware hero copy. The payment choice itself lives INSIDE the WC
// checkout below: annual carts offer Pipe Pay (payment apps, manual renewal)
// and Credit/Debit Card (auto-renewing Stripe subscription, via the
// pipepay_stripe_sub gateway); monthly carts offer the card gateway only.
$pp_monthly_labels = array( 526 => '$35/month', 527 => '$65/month', 528 => '$129/month' );

if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
    $has_trial = false;
    $monthly_label = '';
    $has_annual = false;
    foreach ( WC()->cart->get_cart() as $item ) {
        $pid = (int) $item['product_id'];
        if ( 38 === $pid ) {
            $has_trial = true;
        } elseif ( isset( $pp_monthly_labels[ $pid ] ) ) {
            $monthly_label = $pp_monthly_labels[ $pid ];
        } elseif ( in_array( $pid, array( 34, 35, 36 ), true ) ) {
            $has_annual = true;
        }
    }
    if ( $has_trial ) {
        $kicker = 'Trial';
        $title  = 'Start your 7-day free trial.';
        $sub    = 'Drop in your details. No card required to start. We will email you a trial license and a download link for the plugin.';
    } elseif ( $monthly_label ) {
        $kicker = 'Subscribe';
        $title  = 'Start your monthly subscription.';
        $sub    = 'Billed ' . $monthly_label . ' by card through Stripe. Cancel anytime from your billing portal.';
    } elseif ( $has_annual ) {
        $sub = 'Pay by card and your license auto-renews each year, or pay with a payment app (Venmo, Cash App, PayPal, Zelle) and renew manually. Pick your payment method below.';
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
        <p class="pp-checkout-consent">By placing this order you agree to our <a href="<?php echo esc_url( home_url( '/terms' ) ); ?>">Terms of Service</a> and <a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>">Privacy Policy</a>. License sales are subject to our <a href="<?php echo esc_url( home_url( '/refund-policy' ) ); ?>">Refund Policy</a>.</p>
    </div>
</section>

<?php get_footer(); ?>
