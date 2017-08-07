<?php
/**
 * The sidebar containing the main widget area.
 *
 * @package Cambridge_Coaching
 */
namespace Cambridge_Coaching\CC_Website\Theme;

if ( ! is_active_sidebar( 'blog-sidebar' ) ) {
	return;
}
?>

<aside id="secondary" class="site-sidebar" role="complementary">
	<?php dynamic_sidebar( 'blog-sidebar' ); ?>
</aside><!-- #secondary -->
