<?php
/**
 * Hooks and theme setup.
 */
namespace Cambridge_Coaching\CC_Website\Theme;

class Hooks {

	/**
	 * Set up WordPress hooks
	 */
	public function register_hooks() {

		// Front-end styles and scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );

		// Register widget areas
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );

		// Excerpts
		add_filter( 'excerpt_length', array( $this, 'custom_excerpt_length' ), 999 );
		add_filter( 'excerpt_more',   array( $this, 'excerpt_more' ), 999 );

		// Editor styles
		add_action( 'mce_css', array( $this, 'editor_styles' ) );

		// Menus
		add_filter( 'walker_nav_menu_start_el', array( $this, 'nav_menu_social_icons' ), 10, 4 );

		// Simplify WordPress functionality
		add_action( '_admin_menu',        array( $this, 'remove_theme_editor' ), 1 );
		add_action( 'customize_register', array( $this, 'remove_custom_css_control' ) );

		// ACF/Front-end performance
		add_filter( 'option_active_plugins', array( $this, 'disable_acf_on_frontend' ) );
	}

	/**
	 * Set up theme defaults and register supported WordPress features.
	 */
	public function configure_theme() {
		load_theme_textdomain( 'cambridge-coaching', get_template_directory() . '/languages' );

		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );

		add_theme_support( 'custom-logo', array(
			'height'      => 242,
			'width'       => 400,
			'flex-height' => true,
			'flex-width'  => true,
		) );

		add_theme_support( 'html5', array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
		) );

		// Image Sizes
		add_image_size( 'excerpt', 300, 300 );
	}

	/**
	 * Set the content width in pixels, based on the theme's design and stylesheet.
	 *
	 * Priority 0 to make it available to lower priority callbacks.
	 *
	 * @global int $content_width
	 */
	public function content_width() {
		$GLOBALS['content_width'] = apply_filters( 'cambridge_coaching_content_width', 640 );
	}

	/**
	 * Register widget area and widgets.
	 */
	public function widgets_init() {
	}

	/**
	 * Enqueue styles.
	 */
	public function styles() {

		// Remove Custom Contact Forms' styling
		wp_dequeue_style( 'ccf-form' );
		wp_dequeue_style( 'ccf-jquery-ui-css' );

		// Fonts
		wp_enqueue_style(
			'source-sans-pro',
			'https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,400i,700,700i',
			array(),
			THEME_VERSION
		);

		// Theme Styling
		wp_enqueue_style(
			'cambridge-coaching-style',
			ASSETS_DIRECTORY . 'css/cambridge-coaching.min.css',
			array(),
			THEME_VERSION
		);
	}

	/**
	 * Enqueue scripts.
	 */
	public function scripts() {

		// Remove jQuery-Migrate
		wp_deregister_script( 'jquery' );
		wp_register_script(
			'jquery',
			includes_url( '/js/jquery/jquery.js' ),
			array(),
			THEME_VERSION,
			true
		);
		wp_enqueue_script( 'jquery' );

		// Load theme JS
		wp_enqueue_script(
			'cambridge-coaching-js',
			ASSETS_DIRECTORY . 'js/dist/bundle.min.js',
			array(),
			THEME_VERSION,
			true
		);
	}

	/**
	 * Register menu areas.
	 */
	public function register_menus() {
		register_nav_menu( 'primary', __( 'Primary Menu', 'cambridge-coaching' ) );
		register_nav_menu( 'footer', __( 'Footer Menu', 'cambridge-coaching' ) );

		// Social Menu
		register_nav_menu( 'social', __( 'Social Menu', 'cambridge-coaching' ) );
	}

	/**
	 * Filter the except length.
	 *
	 * @param int $length Excerpt length.
	 * @return int (Maybe) modified excerpt length.
	 */
	public function custom_excerpt_length( $length ) {
		return 50;
	}

	/**
	 * Filter the excerpt "read more" string.
	 *
	 * @param string $more "Read more" excerpt string.
	 * @return string (Maybe) modified "read more" excerpt string.
	 */
	public function excerpt_more( $more ) {
		return '&hellip;';
	}

	/**
	 * Loads our styles in the TinyMCE editor.
	 */
	public function editor_styles( $mce_css ) {

		if ( ! empty( $mce_css ) ) {
			$mce_css .= ',';
		}

		$mce_css .= ASSETS_DIRECTORY . 'css/editor-styles.css';

		return $mce_css;
	}

	/**
	 * Removes support for the Theme Editor.
	 */
	public function remove_theme_editor() {
		remove_action( 'admin_menu', '_add_themes_utility_last', 101 );
	}

	/**
	 * Removes support for the "Custom CSS" control in the WP 4.7 Customizer.
	 *
	 * @param WP_Customize_Manager $wp_customize WP_Customize_Manager instance.
	 */
	public function remove_custom_css_control( $wp_customize ) {
		$wp_customize->remove_control( 'custom_css' );
	}

	/**
	 * Prevent the ACF plugin from loading on the front-end for performance.
	 */
	public function disable_acf_on_frontend( $plugins ) {

		// Don't run on the admin side
		if ( is_admin() ) {
			return $plugins;
		}

		foreach ( $plugins as $i => $plugin ) {
			if ( 'advanced-custom-fields-pro/acf.php' === $plugin ) {
				unset( $plugins[ $i ] );
			}
		}

		return $plugins;
	}

	/**
	 * Display SVG icons in social links menu.
	 *
	 * @param  string  $item_output The menu item output.
	 * @param  WP_Post $item        Menu item object.
	 * @param  int     $depth       Depth of the menu.
	 * @param  array   $args        wp_nav_menu() arguments.
	 * @return string  $item_output The menu item output with social icon.
	 */
	function nav_menu_social_icons( $item_output, $item, $depth, $args ) {

		// Get supported social icons.
		$social_icons = array(
			'facebook.com'    => 'social-facebook',
			'instagram.com'   => 'social-instagram',
			'twitter.com'     => 'social-twitter',
			'linkedin.com'    => 'social-linkedin',
		);

		// Change SVG icon inside social links menu if there is supported URL.
		if ( 'social' !== $args->theme_location ) {
			return $item_output;
		}

		foreach ( $social_icons as $attr => $value ) {
			if ( false !== strpos( $item_output, $attr ) ) {
				$item_output = str_replace(
					$args->link_after,
					'</span>' . Helpers\get_svg_icon( esc_attr( $value ), array( 'class' => 'footer-menu__item__icon' ) ),
					$item_output
				);
			}
		}

		return $item_output;
	}
}
