<?php

/*
Plugin Name: iFavorites
Plugin URI: http://wordpress.org/extend/plugins/ifavorites/
Description: Manage and display a list of your favorite iOS apps.
Author: Matt DeClaire
Version: 1.6
Author URI: http://declaire.com
*/

new iFavorites;

class iFavorites {
	protected $slug = 'app';
	protected $archive_slug = 'apps';
	protected $app_data = array();

	function __construct()
	{
		add_action('init', array($this, 'register'));
		add_action('admin_init', array($this, 'meta_boxes'));
		add_action('save_post', array($this, 'save'));
		add_filter('the_content', array($this, 'the_content_filter'));
	}

	function register()
	{
		register_taxonomy('app-genre', $this->slug, array(
			'labels' => array(
				'name' => _n("App Genre", "App Genres", 2, 'ifavorites'),
				'singular_name' => _n("App Genre", "App Genres", 1, 'ifavorites'),
				'search_items' => __("Search App Genres", 'ifavorites'),
				'all_items' => __('All App Genres'),
				'parent_item' => __('Parent App Genre'),
				'parent_item_colon' => __('Parent App Genre:'),
				'edit_item' => __('Edit App Genre'), 
				'update_item' => __('Update App Genre'),
				'add_new_item' => __('Add New App Genre'),
				'new_item_name' => __('New App Genre Name'),
				'menu_name' => __('App Genres'),
			),
			'public' => true,
		));

		register_post_type($this->slug, array_merge(array(
			'labels' => array(
				'name' => _n("App", "Apps", 2, 'ifavorites'),
				'singular_name' => _n("App", "Apps", 1, 'ifavorites'),
				'add_new' => __("Add New App", 'ifavorites'),
				'add_new_item' => __("Add New App", 'ifavorites'),
				'edit_item' => __("Edit App", 'ifavorites'),
				'new_item' => __("New App", 'ifavorites'),
				'view_item' => __("View App", 'ifavorites'),
				'search_items' => __("Search Apps", 'ifavorites'),
				'not_found' => __("No Apps found", 'ifavorites'),
				'not_found_in_trash' => __("No Apps found in Trash", 'ifavorites'),
			),
			'public' => true,
			'show_ui' => true,
			'menu_position' => 22,
			'capability_type' => 'post',
			'supports' => array(
				'title',
				'editor',
				'author',
				'thumbnail',
				'excerpt',
				'custom-fields',
				'comments',
				'revisions',
			),
			'has_archive' => $this->archive_slug,
			'rewrite' => array(
				'slug' => $this->archive_slug,
				'with_front' => false,
			),
			'taxonomies' => array(
				'app-genre',
			),
		)));
	}

	function meta_boxes()
	{
		add_meta_box(
			$this->slug."-options",
			__("Options", 'ifavorites'),
			array($this, 'options'),
			$this->slug,
			'side'
		);
	}

	function options($post)
	{
		wp_nonce_field(plugin_basename(__FILE__), 'ifavorites_nonce');

		$app_id = get_post_meta($post->ID, '_app_id', true);

		?>

		<table>
			<tr>
				<th><label><?=__("App ID", 'ifavorites')?></label></th>
				<td><input type="text" name="ifavorites[app_id]" value="<?=esc_attr($app_id)?>" /></td>
			</tr>
		</table>

		<?php
	}

