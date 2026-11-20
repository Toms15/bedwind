<?php

namespace App\Navigation;

use WP_Post_Type;
use WP_Term;
use WP_User;

/**
 * Breadcrumb trail of the current request, as plain data.
 *
 * Stateless static methods (like App\Queries\*): the trail depends only on
 * the main query, so it can be built from any view, component or block,
 * wherever <x-breadcrumbs /> is included. It is not a View Composer because
 * a composer is bound to view names, while the component must work in any
 * template without being registered for it.
 *
 * Contract of items():
 * - empty array on the front page (nothing to show);
 * - otherwise the first item is the home page and the last one is the
 *   current page, with 'url' => null;
 * - labels are plain text (WordPress titles and names are stripped of
 *   tags and decoded from entities when the trail is built; the search
 *   terms are kept as typed): print them escaped with Blade {{ }}, never raw.
 *
 * The final list goes through the `sage/breadcrumbs` filter.
 */
class Breadcrumbs
{
    /**
     * Build the breadcrumb trail of the current request.
     *
     * @return array<int, array{label: string, url: ?string}>
     */
    public static function items(): array
    {
        $items = static::trail();

        /**
         * Filter the breadcrumb items of the current request.
         *
         * @param  array<int, array{label: string, url: ?string}>  $items  Items, the last one is the current page.
         */
        $items = apply_filters('sage/breadcrumbs', static::normalize($items));

        return static::normalize(is_array($items) ? $items : []);
    }

    /**
     * Enforce the shape of the items: string labels (trimmed, empty ones
     * dropped), string or null URLs, last item (current page) without URL.
     *
     * Idempotent and without decoding: labels coming from the filter or from
     * <x-breadcrumbs :items="..."> are already plain text.
     *
     * @param  array<mixed>  $items
     * @return array<int, array{label: string, url: ?string}>
     */
    public static function normalize(array $items): array
    {
        $clean = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_scalar($item['label'] ?? null)) {
                continue;
            }

            $label = trim((string) $item['label']);
            $url = $item['url'] ?? null;

            if ($label === '') {
                continue;
            }

