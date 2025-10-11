<?php

class Mobile_Menu_Walker extends Walker_Nav_Menu {

	
	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {

		if ( $item->menu_item_parent ) return;
		if ( in_array( 'hide-mobile', $item->classes, true ) ) return;

		$classes   = empty( $item->classes ) ? [] : (array) $item->classes;
		$is_custom = ( $item->mobile_submenu_type === 'custom_grid' && in_array( 'has-children', $classes, true ) );

		
		if ( $is_custom ) $classes[] = 'menu-item-custom-grid';
		$class_names = join( ' ', $classes );
		$output     .= '<li class="' . esc_attr( $class_names ) . '">';

		
		if ( ! $is_custom ) {
			$output .= '<a class="menu-button" href="' . esc_url( $item->url ) . '">' . esc_html( $item->title ) . '</a></li>';
			return;
		}

		
		$output .= '<a class="menu-button opportunities-toggle" href="#" aria-expanded="false">';
		$output .= '<span>' . esc_html( $item->title ) . '</span>';
		$output .= '<svg class="arrow-icon" width="24" height="24"><use xlink:href="'.get_template_directory_uri().'/assets/img/sprites.svg#icon-arrows"></use></svg>';
		$output .= '</a>';

		
		$children = get_posts( [
			'post_type'   => 'nav_menu_item',
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'meta_query'  => [ [ 'key' => '_menu_item_menu_item_parent', 'value' => $item->ID ] ]
		] );

		if ( $children ) {
			$half = ceil( count( $children ) / 2 );
			$rows = array_chunk( $children, $half );

			$output .= '<div class="mobile-submenu">';
			foreach ( $rows as $row ) {
				$output .= '<div class="mobile-submenu-row">';
				foreach ( $row as $ch ) {
					$img_id = get_field( 'images', $ch->ID );
					$url    = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : '';
					$output .= '<a class="mobile-submenu-item" href="' . esc_url( get_post_meta( $ch->ID, '_menu_item_url', true ) ) . '">';
					if ( $url ) $output .= '<img src="' . esc_url( $url ) . '" alt="" class="submenu-image">';
					$output .= '<span class="text">' . esc_html( $ch->post_title ) . '</span></a>';
				}
				$output .= '</div>';
			}
			$output .= '</div>';
		}
		$output .= '</li>';
	}

	}