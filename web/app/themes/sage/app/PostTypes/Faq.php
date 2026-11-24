<?php

declare(strict_types=1);

namespace App\PostTypes;

/**
 * Il custom post type "faq".
 *
 * Le FAQ sono contenuti solo dati: si gestiscono nell'admin e si renderizzano
 * tramite blocchi/query, quindi non hanno pagine singole né archivio pubblici.
 * Il post type resta disponibile nella REST API (richiesta dal block editor)
 * e per WP_Query/get_posts().
 *
 * La scorciatoia "Nuovo → FAQ" della admin bar resta, per comodità degli editor.
 *
 * Registrato su "init" da App\Providers\ThemeServiceProvider.
 */
class Faq
{
    /**
     * Chiave del post type.
     */
    public const NAME = 'faq';

    /**
     * Registra il post type.
     */
    public static function register(): void
    {
        register_post_type(self::NAME, [
            'label' => _x('FAQ', 'post type general name', 'sage'),
            'description' => '',
            'labels' => [
                'name' => _x('FAQ', 'post type general name', 'sage'),
                'singular_name' => _x('FAQ', 'post type singular name', 'sage'),
                'menu_name' => _x('FAQ', 'admin menu', 'sage'),
                'name_admin_bar' => _x('FAQ', 'add new on admin bar', 'sage'),
                'add_new' => _x('Aggiungi FAQ', 'faq', 'sage'),
                'add_new_item' => __('Aggiungi nuova FAQ', 'sage'),
                'new_item' => __('Nuova FAQ', 'sage'),
                'edit_item' => __('Modifica FAQ', 'sage'),
                'view_item' => __('Visualizza FAQ', 'sage'),
                'all_items' => __('Tutte le FAQ', 'sage'),
                'search_items' => __('Cerca FAQ', 'sage'),
                'not_found' => __('Nessuna FAQ trovata.', 'sage'),
                'not_found_in_trash' => __('Nessuna FAQ trovata nel cestino.', 'sage'),
            ],
            'supports' => ['title', 'editor'],
            'hierarchical' => false,
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => true,
            'menu_icon' => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgZmlsbD0iIzAwMDAwMCIgdmlld0JveD0iMCAwIDI1NiAyNTYiPjxwYXRoIGQ9Ik0xMjgsMjRBMTA0LDEwNCwwLDEsMCwyMzIsMTI4LDEwNC4xMSwxMDQuMTEsMCwwLDAsMTI4LDI0Wm0wLDE2OGExMiwxMiwwLDEsMSwxMi0xMkExMiwxMiwwLDAsMSwxMjgsMTkyWm04LTQ4LjcyVjE0NGE4LDgsMCwwLDEtMTYsMHYtOGE4LDgsMCwwLDEsOC04YzEzLjIzLDAsMjQtOSwyNC0yMHMtMTAuNzctMjAtMjQtMjAtMjQsOS0yNCwyMHY0YTgsOCwwLDAsMS0xNiwwdi00YzAtMTkuODUsMTcuOTQtMzYsNDAtMzZzNDAsMTYuMTUsNDAsMzZDMTY4LDEyNS4zOCwxNTQuMjQsMTM5LjkzLDEzNiwxNDMuMjhaIj48L3BhdGg+PC9zdmc+',
            'capability_type' => 'post',
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'can_export' => true,
        ]);
    }
}
