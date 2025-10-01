<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
$login_button_text = get_field('header_button', 'option') ?: 'Увійти';
?>
<?php
// === ОТЛАДКА: проверка меню ===
$locations = get_nav_menu_locations();
$menu_name = 'menu-header';
echo '<!-- Меню-локации: ' . print_r($locations, true) . ' -->';
if (!empty($locations[$menu_name])) {
  $menu = wp_get_nav_menu_object($locations[$menu_name]);
  echo '<!-- Объект меню: ' . print_r($menu, true) . ' -->';
  if ($menu) {
    $items = wp_get_nav_menu_items($menu->term_id);
    echo '<!-- Кол-во пунктов: ' . count((array)$items) . ' -->';
  }
}
?>
<header class="header">
	<div class="container">
		<div class="header-inner">

			<!-- Логотип -->
			<div class="header-left">
				<div class="header-logo">
					<?php if (has_custom_logo()) echo get_custom_logo(); ?>
				</div>
			</div>

			<!-- Чекбокс для мобильного меню (скрыт) -->
			<input type="checkbox" id="mobile-menu-toggle" class="mobile-menu-toggle" />

			<!-- Кнопка "Меню" -->
			<label for="mobile-menu-toggle" class="burger-menu-button">
				<span>Меню</span>
				<svg class="burger-icon" width="16" height="16" aria-hidden="true">
					<use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-burger"></use>
				</svg>
			</label>

			<!-- Кнопки поиска, языка и закрытия -->
			<div class="mobile-header-buttons">
				<div class="search-language-container">
					<div class="search-icon">
						<svg width="16" height="16" aria-hidden="true">
							<use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-search"></use>
						</svg>
					</div>
					<div class="language-switch">
						<button class="lang-btn">УКР</button>
						<span class="lang-separator">|</span>
						<button class="lang-btn">ENG</button>
					</div>
				</div>
				<label for="mobile-menu-toggle" class="close-menu-button">
					<svg class="close-icon" width="16" height="16" aria-hidden="true">
						<use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-close"></use>
					</svg>
				</label>
			</div>

			<!-- Десктопное меню -->
			<div class="header-center">
				<nav class="site-head-nav">
					<ul class="menu">
						<?php
						wp_nav_menu([
							'theme_location' => 'menu-header',
							'container'      => false,
							'items_wrap'     => '%3$s',
							'echo'           => true,
						]);
						?>
						<li class="menu-item menu-item-search">
							<div class="div-search">
								<svg class="search-icon">
									<use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-search"></use>
								</svg>
							</div>
						</li>
						<li class="menu-item menu-item-lang">
							<div class="lang">
								<button class="lang-btn">УКР</button>
								<span class="lang-separator">|</span>
								<button class="lang-btn">ENG</button>
							</div>
						</li>
					</ul>
				</nav>
			</div>

			<!-- Десктопная кнопка входа -->
			<div class="header-right">
				<a href="#" class="header-login-btn"><?php echo esc_html($login_button_text); ?></a>
			</div>

			<!-- Мобильное меню (динамическое с ACF) -->
			<div class="burger-menu">
				<ul class="mobile-menu-list">
					<?php
					$menu_name = 'menu-header';
					$locations = get_nav_menu_locations();
					if (!empty($locations[$menu_name])) {
						$menu = wp_get_nav_menu_object($locations[$menu_name]);
						$menu_items = wp_get_nav_menu_items($menu->term_id, ['orderby' => 'menu_order']);

						if ($menu_items) {
							// Группируем детей по parent ID
							$children = [];
							foreach ($menu_items as $item) {
								$children[$item->ID] = [];
							}
							foreach ($menu_items as $item) {
								if ($item->menu_item_parent && isset($children[$item->menu_item_parent])) {
									$children[$item->menu_item_parent][] = $item;
								}
							}

							// Выводим только корневые пункты
							foreach ($menu_items as $item) {
								if ($item->menu_item_parent != 0) continue;

								// Пропускаем служебные пункты
								if (in_array('menu-item-search', $item->classes) || in_array('menu-item-lang', $item->classes)) {
									continue;
								}

								$has_children = !empty($children[$item->ID]); 
								$submenu_type = get_field('mobile_submenu_type', 'nav_menu_item_' . $item->ID);
 error_log("Пункт: {$item->title} | ID: {$item->ID} | Parent: {$item->menu_item_parent} | Детей: " . count($children[$item->ID]) . " | ACF: " . ($submenu_type ?: 'пусто'));
								if ($has_children && $item->title === 'Можливості') {
									// if ($has_children && $submenu_type === 'custom_grid') {
									// === Кастомное подменю (сетка 2 ряда) ===
									echo '<li class="menu-item menu-item-opportunities">';
									echo '<a href="#" class="menu-button opportunities-toggle">';
									echo '<span>' . esc_html($item->title) . '</span>';
									echo '<svg class="arrow-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
									echo '<path d="M6 9L12 15L18 9" stroke="#000000" stroke-width="2"/>';
									echo '</svg>';
									echo '</a>';

									$child_items = $children[$item->ID];
									if ($child_items) {
										echo '<div class="mobile-submenu">';
										$half = ceil(count($child_items) / 2);
										$first_row = array_slice($child_items, 0, $half);
										$second_row = array_slice($child_items, $half);

										echo '<div class="mobile-submenu-row">';
										foreach ($first_row as $child) {
											echo '<a href="' . esc_url($child->url) . '" class="mobile-submenu-item">';
											echo '<span class="text">' . esc_html($child->title) . '</span>';
											echo '</a>';
										}
										echo '</div>';

										if (!empty($second_row)) {
											echo '<div class="mobile-submenu-row">';
											foreach ($second_row as $child) {
												echo '<a href="' . esc_url($child->url) . '" class="mobile-submenu-item">';
												echo '<span class="text">' . esc_html($child->title) . '</span>';
												echo '</a>';
											}
											echo '</div>';
										}
										echo '</div>'; // .mobile-submenu
									}
									echo '</li>';

								} else {
									// === Обычный пункт меню ===
									echo '<li class="menu-item">';
									echo '<a href="' . esc_url($item->url) . '" class="menu-button">' . esc_html($item->title) . '</a>';
									echo '</li>';
								}
							}
						}
					}
					?>
				</ul>

				<a href="#" class="login-button-mobile"><?php echo esc_html($login_button_text); ?></a>
			</div>

		</div>
	</div>
</header>