<?php

declare(strict_types=1);

/**
 * Helper del tema nel namespace App.
 *
 * Si chiamano come \App\name() dalle view Blade e dagli altri namespace,
 * oppure si importano con `use function App\name;`.
 *
 * Qui stanno solo funzioni semplici senza effetti collaterali: gli hook vanno
 * nei file degli hook (setup.php, filters.php, ...) o nelle classi in app/.
 */

namespace App;

use Illuminate\Support\Facades\Vite;

/**
 * Restituisce il tag di titolo che segue un dato livello di titolo.
 *
 * Serve per i titoli figli di un blocco (sottotitoli, titoli delle card, ...):
 * il titolo del blocco usa $current_heading, i figli il tag restituito.
 * Esempi: h2 → h3, h5 → h6, h6 → p, p → p, h5 con incremento 2 → p.
 *
 * Qualsiasi valore diverso da h1..h6 o p (es. null, '', 'h', 'h9', 'div')
 * viene trattato come 'h2' (il tag di default del titolo dei blocchi) prima del calcolo.
 *
 * @param  string|null  $current_heading  Il tag corrente: h1..h6 o p (null → 'h2').
 * @param  int  $increment  Di quanti livelli scendere (minimo 1).
 * @return string Il tag successivo: h2..h6, o p quando il livello supera h6.
 */
function get_next_heading_level(?string $current_heading, int $increment = 1): string
{
    if (! in_array($current_heading, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'], true)) {
        $current_heading = 'h2';
    }

    if ($current_heading === 'h6' || $current_heading === 'p') {
        return 'p';
    }

    $increment = max(1, $increment);

    $current_level = (int) substr($current_heading, 1, 1);
    $next_level = $current_level + $increment;

    if ($next_level > 6) {
        return 'p';
    }

    return 'h'.$next_level;
}

/**
 * Genera una stringa casuale minuscola, es. per gli id HTML.
 *
 * Usa solo le lettere a-z, quindi il risultato è sempre un inizio di id valido
 * (gli id HTML usati nei selettori CSS non possono iniziare con una cifra).
 * Usa random_int(), un generatore crittograficamente sicuro.
 *
 * @param  int  $length  Numero di caratteri (minimo 1).
 * @return string La stringa casuale.
 */
function random_string_for_id(int $length = 5): string
{
    $characters = 'abcdefghijklmnopqrstuvwxyz';
    $max = strlen($characters) - 1;
    $length = max(1, $length);
    $random_string = '';

    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[random_int(0, $max)];
    }

    return $random_string;
}

/**
 * Restituisce l'URL dell'immagine di fallback locale (resources/images/fallback.svg).
 *
 * L'immagine è un SVG 16:9 compilato da Vite, quindi scala a qualsiasi
 * dimensione e non coinvolge servizi esterni.
 *
 * @param  int  $width  Ignorato, mantenuto per retrocompatibilità (l'SVG non dipende dalla risoluzione).
 * @param  int  $height  Ignorato, mantenuto per retrocompatibilità.
 * @return string L'URL dell'asset, o '' se l'asset manca dal manifest di Vite.
 */
function get_fallback_image_url(int $width = 1920, int $height = 1080): string
{
    try {
        return Vite::asset('resources/images/fallback.svg');
    } catch (\Throwable) {
        return '';
    }
}

/**
 * Restituisce un excerpt accorciato di un post.
 *
 * Testo di partenza, in ordine: il campo ACF 'excerpt' (se ACF è attivo),
 * l'excerpt manuale del post, il contenuto del post senza shortcode e tag.
 * Il testo viene accorciato con wp_trim_words() e, se tagliato, termina con '…'.
 *
 * @param  int  $num  Numero massimo di parole.
 * @param  int|\WP_Post|null  $post  ID o oggetto del post, il post corrente se null.
 * @return string L'excerpt, o '' se il post non esiste.
 */
function get_trimmed_excerpt(int $num, int|\WP_Post|null $post = null): string
{
    $post = get_post($post);

    if (! $post instanceof \WP_Post) {
        return '';
    }

    $text = function_exists('get_field') ? get_field('excerpt', $post->ID) : '';
    $text = is_string($text) ? $text : '';

    if (trim($text) === '') {
        $text = $post->post_excerpt;
    }

    if (trim($text) === '') {
        $text = wp_strip_all_tags(strip_shortcodes($post->post_content));
    }

    return wp_trim_words($text, $num, '…');
}

/**
 * Ricava l'ID di un allegato dai formati di ritorno dei campi immagine di ACF.
 *
 * Accetta:
 *  - un intero (ID, formato "id" di ACF), restituito così com'è se positivo;
 *  - una stringa di sole cifre (es. un valore salvato come testo, "42"),
 *    purché rientri negli int: "99999999999999999999" restituisce 0;
 *  - un array con chiave 'ID' o 'id' (formato "array" di ACF), con uno dei valori sopra;
 *  - un WP_Post di tipo attachment (formato "object" delle gallery).
 * Qualsiasi altro valore (null, '', 0, negativi, float, bool, stringhe come
 * "42abc", array senza ID, post che non sono allegati) restituisce 0.
 *
 * Non verifica che l'allegato esista né che sia un'immagine:
 * lo fa chi stampa l'immagine (wp_attachment_is_image()).
 *
 * @param  mixed  $value  Il valore del campo.
 * @return int L'ID dell'allegato, 0 se non ricavabile.
 */
function get_attachment_id(mixed $value): int
{
    if ($value instanceof \WP_Post) {
        return $value->post_type === 'attachment' ? max(0, (int) $value->ID) : 0;
    }

    if (is_array($value)) {
        $value = $value['ID'] ?? $value['id'] ?? null;
    }

    if (is_string($value) && preg_match('/^\d+$/', trim($value))) {
        // Senza zeri iniziali, per confrontarla con il suo cast: una stringa
        // fuori dal range degli int diventerebbe PHP_INT_MAX, non il suo valore.
        $digits = ltrim(trim($value), '0');
        $value = $digits !== '' && (string) (int) $digits === $digits ? (int) $digits : 0;
    }

    return is_int($value) && $value > 0 ? $value : 0;
}
