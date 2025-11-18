<?php

$default_classes = [
    'section'        => 'contact-section',
    'titles'         => 'contact-titles',
    'title-small'    => 'contact-title-small',
    'title-medium'   => 'contact-title-medium',
    'title-big'      => 'contact-title-big',
    'subtitle'       => 'contact-subtitle',

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
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['contact_form'] ?? []);
}

/* ===========================
   Поля блока из ACF
   =========================== */

$small     = get_field('title_small');
$medium    = get_field('title_medium');
$big       = get_field('title_big');
$subtitle  = get_field('subtitle');

$form_name_label        = get_field('form_name_label');
$form_phone_label       = get_field('form_phone_label');
$form_email_label       = get_field('form_email_label');
$form_message_label     = get_field('form_message_label');
$form_message_placeholder = get_field('form_message_placeholder');
$form_counter_label     = get_field('form_counter_label');
$form_privacy           = get_field('form_privacy_text');
$form_submit_text       = get_field('form_submit_text');

/* ===========================
   Поля из Theme Settings → Common Info
   =========================== */

$email        = get_field('email', 'option');
$telegram     = get_field('telegram', 'option');
$linkedin     = get_field('linkedin', 'option');

$avatar_email    = get_field('avatar_email', 'option');
$avatar_telegram = get_field('avatar_telegram', 'option');
$avatar_linkedin = get_field('avatar_linkedin', 'option');

?>

<section class="<?= esc_attr($classes['section']) ?>">
<div class="container <?php echo esc_attr($classes['container']); ?>">
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

        <?php if ($subtitle): ?>
            <p class="<?= esc_attr($classes['subtitle']) ?>"><?= esc_html($subtitle) ?></p>
        <?php endif; ?>
    </div>

    <div class="<?= esc_attr($classes['contacts']) ?>">

        <?php if ($email): ?>
        <div class="<?= esc_attr($classes['contact-item']) ?>">
            <span class="<?= esc_attr($classes['contact-label']) ?>">Email:</span>
            <a href="mailto:<?= esc_attr($email) ?>" class="<?= esc_attr($classes['contact-value']) ?>">
                <?= esc_html($email) ?>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($telegram): ?>
        <div class="<?= esc_attr($classes['contact-item']) ?>">
            <span class="<?= esc_attr($classes['contact-label']) ?>">Telegram:</span>
            <a href="https://t.me/<?= esc_attr($telegram) ?>" target="_blank" class="<?= esc_attr($classes['contact-value']) ?>">
                @<?= esc_html($telegram) ?>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($linkedin): ?>
        <div class="<?= esc_attr($classes['contact-item']) ?>">
            <span class="<?= esc_attr($classes['contact-label']) ?>">LinkedIn:</span>
            <a href="<?= esc_url($linkedin) ?>" target="_blank" class="<?= esc_attr($classes['contact-value']) ?>">
                <?= esc_html($linkedin) ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="<?= esc_attr($classes['avatars']) ?>">
        <?php if ($avatar_email): ?>
            <div class="<?= esc_attr($classes['avatar-item']) ?>">
                <img src="<?= esc_url($avatar_email['url']) ?>" alt="email avatar">
            </div>
        <?php endif; ?>

        <?php if ($avatar_telegram): ?>
            <div class="<?= esc_attr($classes['avatar-item']) ?>">
                <img src="<?= esc_url($avatar_telegram['url']) ?>" alt="telegram avatar">
            </div>
        <?php endif; ?>

        <?php if ($avatar_linkedin): ?>
            <div class="<?= esc_attr($classes['avatar-item']) ?>">
                <img src="<?= esc_url($avatar_linkedin['url']) ?>" alt="linkedin avatar">
            </div>
        <?php endif; ?>
    </div>

    <form class="<?= esc_attr($classes['form']) ?>">
        <label class="<?= esc_attr($classes['form-field']) ?>">
            <?= esc_html($form_name_label) ?>
            <input type="text" name="name">
        </label>

        <label class="<?= esc_attr($classes['form-field']) ?>">
            <?= esc_html($form_phone_label) ?>
            <input type="text" name="phone">
        </label>

        <label class="<?= esc_attr($classes['form-field']) ?>">
            <?= esc_html($form_email_label) ?>
            <input type="email" name="email">
        </label>

        <label class="<?= esc_attr($classes['form-textarea']) ?>">
            <?= esc_html($form_message_label) ?>
            <textarea name="message" maxlength="500" placeholder="<?= esc_attr($form_message_placeholder) ?>"></textarea>
            <span class="<?= esc_attr($classes['counter']) ?>"><?= esc_html($form_counter_label) ?></span>
        </label>

        <p class="<?= esc_attr($classes['privacy']) ?>"><?= esc_html($form_privacy) ?></p>

        <button type="submit" class="<?= esc_attr($classes['submit']) ?>">
            <?= esc_html($form_submit_text) ?>
        </button>
    </form>

</section>
