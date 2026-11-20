<?php

namespace App;

/**
 * Registra tutti i blocchi che hanno un block.json.
 */
add_action('init', function () {
    foreach (glob(get_theme_file_path('/resources/views/blocks/*'), GLOB_ONLYDIR) as $dir) {
        if (file_exists("{$dir}/block.json")) {
            register_block_type($dir);
        }
    }
});

/**
 * Render callback condiviso da tutti i blocchi ACF.
 * In editor: nessuna preview, placeholder "Modifica blocco" per ogni blocco.
 */
function render_acf_block($block, $content = '', $is_preview = false, $post_id = 0, $wp_block = null, $context = [])
{
    $slug = str_replace('acf/', '', $block['name']);
    $block['slug'] = $slug;

    if ($is_preview) {
        echo view('components.acf.placeholder', [
            'block' => $block,
            'title' => $slug === 'empty' ? null : get_field('title'),
            'variant' => $slug === 'empty' ? 'empty' : 'default',
        ]);

        return;
    }

    $view = get_theme_file_path("/resources/views/blocks/{$slug}/{$slug}.blade.php");

    if (file_exists($view)) {
        echo view($view, ['block' => $block]);
    }
}

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

    $allow_core = in_array($editor_context->name, $core_contexts, true)
        || ($editor_context->post instanceof \WP_Post
            && in_array($editor_context->post->post_type, $core_post_types, true));

    return array_values(array_filter(
        $candidates,
        fn ($name) => is_string($name)
            && (str_starts_with($name, 'acf/') || ($allow_core && str_starts_with($name, 'core/')))
    ));
}

add_filter('allowed_block_types_all', __NAMESPACE__.'\\allowed_block_types', 25, 2);
