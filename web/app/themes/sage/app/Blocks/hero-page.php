<?php

namespace App;

add_action('init', __NAMESPACE__.'\\hero_page');

function hero_page()
{
    if (! function_exists('acf_register_block_type')) {
        return;
    }

    acf_register_block_type([
        'name' => 'hero-page',
        'title' => __('Hero (Pagina)'),
        'description' => __('Sezione hero per pagine principali'),
        'mode' => 'edit',
        'render_callback' => function ($block) {
            $slug = str_replace('acf/', '', $block['name']);
            $block['slug'] = $slug;
            if (file_exists(get_theme_file_path('/resources/views/blocks/hero-page.blade.php'))) {
                echo view(get_template_directory().'/resources/views/blocks/hero-page.blade.php', ['block' => $block]);
            }
        },
        'category' => 'heroes',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 256 256"><path d="M224,48H32A16,16,0,0,0,16,64V192a16,16,0,0,0,16,16H224a16,16,0,0,0,16-16V64A16,16,0,0,0,224,48Zm0,144H32V64H224V192Z"/></svg>',
        'keywords' => ['hero', 'page', 'banner'],
        'supports' => [
            'mode' => false,
            'jsx' => true,
            'anchor' => true,
        ],
//        'example' => [
//            'attributes' => [
//                'mode' => 'preview',
//                'data' => [
//                    'title' => 'Benvenuto nel nostro sito',
//                    'subtitle' => 'Scopri tutto quello che abbiamo da offrirti',
//                    'cta_text' => 'Scopri di più',
//                ],
//            ],
//        ],
        'enqueue_style' => false, // Gli stili sono in Tailwind
        'enqueue_script' => false,
    ]);
}
