<?php
namespace Cambridge_Coaching\CC_Website\Theme;

define( 'THEME_VERSION', '0.1.0' );
define( 'ASSETS_DIRECTORY', get_template_directory_uri() . '/assets/' );

// Include main theme file
include __DIR__ . '/includes/class-cambridge-coaching.php';

// Include various theme hooks
include __DIR__ . '/includes/class-hooks.php';

// Include theme components
include __DIR__ . '/includes/class-breadcrumbs.php';

// Include other helper functions
include __DIR__ . '/includes/helpers.php';

// Load the theme
$cambridge_coaching = new Cambridge_Coaching( __FILE__ );
$cambridge_coaching->setup();
