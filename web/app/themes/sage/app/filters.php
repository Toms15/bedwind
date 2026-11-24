<?php

declare(strict_types=1);

/**
 * Filtri del tema.
 */

namespace App;

/**
 * Aggiunge "… Continua a leggere" agli excerpt automatici (the_excerpt()).
 *
 * Il titolo del post è aggiunto solo per gli screen reader, così ogni link ha
 * un nome univoco e descrittivo. La lunghezza dell'excerpt la imposta excerpt_length().
 *
 * @return string
 */
add_filter('excerpt_more', function () {
    return sprintf(
        ' &hellip; <a href="%s" class="more-link">%s<span class="sr-only">: %s</span></a>',
        esc_url(get_permalink()),
        esc_html__('Continua a leggere', 'sage'),
        esc_html(wp_strip_all_tags(get_the_title()))
    );
});

/**
 * Imposta a 20 parole la lunghezza degli excerpt automatici (the_excerpt()).
 *
 * Priorità 999 per prevalere sui plugin che impostano una propria lunghezza.
 * Il link "Continua a leggere" lo aggiunge il filtro excerpt_more qui sopra.
 *
 * @param  mixed  $length  La lunghezza impostata finora (ignorata).
 */
function excerpt_length(mixed $length): int
{
    return 20;
}
add_filter('excerpt_length', __NAMESPACE__.'\\excerpt_length', 999);

/**
 * Imposta in automatico titolo e testo alternativo delle immagini caricate dal nome del file.
 *
 * Funziona con ogni canale di caricamento (libreria media, REST/Gutenberg, WP-CLI,
 * media_sideload_image) perché il nome arriva dal file allegato.
 * Il titolo viene sostituito solo se è quello di default ricavato da WordPress
 * dal nome del file, l'alt solo se vuoto. Didascalia e descrizione restano invariate.
 */
function add_image_meta_data(int $attachment_id): void
{
    if (! wp_attachment_is_image($attachment_id)) {
        return;
    }

    $file = get_attached_file($attachment_id);

    if (! $file) {
        return;
    }

    $name = pathinfo($file, PATHINFO_FILENAME);
    // Rimuove i suffissi generati da WordPress (-scaled, -150x150, -150x150-1).
    $name = preg_replace('/(-scaled|-\d+x\d+)+(-\d+)?$/i', '', $name) ?? $name;
    $name = str_replace(['-', '_'], ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;
    $name = sanitize_text_field(trim($name));

    if ($name === '') {
        return;
    }

    // Sostituisce solo il titolo che WordPress ricava dal nome del file. Un titolo
    // impostato esplicitamente (REST "title", --title, post_data, IPTC/EXIF) resta com'è.
    // Dentro l'hook il nome originale del file caricato non c'è più, quindi si
    // confronta una chiave normalizzata del titolo con il nome del file salvato,
    // che può essere sanificato (accenti, spazi, punti) e avere un suffisso "-N" di unicità.
    $key = static function (string $value): string {
        $value = mb_strtolower(remove_accents(wp_specialchars_decode($value, ENT_QUOTES)));

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '', '-');
    };

    $stored = pathinfo($file, PATHINFO_FILENAME);
    $title_key = $key((string) get_post_field('post_title', $attachment_id, 'raw'));
    $defaults = array_unique([
        $key($stored),
        $key(preg_replace('/-\d+$/', '', $stored) ?? $stored),
        $key(preg_replace('/(-scaled|-\d+x\d+)+(-\d+)?$/i', '', $stored) ?? $stored),
    ]);

    if ($title_key === '' || in_array($title_key, $defaults, true)) {
        wp_update_post([
            'ID' => $attachment_id,
            'post_title' => $name,
        ]);
    }

    if (get_post_meta($attachment_id, '_wp_attachment_image_alt', true) === '') {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $name);
    }
}
add_action('add_attachment', __NAMESPACE__.'\\add_image_meta_data');

