<?php

declare(strict_types=1);

/**
 * Setup del tema.
 */

namespace App;

use Illuminate\Support\Facades\Vite;

/**
 * Inserisce gli stili nel block editor.
 */
add_filter('block_editor_settings_all', function ($settings) {
    $style = Vite::asset('resources/css/editor.css');

    $settings['styles'][] = [
        'css' => "@import url('{$style}')",
    ];

    return $settings;
});

/**
 * Inserisce gli script nel block editor.
 */
add_action('admin_head', function () {
    if (! get_current_screen()?->is_block_editor()) {
        return;
    }

    $dependencies = json_decode(Vite::content('editor.deps.json'));

    foreach ($dependencies as $dependency) {
        if (! wp_script_is($dependency)) {
            wp_enqueue_script($dependency);
        }
    }

    // Warning in console di editor.js (es. selettori ACF non trovati) solo in debug.
    $debug = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) || wp_get_environment_type() === 'development';

    wp_print_inline_script_tag('window.sageEditor = '.wp_json_encode(['debug' => $debug]).';');

    echo Vite::withEntryPoints([
        'resources/js/editor.js',
    ])->toHtml();
});

/**
 * Precarica i webfont del tema.
 *
 * Di default non si precarica nulla: il filtro 'sage/preload_fonts' deve
 * restituire i sorgenti woff2 da precaricare, come percorsi Vite relativi al
 * tema (es. 'resources/fonts/inter-var.woff2'). I file assenti dal tema
 * (o dal manifest di Vite) vengono saltati, sia in build sia con il dev
 * server, così una voce non aggiornata non rompe mai la pagina. L'URL è
 * lo stesso a cui punta il @font-face compilato in fonts.css, quindi il font
 * viene scaricato una volta sola.
 */
add_action('wp_head', function () {
    $fonts = apply_filters('sage/preload_fonts', []);

    if (! is_array($fonts)) {
        return;
    }

    foreach (array_unique(array_filter($fonts, 'is_string')) as $font) {
        if (! is_file(get_theme_file_path($font))) {
            continue;
        }

        try {
            $url = Vite::asset($font);
        } catch (\Throwable) {
            continue;
        }

        printf(
            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>'."\n",
            esc_url($url)
        );
    }
}, 2);

/**
 * Usa il file theme.json generato.
 */
add_filter('theme_file_path', function ($path, $file) {
    return $file === 'theme.json'
        ? public_path('build/assets/theme.json')
        : $path;
}, 10, 2);

/**
 * Registra il setup iniziale del tema.
 */
add_action('after_setup_theme', function () {
    /**
     * Rende il tema traducibile.
     *
     * Le stringhe sorgente sono in italiano (text domain `sage`); le traduzioni
     * stanno in resources/lang come {locale}.mo / {locale}.l10n.php (es. en_US.mo).
     * Da WP 6.7 questo registra solo il percorso: i file vengono caricati al
     * momento, alla prima chiamata a __(). Acorn non lo fa per il tema.
     *
     * @link https://developer.wordpress.org/reference/functions/load_theme_textdomain/
     */
    load_theme_textdomain('sage', get_template_directory().'/resources/lang');

    /**
     * Disattiva il supporto al full-site editing.
     *
     * @link https://wptavern.com/gutenberg-10-5-embeds-pdfs-adds-verse-block-color-options-and-introduces-new-patterns
     */
    remove_theme_support('block-templates');

    /**
     * Registra i menu di navigazione.
     *
     * @link https://developer.wordpress.org/reference/functions/register_nav_menus/
     */
    register_nav_menus([
        'primary_navigation' => __('Navigazione principale', 'sage'),
    ]);

    /**
     * Disattiva i block pattern di default.
     *
     * @link https://developer.wordpress.org/block-editor/developers/themes/theme-support/#disabling-the-default-block-patterns
     */
    remove_theme_support('core-block-patterns');

    /**
     * Permette ai plugin di gestire il titolo del documento.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#title-tag
     */
    add_theme_support('title-tag');

    /**
     * Attiva il supporto alle immagini in evidenza.
     *
     * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
     */
    add_theme_support('post-thumbnails');

    /**
     * Attiva il custom logo (Customizer → Identità del sito).
     *
     * Dimensioni flessibili, l'altezza la limita l'header. Il logo resta
     * collegato alla home anche sulla front page: con `unlink-homepage-logo`
     * WordPress lì lo stamperebbe con un alt vuoto, e l'header perderebbe il
     * nome del sito.
     *
     * @link https://developer.wordpress.org/themes/functionality/custom-logo/
     */
    add_theme_support('custom-logo', [
        'height' => 80,
        'width' => 240,
        'flex-height' => true,
        'flex-width' => true,
    ]);

    /**
     * Attiva il supporto agli embed responsive.
     *
     * @link https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-support/#responsive-embedded-content
     */
    add_theme_support('responsive-embeds');

    /**
     * Attiva il supporto al markup HTML5.
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
     * Attiva il refresh selettivo dei widget nel Customizer.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#customize-selective-refresh-widgets
     */
    add_theme_support('customize-selective-refresh-widgets');
}, 20);

/**
 * Registra le sidebar del tema.
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
 * Salvataggio/caricamento ACF JSON
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
 * Traduce i field group ACF del tema con il text domain `sage`.
 *
 * Label, istruzioni, scelte, etichette dei pulsanti e titoli dei gruppi in
 * acf-json sono scritti in italiano; con un text domain impostato ACF li passa
 * da __($string, 'sage') quando li carica, quindi en_US.po li traduce come
 * qualsiasi altra stringa del tema. Il .pot li prende da
 * resources/scripts/acf-strings.js (vedi `npm run translate:pot`).
 *
 * Il text domain viene disattivato mentre i field group si modificano o si
 * salvano: nelle schermate admin ACF di `acf-field-group` e nelle azioni AJAX
 * dei field group ACF (`acf/field_group/*`, es. `acf/field_group/move_field`,
 * che carica i gruppi e lancia `acf/update_field_group`, così il local JSON
 * riscrive i file; lì `$typenow` è vuoto). In queste richieste ACF non salta
 * la traduzione, quindi con una lingua dell'admin diversa dall'italiano i
 * valori tradotti verrebbero mostrati nell'editor e riscritti in acf-json.
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
 * Imposta una struttura dei permalink di default all'attivazione del tema.
 *
 * Con i permalink "semplici" WordPress salta il confronto con le regole di
 * rewrite, quindi gli URL sconosciuti ricadono sulla front page con stato 200
 * e le rewrite personalizzate del tema non corrispondono mai. Vale solo se non
 * c'è una struttura impostata, così una configurazione esistente non viene mai sovrascritta.
 */
add_action('after_switch_theme', function () {
    if (get_option('permalink_structure')) {
        return;
    }

    global $wp_rewrite;

    $wp_rewrite->set_permalink_structure('/%postname%/');

    flush_rewrite_rules();
});
