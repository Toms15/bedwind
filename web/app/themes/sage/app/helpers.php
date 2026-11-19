<?php

/**
 * Theme helpers.
 */

/**
 * Automatically set title and alt text of uploaded images from their file name.
 *
 * Works for every upload channel (media library, REST/Gutenberg, WP-CLI,
 * media_sideload_image) because the name comes from the attached file.
 * The title is replaced only when it is WordPress' file-name default, the
 * alt only when empty. Caption and description are left untouched.
 */
add_action('add_attachment', 'wordpack_add_image_meta_data');
function wordpack_add_image_meta_data(int $attachment_id): void
{
    if (! wp_attachment_is_image($attachment_id)) {
        return;
    }

    $file = get_attached_file($attachment_id);

    if (! $file) {
        return;
    }

    $name = pathinfo($file, PATHINFO_FILENAME);
    // Drop WordPress generated suffixes (-scaled, -150x150, -150x150-1).
    $name = preg_replace('/(-scaled|-\d+x\d+)+(-\d+)?$/i', '', $name) ?? $name;
    $name = str_replace(['-', '_'], ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;
    $name = sanitize_text_field(trim($name));

    if ($name === '') {
        return;
    }

    // Only replace the title WordPress derives from the file name. A title set
    // explicitly (REST "title", --title, post_data, IPTC/EXIF) is kept as is.
    // Inside the hook the original upload name is gone, so compare a
    // normalized key of the title with the stored file name, which may be
    // sanitized (accents, spaces, dots) and carry a "-N" uniqueness suffix.
    $key = static function (string $value): string {
        $value = mb_strtolower(remove_accents(wp_specialchars_decode($value, ENT_QUOTES)));

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '', '-');
    };

    $stored = pathinfo($file, PATHINFO_FILENAME);
    $title_key = $key((string) get_post_field('post_title', $attachment_id, 'raw'));
    $defaults = array_unique([
        $key($stored),
        $key(preg_replace('/-\d+$/', '', $stored) ?? $stored),
        $key(preg_replace('/(-scaled|-\d+x\d+)+(-\d+)?$/i', '', $stored) ?? $stored),
    ]);

    if ($title_key === '' || in_array($title_key, $defaults, true)) {
        wp_update_post([
            'ID' => $attachment_id,
            'post_title' => $name,
        ]);
    }

    if (get_post_meta($attachment_id, '_wp_attachment_image_alt', true) === '') {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $name);
    }
}

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
 * Get fallback image
 */
function get_fallback_image_url($width = 1920, $height = 1080): string
{
    return "https://placehold.co/{$width}x{$height}/webp";
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
function wordpack_excerpt(int $num, int|\WP_Post|null $post = null): string
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

/**
 * Set the length of automatic excerpts (the_excerpt()) to 20 words.
 *
 * Priority 999 so it wins over plugins setting their own length.
 * The "Continue reading" link is added by the excerpt_more filter in
 * app/filters.php.
 */
function wordpack_excerpt_length($length)
{
    return 20;
}
add_filter('excerpt_length', 'wordpack_excerpt_length', 999);

/**
 * Make theme available for translation
 * Translations can be placed in the /resources/lang directory
 */
add_action('after_setup_theme', function () {
    load_theme_textdomain('sage', get_template_directory().'/resources/lang');
});

/**
 * Performance optimizations: disable the WordPress emoji assets.
 *
 * Removes the emoji detection script and inline styles from the frontend
 * <head>, and the static emoji images from feeds and emails.
 *
 * Admin: the core admin emoji hooks (admin_print_scripts/admin_print_styles)
 * are registered by wp-admin/includes/admin-filters.php, which is loaded
 * after "init", so removing them here would be a no-op. They are left alone
 * on purpose: the admin keeps the core emoji support.
 *
 * wp-embed is intentionally NOT touched. Since WP 5.9 core enqueues it only
 * when the rendered content contains a WordPress post embed
 * (wp_maybe_enqueue_oembed_host_js() on "embed_oembed_html"), so there is
 * nothing to save on pages without embeds. Deregistering it broke the
 * auto-resize of post embeds and the TinyMCE preview in the admin, and
 * unhooking wp_oembed_add_host_js would only add that breakage back.
 */
function wordpack_performance_optimizations(): void
{
    // Frontend emoji script and styles.
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');

    // Static emoji images in feeds and emails.
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
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
 * settings.id is the block anchor when set, otherwise the ACF block id
 * (e.g. "block_abc123"), so every block section always has an id.
 * Colors are validated with sanitize_hex_color(): invalid values become ''.
 *
 * Heading convention for every block:
 * - contents.title_tag is the tag of the block title (ACF title_formatting,
 *   whitelisted to h1..h6/p, default 'h2'): <x-title :tag="$contents['title_tag']">.
 * - contents.next_heading is the tag for the block's child headings
 *   (subtitles, card titles, ...), from get_next_heading_level(title_tag).
 *
 * @param  array  $block  The ACF block data.
 * @return array{
 *     settings: array{
 *         id: string,
 *         background_color: string,
 *         text_color: string
 *     },
 *     contents: array{
 *         title: mixed,
 *         title_formatting: mixed,
 *         title_tag: 'h1'|'h2'|'h3'|'h4'|'h5'|'h6'|'p',
 *         next_heading: 'h2'|'h3'|'h4'|'h5'|'h6'|'p',
 *         content: mixed,
 *         buttons: list<array{button: array{url?: string, title?: string, target?: string}|mixed}>
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
        ? 'custom-'.$padding.'-'.$padding_dimension
        : 'no-padding';

    $custom_margin = $margin !== 'no'
        ? 'custom-'.$margin.'-'.$margin_dimension
        : 'no-margin';

    // Repeater: false/null when there are no rows.
    $buttons = get_field('buttons');
    $buttons = is_array($buttons) ? array_values($buttons) : [];

    $title_formatting = get_field('title_formatting');
    $title_tag = in_array($title_formatting, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'], true)
        ? $title_formatting
        : 'h2';

    return [
        'settings' => [
            'id' => ! empty($block['anchor']) ? $block['anchor'] : ($block['id'] ?? ''),
            'background_color' => sanitize_hex_color((string) get_field('background_color')) ?: '',
            'text_color' => sanitize_hex_color((string) get_field('text_color')) ?: '',
        ],

        'contents' => [
            'title' => get_field('title'),
            'title_formatting' => $title_formatting,
            'title_tag' => $title_tag,
            'next_heading' => get_next_heading_level($title_tag),
            'content' => get_field('content'),
            'buttons' => $buttons,
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