	function save($post_id)
	{
		if (wp_is_post_revision($post_id)) return $post_id;
		if (!isset($_REQUEST['ifavorites_nonce'])) return $post_id;
		if (!wp_verify_nonce($_REQUEST['ifavorites_nonce'], plugin_basename(__FILE__))) return $post_id;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_id;
		if ($_REQUEST['post_type'] != $this->slug) return $post_id;
		if (!current_user_can('edit_post', $post_id)) return $post_id;
		if (!isset($_REQUEST['ifavorites'])) return $post_id;

		$app_id = trim($_REQUEST['ifavorites']['app_id']);

		if ($app_id == get_post_meta($post_id, '_app_id', true)) die('save app id'); // return $post_id;

		if (!$app_id) {
			delete_post_meta($post_id, '_app_id');
			return $post_id;
		}

		$results = $this->app_search($app_id);

		if (!$results) {
			if (!session_id()) session_start();
			$_SESSION['ifavorites']['save_errors'][] = "Error while accessing iTunes store";
			return false;
		}

		update_post_meta($post_id, '_app_id', $app_id);

		$app_meta = $results[0];
		$post = get_post($post_id);

		update_post_meta($post_id, '_app_meta', array(
			'title' => $app_meta->trackName,
			'author' => $app_meta->artistName,
			'description' => $app_meta->description,
			'price' => floatval($app_meta->price),
			'version' => $app_meta->version,
			'url' => $app_meta->trackViewUrl,
			'vendor_url' => $app_meta->sellerUrl,
			'release_date' => strtotime($app_meta->releaseDate),
			'genres' => $app_meta->genres,
			'rating' => $app_meta->averageUserRatingForCurrentVersion,
			'rating_count' => $app_meta->userRatingCount,
		));

		wp_set_post_terms($post_id, $app_meta->genres, 'app-genre');

		if ($attachment_id = $this->import_photo(
			$app_meta->artworkUrl512,
			"$post->post_title app icon",
			$post_id,
			"app-icon-$post_id-"
		)) {
			set_post_thumbnail($post_id, $attachment_id);
		}

		foreach ($app_meta->screenshotUrls as $ndx => $iphone_screenshot) {
			$this->import_photo(
				$iphone_screenshot,
				"$post->post_title iPhone screenshot ".($ndx+1),
				$post_id,
				"app-iphone-screenshot-$post_id-"
			);
		}

		foreach ($app_meta->ipadScreenshotUrls as $ndx => $ipad_screenshot) {
			$this->import_photo(
				$ipad_screenshot,
				"$post->post_title iPad screenshot ".($ndx+1),
				$post_id,
				"app-ipad-screenshot-$post_id-"
			);
		}

		return $post_id;
	}

	function app_search($search)
	{
		$params = wp_parse_args($params, array(
		));

		$url = "https://itunes.apple.com/lookup?".http_build_query(array(
			'id' => $search,
			'country' => 'us',
			'media' => 'software',
		));

		if (!($response = wp_remote_get($url))) return false;
		if (wp_remote_retrieve_response_code($response) != '200') return false;
		if (!($json = wp_remote_retrieve_body($response))) return false;
		if (!($data = json_decode($json))) return false;

		return $data->results;
	}

	function import_photo($url, $title, $post_id = 0, $prefix = '')
	{
		$attachment = wp_upload_bits($prefix.basename($url), null, '');
		if ($attachment['error']) return false;

		$local = fopen($attachment['file'], 'w');
		$remote = fopen($url, 'r');

		if (!$local || !$remote) return false;

		while (!feof($remote)) {
 			fwrite($local, fread($remote, 8192));
 		}

		$filetype = wp_check_filetype($attachment['file'], null);

		$attach_id = wp_insert_attachment(
			array(
				'post_mime_type' => $filetype['type'],
				'post_title' => $title,
				'post_content' => '',
				'post_status' => 'inherit',
			),
			$attachment['file'],
			$post_id
		);

		$attach_data = wp_generate_attachment_metadata($attach_id, $attachment['file']);
		wp_update_attachment_metadata($attach_id, $attach_data);

		return $attach_id;
	}

	function the_content_filter($content)
	{
		global $post;

		if ($post->post_type == $this->slug) {
			$meta = get_post_meta($post->ID, '_app_meta', true);
			ob_start();
			?>

			<p class="meta">
				<?=__("genres: ", 'ifavorites')?> <?=get_the_term_list($post->ID, 'app-genre', false, ', ')?><br>
				price: <?=$meta['price'] ? '$'.number_format($meta['price']) : 'free'?><br>
				<a href="<?=$meta['url']?>" target="_blank">app link</a><br>
				rating: <?=$meta['rating']?>
			</p>

			[gallery]

			<?php

			$content .= ob_get_clean();
		}

		return $content;
	}
}