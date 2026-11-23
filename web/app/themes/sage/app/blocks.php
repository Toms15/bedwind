<?php

declare(strict_types=1);

namespace App;

/**
 * Registra i blocchi del tema (resources/views/blocks/<slug>/block.json).
 *
 * Da WordPress 6.8 la registrazione legge il manifest generato
 * resources/views/blocks/blocks-manifest.php (npm run blocks:manifest, lo
 * rigenera anche Vite): niente glob() né decodifica dei block.json a ogni
 * richiesta. I metadati passano comunque da register_block_type_from_metadata(),
 * quindi dagli stessi filtri di prima (block_type_metadata e
 * block_type_metadata_settings, usati da ACF). Un blocco assente dal manifest
 * non viene registrato: il manifest va rigenerato quando cambia un block.json.
 *
 * Senza la funzione (WordPress < 6.8) o senza manifest: un block.json per
 * cartella, come prima.
 *
 * Il render dei blocchi ACF è App\Blocks\BlockRenderer::render (block.json).
 */
add_action('init', function () {
    $blocks_dir = get_theme_file_path('/resources/views/blocks');
    $manifest = "{$blocks_dir}/blocks-manifest.php";

    if (function_exists('wp_register_block_types_from_metadata_collection') && is_file($manifest)) {
        wp_register_block_types_from_metadata_collection($blocks_dir, $manifest);

        return;
    }

    foreach (glob("{$blocks_dir}/*", GLOB_ONLYDIR) ?: [] as $dir) {
        if (file_exists("{$dir}/block.json")) {
            register_block_type($dir);
        }
    }
});

/**
 * Aggiungi categorie blocchi personalizzate
 */
add_filter('block_categories_all', function ($categories) {
    $new_categories = [
        [
            'slug' => 'carousels',
            'title' => __('Caroselli', 'sage'),
        ],
        [
            'slug' => 'dynamic-content',
            'title' => __('Contenuti dinamici', 'sage'),
        ],
        [
            'slug' => 'static-content',
            'title' => __('Contenuti statici', 'sage'),
        ],
        [
            'slug' => 'commons',
            'title' => __('Generici', 'sage'),
        ],
        [
            'slug' => 'heroes',
            'title' => __('Hero', 'sage'),
        ],
        [
            'slug' => 'dividers',
            'title' => __('Separatori', 'sage'),
        ],
    ];

    // Inserisce le categorie all'inizio
    return array_merge($new_categories, $categories);
});

/**
 * Restrict the blocks available in the editor.
 *
 * ACF blocks are always allowed. Core blocks are allowed only in the post
 * types returned by the `sage/core_blocks_post_types` filter and in the
 * non-content editors (widgets, Customizer widgets, site editor). Every other
 * post type (e.g. pages) gets ACF blocks only.
 *
 * Blocks of other namespaces (e.g. third-party plugins) are excluded, unless
 * their full names (e.g. 'my-plugin/form') are returned by the
 * `sage/extra_blocks` filter (default: none): those are allowed in every
 * editor, like the ACF blocks.
 *
 * Synced patterns (`core/block`) are a core block and follow the same rule:
 * they are not allowed where only ACF blocks are, because a synced pattern can
 * contain core blocks and would bypass the restriction.
 *
 * The site editor gets core blocks too: the theme removes `block-templates`
 * support, so it is normally unreachable, but if it is ever opened (e.g. for
 * template parts) it cannot work without core blocks.
 *
 * The incoming value is respected: `false` stays `false`, and an array coming
 * from earlier filters is only narrowed, never widened.
 *
 * @param  bool|array<mixed>  $allowed_blocks  true (all), false (none) or the allowed block names.
 * @return bool|list<string> false, or the allowed block names.
 */
function allowed_block_types(bool|array $allowed_blocks, \WP_Block_Editor_Context $editor_context): bool|array
{
    if ($allowed_blocks === false) {
        return false;
    }

    $candidates = is_array($allowed_blocks)
        ? $allowed_blocks
        : array_keys(\WP_Block_Type_Registry::get_instance()->get_all_registered());

    $core_contexts = ['core/edit-widgets', 'core/customize-widgets', 'core/edit-site'];
    $core_post_types = (array) apply_filters('sage/core_blocks_post_types', ['post', 'faq']);
    $extra_blocks = (array) apply_filters('sage/extra_blocks', []);

    $allow_core = in_array($editor_context->name, $core_contexts, true)
        || ($editor_context->post instanceof \WP_Post
            && in_array($editor_context->post->post_type, $core_post_types, true));

    return array_values(array_filter(
        $candidates,
        fn ($name) => is_string($name)
            && (str_starts_with($name, 'acf/')
                || ($allow_core && str_starts_with($name, 'core/'))
                || in_array($name, $extra_blocks, true))
    ));
}

add_filter('allowed_block_types_all', __NAMESPACE__.'\\allowed_block_types', 25, 2);
