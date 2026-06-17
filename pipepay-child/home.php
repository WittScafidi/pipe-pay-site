<?php
/**
 * Blog index – the page set as "Posts page" in Settings → Reading (slug: blog).
 * Branded listing of posts; matches the docs/changelog page-hero + card-grid style.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>

<section class="pp-page-hero">
	<div class="pp-container">
		<span class="pp-page-hero__kicker">Blog</span>
		<h1 class="pp-page-title">The Pipe Pay blog</h1>
		<p class="pp-page-hero__sub">Guides for WooCommerce store owners taking Venmo, Cash App, PayPal, and Zelle &ndash; and for merchants underserved by the major processors.</p>
	</div>
</section>

<section class="pp-section pp-section--tight">
	<div class="pp-container">
		<?php if ( have_posts() ) : ?>
			<div class="pp-blog-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'partials/post-card' );
				endwhile;
				?>
			</div>
			<?php
			the_posts_pagination( array(
				'mid_size'  => 1,
				'prev_text' => '&larr; Newer',
				'next_text' => 'Older &rarr;',
			) );
			?>
		<?php else : ?>
			<div class="pp-docs-cta">
				<h2>No posts yet</h2>
				<p>We&rsquo;re writing the first articles now. Check back soon, or <a href="<?php echo esc_url( home_url( '/docs' ) ); ?>">read the docs</a> in the meantime.</p>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
