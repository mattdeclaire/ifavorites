<?php

class iFavorites_Recent_Apps_Widget extends WP_Widget {
	function __construct()
	{
		parent::__construct(
			'recent_apps_widget',
			__("Recent Apps", 'ifavorites'),
			array(
				'description' => __("The most recent apps on your site", 'ifavorites'),
			)
		);
	}

	function widget($args, $instance)
	{
		extract($args);

		echo $before_widget;

		$title = apply_filters('widget_title', $instance['title']);
		if (empty($title)) $title = __("Recent Apps", 'ifavorites');
		if ($title) echo $before_title.$title.$after_title;

		$apps = new WP_Query(array(
			'post_type' => 'app',
			'posts_per_page' => $instance['number'],
			'post_status' => 'publish',
			'ignore_sticky_posts' => true,
		));
		?>
		
		<ul>
			<?php while ($apps->have_posts()): $apps->the_post(); ?>
				<li>
					<a
						href="<?php the_permalink(); ?>"
						title="<?=esc_attr(get_the_title())?>"
					>
						<?php the_title(); ?>
					</a>
				</li>
			<?php endwhile; ?>
		</ul>

		<?php

		echo $after_widget;

		wp_reset_postdata();
	}

	function update($new_instance, $old_instance)
	{
		return array_merge($old_instance, array(
			'title' => strip_tags($new_instance['title']),
			'number' => intval($new_instance['number']),
		));
	}

	function form($instance)
	{
		$title = isset($instance['title']) ? $instance['title'] : '';
		$number = isset($instance['number']) ? $instance['number'] : 5;

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
			<label for="<?=$this->get_field_id('number')?>">
				<?=__("Number of apps to show:", 'ifavorites')?>
			</label>
			<input
				type="text"
				size="3"
				id="<?=$this->get_field_id('number')?>"
				name="<?=esc_attr($this->get_field_name('number'))?>"
				value="<?=esc_attr($number)?>"
			/>
		</p>

		<?php
	}
}