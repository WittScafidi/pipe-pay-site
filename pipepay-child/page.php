<?php
/**
 * Default page template - used by simple prose pages whose content
 * comes from the WordPress post_content field (privacy, terms, refund-policy).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

/**
 * Slug-aware kicker for pages that use this default template. Legal pages
 * get "Legal" to match /privacy and /sub-processors. WC's /cart page lands
 * here too (it's a regular WP page; /shop and /checkout use their own WC
 * templates). For any unmapped slug we fall back to "Pipe Pay" — that
 * shouldn't happen in practice but is safe.
 *
 * The "Last updated" line at the bottom is only meaningful for legal pages
 * (whose modified date carries real semantic weight). For /cart and any
 * future utility page it's noise based on WC's internal page-edit history,
 * so we suppress it.
 */
$pp_slug      = get_post_field( 'post_name' );
$pp_legal     = array( 'terms', 'refund-policy', 'refunds' );
$pp_utility   = array(
    'cart'     => 'Cart',
    'checkout' => 'Checkout',
);

if ( in_array( $pp_slug, $pp_legal, true ) ) {
    $pp_kicker       = 'Legal';
    $pp_show_updated = true;
} elseif ( isset( $pp_utility[ $pp_slug ] ) ) {
    $pp_kicker       = $pp_utility[ $pp_slug ];
    $pp_show_updated = false;
} else {
    $pp_kicker       = 'Pipe Pay';
    $pp_show_updated = false;
}
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker"><?php echo esc_html( $pp_kicker ); ?></span>
        <h1 class="pp-page-title"><?php the_title(); ?></h1>
        <?php if ( has_excerpt() ) : ?>
            <p class="pp-page-hero__sub"><?php echo esc_html( get_the_excerpt() ); ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container pp-container--narrow">
        <article class="pp-prose<?php echo in_array( $pp_slug, $pp_legal, true ) ? ' pp-prose--legal' : ''; ?>">
            <?php
            while ( have_posts() ) :
                the_post();
                the_content();
            endwhile;
            ?>
            <?php if ( $pp_show_updated ) : ?>
                <p class="pp-prose__updated">Last updated: <?php echo esc_html( get_the_modified_date( 'F j, Y' ) ); ?></p>
            <?php endif; ?>
        </article>
    </div>
</section>

<?php get_footer(); ?>