            $clean[] = [
                'label' => $label,
                'url' => is_string($url) && $url !== '' ? $url : null,
            ];
        }

        if ($clean) {
            $clean[array_key_last($clean)]['url'] = null;
        }

        return $clean;
    }

    /**
     * Trail before normalize() and the filter. The current page keeps its
     * URL here: normalize() drops it, after the pagination item.
     *
     * @return array<int, array{label: string, url: mixed}>
     */
    protected static function trail(): array
    {
        $page = static::pageNumber();

        if (is_front_page() && $page < 2) {
            return [];
        }

        $items = [static::item(__('Home', 'sage'), home_url('/'))];

        if (is_front_page()) {
            // Paginated front page: Home → Pagina N.
        } elseif (is_home()) {
            $items = [...$items, ...static::postsPage()];
        } elseif (is_singular()) {
            $items = [...$items, ...static::singular(get_queried_object_id())];
        } elseif (is_category() || is_tag() || is_tax()) {
            $items = [...$items, ...static::term(get_queried_object())];
        } elseif (is_post_type_archive()) {
            $items = [...$items, ...static::postTypeArchive(get_queried_object())];
        } elseif (is_date()) {
            $items = [...$items, ...static::postsPage(), ...static::date()];
        } elseif (is_author()) {
            $items = [...$items, ...static::postsPage(), static::author()];
        } elseif (is_search()) {
            // Not through item(): the search terms are shown as typed
            // (no tag stripping or entity decoding); Blade {{ }} and
            // JSON_HEX_TAG escape them on output.
            $items[] = [
                /* translators: %s: search terms. */
                'label' => sprintf(__('Risultati per “%s”', 'sage'), get_search_query(false)),
                'url' => get_search_link(),
            ];
        } elseif (is_404()) {
            $items[] = static::item(__('Pagina non trovata', 'sage'), null);
        }

        // A paginated URL past the last page is a 404: no "Pagina N" after
        // "Pagina non trovata".
        if ($page > 1 && ! is_404()) {
            $items[] = static::item(
                /* translators: %d: page number. */
                sprintf(__('Pagina %d', 'sage'), $page),
                null
            );
        }

        return $items;
    }

    /**
     * Author archive. When the display name is empty, fall back to the
     * nicename, then the login, then to a generic "Autore", so the trail
     * never collapses to Home alone.
     *
     * @return array{label: string, url: mixed}
     */
    protected static function author(): array
    {
        $user = get_queried_object();

        if (! $user instanceof WP_User) {
            $name = (string) get_query_var('author_name');

            return static::item($name !== '' ? $name : __('Autore', 'sage'), null);
        }

        $name = '';

        foreach (['display_name', 'user_nicename', 'user_login'] as $field) {
            $name = trim(wp_strip_all_tags((string) $user->{$field}));

            if ($name !== '') {
                break;
            }
        }

        return static::item($name !== '' ? $name : __('Autore', 'sage'), get_author_posts_url($user->ID));
    }

    /**
     * Current page number: 'paged' on archives, 'page' on a paginated static
     * front page or on a post split with <!--nextpage-->.
     */
    protected static function pageNumber(): int
    {
        return max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
    }

    /**
     * Posts page (Settings → Reading), with its parent pages.
     *
     * Empty when the posts are shown on the front page: there the home item
     * already is the blog.
     *
     * @return array<int, array{label: string, url: mixed}>
     */
    protected static function postsPage(): array
    {
        $id = (int) get_option('page_for_posts');

        if (get_option('show_on_front') !== 'page' || ! $id || get_post_status($id) !== 'publish') {
            return [];
        }

        return static::postWithAncestors($id);
    }

    /**
     * Singular content.
     *
     * - post: posts page → first category (with its parents) → title;
     * - other post types: archive (if has_archive) → parents → title.
     *   Parents are the page hierarchy, or the parent post of an attachment.
     *
     * @return array<int, array{label: string, url: mixed}>
     */
    protected static function singular(int $id): array
    {
        $type = get_post_type($id);

        if ($type === 'post') {
            $items = static::postsPage();
            $category = get_the_category($id)[0] ?? null;

            if ($category instanceof WP_Term) {
                $items = [...$items, ...static::termWithAncestors($category)];
            }

            return [...$items, static::item(get_the_title($id), get_permalink($id))];
        }

        $items = [];
        $object = get_post_type_object($type);

        if ($object instanceof WP_Post_Type && $object->has_archive) {
            $items[] = static::item($object->labels->name, get_post_type_archive_link($type));
        }

        return [...$items, ...static::postWithAncestors($id)];
    }

    /**
     * Category, tag or custom taxonomy archive.
     *
     * Taxonomies of posts start from the posts page; taxonomies of other
     * post types from the archive of the first one that has it.
     *
     * @return array<int, array{label: string, url: mixed}>
     */
    protected static function term(mixed $term): array
    {
        if (! $term instanceof WP_Term) {
            return [];
        }

        $items = [];
        $types = get_taxonomy($term->taxonomy)->object_type ?? [];

        if (in_array('post', $types, true)) {
            $items = static::postsPage();
        } else {
            foreach ($types as $type) {
                $object = get_post_type_object($type);

                if ($object instanceof WP_Post_Type && $object->public && $object->has_archive) {
                    $items[] = static::item($object->labels->name, get_post_type_archive_link($type));
                    break;
                }
            }
        }

        return [...$items, ...static::termWithAncestors($term)];
    }

    /**
     * Post type archive.
     *
     * @return array<int, array{label: string, url: mixed}>
     */
    protected static function postTypeArchive(mixed $object): array
    {
        if (! $object instanceof WP_Post_Type) {
            return [];
        }

        return [static::item($object->labels->name, get_post_type_archive_link($object->name))];
    }

    /**
     * Date archive: year → month → day. get_year_link() goes through the
     * `year_link` filter of app/rewrites.php (news/archivio base).
     *
     * @return array<int, array{label: string, url: mixed}>
     */
    protected static function date(): array
    {
        global $wp_locale;

        $year = (int) get_query_var('year');
        $month = (int) get_query_var('monthnum');
        $day = (int) get_query_var('day');

        // Archives requested with ?m=YYYYMMDD.
        if (! $year && ($m = (string) get_query_var('m'))) {
            $year = (int) substr($m, 0, 4);
            $month = (int) substr($m, 4, 2);
            $day = (int) substr($m, 6, 2);
        }

        $items = [static::item((string) $year, get_year_link($year))];

        if (is_month() || is_day()) {
            $items[] = static::item($wp_locale->get_month($month), get_month_link($year, $month));
        }

        if (is_day()) {
            $items[] = static::item((string) $day, get_day_link($year, $month, $day));
        }

        return $items;
    }

    /**
     * A post preceded by its ancestors (page hierarchy).
     *
     * @return array<int, array{label: string, url: mixed}>
     */
    protected static function postWithAncestors(int $id): array
    {
        $ids = [...array_reverse(get_post_ancestors($id)), $id];

        return array_map(
            fn (int $id): array => static::item(get_the_title($id), get_permalink($id)),
            $ids
        );
    }

    /**
     * A term preceded by its ancestors.
     *
     * @return array<int, array{label: string, url: mixed}>
     */
    protected static function termWithAncestors(WP_Term $term): array
    {
        $ids = [...array_reverse(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy')), $term->term_id];
        $items = [];

        foreach ($ids as $id) {
            $current = get_term($id, $term->taxonomy);

            if ($current instanceof WP_Term) {
                $link = get_term_link($current);
                $items[] = static::item($current->name, is_wp_error($link) ? null : $link);
            }
        }

        return $items;
    }

    /**
     * Item from WordPress data: titles and term names may contain tags and
     * HTML entities (e.g. &#8217; from wptexturize, &amp; in term names),
     * turned here into plain text.
     *
     * @return array{label: string, url: mixed}
     */
    protected static function item(mixed $label, mixed $url): array
    {
        $label = html_entity_decode(
            wp_strip_all_tags((string) $label),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return ['label' => $label, 'url' => $url];
    }
}
