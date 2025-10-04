<?php
/**
 * Mobile walker: обычный список / сетка-2-ряда + поиск + язык
 */
class Mobile_Menu_Walker extends Walker_Nav_Menu {

	/* убираем пустые <ul> от WP */
	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {

		if ( $item->menu_item_parent ) return; // детей выводим внутри родителя

		$classes   = empty( $item->classes ) ? [] : (array) $item->classes;
		$is_custom = ( $item->mobile_submenu_type === 'custom_grid' && in_array( 'has-children', $classes, true ) );

		/* добавляем нужный класс к <li> */
		if ( $is_custom ) $classes[] = 'menu-item-custom-grid';
		$class_names = join( ' ', $classes );
		$output     .= '<li class="' . esc_attr( $class_names ) . '">';

		/* 1) обычный пункт */
		if ( ! $is_custom ) {
			$output .= '<a class="menu-button" href="' . esc_url( $item->url ) . '">' . esc_html( $item->title ) . '</a></li>';
			return;
		}

		/* 2) кастомная сетка */
		$output .= '<a class="menu-button opportunities-toggle" href="#" aria-expanded="false">';
		$output .= '<span>' . esc_html( $item->title ) . '</span>';
		$output .= '<svg class="arrow-icon" width="24" height="24"><use xlink:href="'.get_template_directory_uri().'/assets/img/sprites.svg#icon-arrow-down"></use></svg>';
		$output .= '</a>';

		// дети
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

	/* поиск + язык в конец мобильного меню */
	// public function walk( $elements, $max_depth, ...$args ) {
	// 	$output = parent::walk( $elements, $max_depth, ...$args );
	// 	if ( isset( $args[0]->theme_location ) && $args[0]->theme_location === 'menu-header' && $args[0]->walker instanceof $this ) {
	// 		$output .= '
	// 		<li class="menu-item menu-item-search">
	// 			<a class="menu-button" href="#">
	// 				<svg width="16" height="16"><use xlink:href="'.get_template_directory_uri().'/assets/img/sprites.svg#icon-search"></use></svg>
	// 				<span>Поиск</span>
	// 			</a>
	// 		</li>
	// 		<li class="menu-item menu-item-lang">
	// 			<div class="language-switch">
	// 				<button class="lang-btn">УКР</button>
	// 				<span class="lang-separator">|</span>
	// 				<button class="lang-btn">ENG</button>
	// 			</div>
	// 		</li>';
	// 	}
	// 	return $output;
	// }
}