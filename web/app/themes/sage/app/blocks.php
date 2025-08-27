<?php

/**
 * Auto-registrazione blocchi ACF
 * Carica automaticamente tutti i blocchi dalla cartella app/Blocks
 */

// Auto-load blocchi dalla cartella Blocks
add_action('acf/init', function () {
    if (!function_exists('acf_register_block_type')) {
        return;
    }

    // Path della cartella blocchi
    $blocks_path = get_template_directory().'/app/Blocks';

    // Verifica che la cartella esista
    if (!is_dir($blocks_path)) {
        wp_mkdir_p($blocks_path);

        return;
    }

    // Carica tutti i file .php dalla cartella Blocks
    require_once ABSPATH.'wp-admin/includes/file.php';
    $blocks = list_files($blocks_path);

    if (!empty($blocks)) {
        foreach ($blocks as $single_block) {
            if (str_ends_with($single_block, '.php')) {
                include $single_block;
            }
        }
    }
});

/**
 * Aggiungi categorie blocchi personalizzate
 */
//add_filter('block_categories_all', function ($categories) {
//    $new_categories = [
//        [
//            'slug' => 'heroes',
//            'title' => 'Heroes',
//            'icon' => 'format-image',
//        ],
//        [
//            'slug' => 'contents',
//            'title' => 'Contenuti',
//            'icon' => 'admin-post',
//        ],
//        [
//            'slug' => 'grids',
//            'title' => 'Griglie',
//            'icon' => 'grid-view',
//        ],
//        [
//            'slug' => 'carousels',
//            'title' => 'Carousel',
//            'icon' => 'slides',
//        ],
//        [
//            'slug' => 'commons',
//            'title' => 'Generici',
//            'icon' => 'admin-generic',
//        ],
//        [
//            'slug' => 'dividers',
//            'title' => 'Separatori',
//            'icon' => 'minus',
//        ],
//    ];
//
//    // Inserisce le categorie all'inizio
//    return array_merge($new_categories, $categories);
//});

function allowed_block_types($allowed_blocks, $editor_context)
{
    $registered_block_slugs = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());
    $allowed_blocks = [];

    // Controlla se siamo nell'editor dei widget
    $is_widget_editor = isset($editor_context->name) &&
        (strpos($editor_context->name, 'core/widget-area') !== false ||
            strpos($editor_context->name, 'widget') !== false);

    if ($registered_block_slugs != null) {
        foreach ($registered_block_slugs as $singleCustomBlock) {
            // Sempre abilitare i blocchi ACF
            if (str_starts_with($singleCustomBlock, 'acf')) {
                array_push($allowed_blocks, $singleCustomBlock);
            } // Abilitare i blocchi core SOLO se siamo nell'editor dei widget
            elseif ($is_widget_editor && str_starts_with($singleCustomBlock, 'core/')) {
                array_push($allowed_blocks, $singleCustomBlock);
            }
        }
    }

    return $allowed_blocks;
}

add_filter('allowed_block_types_all', 'allowed_block_types', 25, 2);
?>
