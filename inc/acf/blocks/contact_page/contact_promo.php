<?php
/**
 * Block: Contact Promo
 */

$default_classes = [
    'promo-section'   => 'promo-section',
    'promo-container' => 'promo-container',
    'promo-image'     => 'promo-image',
    'promo-content'   => 'promo-content',
    'promo-title'     => 'promo-title',
    'promo-text'      => 'promo-text',
    'promo-contacts'  => 'promo-contacts',
    'contact-item'    => 'contact-item',
    'contact-label'   => 'contact-label',
    'contact-value'   => 'contact-value',
    'icon'            => 'icon',
    'promo-planet'    => 'promo-planet',
];

// Подключение классов из modules.json (если есть)
$classes = $default_classes;
// Логика modules.json опущена для краткости, если нужно - добавлю, 
// но обычно $classes['...'] берется напрямую из массива выше.

/* --- Локальные поля блока (то, что ты создаешь сейчас) --- */
$image_id = get_field('promo_image');
$title    = get_field('promo_title');
$text     = get_field('promo_text');

/* --- Глобальные поля (из _Common Info / Options) --- */
// Используем 'option', так как в твоем contact.php они берутся оттуда
$email_link     = get_field('email_link', 'option');
$email_name     = get_field('email_name', 'option'); 
$telegram_link  = get_field('telegram_link', 'option');
$telegram_name  = get_field('telegram_name', 'option');
$linkedin_link  = get_field('linkedin_link', 'option');
$linkedin_name  = get_field('linkedin_name', 'option');

/* --- Хелперы для ссылок (из твоего примера) --- */
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

// Функция для иконок
function cp_icon($id) {
    return get_template_directory_uri() . '/assets/img/sprites.svg#' . $id;
}
?>

<section class="<?= $classes['promo-section'] ?>">
    <div class="<?= $classes['promo-container'] ?>">
        
        <?php if ($image_id): ?>
            <div class="<?= $classes['promo-image'] ?>">
                <?= wp_get_attachment_image($image_id, 'full', false, ['alt' => esc_attr($title)]) ?>
            </div>
        <?php endif; ?>

        <div class="<?= $classes['promo-content'] ?>">
            <div>
                <?php if ($title): ?>
                    <h5 class="<?= $classes['promo-title'] ?>"><?= esc_html($title) ?></h5>
                <?php endif; ?>

                <?php if ($text): ?>
                    <div class="<?= $classes['promo-text'] ?>"><?= wpautop(esc_html($text)) ?></div>
                <?php endif; ?>

                <div class="<?= $classes['promo-contacts'] ?>">
                    <?php if ($email_link): ?>
                        <div class="<?= $classes['contact-item'] ?>">
                            <svg class="<?= $classes['icon'] ?>"><use xlink:href="<?= cp_icon('icon-email') ?>"></use></svg>
                            <span class="<?= $classes['contact-label'] ?>">Email:</span>
                            <a href="mailto:<?= esc_attr($email_link) ?>" class="<?= $classes['contact-value'] ?>">
                                <?= esc_html($email_name ?: $email_link) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($telegram_link): ?>
                        <div class="<?= $classes['contact-item'] ?>">
                            <svg class="<?= $classes['icon'] ?>"><use xlink:href="<?= cp_icon('icon-telegram') ?>"></use></svg>
                            <span class="<?= $classes['contact-label'] ?>">Telegram:</span>
                            <a href="<?= esc_url($telegram_full_url) ?>" target="_blank" class="<?= $classes['contact-value'] ?>">
                                @<?= esc_html($telegram_name ?: $clean_telegram) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($linkedin_link): ?>
                        <div class="<?= $classes['contact-item'] ?>">
                            <svg class="<?= $classes['icon'] ?>"><use xlink:href="<?= cp_icon('icon-linkedin') ?>"></use></svg>
                            <span class="<?= $classes['contact-label'] ?>">LinkedIn:</span>
                            <a href="<?= esc_url($linkedin_full_url) ?>" target="_blank" class="<?= $classes['contact-value'] ?>">
                                <?= esc_html($linkedin_name ?: $clean_linkedin) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="<?= $classes['promo-planet'] ?>">
                <div class="planet-inner"></div>
                <div class="planet-star"></div>
            </div>
        </div>
    </div>
</section>