/**
 * Ottimizzazioni di performance: disattiva gli asset emoji di WordPress.
 *
 * Rimuove lo script di rilevamento delle emoji e gli stili inline dal <head>
 * del frontend, e le immagini statiche delle emoji da feed ed email.
 *
 * Gli asset emoji dell'admin li gestiscono disable_admin_emoji() e
 * disable_tinymce_emoji(): gli hook dell'admin sono registrati dopo
 * "init", quindi non si possono rimuovere da qui.
 *
 * wp-embed volutamente NON viene toccato. Da WP 5.9 il core lo carica solo
 * quando il contenuto renderizzato contiene l'embed di un post WordPress
 * (wp_maybe_enqueue_oembed_host_js() su "embed_oembed_html"), quindi sulle
 * pagine senza embed non c'è nulla da risparmiare. Deregistrarlo rompeva il
 * ridimensionamento automatico degli embed dei post e l'anteprima TinyMCE
 * nell'admin, e sganciare wp_oembed_add_host_js reintrodurrebbe solo quel guasto.
 */
function disable_emoji(): void
{
    // Script e stili delle emoji nel frontend.
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');

    // Immagini statiche delle emoji in feed ed email.
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', __NAMESPACE__.'\\disable_emoji');

/**
 * Disattiva gli asset emoji di WordPress nell'admin.
 *
 * Il core registra gli hook emoji dell'admin in wp-admin/includes/admin-filters.php
 * (print_emoji_detection_script su "admin_print_scripts", print_emoji_styles
 * su "admin_print_styles", entrambi alla priorità di default 10). Quel file è
 * caricato dopo "init", quindi la rimozione gira invece su "admin_init".
 *
 * Rimuovere print_emoji_styles basta anche per il CSS:
 * wp_enqueue_emoji_styles() (su "admin_enqueue_scripts") esce subito quando
 * print_emoji_styles non è più agganciato a "admin_print_styles".
 * Il block editor sgancia già da sé lo script di rilevamento
 * (wp-admin/edit-form-blocks.php).
 */
function disable_admin_emoji(): void
{
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
}
add_action('admin_init', __NAMESPACE__.'\\disable_admin_emoji');

/**
 * Rimuove il plugin TinyMCE "wpemoji".
 *
 * Vale per l'editor classico e per il blocco Classico del block editor,
 * che passano entrambi la loro lista di plugin da "tiny_mce_plugins".
 *
 * @param  array<int, string>  $plugins  Nomi dei plugin TinyMCE.
 * @return array<int, string>
 */
function disable_tinymce_emoji(array $plugins): array
{
    return array_values(array_diff($plugins, ['wpemoji']));
}
add_filter('tiny_mce_plugins', __NAMESPACE__.'\\disable_tinymce_emoji');

/**
 * Adatta l'immagine del custom logo all'header (get_custom_logo()).
 *
 * - sizes: l'header limita il logo a 80px di altezza (max-h-20, la
 *   'height' del supporto custom-logo in setup.php) con larghezza
 *   automatica, quindi la larghezza renderizzata è 80px per il rapporto
 *   dell'immagine (240px per il 240×80 consigliato). Il core userebbe la
 *   larghezza piena dell'immagine (es. "(max-width: 480px) 100vw, 480px") e
 *   il browser scaricherebbe una variante più grande del necessario.
 * - fetchpriority: "auto" (default del browser) invece del "high" che il core
 *   assegna alla prima immagine oltre 50000px² che incontra. Il logo è piccolo
 *   e raramente è l'elemento LCP; le immagini marcate esplicitamente "high"
 *   (x-ui.image con eager) mantengono comunque la priorità. "auto" invece di
 *   rimuovere l'attributo: senza, il core aggiungerebbe di nuovo "high"
 *   (wp_maybe_add_fetchpriority_high_attr()). Un valore già impostato da un
 *   altro filtro resta invariato.
 *
 * @param  array<string, mixed>  $attr  Attributi dell'immagine.
 * @param  int|string  $attachment_id  ID dell'allegato del logo (theme mod).
 * @return array<string, mixed>
 */
function custom_logo_image_attributes(array $attr, int|string $attachment_id): array
{
    $max_height = 80;
    $image = wp_get_attachment_image_src((int) $attachment_id, 'full');

    if ($image && empty($attr['sizes']) && $image[1] > 0 && $image[2] > 0) {
        $width = (int) min($image[1], ceil($max_height * $image[1] / $image[2]));
        $attr['sizes'] = sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $width);
    }

    $attr['fetchpriority'] ??= 'auto';

    return $attr;
}
add_filter('get_custom_logo_image_attributes', __NAMESPACE__.'\\custom_logo_image_attributes', 10, 2);
