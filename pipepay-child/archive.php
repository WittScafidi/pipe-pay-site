<?php
/**
 * Blog archives - category, tag, author, date. Reuses the blog index grid.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$pp_blog_url = get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/blog' );
$pp_desc     = get_the_archive_description();
?>

<section class="pp-page-hero">
	<div class="pp-container">
		<a class="pp-doc-back" href="<?php echo esc_url( $pp_blog_url ); ?>">&larr; Back to blog</a>
		<span class="pp-page-hero__kicker">Blog</span>
		<h1 class="pp-page-title"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		<?php if ( $pp_desc ) : ?>
			<div class="pp-page-hero__sub"><?php echo wp_kses_post( $pp_desc ); ?></div>
		<?php endif; ?>
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
				<h2>Nothing here yet</h2>
				<p>No posts in this section yet. <a href="<?php echo esc_url( $pp_blog_url ); ?>">Back to the blog</a>.</p>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
