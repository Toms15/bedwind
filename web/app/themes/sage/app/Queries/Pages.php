<?php

declare(strict_types=1);

namespace App\Queries;

/**
 * Query sulle pagine.
 */
class Pages
{
    /**
     * Restituisce le pagine figlie pubblicate di una pagina, in ordine di menu.
     *
     * @return \WP_Post[]
     */
    public static function children(int $page_id): array
    {
        return get_posts([
            'post_type' => 'page',
            'post_parent' => $page_id,
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'post_status' => 'publish',
        ]);
    }
}
