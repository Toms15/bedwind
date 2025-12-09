<?php

/**
 * Theme helpers.
 */

/**
 * Allow additional file types for media uploads
 */
function custom_mime_types($mimes)
{
    $mimes['ico'] = 'image/x-icon';
    $mimes['webp'] = 'image/webp';
    $mimes['manifest'] = 'application/manifest+json';
    $mimes['webmanifest'] = 'application/manifest+json';
    $mimes['svg'] = 'image/svg+xml';

    return $mimes;
}
add_filter('upload_mimes', 'custom_mime_types');

/**
 * Automatically add media information at upload time
 */
add_action('add_attachment', 'wordpack_add_image_meta_data');
function wordpack_add_image_meta_data($attachment_ID)
{
    $filename = $_REQUEST['name']; // or get_post by ID
    $withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);
    $withoutExt = str_replace(['-'], ' ', $withoutExt);
    $my_post = [
        'post_title' => $withoutExt,  // title
        'ID' => $attachment_ID,
        'post_excerpt' => $withoutExt,  // caption
        'post_content' => $withoutExt,  // description
    ];
    wp_update_post($my_post);
    // update alt text for post
    update_post_meta($attachment_ID, '_wp_attachment_image_alt', $withoutExt);
}

/**
 * Get Next Heading Leve
 */
function get_next_heading_level($current_heading, $increment = 1)
{
    if ($current_heading === 'h6' || $current_heading === 'p') {
        return 'p';
    }

    $increment = max(1, (int) $increment);

    $current_level = (int) substr($current_heading, 1, 1);
    $next_level = $current_level + $increment;

    if ($next_level > 6) {
        return 'p';
    }

    return 'h'.$next_level;
}

/**
 * Generate Random String
 */
function random_string_for_id($length = 5): string
{
    $characters = 'abcdefghijklmnopqrstuvwxyz';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomString;
}

/**
 * Get fallback image
 */
function get_fallback_image_url($width = 1920, $height = 1080): string
{
    return "https://placehold.co/{$width}x{$height}/webp";
}

/**
 * Excerpt functions
 */
function wordpack_excerpt($num, $post_id = null)
{
    if ($post_id) {
        $post = get_post($post_id);
        $excerpt_text = get_field('excerpt', $post_id);

        if (empty($excerpt_text)) {
            $excerpt_text = wp_strip_all_tags($post->post_content);
        }
    } else {
        $excerpt_text = get_the_excerpt();
    }

    $limit = $num + 1;
    $excerpt = explode(' ', $excerpt_text, $limit);

    if (count($excerpt) >= $limit) {
        array_pop($excerpt);
        $excerpt = implode(' ', $excerpt).'...';
    } else {
        $excerpt = implode(' ', $excerpt);
    }

    return $excerpt;
}
function wordpack_excerpt_length($length)
{
    return 20;
}
add_filter('excerpt_length', 'wordpack_excerpt_length', 999);
function wordpack_excerpt_more($more)
{
    return '...';
}
add_filter('excerpt_more', 'wordpack_excerpt_more');

/**
 * Make theme available for translation
 * Translations can be placed in the /resources/lang directory
 */
add_action('after_setup_theme', function () {
    load_theme_textdomain('sage', get_template_directory().'/resources/lang');
});

/**
 * Performance optimizations
 */
function wordpack_performance_optimizations()
{
    // Disable emojis
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', 'wordpack_disable_emojis_tinymce');

    // Disable wp-embed
    wp_deregister_script('wp-embed');
}
add_action('init', 'wordpack_performance_optimizations');
