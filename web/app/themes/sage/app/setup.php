<?php

/**
 * Theme setup.
 */

namespace App;

use Illuminate\Support\Facades\Vite;

/**
 * Inject styles into the block editor.
 *
 * @return array
 */
add_filter('block_editor_settings_all', function ($settings) {
    $style = Vite::asset('resources/css/editor.css');

    $settings['styles'][] = [
        'css' => "@import url('{$style}')",
    ];

    return $settings;
});

/**
 * Inject scripts into the block editor.
 *
 * @return void
 */
add_filter('admin_head', function () {
    if (! get_current_screen()?->is_block_editor()) {
        return;
    }

    $dependencies = json_decode(Vite::content('editor.deps.json'));

    foreach ($dependencies as $dependency) {
        if (! wp_script_is($dependency)) {
            wp_enqueue_script($dependency);
        }
    }

    echo Vite::withEntryPoints([
        'resources/js/editor.js',
    ])->toHtml();
});

/**
 * Use the generated theme.json file.
 *
 * @return string
 */
add_filter('theme_file_path', function ($path, $file) {
    return $file === 'theme.json'
        ? public_path('build/assets/theme.json')
        : $path;
}, 10, 2);

/**
 * Register the initial theme setup.
 *
 * @return void
 */
add_action('after_setup_theme', function () {
    /**
     * Make the theme available for translation.
     *
     * Source strings are in Italian (text domain `sage`); translations live
     * in resources/lang as {locale}.mo / {locale}.l10n.php (e.g. en_US.mo).
     * Since WP 6.7 this only registers the path: the files are loaded just in
     * time on the first __() call. Acorn does not do this for the theme.
     *
     * @link https://developer.wordpress.org/reference/functions/load_theme_textdomain/
     */
    load_theme_textdomain('sage', get_template_directory().'/resources/lang');

    /**
     * Disable full-site editing support.
     *
     * @link https://wptavern.com/gutenberg-10-5-embeds-pdfs-adds-verse-block-color-options-and-introduces-new-patterns
     */
    remove_theme_support('block-templates');

    /**
     * Register the navigation menus.
     *
     * @link https://developer.wordpress.org/reference/functions/register_nav_menus/
     */
    register_nav_menus([
        'primary_navigation' => __('Navigazione principale', 'sage'),
    ]);

    /**
     * Disable the default block patterns.
     *
     * @link https://developer.wordpress.org/block-editor/developers/themes/theme-support/#disabling-the-default-block-patterns
     */
    remove_theme_support('core-block-patterns');

    /**
     * Enable plugins to manage the document title.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#title-tag
     */
    add_theme_support('title-tag');

    /**
     * Enable post thumbnail support.
     *
     * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
     */
    add_theme_support('post-thumbnails');

    /**
     * Enable responsive embed support.
     *
     * @link https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-support/#responsive-embedded-content
     */
    add_theme_support('responsive-embeds');

    /**
     * Enable HTML5 markup support.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#html5
     */
    add_theme_support('html5', [
        'caption',
        'comment-form',
        'comment-list',
        'gallery',
        'search-form',
        'script',
        'style',
    ]);

    /**
     * Enable selective refresh for widgets in customizer.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#customize-selective-refresh-widgets
     */
    add_theme_support('customize-selective-refresh-widgets');
}, 20);

/**
 * Register the theme sidebars.
 *
 * @return void
 */
add_action('widgets_init', function () {
    $config = [
        'before_widget' => '<section class="widget %1$s %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h3>',
        'after_title' => '</h3>',
    ];

    register_sidebar([
        'name' => __('Principale', 'sage'),
        'id' => 'sidebar-primary',
    ] + $config);

    register_sidebar([
        'name' => __('Piè di pagina', 'sage'),
        'id' => 'sidebar-footer',
    ] + $config);
});

/**
 * ACF JSON Save/Load
 */
add_filter('acf/settings/save_json', function ($path) {
    return get_stylesheet_directory().'/acf-json';
});

add_filter('acf/settings/load_json', function ($paths) {
    unset($paths[0]);
    $paths[] = get_stylesheet_directory().'/acf-json';

    return $paths;
});

/**
 * Translate the ACF field groups of the theme with the `sage` text domain.
 *
 * Labels, instructions, choices, button labels and group titles in
 * acf-json are written in Italian; with a text domain set ACF passes them
 * through __($string, 'sage') when it loads them, so en_US.po translates
 * them like any other theme string. The .pot gets them from
 * resources/scripts/acf-strings.js (see `npm run translate:pot`).
 *
 * The text domain is turned off while field groups are edited or saved:
 * on the ACF admin screens of `acf-field-group` and in the ACF field group
 * AJAX actions (`acf/field_group/*`, e.g. `acf/field_group/move_field`,
 * which loads the groups and fires `acf/update_field_group`, so local JSON
 * rewrites the files; there `$typenow` is empty). ACF does not skip
 * translation in these requests, so with a non-Italian admin language the
 * translated values would be shown in the editor and written back to
 * acf-json.
 *
 * @link https://www.advancedcustomfields.com/resources/acf-settings/
 */
add_filter('acf/settings/l10n_textdomain', function () {
    global $typenow;

    $editing = (is_admin() && ($typenow ?? null) === 'acf-field-group')
        || (wp_doing_ajax() && str_starts_with((string) ($_REQUEST['action'] ?? ''), 'acf/field_group/'));

    if ($editing) {
        return '';
    }

    return 'sage';
});

/**
 * Set a default permalink structure on theme activation.
 *
 * With "plain" permalinks WordPress skips rewrite rule matching, so
 * unknown URLs fall back to the front page with a 200 status and the
 * theme's custom rewrites never match. Only applies when no structure
 * is set, so an existing configuration is never overwritten.
 *
 * @return void
 */
add_action('after_switch_theme', function () {
    if (get_option('permalink_structure')) {
        return;
    }

    global $wp_rewrite;

    $wp_rewrite->set_permalink_structure('/%postname%/');

    flush_rewrite_rules();
});
