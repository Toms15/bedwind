<?php

declare(strict_types=1);

namespace App\PostTypes;

/**
 * The "faq" custom post type.
 *
 * FAQs are data-only content: they are managed in the admin and rendered
 * through blocks/queries, so they have no public single pages or archive.
 * The post type stays available in the REST API (required by the block
 * editor) and to WP_Query/get_posts().
 *
 * The admin bar "New → FAQ" shortcut is kept for editors' convenience.
 *
 * Registered on "init" by App\Providers\ThemeServiceProvider.
 */
class Faq
{
    /**
     * Post type key.
     */
    public const NAME = 'faq';

    /**
     * Register the post type.
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
