<?php
/**
 * WP-CLI script to import from the Hubspot REST API into WordPress.
 *
 * Optional arguments:
 * offset - to start at a specific offset, instead of 0
 * ID     - to import a single
 */
namespace Cambridge_Coaching\CC_Website\Hubspot_Script;

/**
 * URL components, an example URL:
 * https://api.hubapi.com/content/api/v2/blog-posts?hapikey=a799f7ac-490c-4912-987a-5612b50d7482&limit=5&offset=0&state=PUBLISHED
 *
 * or if we're importing a single post by ID:
 * https://api.hubapi.com/content/api/v2/blog-posts/3716104219?hapikey=a799f7ac-490c-4912-987a-5612b50d7482
 */
define( 'BLOG_ENDPOINT_URL',   'https://api.hubapi.com/content/api/v2/blog-posts/' ); // Hubspot Blog API endpoint
define( 'TOPICS_ENDPOINT_URL', 'https://api.hubapi.com/blogs/v3/topics/' );           // Hubspot Topics API endpoint
define( 'HAPI_KEY',            'a799f7ac-490c-4912-987a-5612b50d7482' );              // API key specific for the blog
define( 'LIMIT',               5 );                                                   // low limit to avoid any issues

/**
 * Downloads an image and adds it to the Media Library.
 *
 * @param string $url     URL for original source for the image to add.
 * @param int    $post_id Post ID to attach the image to.
 * @param string $alt     Alt text for the image.
 *
 * @return int Image ID, or false if the import failed.
 */
