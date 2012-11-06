<?php

class iFavorites_App_Genre_Widget extends WP_Widget {
	function __construct()
	{
		parent::__construct(
			'app_genre_widget',
			__("App Genres", 'ifavorites'),
			array(
				'description' => __("A list or dropdown of app genres", 'ifavorites'),
			)
		);
	}

	function widget($args, $instance)
	{
		extract($args);

		echo $before_widget;

		$title = apply_filters('widget_title', $instance['title']);
		if (empty($title)) $title = __("App Genres", 'ifavorites');
		if ($title) echo $before_title.$title.$after_title;

		$args = array(
			'taxonomy' => 'app_genre',
			'orderby' => 'name',
			'show_count' => $instance['count'],
			'hierarchical' => $instance['hierachical'],
			'hide_empty' => !$instance['show_empty'],
		);

		if ($instance['dropdown']) {
			wp_dropdown_categories(array_merge($args, array(
				'id' => 'app_genre_widget_dropdown',
				'name' => 'app_genre',
				'show_option_all' => __("Select Genre", 'ifavorites'),
			)));

			?>

			<script>
				document.getElementById('app_genre_widget_dropdown').onchange = function() {
					var genre_id = this.options[this.selectedIndex].value;
					if (genre_id > 0) location.href = "<?=home_url()?>/?app_genre="+genre_id;
				}
			</script>

			<?php
		} else {
			echo '<ul>';
			wp_list_categories(array_merge($args, array(
				'title_li' => '',
			)));
			echo '</ul>';
		}

		echo $after_widget;
	}

	function update($new_instance, $old_instance)
	{
		return array_merge($old_instance, array(
			'title' => strip_tags($new_instance['title']),
			'dropdown' => !empty($new_instance['dropdown']),
			'count' => !empty($new_instance['count']),
			'hierarchical' => !empty($new_instance['hierarchical']),
			'show_empty' => !empty($new_instance['show_empty']),
		));
	}

	function form($instance)
	{
		$title = isset($instance['title']) ? $instance['title'] : '';
		$dropdown = isset($instance['dropdown']) ? (bool) $instance['dropdown'] : false;
		$count = isset($instance['count']) ? (bool) $instance['count'] : false;
		$hierarchical = isset($instance['hierarchical']) ? (bool) $instance['hierarchical'] : false;
		$show_empty = isset($instance['show_empty']) ? (bool) $instance['show_empty'] : false;

		?>

		<p>
			<label for="<?=$this->get_field_id('title')?>">
				<?=__("Title:", 'ifavorites')?>
			</label>
			<input
				type="text"
				class="widefat"
				id="<?=$this->get_field_id('title')?>"
				name="<?=esc_attr($this->get_field_name('title'))?>"
				value="<?=esc_attr($title)?>"
			/>
		</p>

		<p>
			<input
				type="checkbox"
				class="checkbox"
				id="<?=$this->get_field_id('dropdown')?>"
				name="<?=esc_attr($this->get_field_name('dropdown'))?>"
				<?php checked($dropdown); ?>
			/>
			<label for="<?=$this->get_field_id('dropdown')?>">
				<?=__("Display as dropdown", 'ifavorites')?>
			</label><br/>

			<input
				type="checkbox"
				class="checkbox"
				id="<?=$this->get_field_id('count')?>"
				name="<?=esc_attr($this->get_field_name('count'))?>"
				<?php checked($count); ?>
			/>
			<label for="<?=$this->get_field_id('count')?>">
				<?=__("Show post counts", 'ifavorites')?>
			</label><br/>

			<input
				type="checkbox"
				class="checkbox"
				id="<?=$this->get_field_id('hierarchical')?>"
				name="<?=esc_attr($this->get_field_name('hierarchical'))?>"
				<?php checked($hierarchical); ?>
			/>
			<label for="<?=$this->get_field_id('hierarchical')?>">
				<?=__("Show hierarchy", 'ifavorites')?>
			</label><br/>

			<input
				type="checkbox"
				class="checkbox"
				id="<?=$this->get_field_id('show_empty')?>"
				name="<?=esc_attr($this->get_field_name('show_empty'))?>"
				<?php checked($show_empty); ?>
			/>
			<label for="<?=$this->get_field_id('show_empty')?>">
				<?=__("Show empty genres", 'ifavorites')?>
			</label>

		</p>

		<?php
	}
}