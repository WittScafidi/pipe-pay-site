<?php
/**
 * Search results. Reuses the blog index grid styling.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>

<section class="pp-page-hero">
	<div class="pp-container">
		<span class="pp-page-hero__kicker">Search</span>
		<h1 class="pp-page-title">Results for &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;</h1>
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
				<h2>Nothing found</h2>
				<p>No posts matched that search. Try the <a href="<?php echo esc_url( home_url( '/blog' ) ); ?>">blog index</a> or the <a href="<?php echo esc_url( home_url( '/docs' ) ); ?>">docs</a>.</p>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
