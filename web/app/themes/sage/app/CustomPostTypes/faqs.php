<?php

// Register FAQs Custom Post Type

function green_faqs() {
    $supports = array(
        'title',
        'editor',
    );
    $labels = array(
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
    );
    $args = array(
        'label' => __('FAQs', 'sage'),
        'description' => __('', 'sage'),
        'labels' => $labels,
        'supports' => $supports,
        'hierarchical' => false,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => null,
        'menu_icon' => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgZmlsbD0iIzAwMDAwMCIgdmlld0JveD0iMCAwIDI1NiAyNTYiPjxwYXRoIGQ9Ik0xMjgsMjRBMTA0LDEwNCwwLDEsMCwyMzIsMTI4LDEwNC4xMSwxMDQuMTEsMCwwLDAsMTI4LDI0Wm0wLDE2OGExMiwxMiwwLDEsMSwxMi0xMkExMiwxMiwwLDAsMSwxMjgsMTkyWm04LTQ4LjcyVjE0NGE4LDgsMCwwLDEtMTYsMHYtOGE4LDgsMCwwLDEsOC04YzEzLjIzLDAsMjQtOSwyNC0yMHMtMTAuNzctMjAtMjQtMjAtMjQsOS0yNCwyMHY0YTgsOCwwLDAsMS0xNiwwdi00YzAtMTkuODUsMTcuOTQtMzYsNDAtMzZzNDAsMTYuMTUsNDAsMzZDMTY4LDEyNS4zOCwxNTQuMjQsMTM5LjkzLDEzNiwxNDMuMjhaIj48L3BhdGg+PC9zdmc+',
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'can_export' => true,
        'has_archive' => false,
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'capability_type' => 'post',
        'rewrite' => [ 'slug' => 'faq' ]
    );
    register_post_type('faq', $args);
}

add_action('init', 'green_faqs');
