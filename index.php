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
	}

	function register()
	{
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

		if ($form = $_REQUEST['ifavorites']) {
			$app_id = trim($form['app_id']);
			if ($app_id != get_post_meta($post_id, '_app_id', true)) {
				if ($app_id) {
					update_post_meta($post_id, '_app_id', $app_id);
					if ($app_meta = $this->get_app_meta($app_id)) {
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

						if ($attachment_id = $this->import_photo(
							$app_meta->artworkUrl512,
							"$app_meta->trackName cpp icon",
							$post_id,
							"app-icon-$post_id-"
						)) {
							set_post_thumbnail($post_id, $attachment_id);
						}

						foreach ($app_meta->screenshotUrls as $ndx => $iphone_screenshot) {
							$this->import_photo(
								$iphone_screenshot,
								"$app_meta->trackName iPhone screenshot ".($ndx+1),
								$post_id,
								"app-iphone-screenshot-$post_id-"
							);
						}

						foreach ($app_meta->ipadScreenshotUrls as $ndx => $ipad_screenshot) {
							$this->import_photo(
								$ipad_screenshot,
								"$app_meta->trackName iPad screenshot ".($ndx+1),
								$post_id,
								"app-ipad-screenshot-$post_id-"
							);
						}
					} else die('could not retrieve data from apple');
				} else {
					delete_post_meta($post_id, '_app_id');
				}
			} // $app_id != 
		} // if $form
	}

	function get_app_meta($app_id)
	{
		if (!($response = wp_remote_get("https://itunes.apple.com/lookup?id=$app_id"))) return false;
		if (wp_remote_retrieve_response_code($response) != '200') return false;
		if (!($json = wp_remote_retrieve_body($response))) return false;
		if (!($data = json_decode($json))) return false;
		if (!$data->resultCount) return false;
		return $data->results[0];
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
}