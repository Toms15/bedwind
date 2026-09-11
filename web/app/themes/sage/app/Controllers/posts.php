<?php

function extractPostData(?int $post_id = null): ?array
{
    $post_id = $post_id ?: get_the_ID();
    if (! $post_id) {
        return null;
    }

    $title          = get_the_title($post_id);
    $permalink      = get_the_permalink($post_id);
    $featured_image = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'full');
    $image          = $featured_image ? $featured_image[0] : get_fallback_image_url(1280, 720);
    $categories     = get_the_category($post_id);
    $date_formatted = wp_date('M d, Y', get_post_timestamp($post_id));

    return [
        'title'      => html_entity_decode($title),
        'link'       => $permalink,
        'image'      => $image,
        'date'       => $date_formatted,
        'categories' => $categories,
    ];
}

function getAllPosts($page = 1, $category_id = null): array
{
    $args = [
        'posts_per_page' => 10,
        'post_status' => 'publish',
        'paged' => $page,
        'post_type' => 'post',
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    if ($category_id) {
        $args['cat'] = $category_id;
    }

    $query = new WP_Query($args);
    $posts = [];

    foreach ($query->posts as $post) {
        $news_data = extractPostData($post->ID);
        if ($news_data) {
            $posts[] = $news_data;
        }
    }

    return [
        'posts' => $posts,
        'total_pages' => $query->max_num_pages,
        'current_page' => $page,
        'total_posts' => $query->found_posts,
    ];
}

function latestNews($posts = 3): array
{
    $args = [
        'posts_per_page' => $posts,
        'post_type' => 'post',
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    $query = new WP_Query($args);
    $latest_news = [];

    foreach ($query->posts as $post) {
        $news_data = extractPostData($post->ID);
        if ($news_data) {
            $latest_news[] = $news_data;
        }
    }

    return $latest_news;
}
