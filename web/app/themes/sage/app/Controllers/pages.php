<?php

function getSubpages(int $page_id): array
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
