<?php
/**
 * Blog post card. Runs inside The Loop (uses the current global $post).
 * Shared by home.php (blog index), archive.php, and search.php.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$pp_cats = get_the_category();
$pp_cat  = ! empty( $pp_cats ) ? $pp_cats[0] : null;
?>
<a class="pp-post-card" href="<?php the_permalink(); ?>">
	<?php if ( has_post_thumbnail() ) : ?>
		<span class="pp-post-card__img">
			<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'alt' => esc_attr( get_the_title() ) ) ); ?>
		</span>
	<?php endif; ?>
	<span class="pp-post-card__meta">
		<?php if ( $pp_cat ) : ?><span class="pp-post-card__cat"><?php echo esc_html( $pp_cat->name ); ?></span><?php endif; ?>
		<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
	</span>
	<h3 class="pp-post-card__title"><?php the_title(); ?></h3>
	<p class="pp-post-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 28, '&hellip;' ) ); ?></p>
	<span class="pp-post-card__cta">Read &rarr;</span>
</a>
