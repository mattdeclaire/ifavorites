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

		$meta = get_post_custom($post->ID);
		extract(array(
			'app_id' => $meta['_app_id'][0],
		));

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
		if (!isset($_POST['ifavorites_nonce'])) return $post_id;
		if (!wp_verify_nonce($_POST['ifavorites_nonce'], plugin_basename(__FILE__))) return $post_id;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_id;
		if ($_POST['post_type'] != $this->slug) return $post_id;
		if (!current_user_can('edit_post', $post_id)) return $post_id;

		if ($meta = $_POST['ifavorites']) {
			update_post_meta($post_id, '_app_id', trim($meta['app_id']));
		}
	}
}