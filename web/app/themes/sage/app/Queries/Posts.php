<?php

declare(strict_types=1);

namespace App\Queries;

use WP_Query;

/**
 * Query sui post che restituiscono array semplici di dati dei post per le view.
 *
 * Metodi statici senza stato, quindi si possono chiamare indifferentemente da
 * view Blade, View Composer e template dei blocchi (es. un blocco "ultime news"
 * o un Composer che alimenta un elenco). Non sono View Composer perché i dati
 * dipendono da argomenti (pagina, categoria, numero) scelti da chi chiama,
 * non dalla view che si sta renderizzando.
 *
 * @phpstan-type PostData array{title: string, link: string|false, image: string, image_id: int|null, date: string|int|false, categories: \WP_Term[]}
 */
class Posts
{
    /**
     * Estrae i dati di visualizzazione di un post.
     *
     * 'title' è decodificato dalle entity HTML, quindi va stampato con l'escape
     * (Blade {{ }}), mai raw. 'image' è l'URL della miniatura 'large' o il fallback
     * locale; 'image_id' è l'ID dell'allegato (null senza miniatura) per il
     * markup responsive. 'date' segue l'opzione date_format del sito.
     *
     * @param  int|null  $post_id  ID del post, il post corrente se null.
     * @return PostData|null
     */
    public static function data(?int $post_id = null): ?array
    {
        $post_id = $post_id ?: get_the_ID();
        if (! $post_id) {
            return null;
        }

        $image_id = get_post_thumbnail_id($post_id) ?: null;
        $image = $image_id ? get_the_post_thumbnail_url($post_id, 'large') : false;

        return [
            'title' => html_entity_decode((string) get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'link' => get_the_permalink($post_id),
            'image' => $image ?: \App\get_fallback_image_url(1280, 720),
            'image_id' => $image_id,
            'date' => get_the_date('', $post_id),
            'categories' => get_the_category($post_id),
        ];
    }

    /**
     * Restituisce una pagina di post pubblicati, filtrabile per categoria.
     *
     * I post in evidenza non vengono spostati in cima; il conteggio delle righe
     * trovate resta, per la paginazione.
     *
     * @param  int|string  $page  Numero di pagina (es. get_query_var("paged")), almeno 1.
     * @param  int|null  $category_id  ID del termine categoria, tutte le categorie se null.
     * @return array{posts: list<PostData>, total_pages: int, current_page: int, total_posts: int}
     */
    public static function paginated(int|string $page = 1, ?int $category_id = null): array
    {
        $page = max(1, (int) $page);

        $args = [
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'paged' => $page,
            'post_type' => 'post',
            'orderby' => 'date',
            'order' => 'DESC',
            'ignore_sticky_posts' => true,
        ];

        if ($category_id) {
            $args['cat'] = $category_id;
        }

        $query = new WP_Query($args);

        return [
            'posts' => self::collect($query),
            'total_pages' => $query->max_num_pages,
            'current_page' => $page,
            'total_posts' => $query->found_posts,
        ];
    }

    /**
     * Restituisce gli ultimi post pubblicati, senza spostare in cima quelli in evidenza.
     *
     * @param  int  $posts  Numero di post.
     * @return list<PostData> Dati dei post come li restituisce self::data().
     */
    public static function latest(int $posts = 3): array
    {
        return self::collect(new WP_Query([
            'posts_per_page' => $posts,
            'post_status' => 'publish',
            'post_type' => 'post',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ]));
    }

    /**
     * Converte i post di una query nei loro dati di visualizzazione.
     *
     * Le miniature vengono precaricate con una sola query per evitare N+1 lookup.
     *
     * @return list<PostData> Dati dei post come li restituisce self::data().
     */
    private static function collect(WP_Query $query): array
    {
        update_post_thumbnail_cache($query);

        $posts = [];

        foreach ($query->posts as $post) {
            $data = self::data($post->ID);
            if ($data) {
                $posts[] = $data;
            }
        }

        return $posts;
    }
}
