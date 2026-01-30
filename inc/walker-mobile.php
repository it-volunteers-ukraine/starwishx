<?php

class Mobile_Menu_Walker extends Walker_Nav_Menu
{
	// array to track exactly which items successfully opened an <li>.
	// removes the need to guess or repeat logic in end_el.
	private $opened_items = [];

	public function start_lvl(&$output, $depth = 0, $args = null) {}
	public function end_lvl(&$output, $depth = 0, $args = null) {}

	public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
	{

		if ($item->menu_item_parent) return;
		if (in_array('hide-mobile', $item->classes, true)) return;

		$classes   = empty($item->classes) ? [] : (array) $item->classes;
		$classes = apply_filters('nav_menu_css_class', array_filter($classes), $item, $args);

		if (in_array('hide-mobile', $classes, true)) return;

		$this->opened_items[] = $item->ID;

		$is_custom = (
			isset($item->mobile_submenu_type) &&
			$item->mobile_submenu_type === 'custom_grid' &&
			in_array('has-children', $classes, true)
		);


		if ($is_custom) $classes[] = 'menu-item-custom-grid';
		$class_names = join(' ', $classes);

		$output .= '<li class="' . esc_attr($class_names) . '">';

		// render standard Link
		if (! $is_custom) {
			$output .= '<a class="menu-button" href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
		}
		// render Custom Grid
		else {
			$output .= '<a class="menu-button opportunities-toggle" href="#" aria-expanded="false">';
			$output .= '<span>' . esc_html($item->title) . '</span>';
			$output .= '<svg class="arrow-icon" width="24" height="24"><use xlink:href="' . get_template_directory_uri() . '/assets/img/sprites.svg#icon-arrows"></use></svg>';
			$output .= '</a>';

			$children = get_posts([
				'post_type'   => 'nav_menu_item',
				'numberposts' => -1,
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
				'meta_query'  => [['key' => '_menu_item_menu_item_parent', 'value' => $item->ID]]
			]);

			if ($children) {
				$half = ceil(count($children) / 2);
				$rows = array_chunk($children, $half);

				$output .= '<div class="mobile-submenu">';
				foreach ($rows as $row) {
					$output .= '<div class="mobile-submenu-row">';
					foreach ($row as $ch) {
						$img_id = get_field('images', $ch->ID);
						$url    = $img_id ? wp_get_attachment_image_url($img_id, 'full') : '';
						$output .= '<a class="mobile-submenu-item" href="' . esc_url(get_post_meta($ch->ID, '_menu_item_url', true)) . '">';
						if ($url) $output .= '<img src="' . esc_url($url) . '" alt="" class="submenu-image">';
						$output .= '<span class="text">' . esc_html($ch->post_title) . '</span></a>';
					}
					$output .= '</div>';
				}
				$output .= '</div>';
			}
		}
	}
	public function end_el(&$output, $item, $depth = 0, $args = null)
	{
		// array checking. if this item's ID is in the "Opened" list, we close it
		if (in_array($item->ID, $this->opened_items, true)) {
			$output .= '</li>';
		}
	}
}
