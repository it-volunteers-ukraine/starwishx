<?php
/**
 * contact_promo.php
 */

/* ===========================
   Default classes
   =========================== */
$default_classes = [
    'promo-section'   => 'promo-section',
    'promo-container' => 'promo-container',
    'promo-image'     => 'promo-image',
    'promo-content'   => 'promo-content',
    'promo-wrapper'   => 'promo-wrapper',
    'promo-title'     => 'promo-title',
    'promo-text'      => 'promo-text',
    'promo-contacts'  => 'promo-contacts',
    
    // Переиспользуемые классы (должны совпадать с SCSS)
    'contact-item'    => 'contact-item',
    'contact-label'   => 'contact-label',
    'contact-value'   => 'contact-value',
    'icon'            => 'icon',
    'icon-star'       => 'icon-star',
    'icon-bg'         => 'icon-bg',
    'icon-mask'       => 'icon-mask'
];

/* Load compiled module classes if exist */
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    // Ищем ключ 'contact_promo' (или добавь его в modules.json, если там строго)
    $classes = array_merge($default_classes, $modules['contact_promo'] ?? []);
}

/* ===========================
   ACF block fields
   =========================== */
$image_id = get_field('promo_image');
$title    = get_field('promo_title');
$text     = get_field('promo_text');

/* ===========================
   Theme settings (Common Info)
   =========================== */
$email_link     = get_field('email_link', 'option');
$email_name     = get_field('email_name', 'option');

$telegram_link  = get_field('telegram_link', 'option');
$telegram_name  = get_field('telegram_name', 'option');

$linkedin_link  = get_field('linkedin_link', 'option');
$linkedin_name  = get_field('linkedin_name', 'option');

/* Clean telegram / linkedin */
$telegram_full_url = '';
$clean_telegram = '';
if ($telegram_link) {
    $clean_telegram = trim($telegram_link);
    $clean_telegram = str_replace('https://t.me/', '', $clean_telegram);
    $clean_telegram = ltrim($clean_telegram, '@');
    $telegram_full_url = 'https://t.me/' . $clean_telegram;
}

$linkedin_full_url = '';
$clean_linkedin = '';
if ($linkedin_link) {
    $clean_linkedin = trim($linkedin_link);
    if (strpos($clean_linkedin, 'http') === 0) {
        $linkedin_full_url = $clean_linkedin;
    } else {
        $linkedin_full_url = 'https://linkedin.com/in/' . $clean_linkedin;
    }
}

/* Helper: render svg use tag */
// Используем уникальное имя функции или анонимную, чтобы не было конфликта с contact.php
if (!function_exists('promo_icon_use')) {
    function promo_icon_use($icon_id, $classes = []) {
        $sprite = get_template_directory_uri() . '/assets/img/sprites.svg';
        $icon_class = $classes['icon'] ?? 'icon';
        return '<svg class="' . esc_attr($icon_class) . '" aria-hidden="true"><use xlink:href="' . esc_attr($sprite . '#' . $icon_id) . '"></use></svg>';
    }
}
?>

<section class="<?= esc_attr($classes['promo-section']) ?>">
    <div class="<?= esc_attr($classes['promo-container']) ?>">
        
        <?php if ($image_id): ?>
            <div class="<?= esc_attr($classes['promo-image']) ?>">
                <?= wp_get_attachment_image($image_id, 'full', false, ['alt' => esc_attr($title)]) ?>
            </div>
        <?php endif; ?>

        <div class="<?= esc_attr($classes['promo-content']) ?>">
            
            <div class="<?= esc_attr($classes['promo-wrapper']) ?>">
                

                <?php if ($title): ?>
                    <div class="<?= esc_attr($classes['promo-title']) ?>"><?= esc_html($title) ?>
                </div><?php endif; ?>

                

                <?php if ($text): ?>
                    <div class="<?= esc_attr($classes['promo-text']); ?>"><?php echo $text; ?></div>
                    <?php endif; ?>

                <div class="<?= esc_attr($classes['promo-contacts']) ?>">
                    <?php if ($email_link): ?>
                        <div class="<?= esc_attr($classes['contact-item']) ?>">
                            <?= promo_icon_use('icon-email', $classes) ?>
                            <span class="<?= esc_attr($classes['contact-label']) ?>">Email:</span>
                            <a href="mailto:<?= esc_attr($email_link) ?>" class="<?= esc_attr($classes['contact-value']) ?>">
                                <?= esc_html($email_name ?: $email_link) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($telegram_link): ?>
                        <div class="<?= esc_attr($classes['contact-item']) ?>">
                            <?= promo_icon_use('icon-telegram', $classes) ?>
                            <span class="<?= esc_attr($classes['contact-label']) ?>">Telegram:</span>
                            <a href="<?= esc_url($telegram_full_url) ?>" target="_blank" class="<?= esc_attr($classes['contact-value']) ?>">
                                @<?= esc_html($telegram_name ?: $clean_telegram) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($linkedin_link): ?>
                        <div class="<?= esc_attr($classes['contact-item']) ?>">
                            <?= promo_icon_use('icon-linkedin', $classes) ?>
                            <span class="<?= esc_attr($classes['contact-label']) ?>">LinkedIn:</span>
                            <a href="<?= esc_url($linkedin_full_url) ?>" target="_blank" class="<?= esc_attr($classes['contact-value']) ?>">
                                <?= esc_html($linkedin_name ?: $clean_linkedin) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="<?php echo esc_attr($classes['icon-star']); ?>">
                    <img src="<?php echo get_template_directory_uri(); ?>/assets/img/star1-bg.png" class="<?php echo esc_attr($classes['icon-bg']); ?>" alt="">
                    <svg class="<?php echo esc_attr($classes['icon-mask']); ?>">
                        <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-element_planet_3-circle"></use>
                    </svg> 
                </div>

        </div>
    </div>
</section>