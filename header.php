<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
$login_button_text = get_field('header_button', 'option') ?: 'Увійти';
?>

<header class="header">
	<div class="container">
		<div class="header-inner">

			
			<div class="header-left">
				<div class="header-logo">
					<?php if (has_custom_logo()) echo get_custom_logo(); ?>
				</div>
			</div>

			
			<input type="checkbox" id="mobile-menu-toggle" class="mobile-menu-toggle" />

			
			<label for="mobile-menu-toggle" class="burger-menu-button">
				<span>Меню</span>
				<svg class="burger-icon" width="16" height="16" aria-hidden="true">
					<use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-burger"></use>
				</svg>
			</label>

			
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

			
			<div class="header-right">
				<a href="#" class="header-login-btn"><?php echo esc_html($login_button_text); ?></a>
			</div>

			
			<div class="burger-menu">
				
				<a href="#" id="opportunities-button-mobile" class="menu-button">
					Можливості
					<svg class="arrow-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M6 9L12 15L18 9" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</a>

				
				<div class="mobile-submenu" id="mobile-submenu">
					<a href="#" class="mobile-submenu-item"><div class="text">Соціальна підтримка</div></a>
					<a href="#" class="mobile-submenu-item"><div class="text">Професійний розвиток</div></a>
					<a href="#" class="mobile-submenu-item"><div class="text">Культура та хобі</div></a>
					<a href="#" class="mobile-submenu-item"><div class="text">Здоров’я та побут</div></a>
					<a href="#" class="mobile-submenu-item"><div class="text">Технології та інновації</div></a>
					<a href="#" class="mobile-submenu-item"><div class="text">Подорожі та еміграція</div></a>
				</div>

				
				<?php
				wp_nav_menu([
					'theme_location' => 'menu-header',
					'container'      => false,
					'items_wrap'     => '%3$s',
					'echo'           => true,
				]);
				?>

				
				<a href="#" class="login-button-mobile"><?php echo esc_html($login_button_text); ?></a>
			</div>

		</div>
	</div>
</header>