<?php

/**
 * Blocks module — Core Singleton
 *
 * Registers every inc/blocks/{slug}/block.json as a native block, supplies the
 * "Custom Blocks" inserter category (shared with the legacy ACF blocks), merges
 * translatable attribute labels (labels.php) into the block definitions, and
 * ships the editor helper that gives blocks a Media Library picker and a term
 * select for attributes marked with a custom control.
 *
 * Block folders are source = runtime: block.json, render.php and labels.php
 * run in place; gulp compiles style.scss / view.js into {slug}/build/, which
 * block.json references as "file:./build/…".
 *
 * Author: DevFrappe
 * Email: dev.frappe@proton.me
 * License: GPL v2 or later
 *
 * File: inc/blocks/Core/BlocksCore.php
 */

declare(strict_types=1);

namespace Blocks\Core;

final class BlocksCore
{
    /** Inserter category slug — the ACF blocks in inc/acf/blocks use it too. */
    public const CATEGORY_SLUG = 'custom-blocks';

    /**
     * block.json attribute marker: { "type": "integer", "control": "media" }.
     * Consumed by Assets/editor-media-attributes.js (Media Library picker) and
     * hidden from core's auto-generated inspector controls.
     */
    public const CONTROL_MEDIA = 'media';

    /**
     * block.json attribute marker: { "type": "integer", "control": "term",
     * "taxonomy": "category-oportunities", "parentOnly": true }. The editor helper
     * renders a select fed by termOptions() (no REST dependency).
     */
    public const CONTROL_TERM = 'term';

    private const EDITOR_SCRIPT = 'starwishx-block-editor-media';

    /** block.json fields whose "file:" paths point at gulp output in build/. */
    private const ASSET_FIELDS = [
        'style',
        'editorStyle',
        'viewStyle',
        'script',
        'editorScript',
        'viewScript',
        'viewScriptModule',
    ];

    private static ?self $instance = null;

