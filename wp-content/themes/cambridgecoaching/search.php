<?php
/**
 * @package Cambridge_Coaching
 */
namespace Cambridge_Coaching\CC_Website\Theme;

get_header(); ?>

	<header class="page-header">
		<h1 class="page-header__title">
			<?php printf( esc_html__( 'Search Results for: %s', 'cambridge-coaching' ), '<span>' . get_search_query() . '</span>' ); ?>
		</h1>
	</header>

	<div id="primary" class="content-area">
		<main id="main" class="main" role="main">

		<?php
		if ( have_posts() ) : ?>
			<?php
			while ( have_posts() ) : the_post();
				get_template_part( 'template-parts/content', 'excerpt' );
			endwhile;

			the_posts_navigation();

		else :

			get_template_part( 'template-parts/content', 'none' );

		endif; ?>

		</main><!-- .main -->
	</div><!-- #primary -->

<?php
get_footer();
