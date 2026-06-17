<?php
/**
 * Single blog post. Page-hero (back link + category kicker + title + byline)
 * over a narrow prose column, reusing the .pp-prose--doc article style.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$pp_blog_url = get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/blog' );

while ( have_posts() ) :
	the_post();
	$pp_cats  = get_the_category();
	$pp_cat   = ! empty( $pp_cats ) ? $pp_cats[0] : null;
	$pp_words = str_word_count( wp_strip_all_tags( get_the_content() ) );
	$pp_mins  = max( 1, (int) round( $pp_words / 200 ) );
	?>

	<section class="pp-page-hero">
		<div class="pp-container">
			<a class="pp-doc-back" href="<?php echo esc_url( $pp_blog_url ); ?>">&larr; Back to blog</a>
			<?php if ( $pp_cat ) : ?><span class="pp-page-hero__kicker"><?php echo esc_html( $pp_cat->name ); ?></span><?php endif; ?>
			<h1 class="pp-page-title"><?php the_title(); ?></h1>
			<p class="pp-post-byline">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				<span class="pp-post-byline__sep">&middot;</span>
				<?php echo esc_html( $pp_mins ); ?> min read
			</p>
		</div>
	</section>

	<section class="pp-section pp-section--tight">
		<div class="pp-container pp-container--narrow">
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="pp-post-hero-img"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>
			<article class="pp-prose pp-prose--doc">
				<?php the_content(); ?>
			</article>
			<p class="pp-doc-stub__back-link"><a href="<?php echo esc_url( $pp_blog_url ); ?>">&larr; Back to all posts</a></p>
		</div>
	</section>

	<section class="pp-section pp-section--snug pp-section--alt">
		<div class="pp-container pp-container--narrow">
			<div class="pp-docs-cta">
				<h2>Stop chasing payments by hand</h2>
				<p>Pipe Pay verifies Venmo, Cash App, PayPal, and Zelle payments right at your WooCommerce checkout, so you only touch the ones flagged for review.</p>
				<a class="pp-btn pp-btn--primary" href="<?php echo esc_url( home_url( '/pricing' ) ); ?>">See pricing</a>
			</div>
		</div>
	</section>

	<?php
endwhile;

get_footer();
