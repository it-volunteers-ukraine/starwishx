<?php
/**
 * contact.php
 * ACF block render for Contact (keys == class names)
 */

/* ===========================
   Default classes (keys equal values)
   =========================== */
$default_classes = [
    'contact-section'        => 'contact-section',
    'contact-container'      => 'contact-container',

    'contact-block'          => 'contact-block',
    'contact-group'          => 'contact-group',
    'contact-titles'         => 'contact-titles',
    'contact-title-small'    => 'contact-title-small',
    'contact-title-medium'   => 'contact-title-medium',
    'contact-title-big'      => 'contact-title-big',
    'contact-subtitle'       => 'contact-subtitle',

    'contact-list'           => 'contact-list',
    'contact-item'           => 'contact-item',
    'contact-label'          => 'contact-label',
    'contact-value'          => 'contact-value',
    'icon'                   => 'icon',

    'contact-avatars'        => 'contact-avatars',
    'contact-avatar-item'    => 'contact-avatar-item',

    'contact-form'           => 'contact-form',
    'contact-form-field'     => 'contact-form-field',
    'contact-form-textarea'  => 'contact-form-textarea',
    'contact-counter'        => 'contact-counter',

    'label-text'             => 'label-text',
    'label-required'         => 'label-required',
    'label-wrapper'          => 'label-wrapper',

    'contact-privacy'        => 'contact-privacy',
    'contact-submit'         => 'contact-submit'
];

/* Load compiled module classes if exist */
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    // modules.json uses top-level key "contact" — merge if available
    $classes = array_merge($default_classes, $modules['contact'] ?? []);
}

/* ===========================
   ACF block fields
   =========================== */
$small     = get_field('title_small');
$medium    = get_field('title_medium');
$big       = get_field('title_big');
$subtitle  = get_field('subtitle');

$form_name_label        = get_field('form_name_label');
$form_phone_label       = get_field('form_phone_label');
$form_email_label       = get_field('form_email_label');
$form_message_label     = get_field('form_message_label');

$form_name_placeholder  = get_field('form_name_placeholder');
$form_phone_placeholder = get_field('form_phone_placeholder');
$form_email_placeholder = get_field('form_email_placeholder');
$form_message_placeholder = get_field('form_message_placeholder');

$req_name    = get_field('form_name_required');
$req_phone   = get_field('form_phone_required');
$req_email   = get_field('form_email_required');
$req_message = get_field('form_message_required');

$form_privacy      = get_field('form_privacy_text');
$form_submit_text  = get_field('form_submit_text');

$form_counter_label_raw = get_field('form_counter_label');
$char_limit = intval($form_counter_label_raw);
if ($char_limit < 1) $char_limit = 500;

/* ===========================
   Theme settings (Common Info)
   =========================== */
$email_link     = get_field('email_link', 'option');
$email_name     = get_field('email_name', 'option');

$telegram_link  = get_field('telegram_link', 'option');
$telegram_name  = get_field('telegram_name', 'option');

$linkedin_link  = get_field('linkedin_link', 'option');
$linkedin_name  = get_field('linkedin_name', 'option');

$avatars        = get_field('avatars', 'option');

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

/* Helper: render label with optional required star */
function contact_field_label_html($text, $required, $classes) {
    $text_safe = esc_html($text);
    $label_text_html = '<span class="' . esc_attr($classes['label-text']) . '">' . $text_safe . '</span>';
    if ($required) {
        $label_text_html .= '<span class="' . esc_attr($classes['label-required']) . '">*</span>';
    }
    return $label_text_html;
}

/* Helper: render svg use tag with full sprite path */
function contact_icon_use($icon_id, $classes = []) {
    $sprite = get_template_directory_uri() . '/assets/img/sprites.svg';
    $icon_class = $classes['icon'] ?? 'icon';
    return '<svg class="' . esc_attr($icon_class) . '" aria-hidden="true"><use xlink:href="' . esc_attr($sprite . '#' . $icon_id) . '"></use></svg>';
}
?>

