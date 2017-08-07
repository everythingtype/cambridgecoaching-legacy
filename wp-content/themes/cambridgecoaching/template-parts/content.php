<?php
/**
 * Template part for displaying posts.
 *
 * @package Cambridge_Coaching
 */
namespace Cambridge_Coaching\CC_Website\Theme;
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'post' ); ?>>

	<div class="entry-content">
		<?php
		the_content();

		wp_link_pages( array(
			'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'cambridge-coaching' ),
			'after'  => '</div>',
		) );

		get_template_part( 'template-parts/sign-up-form' );
		?>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<?php
		/* translators: used between list items, there is a space after the comma */
		$separate_meta = __( ', ', 'cambridge-coaching' );

		// Display categories
		$categories_list = get_the_category_list( $separate_meta );
		if ( ! empty( $categories_list ) ) {
			echo '<div class="entry-footer__categories">';
			esc_html_e( 'Categorized: ', 'cambridge-coaching' );
			echo '<br>';
			echo $categories_list; // WPCS: XSS OK.
			echo '</div>';
		}

		// Display tags
		$tags_list = get_the_tag_list( '', $separate_meta );
		if ( ! empty( $tags_list ) ) {
			echo '<div class="entry-footer__tags">';
			esc_html_e( 'Tagged: ', 'cambridge-coaching' );
			echo '<br>';
			echo $tags_list; // WPCS: XSS OK.
			echo '</div>';
		}
		?>
	</footer><!-- .entry-footer -->
</article><!-- .post -->
