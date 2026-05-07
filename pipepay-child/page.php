<?php
/**
 * Default page template — used by simple prose pages whose content
 * comes from the WordPress post_content field (privacy, terms, refund-policy).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">Pipe Pay</span>
        <h1 class="pp-page-title"><?php the_title(); ?></h1>
        <?php if ( has_excerpt() ) : ?>
            <p class="pp-page-hero__sub"><?php echo esc_html( get_the_excerpt() ); ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container pp-container--narrow">
        <article class="pp-prose pp-prose--legal">
            <?php
            while ( have_posts() ) :
                the_post();
                the_content();
            endwhile;
            ?>
            <p class="pp-prose__updated">Last updated: <?php echo esc_html( get_the_modified_date( 'F j, Y' ) ); ?></p>
        </article>
    </div>
</section>

<?php get_footer(); ?>
