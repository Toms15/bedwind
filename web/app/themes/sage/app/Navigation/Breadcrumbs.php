<?php

declare(strict_types=1);

namespace App\Navigation;

use WP_Post_Type;
use WP_Term;
use WP_User;

/**
 * Percorso di breadcrumb della richiesta corrente, come dati semplici.
 *
 * Metodi statici senza stato (come App\Queries\*): il percorso dipende solo
 * dalla main query, quindi si può costruire da qualsiasi view, componente o
 * blocco, ovunque sia incluso <x-content.breadcrumbs />. Non è un View Composer
 * perché un composer è legato ai nomi delle view, mentre il componente deve
 * funzionare in qualsiasi template senza esservi registrato.
 *
 * Contratto di items():
 * - array vuoto sulla front page (niente da mostrare);
 * - altrimenti la prima voce è la home e l'ultima è la pagina corrente,
 *   con 'url' => null;
 * - le label sono testo semplice (a titoli e nomi di WordPress vengono tolti i
 *   tag e decodificate le entity quando si costruisce il percorso; i termini di
 *   ricerca restano come digitati): stampale con l'escape di Blade {{ }}, mai raw.
 *
 * La lista finale passa dal filtro `sage/breadcrumbs`.
 */
class Breadcrumbs
{
    /**
     * Costruisce il percorso di breadcrumb della richiesta corrente.
     *
     * @return array<int, array{label: string, url: ?string}>
     */
    public static function items(): array
    {
        $items = static::trail();

        /**
         * Filtra le voci di breadcrumb della richiesta corrente.
         *
         * @param  array<int, array{label: string, url: ?string}>  $items  Voci, l'ultima è la pagina corrente.
         */
        $items = apply_filters('sage/breadcrumbs', static::normalize($items));

        // Il PHPDoc qui sopra descrive il valore atteso, ma una callback di terze
        // parti può restituire qualsiasi cosa: il controllo a runtime resta.
        // @phpstan-ignore function.alreadyNarrowedType
        return static::normalize(is_array($items) ? $items : []);
    }

    /**
     * Impone la forma delle voci: label stringa (con trim, quelle vuote
     * scartate), URL stringa o null, ultima voce (pagina corrente) senza URL.
     *
     * Idempotente e senza decodifica: le label che arrivano dal filtro o da
     * <x-content.breadcrumbs :items="..."> sono già testo semplice.
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
     * Percorso prima di normalize() e del filtro. Qui la pagina corrente
     * mantiene l'URL: lo toglie normalize(), dopo la voce della paginazione.
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
            // Front page paginata: Home → Pagina N.
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
            // Non passa da item(): i termini di ricerca si mostrano come digitati
            // (senza rimozione dei tag né decodifica delle entity); Blade {{ }} e
            // JSON_HEX_TAG ne fanno l'escape in output.
            $items[] = [
                /* translators: %s: termini di ricerca. */
                'label' => sprintf(__('Risultati per “%s”', 'sage'), get_search_query(false)),
                'url' => get_search_link(),
            ];
        } elseif (is_404()) {
            $items[] = static::item(__('Pagina non trovata', 'sage'), null);
        }

        // Un URL paginato oltre l'ultima pagina è un 404: niente "Pagina N" dopo
        // "Pagina non trovata".
        if ($page > 1 && ! is_404()) {
            $items[] = static::item(
                /* translators: %d: numero di pagina. */
                sprintf(__('Pagina %d', 'sage'), $page),
                null
            );
        }

        return $items;
    }

    /**
     * Archivio autore. Se il display name è vuoto ripiega sul nicename,
     * poi sul login, poi su un generico "Autore", così il percorso non si
     * riduce mai alla sola Home.
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
     * Numero della pagina corrente: 'paged' negli archivi, 'page' su una front
     * page statica paginata o su un post diviso con <!--nextpage-->.
     */
    protected static function pageNumber(): int
    {
        return max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
    }

    /**
     * Pagina degli articoli (Impostazioni → Lettura), con le pagine genitore.
     *
     * Vuota quando gli articoli sono mostrati sulla front page: lì la voce
     * della home è già il blog.
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
     * Contenuto singolo.
     *
     * - post: pagina degli articoli → prima categoria (con i genitori) → titolo;
     * - altri post type: archivio (se has_archive) → genitori → titolo.
     *   I genitori sono la gerarchia delle pagine, o il post genitore di un allegato.
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
     * Archivio di categoria, tag o tassonomia personalizzata.
     *
     * Le tassonomie dei post partono dalla pagina degli articoli; quelle degli
     * altri post type dall'archivio del primo che ne ha uno.
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
     * Archivio del post type.
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
     * Archivio per data: anno → mese → giorno. get_year_link() passa dal
     * filtro `year_link` di app/rewrites.php (base news/archivio).
     *
     * @return array<int, array{label: string, url: mixed}>
     */
    protected static function date(): array
    {
        global $wp_locale;

        $year = (int) get_query_var('year');
        $month = (int) get_query_var('monthnum');
        $day = (int) get_query_var('day');

        // Archivi richiesti con ?m=YYYYMMDD.
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
     * Un post preceduto dai suoi antenati (gerarchia delle pagine).
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
     * Un termine preceduto dai suoi antenati.
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
     * Voce dai dati di WordPress: titoli e nomi dei termini possono contenere tag
     * ed entity HTML (es. &#8217; da wptexturize, &amp; nei nomi dei termini),
     * qui trasformati in testo semplice.
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
