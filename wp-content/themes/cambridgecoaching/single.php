<?php
/**
 * @package Cambridge_Coaching
 */
namespace Cambridge_Coaching\CC_Website\Theme;

use Cambridge_Coaching\CC_Website\Theme\Helpers;

get_header(); ?>

	<header class="b-post-header">
		<h1 class="b-post-header__title">
			<?php single_post_title(); ?>
		</h1>

		<div class="b-post-header__meta">
			<?php Helpers\post_date(); ?>
		</div>

		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'full' ); ?>
		<?php endif; ?>
	</header>

	<?php get_template_part( 'template-parts/featured-image' ); ?>

	<div id="primary" class="b-content-area">
		<main id="main" class="b-main" role="main">

			<?php
			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/content' );
			}
			?>

		</main><!-- .b-main -->
	</div><!-- .b-content-area -->

<?php
get_footer();