    public static function instance(): self
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        // init, not after_setup_theme: the registry API contract, just-in-time
        // textdomain loading for __() in labels.php, and WP_Block_Supports::init
        // (init 22) must all see the blocks. ACF registers on acf/init (= init 5).
        add_action('init', [$this, 'registerBlocks']);
        add_filter('block_categories_all', [$this, 'registerCategory']);
        add_filter('block_type_metadata', [$this, 'prepareMetadata']);
        add_filter('block_type_metadata_settings', [$this, 'applyAttributeLabels'], 10, 2);
        // Core marks autoGenerateControl at priority 5; strip it from custom-control
        // attributes at 6 so the auto inspector doesn't also show an integer field.
        add_filter('register_block_type_args', [$this, 'filterCustomControlAttributes'], 6, 2);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);

        // Cross-block services: parent/child render context (ids, order), the
        // FAQPage structured data for starwishx/faq, the LCP preload for starwishx/hero.
        (new \Blocks\Support\InnerBlockContext())->register();
        (new \Blocks\Support\FaqSchema())->register();
        (new \Blocks\Support\HeroPreload())->register();
    }

    /** Absolute, forward-slash path of inc/blocks (no trailing slash). */
    public static function dir(): string
    {
        return wp_normalize_path(dirname(__DIR__));
    }

    public function registerBlocks(): void
    {
        foreach (glob(self::dir() . '/*/block.json') ?: [] as $file) {
            register_block_type_from_metadata(dirname($file));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    public function registerCategory(array $categories): array
    {
        foreach ($categories as $category) {
            if (($category['slug'] ?? '') === self::CATEGORY_SLUG) {
                return $categories;
            }
        }

        array_unshift($categories, [
            'slug'  => self::CATEGORY_SLUG,
            'title' => __('Custom Blocks', 'starwishx'),
            'icon'  => null,
        ]);

        return $categories;
    }

    /**
     * Guard and version the gulp-built assets of our own blocks.
     *
     * A "file:" asset whose build output is missing would still be registered
     * by core (a style with no src, a script module with an empty URL that
     * fetches the page itself), so the field is dropped instead. The newest
     * filemtime becomes the metadata version, which core uses to cache-bust
     * the editor <link> and the view module URL on every rebuild.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function prepareMetadata(array $metadata): array
    {
        if (! $this->ownsMetadata($metadata)) {
            return $metadata;
        }

        $block_dir = dirname((string) $metadata['file']);
        $newest    = 0;

        foreach (self::ASSET_FIELDS as $field) {
            if (empty($metadata[$field]) || ! is_string($metadata[$field])) {
                continue;
            }
            if (! str_starts_with($metadata[$field], 'file:')) {
                continue; // a registered handle, not a file
            }

            $path = $block_dir . '/' . remove_block_asset_path_prefix($metadata[$field]);
            if (! is_file($path)) {
                unset($metadata[$field]);
                continue;
            }

            $newest = max($newest, (int) filemtime($path));
        }

        if ($newest > 0) {
            $metadata['version'] = (string) $newest;
        }

        return $metadata;
    }

    /**
     * Merge inc/blocks/{slug}/labels.php ([attribute => __('Label')]) into the
     * attribute definitions as `label`, which core's auto-generated inspector
     * and the media picker display. Labels live in PHP so `npm run i18n`
     * extracts them — block.json attribute labels are not in the i18n schema.
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function applyAttributeLabels(array $settings, array $metadata): array
    {
        if (! $this->ownsMetadata($metadata) || empty($settings['attributes']) || ! is_array($settings['attributes'])) {
            return $settings;
        }

        $labels_file = dirname((string) $metadata['file']) . '/labels.php';
        if (! is_readable($labels_file)) {
            return $settings;
        }

        $labels = require $labels_file;
        if (! is_array($labels)) {
            return $settings;
        }

        foreach ($labels as $attribute => $label) {
            if (is_string($label) && isset($settings['attributes'][$attribute])) {
                $settings['attributes'][$attribute]['label'] = $label;
            }
        }

        return $settings;
    }

    /**
     * Attributes with a custom editor control ("control": "media" | "term")
     * are handled by Assets/editor-media-attributes.js; strip core's marker so
     * the auto-generated inspector doesn't also show a bare integer field.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function filterCustomControlAttributes(array $args, string $block_name): array
    {
        if (empty($args['supports']['autoRegister']) || empty($args['attributes']) || ! is_array($args['attributes'])) {
            return $args;
        }

        foreach ($args['attributes'] as $key => $schema) {
            if (is_array($schema) && is_string($schema['control'] ?? null)) {
                unset($args['attributes'][$key]['autoGenerateControl']);
            }
        }

        return $args;
    }

    public function enqueueEditorAssets(): void
    {
        $relative = '/inc/blocks/Assets/editor-media-attributes.js';
        $path     = get_template_directory() . $relative;

        if (! is_readable($path)) {
            return;
        }

        wp_enqueue_script(
            self::EDITOR_SCRIPT,
            get_template_directory_uri() . $relative,
            ['wp-hooks', 'wp-compose', 'wp-element', 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-core-data'],
            (string) filemtime($path),
            ['in_footer' => true]
        );

        // PHP is the i18n authority: UI strings reach the (classic) script as
        // inline data rather than through wp.i18n JSON bundles.
        wp_add_inline_script(
            self::EDITOR_SCRIPT,
            'window.starwishxBlockEditor = ' . wp_json_encode([
                'mediaControl' => self::CONTROL_MEDIA,
                'termControl'  => self::CONTROL_TERM,
                // Lets editor scripts draw the same sprite icons render.php uses (sw_svg()).
                'spriteUrl'    => apply_filters('sw_svg_sprite_url', get_template_directory_uri() . '/assets/img/sprites.svg'),
                'terms'        => $this->termOptions(),
                'i18n'         => [
                    'panelTitle'      => __('Media', 'starwishx'),
                    'select'          => __('Select image', 'starwishx'),
                    'replace'         => __('Replace image', 'starwishx'),
                    'remove'          => __('Remove image', 'starwishx'),
                    'noPermission'    => __('You do not have permission to upload media.', 'starwishx'),
                    'termsPanelTitle' => __('Category', 'starwishx'),
                    'selectTerm'      => __('— Select —', 'starwishx'),
                    'noTerms'         => __('No terms available.', 'starwishx'),
                ],
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';',
            'before'
        );
    }

    /**
     * Term lists for every `"control": "term"` attribute of a registered block,
     * keyed "{taxonomy}|parents" or "{taxonomy}|all" (see termOptionsKey()).
     *
     * Inlined rather than fetched from the REST API so the picker works for
     * taxonomies without show_in_rest (category-oportunities is one), needs no
     * async state, and keeps PHP the authority over what editors can pick.
     *
     * @return array<string, array<int, array{id: int, name: string, slug: string}>>
     */
    private function termOptions(): array
    {
        $options = [];

        foreach (\WP_Block_Type_Registry::get_instance()->get_all_registered() as $block_type) {
            foreach ((array) $block_type->attributes as $schema) {
                if (! is_array($schema) || ($schema['control'] ?? null) !== self::CONTROL_TERM) {
                    continue;
                }

                $taxonomy = (string) ($schema['taxonomy'] ?? '');
                if ($taxonomy === '' || ! taxonomy_exists($taxonomy)) {
                    continue;
                }

                $parent_only = ! empty($schema['parentOnly']);
                $key         = self::termOptionsKey($taxonomy, $parent_only);
                if (isset($options[$key])) {
                    continue;
                }

                $args = ['taxonomy' => $taxonomy, 'hide_empty' => false];
                if ($parent_only) {
                    $args['parent'] = 0;
                }

                $terms = get_terms($args);
                $options[$key] = [];

                if (! is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $options[$key][] = [
                            'id'   => (int) $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                        ];
                    }
                }
            }
        }

        return $options;
    }

    /** Key of a term list in the inline config; mirrored in the editor helper. */
    public static function termOptionsKey(string $taxonomy, bool $parent_only): string
    {
        return $taxonomy . '|' . ($parent_only ? 'parents' : 'all');
    }

    /**
     * True when the block.json being processed lives under inc/blocks/.
     *
     * @param array<string, mixed> $metadata
     */
    private function ownsMetadata(array $metadata): bool
    {
        if (empty($metadata['file']) || ! is_string($metadata['file'])) {
            return false;
        }

        return str_starts_with(wp_normalize_path($metadata['file']), self::dir() . '/');
    }
}
