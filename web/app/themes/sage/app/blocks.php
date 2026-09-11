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
 * In editor: nessuna preview, placeholder "Edit Block" per ogni blocco.
 */
function render_acf_block($block, $content = '', $is_preview = false, $post_id = 0, $wp_block = null, $context = [])
{
    if ($is_preview) {
        echo 'acf-block-preview-no-html';
        return;
    }

    $slug = str_replace('acf/', '', $block['name']);
    $block['slug'] = $slug;

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
            'title' => 'Carousel'
        ],
        [
            'slug' => 'dynamic-content',
            'title' => 'Contenuti dinamici'
        ],
        [
            'slug' => 'static-content',
            'title' => 'Contenuti statici'
        ],
        [
            'slug' => 'commons',
            'title' => 'Generici'
        ],
        [
            'slug' => 'heroes',
            'title' => 'Heroes'
        ],
        [
            'slug' => 'dividers',
            'title' => 'Separatori'
        ],
    ];

    // Inserisce le categorie all'inizio
    return array_merge($new_categories, $categories);
});

function allowed_block_types($allowed_blocks, $editor_context)
{
    $registered_block_slugs = array_keys(\WP_Block_Type_Registry::get_instance()->get_all_registered());
    $allowed_blocks = [];

    // Controlla se siamo nell'editor dei widget
    $is_widget_editor = isset($editor_context->name) &&
        (strpos($editor_context->name, 'core/widget-area') !== false ||
            strpos($editor_context->name, 'widget') !== false);

    $is_post_editor = isset($editor_context->post) &&
        in_array($editor_context->post->post_type, ['post']);

    $allow_core = $is_widget_editor || $is_post_editor;

    if ($registered_block_slugs != null) {
        foreach ($registered_block_slugs as $singleCustomBlock) {
            // Sempre abilitare i blocchi ACF
            if (str_starts_with($singleCustomBlock, 'acf')) {
                array_push($allowed_blocks, $singleCustomBlock);
            } // Abilitare i blocchi core SOLO se siamo nell'editor dei widget
            elseif ($allow_core && str_starts_with($singleCustomBlock, 'core/')) {
                array_push($allowed_blocks, $singleCustomBlock);
            }
        }
    }

    return $allowed_blocks;
}

add_filter('allowed_block_types_all', __NAMESPACE__ . '\\allowed_block_types', 25, 2);
