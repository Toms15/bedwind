<?php

declare(strict_types=1);

namespace App;

/**
 * Registra i blocchi del tema (resources/views/blocks/<slug>/block.json).
 *
 * Da WordPress 6.8 la registrazione legge il manifest generato
 * resources/views/blocks/blocks-manifest.php (npm run blocks:manifest, lo
 * rigenera anche Vite): niente glob() né decodifica dei block.json a ogni
 * richiesta. I metadati passano comunque da register_block_type_from_metadata(),
 * quindi dagli stessi filtri di prima (block_type_metadata e
 * block_type_metadata_settings, usati da ACF). Un blocco assente dal manifest
 * non viene registrato: il manifest va rigenerato quando cambia un block.json.
 *
 * Senza la funzione (WordPress < 6.8) o senza manifest: un block.json per
 * cartella, come prima.
 *
 * Il render dei blocchi ACF è App\Blocks\BlockRenderer::render (block.json).
 */
add_action('init', function () {
    $blocks_dir = get_theme_file_path('/resources/views/blocks');
    $manifest = "{$blocks_dir}/blocks-manifest.php";

    if (function_exists('wp_register_block_types_from_metadata_collection') && is_file($manifest)) {
        wp_register_block_types_from_metadata_collection($blocks_dir, $manifest);

        return;
    }

    foreach (glob("{$blocks_dir}/*", GLOB_ONLYDIR) ?: [] as $dir) {
        if (file_exists("{$dir}/block.json")) {
            register_block_type($dir);
        }
    }
});

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
 * Limita i blocchi disponibili nell'editor.
 *
 * I blocchi ACF sono sempre ammessi. I blocchi core sono ammessi solo nei post
 * type restituiti dal filtro `sage/core_blocks_post_types` e negli editor che
 * non sono di contenuto (widget, widget del Customizer, site editor). Ogni altro
 * post type (es. le pagine) ha solo i blocchi ACF.
 *
 * I blocchi di altri namespace (es. plugin di terze parti) sono esclusi, a meno
 * che il loro nome completo (es. 'my-plugin/form') non sia restituito dal
 * filtro `sage/extra_blocks` (default: nessuno): quelli sono ammessi in ogni
 * editor, come i blocchi ACF.
 *
 * I pattern sincronizzati (`core/block`) sono un blocco core e seguono la stessa
 * regola: non sono ammessi dove ci sono solo i blocchi ACF, perché un pattern
 * sincronizzato può contenere blocchi core e aggirerebbe la restrizione.
 *
 * Anche il site editor ha i blocchi core: il tema rimuove il supporto
 * `block-templates`, quindi di norma non è raggiungibile, ma se mai venisse
 * aperto (es. per i template part) senza blocchi core non funzionerebbe.
 *
 * Il valore in ingresso è rispettato: `false` resta `false`, e un array che
 * arriva da filtri precedenti viene solo ristretto, mai allargato.
 *
 * @param  bool|array<mixed>  $allowed_blocks  true (tutti), false (nessuno) o i nomi dei blocchi ammessi.
 * @return bool|list<string> false, o i nomi dei blocchi ammessi.
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
    $extra_blocks = (array) apply_filters('sage/extra_blocks', []);

    $allow_core = in_array($editor_context->name, $core_contexts, true)
        || ($editor_context->post instanceof \WP_Post
            && in_array($editor_context->post->post_type, $core_post_types, true));

    return array_values(array_filter(
        $candidates,
        fn ($name) => is_string($name)
            && (str_starts_with($name, 'acf/')
                || ($allow_core && str_starts_with($name, 'core/'))
                || in_array($name, $extra_blocks, true))
    ));
}

add_filter('allowed_block_types_all', __NAMESPACE__.'\\allowed_block_types', 25, 2);
