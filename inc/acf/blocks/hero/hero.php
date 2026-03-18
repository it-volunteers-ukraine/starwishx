<?php
// Loading classes
$default_classes = [
    'section'     => 'section',
    'container'   => 'container',
    'hero'        => 'hero',
    'link'        => 'link',
    'image-wrap'  => 'image-wrap',
    'picture'     => 'picture',
    'image'       => 'image',
    'title'       => 'title',
    'subtitle'    => 'subtitle',
    'wrap-link'   => 'wrap-link',
    'text-block'  => 'text-block',
    'text-bottom' => 'text-bottom',
];

$classes = $default_classes;
$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
if (file_exists($modules_file)) {
    $modules = json_decode(file_get_contents($modules_file), true);
    $classes = array_merge($default_classes, $modules['hero'] ?? []);
}

$title       = esc_html(get_field('title'));
$subtitle    = esc_html(get_field('subtitle'));
$give        = esc_html(get_field('give'));
$get         = esc_html(get_field('get'));
$text_bottom = esc_html(get_field('text_bottom'));
$image       = get_field('image');
$pictures    = get_field('picture') ?: [];

// Breakpoints mapped to repeater rows 2..N (row 1 = <img> fallback)
$breakpoints = [768, 1024, 1440, 1920];
?>

<section class="section <?= esc_attr($classes['section']); ?>">
    <?php if ($pictures) : ?>
        <figure class="<?= esc_attr($classes['image-wrap']); ?>">
            <picture class="<?= esc_attr($classes['picture']); ?>">
                <?php
                $total = count($pictures);
                // Sources: rows 2..N mapped to breakpoints, output largest-first
                $sources = [];
                for ($i = 1; $i < $total && $i <= count($breakpoints); $i++) {
                    $src_image = $pictures[$i]['source_image'] ?? null;
                    if ($src_image) {
                        $sources[] = [
                            'breakpoint' => $breakpoints[$i - 1],
                            'srcset'     => sw_build_srcset($src_image),
                        ];
                    }
                }
                // Output largest-first (browser picks first matching source)
                foreach (array_reverse($sources) as $source) :
                    if ($source['srcset']) :
                ?>
                        <source media="(min-width: <?= (int) $source['breakpoint']; ?>px)"
                            srcset="<?= $source['srcset']; ?>"
                            sizes="100vw">
                    <?php
                    endif;
                endforeach;

                // Row 1 = <img> fallback (mobile)
                $fallback = $pictures[0]['source_image'] ?? null;
                if ($fallback) :
                    $fallback_srcset = sw_build_srcset($fallback);
                    $fallback_src    = $fallback['sizes']['large'] ?? $fallback['url'];
                    $fallback_alt    = ($fallback['alt'] ?? '') ?: ($fallback['title'] ?? '');
                    ?>
                    <img src="<?= esc_url($fallback_src); ?>"
                        srcset="<?= $fallback_srcset; ?>"
                        sizes="100vw"
                        class="<?= esc_attr($classes['image']); ?>"
                        alt="<?= esc_attr($fallback_alt); ?>"
                        fetchpriority="high"
                        decoding="async">
                <?php endif; ?>
            </picture>
        </figure>
    <?php elseif ($image) : ?>
        <figure class="<?= esc_attr($classes['image-wrap']); ?>">
            <img src="<?= esc_url($image['sizes']['large'] ?? $image['url']); ?>"
                srcset="<?= sw_build_srcset($image); ?>"
                sizes="100vw"
                class="<?= esc_attr($classes['image']); ?>"
                alt="<?= esc_attr(($image['alt'] ?? '') ?: ($image['title'] ?? '')); ?>"
                fetchpriority="high"
                decoding="async">
        </figure>
    <?php endif; ?>

    <div class="container <?= esc_attr($classes['container']); ?>">
        <div class="<?= esc_attr($classes['hero']); ?>">
            <h1 class="h1 <?= esc_attr($classes['title']); ?>"><?= $title; ?></h1>
            <p class="subtitle-text-r <?= esc_attr($classes['subtitle']); ?>"><?= $subtitle; ?></p>
            <div class="<?= esc_attr($classes['wrap-link']); ?>">
                <a class="h4" href="/opportunities/"><?= $give; ?></a>
                <?php if (is_user_logged_in()) : ?>
                    <a class="h4" href="/launchpad/?panel=opportunities&view=add"><?= $get; ?></a>
                <?php else : ?>
                    <a class="h4" href="/launchpad/?panel=opportunities&view=add"
                        data-wp-interactive="menu"
                        data-wp-on--click="actions.handleAuthGate"><?= $get; ?></a>
                <?php endif; ?>
            </div>
            <p class="text-r <?= esc_attr($classes['text-bottom']); ?>"><?= $text_bottom; ?></p>
        </div>
    </div>
</section>