<?php

/* ===========================
   Дефолтные CSS-классы
   =========================== */

$default_classes = [
    'section'        => 'contact-section',
    'titles'         => 'contact-titles',
    'title-small'    => 'contact-title-small',
    'title-medium'   => 'contact-title-medium',
    'title-big'      => 'contact-title-big',

    'contacts'       => 'contact-list',
    'contact-item'   => 'contact-item',
    'contact-label'  => 'contact-label',
    'contact-value'  => 'contact-value',

    'avatars'        => 'contact-avatars',
    'avatar-item'    => 'contact-avatar-item',

    'form'           => 'contact-form',
    'form-field'     => 'contact-form-field',
    'form-textarea'  => 'contact-form-textarea',
    'counter'        => 'contact-counter',
    'privacy'        => 'contact-privacy',
    'submit'         => 'contact-submit',
    'container'      => 'contact-container'
];

/* Подключение кастомных классов из modules.json */
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['contact_form'] ?? []);
}


/* ===========================
   Поля ACF блока
   =========================== */

$small     = get_field('title_small');
$medium    = get_field('title_medium');
$big       = get_field('title_big');

$form_name_label         = get_field('form_name_label');
$form_phone_label        = get_field('form_phone_label');
$form_email_label        = get_field('form_email_label');
$form_message_label      = get_field('form_message_label');

$form_name_placeholder   = get_field('form_name_placeholder');
$form_phone_placeholder  = get_field('form_phone_placeholder');
$form_email_placeholder  = get_field('form_email_placeholder');
$form_message_placeholder = get_field('form_message_placeholder');

$subtitle                = get_field('subtitle');  // теперь идёт перед textarea
$form_counter_label      = get_field('form_counter_label');
$form_privacy            = get_field('form_privacy_text');
$form_submit_text        = get_field('form_submit_text');


/* ===========================
   Theme Settings → Common Info
   =========================== */

$email_link     = get_field('email_link', 'option');
$email_name     = get_field('email_name', 'option');

$telegram_link  = get_field('telegram_link', 'option');
$telegram_name  = get_field('telegram_name', 'option');

$linkedin_link  = get_field('linkedin_link', 'option');
$linkedin_name  = get_field('linkedin_name', 'option');

$avatars        = get_field('avatars', 'option');


/* ===========================
   Telegram URL
   =========================== */

$telegram_full_url = '';
$clean_telegram = '';

if ($telegram_link) {
    $clean_telegram = trim($telegram_link);
    $clean_telegram = str_replace('https://t.me/', '', $clean_telegram);
    $clean_telegram = ltrim($clean_telegram, '@');
    $telegram_full_url = 'https://t.me/' . $clean_telegram;
}


/* ===========================
   LinkedIn URL
   =========================== */

$linkedin_full_url = '';

if ($linkedin_link) {
    $clean_linkedin = trim($linkedin_link);

    if (strpos($clean_linkedin, 'http') === 0) {
        $linkedin_full_url = $clean_linkedin;
    } else {
        $linkedin_full_url = 'https://linkedin.com/in/' . $clean_linkedin;
    }
}

?>

<section class="<?= esc_attr($classes['section']) ?>">
<div class="container <?= esc_attr($classes['container']) ?>">

    <!-- Заголовки -->
    <div class="<?= esc_attr($classes['titles']) ?>">

        <?php if ($small): ?>
            <div class="<?= esc_attr($classes['title-small']) ?>"><?= esc_html($small) ?></div>
        <?php endif; ?>

        <?php if ($medium): ?>
            <div class="<?= esc_attr($classes['title-medium']) ?>"><?= esc_html($medium) ?></div>
        <?php endif; ?>

        <?php if ($big): ?>
            <div class="<?= esc_attr($classes['title-big']) ?>"><?= esc_html($big) ?></div>
        <?php endif; ?>

    </div>


    <!-- Контакты -->
    <div class="<?= esc_attr($classes['contacts']) ?>">

        <?php if ($email_link): ?>
        <div class="<?= esc_attr($classes['contact-item']) ?>">
            <span class="<?= esc_attr($classes['contact-label']) ?>">Email:</span>
            <a href="mailto:<?= esc_attr($email_link) ?>" class="<?= esc_attr($classes['contact-value']) ?>">
                <?= esc_html($email_name ?: $email_link) ?>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($telegram_link): ?>
        <div class="<?= esc_attr($classes['contact-item']) ?>">
            <span class="<?= esc_attr($classes['contact-label']) ?>">Telegram:</span>
            <a href="<?= esc_url($telegram_full_url) ?>" target="_blank" class="<?= esc_attr($classes['contact-value']) ?>">
                @<?= esc_html($telegram_name ?: $clean_telegram) ?>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($linkedin_link): ?>
        <div class="<?= esc_attr($classes['contact-item']) ?>">
            <span class="<?= esc_attr($classes['contact-label']) ?>">LinkedIn:</span>
            <a href="<?= esc_url($linkedin_full_url) ?>" target="_blank" class="<?= esc_attr($classes['contact-value']) ?>">
                <?= esc_html($linkedin_name ?: $clean_linkedin) ?>
            </a>
        </div>
        <?php endif; ?>

    </div>


    <!-- Аватары -->
    <?php if (!empty($avatars) && is_array($avatars)): ?>
    <div class="<?= esc_attr($classes['avatars']) ?>">
        <?php foreach ($avatars as $avatar):
            $img_data = $avatar['avatar_image'] ?? false;
            $img_id   = $img_data['ID'] ?? false;
            if (!$img_id) continue;
        ?>
            <div class="<?= esc_attr($classes['avatar-item']) ?>">
                <?= wp_get_attachment_image($img_id, 'thumbnail', false, ['alt' => 'avatar']) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>


    <!-- Форма -->
    <form class="<?= esc_attr($classes['form']) ?>">

        <label class="<?= esc_attr($classes['form-field']) ?>">
            <?= esc_html($form_name_label) ?>
            <input type="text" name="name"
                   placeholder="<?= esc_attr($form_name_placeholder) ?>">
        </label>

        <label class="<?= esc_attr($classes['form-field']) ?>">
            <?= esc_html($form_phone_label) ?>
            <input type="text" name="phone"
                   placeholder="<?= esc_attr($form_phone_placeholder) ?>">
        </label>

        <label class="<?= esc_attr($classes['form-field']) ?>">
            <?= esc_html($form_email_label) ?>
            <input type="email" name="email"
                   placeholder="<?= esc_attr($form_email_placeholder) ?>">
        </label>

        <!-- subtitle теперь здесь -->
        <?php if ($subtitle): ?>
            <p class="<?= esc_attr($classes['subtitle']) ?>"><?= esc_html($subtitle) ?></p>
        <?php endif; ?>

        <label class="<?= esc_attr($classes['form-textarea']) ?>">
            <?= esc_html($form_message_label) ?>
            <textarea name="message"
                      maxlength="500"
                      placeholder="<?= esc_attr($form_message_placeholder) ?>"></textarea>
            <span class="<?= esc_attr($classes['counter']) ?>"><?= esc_html($form_counter_label) ?></span>
        </label>

        <p class="<?= esc_attr($classes['privacy']) ?>"><?= esc_html($form_privacy) ?></p>

        <button type="submit" class="<?= esc_attr($classes['submit']) ?>">
            <?= esc_html($form_submit_text) ?>
        </button>

    </form>

</div>
</section>

<!-- Подключение counter.js -->
<script src="<?= get_template_directory_uri(); ?>/inc/acf/blocks/contact_form/contact.js"></script>
