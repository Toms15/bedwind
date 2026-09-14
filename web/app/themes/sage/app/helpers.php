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

    // Disable wp-embed
    wp_deregister_script('wp-embed');
}
add_action('init', 'wordpack_performance_optimizations');

/**
 * Retrieves and prepares the common data for a block.
 *
 * Groups block data into settings, contents, and spacings.
 * Padding and margin values are converted into their corresponding
 * custom classes using the get_custom_padding() and get_custom_margin()
 * helper functions.
 *
 * @param array $block The ACF block data.
 *
 * @return array{
 *     settings: array{
 *         id: string,
 *         border_top: mixed,
 *         border_color: mixed,
 *         background_color: mixed,
 *         text_color: mixed
 *     },
 *     contents: array{
 *         title: mixed,
 *         title_formatting: mixed,
 *         content: mixed,
 *         buttons: mixed
 *     },
 *     spacings: array{
 *         padding: mixed,
 *         margin: mixed
 *     }
 * } The prepared block data grouped by type.
 */
function get_block_data(array $block): array
{
    $padding = get_field('padding');
    $padding_dimension = get_field('padding_dimension');

    $margin = get_field('margin');
    $margin_dimension = get_field('margin_dimension');

    $custom_padding = $padding !== 'no'
        ? 'custom-' . $padding . '-' . $padding_dimension
        : 'no-padding';

    $custom_margin = $margin !== 'no'
        ? 'custom-' . $margin . '-' . $margin_dimension
        : 'no-margin';

    return [
        'settings' => [
            'id' => $block['anchor'] ?? '',
            'border_top' => get_field('border_top'),
            'border_color' => get_field('border_color'),
            'background_color' => get_field('background_color'),
            'text_color' => get_field('text_color'),
        ],

        'contents' => [
            'title' => get_field('title'),
            'title_formatting' => get_field('title_formatting'),
            'content' => get_field('content'),
            'buttons' => get_field('buttons'),
        ],

        'spacings' => [
            'padding' => get_custom_padding($custom_padding),
            'margin' => get_custom_margin($custom_margin),
        ],
    ];
}

/**
 * Helper function per gestire il padding custom
 */
function get_custom_padding($custom_padding)
{
    return match ($custom_padding) {
        'no-padding' => '',
        'custom-py-xl' => 'py-9 lg:py-16 xl:py-20',
        'custom-py-l' => 'py-7 lg:py-12 xl:py-16',
        'custom-py-m' => 'py-5 lg:py-8 xl:py-10',
        'custom-py-s' => 'py-3 lg:py-6',
        'custom-pt-xl' => 'pt-9 lg:pt-16 xl:pt-20',
        'custom-pt-l' => 'pt-7 lg:pt-12 xl:pt-16',
        'custom-pt-m' => 'pt-5 lg:pt-8 xl:pt-10',
        'custom-pt-s' => 'pt-3 lg:pt-6',
        'custom-pb-xl' => 'pb-9 lg:pb-16 xl:pb-20',
        'custom-pb-l' => 'pb-7 lg:pb-12 xl:pb-16',
        'custom-pb-m' => 'pb-5 lg:pb-8 xl:pb-10',
        'custom-pb-s' => 'pb-3 lg:pb-6',
        default => ''
    };
}

/**
 * Helper function per gestire il margin custom
 */
function get_custom_margin($custom_margin)
{
    return match ($custom_margin) {
        'no-margin' => '',
        'custom-my-xl' => 'my-9 lg:my-16 xl:my-20',
        'custom-my-l' => 'my-7 lg:my-12 xl:my-16',
        'custom-my-m' => 'my-5 lg:my-8 xl:my-10',
        'custom-my-s' => 'my-3 lg:my-6',
        'custom-mt-xl' => 'mt-9 lg:mt-16 xl:mt-20',
        'custom-mt-l' => 'mt-7 lg:mt-12 xl:mt-16',
        'custom-mt-m' => 'mt-5 lg:mt-8 xl:mt-10',
        'custom-mt-s' => 'mt-3 lg:mt-6',
        'custom-mb-xl' => 'mb-9 lg:mb-16 xl:mb-20',
        'custom-mb-l' => 'mb-7 lg:mb-12 xl:mb-16',
        'custom-mb-m' => 'mb-5 lg:mb-8 xl:mb-10',
        'custom-mb-s' => 'mb-3 lg:mb-6',
        default => ''
    };
}