function download_image_from_url( $url, $post_id, $alt = '', $credit = '' ) {

	if ( empty( $url ) ) {
		return false;
	}

	$tmp = download_url( $url );

	$file_array = array(
		'name'     => basename( $url ),
		'tmp_name' => $tmp,
	);

	if ( is_wp_error( $tmp ) ) {
		unlink( $file_array['tmp_name'] );
		return false;
	}

	$id = media_handle_sideload( $file_array, $post_id );

	if ( is_wp_error( $id ) ) {
		unlink( $file_array['tmp_name'] );
		return false;
	}

	unlink( $tmp );

	// Set the alt text if set
	if ( ! empty( $alt ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	}

	return $id;
}

/**
 * Creates the 301 redirect with the provided JSON data.
 *
 * Uses Safe Redirect Manager, requires it to be installed.
 *
 * @param  Array         $data    Array of the JSON data for the post.
 * @param  int           $post_id Post ID of the new post.
 * @return int|WP_Error           Redirect post ID if successful, or WP_Error if not.
 */
function create_301_redirect( $data, $post_id ) {

	global $safe_redirect_manager;

	if ( empty( $post_id ) || empty( $old_id ) ) {
		return false;
	}

	// This will look something like:
	// http://blog.cambridgecoaching.com/orgo-1-strategies-finding-and-comparing-alkene-hydration-products
	$old_full_url = $data['absolute_url'];

	// TODO parse the old URL to get a relative path
	$old_relative_url = '';

	// Get the URL of the new post to redirect to
	$new_url = get_the_permalink( $post_id );

	if ( empty( $new_url ) ) {
		return false;
	}

	$redirect_id = $safe_redirect_manager->create_redirect( $old_url, $new_url );

	return $redirect_id;
}

/**
 * Looks up the Topic ID with Hubspot's API, and maps this to WordPress's categories.
 *
 * If a matching category doesn't already exist, it will create one.
 *
 * @param int $topic_id  Hubspot topic ID.
 *
 * @return int           WordPress Category ID.
 */
function get_or_create_category_by_topic_id( $topic_id ) {

	// Example API URL:
	// https://api.hubapi.com/blogs/v3/topics/349001328?hapikey=demo

	// Build the URL:
	$endpoint_url = trailingslashit( TOPICS_ENDPOINT_URL ) . $topic_id;
	$full_url = add_query_arg( 'hapikey', HAPI_KEY, $endpoint_url );

	$response = wp_safe_remote_get( $full_url );

	// Use the response if it's good
	if ( ! is_wp_error( $response ) ) {
		$data = json_decode( $response['body'], true );

		if ( ! empty( $data ) ) {
			$description = $data['description'];
			$name        = $data['name'];
			$slug        = $data['slug'];
			$old_id      = $data['id'];

			$category = get_category_by_slug( $slug );

			// Get the category ID if it already exists, if not then make it
			if ( $category instanceof \WP_Term ) {
				$category_id = $category->term_id;
			} else {
				$category_id = wp_insert_category(
					array(
						'taxonomy'             => 'category',
						'cat_name'             => $name,
						'category_description' => $description,
						'category_nicename'    => $slug,
					)
				);

				add_term_meta( $category_id, 'old_hubspot_topic_id', $old_id, true );
			}

			return $category_id;

		} else {
			\WP_CLI::warning( 'Error trying to parse JSON from this URL: ' . $full_url );
			return false;
		}

	} else {
		\WP_CLI::warning( 'Error trying to get data from this URL: ' . $full_url );
		\WP_CLI::warning( $response->get_error_message() );
		return false;
	} // End if().
}

/**
 * Creates a post with the provided JSON data.
 *
 * @param  Array         $data  Array of the JSON data for the post.
 * @return int|WP_Error         Post ID if successful, or WP_Error if not.
 */
function create_post( $data ) {

	// Extract the data that we're going to need

	// TODO: do we need analytics page ID? How to look up category IDs?
	// Are keywords used? How are Topic IDs used (called "Tags" on the blog)?

	$title             = isset( $data['html_title'] ) ? $data['html_title'] : null; // set
	$old_url           = isset( $data['absolute_url'] ) ? $data['absolute_url'] : null; // set
	$analytics_page_id = isset( $data['analytics_page_id'] ) ? $data['analytics_page_id'] : null; // maybe? set
	$author_email      = isset( $data['author_email'] ) ? $data['author_email'] : null; // set
	$author_name       = isset( $data['author_name'] ) ? $data['author_name'] : null; // set
	$blog_author       = isset( $data['blog_author']['display_name'] ) ? $data['blog_author']['display_name'] : null;
	$category_id       = isset( $data['category_id'] ) ? $data['category_id'] : null;
	$old_id            = isset( $data['id'] ) ? $data['id'] : null; // set
	$keywords          = isset( $data['keywords'] ) ? $data['keywords'] : null; // maybe?
	$meta_description  = isset( $data['meta_description'] ) ? $data['meta_description'] : null;
	$post_body         = isset( $data['post_body'] ) ? $data['post_body'] : null;
	$publish_date      = isset( $data['publish_date'] ) ? $data['publish_date'] : null;
	$slug              = isset( $data['slug'] ) ? $data['slug'] : null; // set
	$topic_ids         = isset( $data['topic_ids'] ) ? $data['topic_ids'] : null;
	$ctas              = isset( $data['ctas'] ) ? $data['ctas'] : null;

	// Get the matching author ID, or create the author if it doesn't exist
	$user = get_user_by( 'email', $author_email );

	if ( false === $user ) {
		$random_password = wp_generate_password( 12, true );

		$email_address_parts = explode( '@', $author_email );
		$username = $parts[0];

		$user_id = wp_insert_user(
			array(
				'user_pass'    => $random_password,
				'user_login'   => sanitize_title( $author_name ),
				'user_email'   => $author_email,
				'display_name' => $author_name,
				'first_name'   => $author_name,
				'role'         => 'author',
			)
		);
	} else {
		$user_id = $user->ID;
	}

	// Map the "topic" IDs to categories, or create the categories if they don't already exist
	$category_ids = array();

	foreach ( $topic_ids as $topic_id ) {
		$category_ids[] = get_or_create_category_by_topic_id( $topic_id );
	}

	// TODO Scan through the body for CTAs, and replace them with the "full_html" data from the "ctas" object

	// TODO Scan through the body for IMG tags, download the images, and replace the src attributes

	// Create the post
	\WP_CLI::log( 'Title: ' . $title );

	$post_id = wp_insert_post(
		array(
			'post_author'    => $user_id,
			'post_date'      => false, // ?
			'post_content'   => $post_body,
			'post_title'     => $title,
			'post_status'    => 'publish',
			'comment_status' => 'open',
			'post_name'      => $slug,
			'post_category'  => $category_ids,
			'meta_input'     => array(
				'old_url'               => $old_url,
				'old_analytics_page_id' => $analytics_page_id,
				'old_post_id'           => $old_id,
				'_yoast_wpseo_metadesc' => $meta_description,
			),
		)
	);

	// Create the 301 redirect, if necessary?
}

/**
 * After all the JSON file is downloaded, read the files and create posts.
 *
 * @param Array $posts Array of post content
 */
function create_posts( $posts ) {

	foreach ( $posts as $post_data ) {
		create_post( $post_data );
	}
}

/**
 * Downloads the full JSON data from the API, pulling 5 posts at a time, until
 * we get to the "total_count" value from the initial endpoint.
 *
 * @param int $offset Offset of number of posts to start with.
 */
function import_from_api( $offset = 0) {

	\WP_CLI::log( 'Offset: ' . $offset );

	// Get the first page
	$full_url = add_query_arg( array(
		'hapikey' => HAPI_KEY,
		'limit'   => LIMIT,
		'offset'  => $offset,
		'state'   => 'PUBLISHED',
	), BLOG_ENDPOINT_URL );

	\WP_CLI::log( 'Starting with page 1: ' . $full_url );

	$response = wp_safe_remote_get( $full_url );

	// Use the response if it's good
	if ( ! is_wp_error( $response ) ) {
		$data = json_decode( $response['body'], true );

		if ( ! empty( $data ) ) {
			$total = $data['total'];
		} else {
			\WP_CLI::warning( 'Error trying to parse JSON from this URL: ' . $full_url );

			return;
		}

	} else {
		\WP_CLI::warning( 'Error trying to get data from this URL: ' . $full_url );
		\WP_CLI::warning( $response->get_error_message() );

		return;
	}

	// Loop through all the posts, starting over at the beginning, since we only really
	// wanted the total number of posts from that first request.
	for ( $offset = 0; $offset <= $total; $offset += LIMIT ) {
		\WP_CLI::log( 'Offset: ' . $offset );

		$full_url = add_query_arg( array(
			'hapikey' => HAPI_KEY,
			'limit'   => LIMIT,
			'offset'  => $offset,
			'state'   => 'PUBLISHED',
		), BLOG_ENDPOINT_URL );

		$response = wp_safe_remote_get( $full_url );

		// Use the response if it's good
		if ( ! is_wp_error( $response ) ) {
			$data = json_decode( $response['body'], true );

			// Validate JSON first
			if ( empty( $data ) || empty( $data['objects'] ) ) {
				\WP_CLI::warning( 'Error trying to parse JSON from this URL: ' . $full_url );
				continue;
			}

			// Create the posts with the content from the response
			create_posts( $data['objects'] );

		} else {
			\WP_CLI::warning( 'Error trying to get data from this URL: ' . $full_url );
			\WP_CLI::warning( $response->get_error_message() );
		}
	}
}

/**
 * Imports a single post from the API.
 *
 * @param int $post_id ID of the post on the Hubspot site.
 */
function import_post_from_api( $post_id ) {
	\WP_CLI::log( 'Importing blog post with ID: ' . $post_id );

	// Get the first page
	$post_url = trailingslashit( BLOG_ENDPOINT_URL ) . $post_id;
	$full_url = add_query_arg( 'hapikey', HAPI_KEY, $post_url );

	$response = wp_safe_remote_get( $full_url );

	// Use the response if it's good
	if ( ! is_wp_error( $response ) ) {
		$data = json_decode( $response['body'], true );

		if ( ! empty( $data ) ) {
			create_post( $data );

		} else {
			\WP_CLI::warning( 'Error trying to parse JSON from this URL: ' . $full_url );
			return;
		}

	} else {
		\WP_CLI::warning( 'Error trying to get data from this URL: ' . $full_url );
		\WP_CLI::warning( $response->get_error_message() );

		return;
	}
}

// Parse the arguments that were passed in
foreach ( $args as $arg ) {
	if ( 0 === strpos( $arg, 'offset=' ) ) {
		$offset = (int) substr( $arg, strlen( 'offset=' ) );
	}

	if ( 0 === strpos( $arg, 'id=' ) ) {
		$blog_post_id = (int) substr( $arg, strlen( 'id=' ) );
	}
}

// Run the script - either just a single post, if the "id" argument was
// set, or everything
if ( ! empty( $blog_post_id ) ) {
	import_post_from_api( $blog_post_id );
} else {
	import_from_api( $offset );
}
