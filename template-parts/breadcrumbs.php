<?php

/**
 * Breadcrumb trail — template part
 *
 * Rendered by sw_breadcrumbs() (inc/theme-helpers.php) from
 * Shared\Breadcrumbs\Trail::items(). Prints the visible trail and its
 * BreadcrumbList JSON-LD from the same items, so structured data always
 * matches what the page shows.
 *
 * - <nav aria-label> landmark with an ordered list; the current page is a
 *   <span aria-current="page">, every other crumb a link.
 * - The home crumb is an icon; its name is visually hidden text (read and
 *   translated like any other text), not an aria-label.
 * - Separators are decorative SVGs (aria-hidden by sw_svg()).
 *
 * @var array $args {
 *     @type list<array{label: string, url: ?string, home?: true}> $items Trail, home first.
 * }
 *
 * File: template-parts/breadcrumbs.php
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$items = $args['items'] ?? [];
if (! $items) {
    return;
}

// JSON-LD: Google accepts the last (current) item without a URL. Labels come
// from get_the_title() and friends, texturized into HTML entities (&#8211;),
// which only HTML decodes - JSON needs the plain characters.
$list = [];
foreach ($items as $position => $item) {
    $element = [
        '@type'    => 'ListItem',
        'position' => $position + 1,
        'name'     => html_entity_decode(wp_strip_all_tags((string) $item['label']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    ];
    if (! empty($item['url'])) {
        $element['item'] = $item['url'];
    }
    $list[] = $element;
}
?>
<nav class="breadcrumbs container" aria-label="<?php esc_attr_e('Breadcrumbs', 'starwishx'); ?>">
    <ol class="breadcrumbs__list text-r">
        <?php foreach ($items as $item) : ?>
            <li class="breadcrumbs__item">
                <?php if (! empty($item['home'])) : ?>
                    <a class="breadcrumbs__link" href="<?php echo esc_url($item['url']); ?>">
                        <?php sw_svg_e('icon-house', 18, null, 'breadcrumbs__home-icon'); ?>
                        <span class="screen-reader-text"><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php else : ?>
                    <?php sw_svg_e('icon-arrow', 10, null, 'breadcrumbs__sep'); ?>
                    <?php if (! empty($item['url'])) : ?>
                        <a class="breadcrumbs__link" href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                    <?php else : ?>
                        <span class="breadcrumbs__current" aria-current="page"><?php echo esc_html($item['label']); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php if (count($list) > 1) : ?>
    <script type="application/ld+json"><?php
        echo wp_json_encode([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    ?></script>
<?php endif; ?>
