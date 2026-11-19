<?php

/**
 * Register the "faq" custom post type.
 *
 * FAQs are data-only content: they are managed in the admin and rendered
 * through blocks/queries, so they have no public single pages or archive.
 * The post type stays available in the REST API (required by the block
 * editor) and to WP_Query/get_posts().
 *
 * The admin bar "New → FAQ" shortcut is kept for editors' convenience.
 */
function sage_register_faq_post_type(): void
{
    register_post_type('faq', [
        'label' => __('FAQs', 'sage'),
        'description' => '',
        'labels' => [
            'name' => __('FAQs', 'sage'),
            'singular_name' => __('FAQ', 'sage'),
            'menu_name' => __('FAQs', 'sage'),
            'edit_item' => __('Modifica faq', 'sage'),
            'name_admin_bar' => __('FAQs', 'sage'),
            'add_new' => __('Aggiungi faq', 'sage'),
            'add_new_item' => __('Aggiungi nuova faq', 'sage'),
            'new_item' => __('Nuova faq', 'sage'),
            'view_item' => __('Visualizza faq', 'sage'),
            'all_items' => __('Tutte le faqs', 'sage'),
            'search_items' => __('Cerca faq', 'sage'),
            'not_found' => __('Nessuna faq trovata', 'sage'),
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
        'menu_position' => null,
        'menu_icon' => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgZmlsbD0iIzAwMDAwMCIgdmlld0JveD0iMCAwIDI1NiAyNTYiPjxwYXRoIGQ9Ik0xMjgsMjRBMTA0LDEwNCwwLDEsMCwyMzIsMTI4LDEwNC4xMSwxMDQuMTEsMCwwLDAsMTI4LDI0Wm0wLDE2OGExMiwxMiwwLDEsMSwxMi0xMkExMiwxMiwwLDAsMSwxMjgsMTkyWm04LTQ4LjcyVjE0NGE4LDgsMCwwLDEtMTYsMHYtOGE4LDgsMCwwLDEsOC04YzEzLjIzLDAsMjQtOSwyNC0yMHMtMTAuNzctMjAtMjQtMjAtMjQsOS0yNCwyMHY0YTgsOCwwLDAsMS0xNiwwdi00YzAtMTkuODUsMTcuOTQtMzYsNDAtMzZzNDAsMTYuMTUsNDAsMzZDMTY4LDEyNS4zOCwxNTQuMjQsMTM5LjkzLDEzNiwxNDMuMjhaIj48L3BhdGg+PC9zdmc+',
        'capability_type' => 'post',
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => false,
        'can_export' => true,
    ]);
}

add_action('init', 'sage_register_faq_post_type');
