<?php

/**
 * Yearly post archives exposed as /{base}/YYYY/ (default base: news/archivio).
 *
 * - Rewrite rules for the archive, its pagination and its feed.
 * - `year_link` filter so get_year_link() and wp_get_archives(type=yearly)
 *   point to the new base (monthly/daily links are untouched).
 * - 301 redirect from the core /YYYY/ URL to avoid duplicate content.
 *
 * The base is filterable via `sage/yearly_archive_base` (no leading or
 * trailing slashes). Rewrite rules are stored in the database: after
 * changing the base or the rules run `wp rewrite flush` (or save the
 * permalink settings in the admin).
 */

namespace App;

/**
 * Get the yearly archive base path, without leading/trailing slashes.
 */
function yearly_archive_base(): string
{
    return trim((string) apply_filters('sage/yearly_archive_base', 'news/archivio'), '/');
}

add_action('init', function (): void {
    // WordPress matches rewrite rules with '#' as delimiter.
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

    // Matches both /feed/{type}/ and /{type}/ (core canonicalizes the
    // default feed to /feed/), like core date feed rules.
    add_rewrite_rule(
        "^{$base}/([0-9]{4})/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$",
        'index.php?year=$matches[1]&feed=$matches[2]',
        'top'
    );
});

/**
 * Covers get_year_link() everywhere, including wp_get_archives(type=yearly).
 */
add_filter('year_link', function (string $link, int|string $year): string {
    return home_url(user_trailingslashit(yearly_archive_base().'/'.(int) $year, 'year'));
}, 10, 2);

/**
 * Redirect the core yearly archive (/YYYY/, /YYYY/page/N/) to the new base.
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

    // Keep the query string (e.g. utm parameters) on the redirect.
    if (! empty($_SERVER['QUERY_STRING'])) {
        $url .= '?'.$_SERVER['QUERY_STRING'];
    }

    wp_safe_redirect($url, 301);
    exit;
});

add_filter('get_the_archive_title', function (string $title): string {
    if (is_year()) {
        $title = __('Archivio', 'sage').' '.get_query_var('year');
    }

    return $title;
});
