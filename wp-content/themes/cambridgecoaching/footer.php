<?php
/**
 * The template for displaying the footer.
 *
 * @package Cambridge_Coaching
 */
namespace Cambridge_Coaching\CC_Website\Theme;
?>

	</div><!-- .page-content -->

	<footer class="site-footer" role="contentinfo">
		<div class="site-footer__container">

			<nav id="footer-navigation" class="site-footer__navigation" role="navigation">
				<?php wp_nav_menu(
					array(
						'theme_location'  => 'primary',
						'container'       => false,
						'menu_class'      => 'footer-menu',
						'depth'           => 1,
					)
				); ?>
			</nav><!-- .footer-navigation -->

			<div class="site-footer__misc">
				<div class="sitefooter__copyright">
					&copy; <?php echo esc_html( date( 'Y' ) ); ?>
				</div>
			</div><!-- .site-footer__misc -->

		</div><!-- .site-footer__container -->
	</footer><!-- .site-footer -->
</div><!-- .site-wrapper -->

<?php wp_footer(); ?>

</body>
</html>
