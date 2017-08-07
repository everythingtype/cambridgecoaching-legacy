<?php
/**
 * @package Cambridge_Coaching
 */
namespace Cambridge_Coaching\CC_Website\Theme;

get_header(); ?>

	<header class="page-header">
		<h1 class="page-header__title">
			<?php single_post_title(); ?>
		</h1>
	</header>

	<div id="primary" class="content-area">
		<main id="main" class="main" role="main">
			<?php
			if ( have_posts() ) {

				while ( have_posts() ) {
					the_post();

					if ( is_search() || is_home() || is_archive() ) {
						get_template_part( 'template-parts/content-excerpt' );
					} else {
						get_template_part( 'template-parts/content' );
					}
				}

				the_posts_navigation();

			} else {
				get_template_part( 'template-parts/content', 'none' );
			}
			?>
		</main><!-- .main -->
	</div><!-- .content-area -->

<?php
get_footer();
