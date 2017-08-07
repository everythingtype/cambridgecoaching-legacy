<?php
/**
 * WP-CLI script to import from the Hubspot REST API into WordPress.
 */
namespace Cambridge_Coaching\CC_Website\Hubspot_Script;

/**
 * URL components, an example URL:
 * https://api.hubapi.com/content/api/v2/blog-posts?hapikey=a799f7ac-490c-4912-987a-5612b50d7482&limit=5&offset=0&state=PUBLISHED
 */
$base_url = 'https://api.hubapi.com/content/api/v2/blog-posts';   // Hubspot Blog API endpoint
$hapi_key = 'a799f7ac-490c-4912-987a-5612b50d7482';               // API key specific for the blog
$limit    = 5;                                                    // low limit to avoid any issues
$offset   = 0;                                                    // start on the first page
$state    = 'PUBLISHED';                                          // only published posts

/**
 * Downloads the full JSON data from the API, pulling 5 posts at a time, until
 * we get to the "total_count" value from the initial endpoint.
 */
function download_json_data() {

	$full_url = add_query_arg( array(
		'hapikey' => $hapi_key,
		'limit'   => $limit,
		'offset'  => $offset,
		'state'   => $state,
	), $base_url );

	\WP_CLI::log( 'Starting with page 1: ' . $full_url );

	// Download the first page

	// Find the total_count, divide by the $limit to get the number of pages

	// Loop through each page, increasing the offset each time
}

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
 * Creates a post with the provided JSON data.
 *
 * @param  Array         $data  Array of the JSON data for the post.
 * @return int|WP_Error         Post ID if successful, or WP_Error if not.
 */
function create_post( $data ) {

	// Something, the meta data all goes here

	// Create the author if it doesn't exist

	// Create the post

	// Scan through the body for IMG tags, download the images, and replace the src attributes

}

/**
 * After all the JSON file is downloaded, read the files and create posts.
 */
function read_files_and_create_posts() {

	// A big loop goes here.
}
