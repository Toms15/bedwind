<?php

declare(strict_types=1);

namespace App\Blocks;

use Illuminate\Support\Facades\Vite;

/**
 * Optional per-block frontend assets.
 *
 * A block folder may contain, next to block.json and <slug>.blade.php:
 * - view.js: enqueued as a script module (type="module", in the footer);
 * - style.css: enqueued as a stylesheet (WordPress 6.9+ hoists the styles
 *   enqueued while rendering into the <head> in classic themes too).
 *
 * Both are Vite entries (vite.config.js globs them) and are enqueued by
 * BlockRenderer::render() only when the block is rendered on the frontend, so
 * pages without the block do not load them. In the editor the blocks render
 * a placeholder, so nothing is enqueued there.
 *
 * With the dev server, Vite::asset() returns the dev server URL (HMR via the
 * Vite client loaded with app.js); in build, the hashed file of the manifest.
 * An asset missing from the Vite manifest (added without rebuilding) is
 * skipped, so it never breaks the page.
 */
class BlockAssets
{
    /**
     * Asset type => file name in the block folder.
     */
    public const FILES = [
        'style' => 'style.css',
        'script' => 'view.js',
    ];

    /**
     * The assets of a block that exist, as Vite paths relative to the theme.
     *
     * @return array{style?: string, script?: string}
     */
    public static function for(string $slug): array
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
            return [];
        }

        $assets = [];

        foreach (self::FILES as $type => $file) {
            $path = "resources/views/blocks/{$slug}/{$file}";

            if (is_file(get_theme_file_path($path))) {
                $assets[$type] = $path;
            }
        }

        return $assets;
    }

    /**
     * Handle of the block assets (stylesheet handle and script module id).
     */
    public static function handle(string $slug): string
    {
        return "sage-block-{$slug}";
    }

    /**
     * Enqueue the assets of a block. Safe to call once per rendered block:
     * WordPress enqueues each handle once.
     */
    public static function enqueue(string $slug): void
    {
        $handle = self::handle($slug);

        foreach (self::for($slug) as $type => $path) {
            try {
                $url = Vite::asset($path);
            } catch (\Throwable) {
                continue;
            }

            // null version: the build files are hashed, the dev server needs none.
            if ($type === 'style') {
                wp_enqueue_style($handle, $url, [], null);
            } else {
                wp_enqueue_script_module($handle, $url, [], null);
            }
        }
    }
}