<section class="<?= esc_attr($classes['contact-section']) ?>">
  <div class="container <?= esc_attr($classes['contact-container']) ?>">

    <div class="<?= esc_attr($classes['contact-block']) ?>">
      <!-- Titles -->
      <div class="<?= esc_attr($classes['contact-titles']) ?>">
        <?php if ($small): ?><div class="<?= esc_attr($classes['contact-title-small']) ?>"><?= esc_html($small) ?></div><?php endif; ?>
        <?php if ($medium): ?><div class="<?= esc_attr($classes['contact-title-medium']) ?>"><?= esc_html($medium) ?></div><?php endif; ?>
        <?php if ($subtitle): ?><div class="<?= esc_attr($classes['contact-subtitle']) ?>"><?= esc_html($subtitle) ?></div><?php endif; ?>
      </div>

      <!-- Contacts -->
      <div class="<?= esc_attr($classes['contact-list']) ?>">
        <!-- С проверкой -->
        <?php if ($big): ?><p class="<?= esc_attr($classes['contact-title-big']) ?>"><?= wp_strip_all_tags($big) ?></p><?php endif; ?>

        <div class="<?= esc_attr($classes['contact-group']) ?>">
          <?php if ($email_link): ?>
          <div class="<?= esc_attr($classes['contact-item']) ?>">
            <?php echo contact_icon_use('icon-email', $classes); ?>
            <span class="<?= esc_attr($classes['contact-label']) ?>">Email:</span>
            <a href="mailto:<?= esc_attr($email_link) ?>" class="<?= esc_attr($classes['contact-value']) ?>"><?= esc_html($email_name ?: $email_link) ?></a>
          </div>
          <?php endif; ?>

          <?php if ($telegram_link): ?>
          <div class="<?= esc_attr($classes['contact-item']) ?>">
            <?php echo contact_icon_use('icon-telegram', $classes); ?>
            <span class="<?= esc_attr($classes['contact-label']) ?>">Telegram:</span>
            <a href="<?= esc_url($telegram_full_url) ?>" target="_blank" class="<?= esc_attr($classes['contact-value']) ?>">@<?= esc_html($telegram_name ?: $clean_telegram) ?></a>
          </div>
          <?php endif; ?>

          <?php if ($linkedin_link): ?>
          <div class="<?= esc_attr($classes['contact-item']) ?>">
            <?php echo contact_icon_use('icon-linkedin', $classes); ?>
            <span class="<?= esc_attr($classes['contact-label']) ?>">LinkedIn:</span>
            <a href="<?= esc_url($linkedin_full_url) ?>" target="_blank" class="<?= esc_attr($classes['contact-value']) ?>"><?= esc_html($linkedin_name ?: $clean_linkedin) ?></a>
          </div>
          <?php endif; ?>
        </div>

        <!-- Avatars -->
        <?php if (!empty($avatars) && is_array($avatars)): ?>
        <div class="<?= esc_attr($classes['contact-avatars']) ?>">
          <?php foreach ($avatars as $avatar):
              $img_id = $avatar['avatar_image']['ID'] ?? $avatar['avatar_image'] ?? false;
              if (!$img_id) continue;
          ?>
            <div class="<?= esc_attr($classes['contact-avatar-item']) ?>">
              <?= wp_get_attachment_image($img_id, [48,48], false, ['alt' => 'avatar']) ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Form -->
    <form class="<?= esc_attr($classes['contact-form']) ?>" novalidate>

      <label class="<?= esc_attr($classes['contact-form-field']) ?>">
        <div class="<?= esc_attr($classes['label-wrapper']) ?>">
          <?= contact_field_label_html($form_name_label, $req_name, $classes) ?>
        </div>
        <input type="text" name="name" <?= $req_name ? 'required' : '' ?> placeholder="<?= esc_attr($form_name_placeholder) ?>">
      </label>

      <label class="<?= esc_attr($classes['contact-form-field']) ?>">
  <div class="<?= esc_attr($classes['label-wrapper']) ?>">
    <?= contact_field_label_html($form_phone_label, $req_phone, $classes) ?>
  </div>
  <input type="tel"
         class="contact-phone-input"
         name="phone"
         <?= $req_phone ? 'required' : '' ?>
         placeholder="<?= esc_attr($form_phone_placeholder) ?>">
</label>


      <label class="<?= esc_attr($classes['contact-form-field']) ?>">
        <div class="<?= esc_attr($classes['label-wrapper']) ?>">
          <?= contact_field_label_html($form_email_label, $req_email, $classes) ?>
        </div>
        <input type="email" name="email" <?= $req_email ? 'required' : '' ?> placeholder="<?= esc_attr($form_email_placeholder) ?>">
      </label>

      <label class="<?= esc_attr($classes['contact-form-textarea']) ?>">
        <div class="<?= esc_attr($classes['label-wrapper']) ?>">
          <?= contact_field_label_html($form_message_label, $req_message, $classes) ?>
        </div>
        <textarea name="message" maxlength="<?= esc_attr($char_limit) ?>" <?= $req_message ? 'required' : '' ?> placeholder="<?= esc_attr($form_message_placeholder) ?>"></textarea>
        <span class="<?= esc_attr($classes['contact-counter']) ?>">0/<?= esc_html($char_limit) ?></span>
      </label>
<!-- Без проверки -->
      <p class="<?= esc_attr($classes['contact-privacy']) ?>"><?= wp_strip_all_tags($form_privacy) ?></p>

      <button type="submit" class="<?= esc_attr($classes['contact-submit']) ?>"><?= esc_html($form_submit_text) ?></button>
    </form>

  </div>
</section>
<!-- Узнать фактические классы, прошедшие через modules.json -->
<script>
window.contactFormClasses = {
    form: "<?= esc_js($classes['contact-form']) ?>",
    counter: "<?= esc_js($classes['contact-counter']) ?>"
};
</script>

<!-- intl-tel-input (CDN) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>
<!-- utilsScript нужен для форматирования/валидации -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js"></script>

<script src="<?= esc_url( get_template_directory_uri() . '/inc/acf/blocks/contact_form/contact.js' ) ?>"></script>


