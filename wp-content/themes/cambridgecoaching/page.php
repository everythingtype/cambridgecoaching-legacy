<?php
/**
 * @package Cambridge_Coaching
 */
namespace Cambridge_Coaching\CC_Website\Theme;

get_header(); ?>

	<header class="page-header <?php echo has_post_thumbnail() ? ' page-header__with-image' : ''; ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'full' ); ?>
		<?php endif; ?>

		<h1 class="page-header__title">
			<?php single_post_title(); ?>
		</h1>
	</header>

	<?php get_template_part( 'template-parts/featured-image' ); ?>

	<div id="primary" class="content-area">
		<main id="main" class="main" role="main">

			<?php
			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/content' );
			}
			?>

		</main><!-- .main -->
	</div><!-- .content-area -->

<?php
get_footer();
