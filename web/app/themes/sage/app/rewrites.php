<?php

declare(strict_types=1);

/**
 * Archivi annuali dei post esposti come /{base}/YYYY/ (base di default: news/archivio).
 *
 * - Regole di rewrite per l'archivio, la sua paginazione e il suo feed.
 * - Filtro `year_link`, così get_year_link() e wp_get_archives(type=yearly)
 *   puntano alla nuova base (i link mensili/giornalieri restano invariati).
 * - Redirect 301 dall'URL /YYYY/ del core per evitare contenuti duplicati.
 *
 * La base è filtrabile con `sage/yearly_archive_base` (senza slash iniziali o
 * finali). Le regole di rewrite sono salvate nel database: dopo aver
 * cambiato la base o le regole esegui `wp rewrite flush` (o salva le
 * impostazioni dei permalink nell'admin).
 */

namespace App;

/**
 * Restituisce il percorso base dell'archivio annuale, senza slash iniziali/finali.
 */
function yearly_archive_base(): string
{
    return trim((string) apply_filters('sage/yearly_archive_base', 'news/archivio'), '/');
}

add_action('init', function (): void {
    // WordPress confronta le regole di rewrite usando '#' come delimitatore.
    $base = preg_quote(yearly_archive_base(), '#');

    add_rewrite_rule(
        "^{$base}/([0-9]{4})/?$",
        'index.php?year=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        "^{$base}/([0-9]{4})/page/([0-9]+)/?$",
        'index.php?year=$matches[1]&paged=$matches[2]',
        'top'
    );

    // Corrisponde sia a /feed/{type}/ sia a /{type}/ (il core rende canonico il
    // feed di default in /feed/), come le regole dei feed per data del core.
    add_rewrite_rule(
        "^{$base}/([0-9]{4})/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$",
        'index.php?year=$matches[1]&feed=$matches[2]',
        'top'
    );
});

/**
 * Copre get_year_link() ovunque, compreso wp_get_archives(type=yearly).
 */
add_filter('year_link', function (string $link, int|string $year): string {
    return home_url(user_trailingslashit(yearly_archive_base().'/'.(int) $year, 'year'));
}, 10, 2);

/**
 * Reindirizza l'archivio annuale del core (/YYYY/, /YYYY/page/N/) alla nuova base.
 */
add_action('template_redirect', function (): void {
    global $wp;

    if (! is_year() || get_query_var('monthnum') || get_query_var('day') || is_feed()) {
        return;
    }

    $base = yearly_archive_base();
    $request = trim((string) $wp->request, '/');

    if ($request === $base || str_starts_with($request, $base.'/')) {
        return;
    }

    $url = get_year_link((int) get_query_var('year'));
    $paged = (int) get_query_var('paged');

    if ($paged > 1) {
        $url = trailingslashit($url).user_trailingslashit('page/'.$paged, 'paged');
    }

    // Mantiene la query string (es. parametri utm) nel redirect.
    if (! empty($_SERVER['QUERY_STRING'])) {
        $url .= '?'.$_SERVER['QUERY_STRING'];
    }

    wp_safe_redirect($url, 301);
    exit;
});

add_filter('get_the_archive_title', function (string $title): string {
    if (is_year()) {
        $title = sprintf(
            /* translators: %s: anno, es. 2026. */
            __('Archivio %s', 'sage'),
            (int) get_query_var('year')
        );
    }

    return $title;
});
