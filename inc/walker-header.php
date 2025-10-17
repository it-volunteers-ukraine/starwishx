<?php

class Header_Menu_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = null ) {
		if ( $depth === 0 ) $output .= '<ul class="sub-menu">';
	}

	public function end_lvl( &$output, $depth = 0, $args = null ) {
		if ( $depth === 0 ) $output .= '</ul>';
	}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {

	$classes   = empty( $item->classes ) ? [] : (array) $item->classes;
	$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );

	$output .= '<li class="' . esc_attr( $class_names ) . '">';

	
	$output .= '<a href="' . esc_url( $item->url ) . '">';

	
	if ( $depth === 1 ) {
		$img_id = get_field( 'images', $item );
		if ( $img_id ) {
			$url = wp_get_attachment_image_url( $img_id, 'full' );
			$output .= '<img src="' . esc_url( $url ) . '" alt="" class="submenu-bg-image">';
		}
	}

	$output .= '<span class="submenu-text">' . $item->title . '</span>';

	
	if ( $depth === 0 && in_array( 'has-children', $classes, true ) ) {
		$output .= '<svg class="arrow-icon" width="24" height="24" aria-hidden="true">
			<use href="' . get_template_directory_uri() . '/assets/img/sprites.svg#icon-arrows"></use>
		</svg>';
	}

	$output .= '</a>';
}

public function end_el( &$output, $item, $depth = 0, $args = null ) {
	$output .= '</li>';
}

	
	public function walk( $elements, $max_depth, ...$args ) {
		$output = parent::walk( $elements, $max_depth, ...$args );

		
		if ( isset( $args[0]->theme_location ) && $args[0]->theme_location === 'menu-header' && $args[0]->walker instanceof $this ) {
			
		}
		return $output;
	}
}