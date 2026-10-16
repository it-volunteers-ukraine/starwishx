<?php

/**
 * Built asset file: URL, dependencies and version of a file tools/build.mjs
 * wrote into the theme, read from the {name}.asset.php next to it - the
 * manifest @wordpress/scripts generates, whose version is a content hash.
 * Core reads these manifests for block.json assets on its own; this does the
 * same for the theme's own wp_register_script_module() / wp_register_script()
 * / wp_register_style() calls, so a deploy busts exactly the files that
 * changed.
 *
 *   $asset = AssetFile::read('assets/js/chat-store.module.js', ['@wordpress/interactivity']);
 *   wp_register_script_module('@starwishx/chat', $asset['url'], $asset['dependencies'], $asset['version']);
 *
 * File: inc/shared/Build/AssetFile.php
 */

declare(strict_types=1);

namespace Shared\Build;

final class AssetFile
{
    /** @var array<string, array{url: string, dependencies: array<int, mixed>, version: string}> */
    private static array $cache = [];

    /**
     * @param string            $file     Theme-relative path of the built file,
     *                                    e.g. 'assets/css/app.css'.
     * @param array<int, mixed> $fallback Dependencies to use when the manifest
     *                                    is missing (a build that predates it).
     * @return array{url: string, dependencies: array<int, mixed>, version: string}
     */
    public static function read(string $file, array $fallback = []): array
    {
        if (isset(self::$cache[$file])) {
            return self::$cache[$file];
        }

        $path     = get_theme_file_path($file);
        $manifest = preg_replace('/\.(js|css)$/', '.asset.php', $path);
        $asset    = is_string($manifest) && is_file($manifest) ? include $manifest : null;

        if (is_array($asset) && isset($asset['version'])) {
            $dependencies = is_array($asset['dependencies'] ?? null) ? $asset['dependencies'] : [];
            $version      = (string) $asset['version'];
        } else {
            // Never fatal: version by mtime like the block assets, and the
            // dependencies the caller knows the file needs.
            $dependencies = $fallback;
            $version      = is_file($path)
                ? (string) filemtime($path)
                : (string) wp_get_theme(get_template())->get('Version');
        }

        return self::$cache[$file] = [
            'url'          => get_theme_file_uri($file),
            'dependencies' => $dependencies,
            'version'      => $version,
        ];
    }
}
