<?php

declare(strict_types=1);

namespace App\Queries;

use WP_Query;

/**
 * Post queries returning plain arrays of post data for views.
 *
 * Stateless static methods, so they can be called from Blade views, View
 * Composers and block templates alike (e.g. a "latest news" block or a
 * Composer that feeds a listing). They are not View Composers themselves
 * because the data depends on arguments (page, category, count) chosen by
 * the caller, not on the view being rendered.
 *
 * @phpstan-type PostData array{title: string, link: string|false, image: string, image_id: int|null, date: string|int|false, categories: \WP_Term[]}
 */
class Posts
{
    /**
     * Extract the display data of a post.
     *
     * 'title' is decoded from HTML entities, so it must be printed escaped
     * (Blade {{ }}), never raw. 'image' is the 'large' thumbnail URL or the local
     * fallback; 'image_id' is the attachment ID (null without thumbnail) for
     * responsive markup. 'date' follows the site's date_format option.
     *
     * @param  int|null  $post_id  Post ID, current post when null.
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
     * Get a page of published posts, optionally filtered by category.
     *
     * Sticky posts are not moved to the top; found rows are kept for pagination.
     *
     * @param  int|string  $page  Page number (e.g. get_query_var("paged")), at least 1.
     * @param  int|null  $category_id  Category term ID, all categories when null.
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
     * Get the latest published posts, sticky posts not moved to the top.
     *
     * @param  int  $posts  Number of posts.
     * @return list<PostData> Post data as returned by self::data().
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
     * Map the posts of a query to their display data.
     *
     * The thumbnails are primed in one query to avoid N+1 lookups.
     *
     * @return list<PostData> Post data as returned by self::data().
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
