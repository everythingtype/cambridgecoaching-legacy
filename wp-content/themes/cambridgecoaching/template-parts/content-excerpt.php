<?php
/**
 * Template part for displaying posts on archive pages.
 *
 * @package Trainer
 */
namespace Cambridge_Coaching\CC_Website\Theme;

$article_classes = 'excerpt';

if ( has_post_thumbnail() ) {
	$article_classes .= ' excerpt--with-image';
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( $article_classes ); ?>>

	<div class="excerpt__main">
		<h2 class="excerpt__title">
			<a
				href="<?php echo esc_url( get_the_permalink() ); ?>"
				rel="bookmark"
				class="excerpt__title__link"
			>
				<?php the_title(); ?>
			</a>

			<?php if ( is_sticky() ) : ?>
				<?php echo Helpers\get_svg_icon( 'symbol-thumbtack', array( 'class' => 'excerpt__sticky-icon' ) ); // WPCS: XSS OK. ?>
			<?php endif; ?>
		</h2>

		<div class="excerpt__content">
			<?php the_excerpt(); ?>
		</div>

		<div class="excerpt__meta">
			<?php Helpers\post_date(); ?>
		</div>
	</div><!-- .excerpt__main -->

	<?php if ( has_post_thumbnail() ) : ?>
		<div class="excerpt__image">
			<?php the_post_thumbnail( 'excerpt' ); ?>
		</div>
	<?php endif; ?>
</article><!-- #post-## -->
