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
function download_image_from_url( $url, $post_id, $alt = '' ) {

	if ( empty( $url ) ) {
		return false;
	}

	$tmp = download_url( $url );

	$file_array = array(
		'name'     => basename( $url ),
		'tmp_name' => $tmp,
	);

	if ( is_wp_error( $tmp ) ) {
		@unlink( $file_array['tmp_name'] );
		return false;
	}

	$id = media_handle_sideload( $file_array, $post_id );

	if ( is_wp_error( $id ) ) {
		\WP_CLI::warning( 'Error downloading the image from this URL: ' . $url );
		\WP_CLI::warning( $id->get_error_message() );
		@unlink( $file_array['tmp_name'] );
		return false;
	}

	@unlink( $tmp );

	// Set the alt text if set
	if ( ! empty( $alt ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	}

	return $id;
}

/**
 * Scans the HTML content for image tags, downloads any media that's needed,
 * and replaces the image sources. Returns an updated version of the HTML.
 *
 * @param string $html_content HTML content to parse.
 * @param int    $post_id      Post ID, so that attachments can be connected to the post.
 *
 * @return string              Updated HTML content.
 */
function replace_image_srcs( $html_content, $post_id ) {
	libxml_use_internal_errors( true );
	$document = new \DOMDocument();
	$document->loadHTML( $html_content );

	$tags = $document->getElementsByTagName( 'img' );

	foreach ( $tags as $tag ) {
		$image_old_src = $tag->getAttribute( 'src' );
		$image_alt     = $tag->getAttribute( 'alt' );

		$new_image_id = download_image_from_url( $image_old_src, $post_id, $image_alt );

		if ( ! empty( $new_image_id ) ) {
			$new_image_url = wp_get_attachment_image_url( $new_image_id, 'full', false );
			$html_content = str_replace( $image_old_src, $new_image_url, $html_content );
		}
	}

	return $html_content;
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
 * Uses the CTAs that are connected with a post, which contain full HTML for the CTA. Finds
 * the shortcodes that are in the content to insert a CTA, and replaces the shortcode with
 * the CTA HTML. Also replaces any links in the CTA - these are set as redirects that go
 * through Hubspot, so they should be updated to go directly to the actual link.
 *
 * @param string $html_content HTML content to parse.
 * @param int    $post_id      Post ID, so that attachments can be connected to the post.
 *
 * @return string              Updated HTML content.
 */
function replace_content_ctas( $html_content, $ctas ) {

	// Just return if there aren't any CTAs used
	if ( empty( $ctas ) ) {
		return $html_content;
	}

	foreach ( $ctas as $cta_key => $cta_data ) {
		// The key is the ID for the CTA itself, and they're inserted into
		// the post content with this format:
		// {{cta('6eec0177-16b8-48e6-9a34-e60fb4f052a6')}}

		// The HTML will replace the shortcode. First checks for the "full_html" data, but
		// this might occasionally be unset
		if ( ! $cta_data['full_html'] ) {
			$full_html = $cta_data['full_html'];
		} elseif ( ! $cta_data['image_html'] ) {
			$full_html = $cta_data['image_html'];
		}

		// Continue if we don't have any data
		if ( empty( $full_html ) ) {
			continue;
		}

		// Update the full HTML to get rid of Hubspot's redirects - all the links in the
		// content will look like this:
		// https://cta-redirect.hubspot.com/cta/redirect/174241/80cb5e58-6cbe-4a0b-bec4-a3cdd6f96b76
		libxml_use_internal_errors( true );

		$document = new \DOMDocument();
		$document->loadHTML( $full_html );

		$tags = $document->getElementsByTagName( 'a' );

		foreach ( $tags as $tag ) {
			$link_old_href = $tag->getAttribute( 'href' );

			// Create a request to follow the URL
			$response = wp_safe_remote_get( $link_old_href );

			// Skip if there was an error
			if ( is_wp_error( $response ) ) {
				continue;
			}

			// Look through the response for the redirect link, it's in some Javascript, like:
			// var redirectUrl = "http://www.cambridgecoaching.com/contact";
			$link_body = $response['body'];

			$matches = array();
			preg_match( '/redirectUrl = \"(.+)\"/', $link_body, $matches );

			$link_new_href = isset( $matches[1] ) ? $matches[1] : null;

			// And replace it
			if ( empty( $link_new_href ) ) {
				continue;
			}

			$tag->setAttribute( 'href', $link_new_href );
		}

		// Strip out any <script> tags. We need to look through the DOM elements for this,
		// wp_kses won't work because we want to remove any inline JS inside the tags, not just
		// the tags themselves.
		$script_tags = $document->getElementsByTagName( 'script' );
		$script_tags_to_remove = array();

		// Weird thing with iterating over DOMElements - we have to create this array of items
		// to remove first
		foreach ( $script_tags as $tag ) {
			$script_tags_to_remove[] = $tag;
		}

		foreach ( $script_tags_to_remove as $tag ) {
			// @codingStandardsIgnoreStart
			$tag->parentNode->removeChild( $tag );
			// @codingStandardsIgnoreEnd
		}

		// Build the shortcode that we're going to search for
		$cta_shortcode = "{{cta('" . $cta_key . "')}}";

		// Get our modified version of the HTML to swap it out with
		$full_html = $document->saveHTML();

		// One more pass to sanitize the HTML, also gets rid of the <DOCTYPE> and
		// <html> tags that are added by DOMDocument
		$full_html = wp_kses_post( $full_html );

		// And do a search-and-replace
		$html_content = str_replace( $cta_shortcode, $full_html, $html_content );
	} // End foreach().

	return $html_content;
}

/**
 * Creates a post with the provided JSON data.
 *
 * @param  Array         $data  Array of the JSON data for the post.
 * @return int|WP_Error         Post ID if successful, or WP_Error if not.
 */
function create_post( $data ) {

	// Extract the data that we're going to need
	$title                   = isset( $data['html_title'] ) ? $data['html_title'] : null;
	$old_url                 = isset( $data['absolute_url'] ) ? $data['absolute_url'] : null;
	$analytics_page_id       = isset( $data['analytics_page_id'] ) ? $data['analytics_page_id'] : null;
	$author_email            = isset( $data['author_email'] ) ? $data['author_email'] : null;
	$author_name             = isset( $data['author_name'] ) ? $data['author_name'] : null;
	$blog_author             = isset( $data['blog_author']['display_name'] ) ? $data['blog_author']['display_name'] : null;
	$category_id             = isset( $data['category_id'] ) ? $data['category_id'] : null;
	$old_id                  = isset( $data['id'] ) ? $data['id'] : null;
	$keywords                = isset( $data['keywords'] ) ? $data['keywords'] : null;
	$meta_description        = isset( $data['meta_description'] ) ? $data['meta_description'] : null;
	$post_body               = isset( $data['post_body'] ) ? $data['post_body'] : null;
	$publish_date            = isset( $data['publish_date'] ) ? $data['publish_date'] : null;
	$slug                    = isset( $data['slug'] ) ? $data['slug'] : null;
	$topic_ids               = isset( $data['topic_ids'] ) ? $data['topic_ids'] : null;
	$ctas                    = isset( $data['ctas'] ) ? $data['ctas'] : null;
	$featured_image          = isset( $data['featured_image'] ) ? $data['featured_image'] : null;
	$featured_image_alt_text = isset( $data['featured_image_alt_text'] ) ? $data['featured_image_alt_text'] : null;

	// Get the matching author ID, or create the author if it doesn't exist
	$user = get_user_by( 'email', $author_email );

	if ( false === $user ) {
		$random_password = wp_generate_password( 12, true );

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

	// Scan through the body for CTAs, and replace them with the "full_html" data from the "ctas" object
	$updated_post_content = replace_content_ctas( $post_body, $ctas );

	// Create the post
	\WP_CLI::log( 'Title: ' . $title . ' by ' . $author_email );

	// Convert the date - API has time since epoch in milliseconds for some reason, and adjust the time zone
	$publish_date = absint( $publish_date ) / 1000;
	$publish_date = $publish_date - (60 * 60 * 4); // subtract 4 hours
	$publish_date = date( 'Y-m-d H:i:s', $publish_date );

	$post_id = wp_insert_post(
		array(
			'post_author'    => $user_id,
			'post_date'      => $publish_date,
			'post_content'   => $updated_post_content,
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
		), true
	);

	// Alert if there's an error and then don't continue this post
	if ( is_wp_error( $post_id ) ) {
		\WP_CLI::warning( 'There was an error creating the post:' );
		\WP_CLI::warning( $post_id->get_error_message() );
		return;
	}

	// Scan through the body for IMG tags, download the images, and replace the src attributes
	$updated_post_content = replace_image_srcs( $updated_post_content, $post_id );

	$updated_post_id = wp_update_post( array(
		'ID'           => $post_id,
		'post_content' => $updated_post_content,
	) );

	if ( is_wp_error( $updated_post_id ) || empty( $updated_post_id ) ) {
		\WP_CLI::warning( 'There was an error updating the post content:' );
		\WP_CLI::warning( $updated_post_id->get_error_message() );
		return;
	}

	// Set the post's featured image
	$featured_image_id = download_image_from_url( $featured_image, $post_id, $featured_image_alt_text );

	if ( ! empty( $featured_image_id ) ) {
		set_post_thumbnail( $post_id, $featured_image_id );
	}
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

// Default for offset
$offset = isset( $offset ) ? $offset : 0;

// Run the script - either just a single post, if the "id" argument was
// set, or everything
if ( ! empty( $blog_post_id ) ) {
	import_post_from_api( $blog_post_id );
} else {
	import_from_api( $offset );
}
