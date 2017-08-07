<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package Cambridge_Coaching
 */
namespace Cambridge_Coaching\CC_Website\Theme;

get_header(); ?>

	<header class="b-page-header">
		<h1 class="b-page-header__title">
			<?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'cambridge-coaching' ); ?>
		</h1>
	</header>

	<div id="primary" class="b-content-area">
		<main id="main" class="b-main" role="main">

			<p>
				<?php esc_html_e( 'It looks like nothing was found at this location. Maybe try one a search?', 'cambridge-coaching' ); ?>
			</p>

			<?php get_search_form(); ?>

		</main><!-- .b-main -->
	</div><!-- .b-content-area -->

<?php
get_footer();
