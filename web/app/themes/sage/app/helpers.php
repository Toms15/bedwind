<?php

/**
 * Theme helpers in the App namespace.
 *
 * Call them as \App\name() from Blade views and from other namespaces,
 * or import them with `use function App\name;`.
 *
 * Only plain functions without side effects live here: hooks belong to
 * the hook files (setup.php, filters.php, ...) or to the classes in app/.
 */

namespace App;

/**
 * Get the heading tag that follows a given heading level.
 *
 * Used for the child headings of a block (subtitles, card titles, ...):
 * the block title uses $current_heading, its children the returned tag.
 * Examples: h2 → h3, h5 → h6, h6 → p, p → p, h5 with increment 2 → p.
 *
 * Any value that is not one of h1..h6 or p (e.g. null, '', 'h', 'h9', 'div')
 * is treated as 'h2' (the default block title tag) before calculating.
 *
 * @param  string|null  $current_heading  The current tag: h1..h6 or p (null → 'h2').
 * @param  int  $increment  How many levels to go down (minimum 1).
 * @return string The next tag: h2..h6, or p when the level goes past h6.
 */
function get_next_heading_level(?string $current_heading, int $increment = 1): string
{
    if (! in_array($current_heading, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'], true)) {
        $current_heading = 'h2';
    }

    if ($current_heading === 'h6' || $current_heading === 'p') {
        return 'p';
    }

    $increment = max(1, $increment);

    $current_level = (int) substr($current_heading, 1, 1);
    $next_level = $current_level + $increment;

    if ($next_level > 6) {
        return 'p';
    }

    return 'h'.$next_level;
}

/**
 * Generate a random lowercase string, e.g. for HTML ids.
 *
 * Only letters a-z are used, so the result is always a valid id start
 * (HTML ids used in CSS selectors cannot start with a digit).
 * Uses random_int(), a cryptographically secure generator.
 *
 * @param  int  $length  Number of characters (minimum 1).
 * @return string The random string.
 */
function random_string_for_id(int $length = 5): string
{
    $characters = 'abcdefghijklmnopqrstuvwxyz';
    $max = strlen($characters) - 1;
    $length = max(1, $length);
    $random_string = '';

    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[random_int(0, $max)];
    }

    return $random_string;
}

/**
 * Get the URL of the local fallback image (resources/images/fallback.svg).
 *
 * The image is a 16:9 SVG compiled by Vite, so it scales to any size and no
 * external service is involved.
 *
 * @param  int  $width  Ignored, kept for backward compatibility (the SVG is resolution independent).
 * @param  int  $height  Ignored, kept for backward compatibility.
 * @return string The asset URL, or '' when the asset is missing from the Vite manifest.
 */
function get_fallback_image_url(int $width = 1920, int $height = 1080): string
{
    try {
        return \Illuminate\Support\Facades\Vite::asset('resources/images/fallback.svg');
    } catch (\Throwable) {
        return '';
    }
}

/**
 * Get a trimmed excerpt of a post.
 *
 * Source text, in order: the ACF 'excerpt' field (when ACF is active),
 * the manual post excerpt, the post content without shortcodes and tags.
 * The text is trimmed with wp_trim_words() and '…' is appended when cut.
 *
 * @param  int  $num  Maximum number of words.
 * @param  int|\WP_Post|null  $post  Post ID or object, current post when null.
 * @return string The excerpt, or '' when the post does not exist.
 */
function get_trimmed_excerpt(int $num, int|\WP_Post|null $post = null): string
{
    $post = get_post($post);

    if (! $post instanceof \WP_Post) {
        return '';
    }

    $text = function_exists('get_field') ? get_field('excerpt', $post->ID) : '';
    $text = is_string($text) ? $text : '';

    if (trim($text) === '') {
        $text = $post->post_excerpt;
    }

    if (trim($text) === '') {
        $text = wp_strip_all_tags(strip_shortcodes($post->post_content));
    }

    return wp_trim_words($text, $num, '…');
}
