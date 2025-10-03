<?php
/**
 * Desktop walker – подменю с картинкой-фоном + поиск + язык
 */
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

        // ссылка
        $output .= '<a href="' . esc_url( $item->url ) . '">';

        /* картинка-фон (только подпункты) */
        if ( $depth === 1 ) {
            $img_id = get_field( 'images', $item );
            if ( $img_id ) {
                $url = wp_get_attachment_image_url( $img_id, 'full' );
                $output .= '<img src="' . esc_url( $url ) . '" alt="" class="submenu-bg-image">';
            }
        }

        $output .= '<span class="submenu-text">' . $item->title . '</span>';
        $output .= '</a>';

        // НЕ закрываем </li> сразу, если есть дети – дождёмся end_el()
    }

    public function end_el( &$output, $item, $depth = 0, $args = null ) {
        $output .= '</li>';
    }

    /* поиск + язык в конец меню */
    public function walk( $elements, $max_depth, ...$args ) {
        $output = parent::walk( $elements, $max_depth, ...$args );
        if ( isset( $args[0]->theme_location ) && $args[0]->theme_location === 'menu-header' ) {
            $output .= '
            <li class="menu-item menu-item-search">
                <div class="div-search">
                    <svg class="search-icon"><use xlink:href="'.get_template_directory_uri().'/assets/img/sprites.svg#icon-search"></use></svg>
                </div>
            </li>
            <li class="menu-item menu-item-lang">
                <div class="lang">
                    <button class="lang-btn">УКР</button>
                    <span class="lang-separator">|</span>
                    <button class="lang-btn">ENG</button>
                </div>
            </li>';
        }
        return $output;
    }
}