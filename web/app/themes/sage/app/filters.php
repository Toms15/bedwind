<?php

declare(strict_types=1);

/**
 * Theme filters.
 */

namespace App;

/**
 * Append "… Continua a leggere" to automatic excerpts (the_excerpt()).
 *
 * The post title is added for screen readers only, so every link has a
 * unique, descriptive name. The excerpt length is set by excerpt_length().
 *
 * @return string
 */
add_filter('excerpt_more', function () {
    return sprintf(
        ' &hellip; <a href="%s" class="more-link">%s<span class="sr-only">: %s</span></a>',
        esc_url(get_permalink()),
        esc_html__('Continua a leggere', 'sage'),
        esc_html(wp_strip_all_tags(get_the_title()))
    );
});

/**
 * Set the length of automatic excerpts (the_excerpt()) to 20 words.
 *
 * Priority 999 so it wins over plugins setting their own length.
 * The "Continua a leggere" link is added by the excerpt_more filter above.
 *
 * @param  mixed  $length  The length set so far (ignored).
 */
function excerpt_length(mixed $length): int
{
    return 20;
}
add_filter('excerpt_length', __NAMESPACE__.'\\excerpt_length', 999);

/**
 * Automatically set title and alt text of uploaded images from their file name.
 *
 * Works for every upload channel (media library, REST/Gutenberg, WP-CLI,
 * media_sideload_image) because the name comes from the attached file.
 * The title is replaced only when it is WordPress' file-name default, the
 * alt only when empty. Caption and description are left untouched.
 */
function add_image_meta_data(int $attachment_id): void
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
add_action('add_attachment', __NAMESPACE__.'\\add_image_meta_data');

/**
 * Performance optimizations: disable the WordPress emoji assets.
 *
 * Removes the emoji detection script and inline styles from the frontend
 * <head>, and the static emoji images from feeds and emails.
 *
 * The admin emoji assets are handled by disable_admin_emoji() and
 * disable_tinymce_emoji(): the admin hooks are registered after
 * "init", so they cannot be removed from here.
 *
 * wp-embed is intentionally NOT touched. Since WP 5.9 core enqueues it only
 * when the rendered content contains a WordPress post embed
 * (wp_maybe_enqueue_oembed_host_js() on "embed_oembed_html"), so there is
 * nothing to save on pages without embeds. Deregistering it broke the
 * auto-resize of post embeds and the TinyMCE preview in the admin, and
 * unhooking wp_oembed_add_host_js would only add that breakage back.
 */
function disable_emoji(): void
{
    // Frontend emoji script and styles.
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');

    // Static emoji images in feeds and emails.
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', __NAMESPACE__.'\\disable_emoji');

/**
 * Disable the WordPress emoji assets in the admin.
 *
 * Core registers the admin emoji hooks in wp-admin/includes/admin-filters.php
 * (print_emoji_detection_script on "admin_print_scripts", print_emoji_styles
 * on "admin_print_styles", both at the default priority 10). That file is
 * loaded after "init", so the removal runs on "admin_init" instead.
 *
 * Removing print_emoji_styles is enough for the CSS as well:
 * wp_enqueue_emoji_styles() (on "admin_enqueue_scripts") bails out when
 * print_emoji_styles is no longer hooked to "admin_print_styles".
 * The block editor already unhooks the detection script itself
 * (wp-admin/edit-form-blocks.php).
 */
function disable_admin_emoji(): void
{
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
}
add_action('admin_init', __NAMESPACE__.'\\disable_admin_emoji');

/**
 * Remove the "wpemoji" TinyMCE plugin.
 *
 * Applies to the classic editor and to the Classic block of the block
 * editor, which both pass their plugin list through "tiny_mce_plugins".
 *
 * @param  array<int, string>  $plugins  TinyMCE plugin names.
 * @return array<int, string>
 */
function disable_tinymce_emoji(array $plugins): array
{
    return array_values(array_diff($plugins, ['wpemoji']));
}
add_filter('tiny_mce_plugins', __NAMESPACE__.'\\disable_tinymce_emoji');

/**
 * Adapt the custom logo image to the header (get_custom_logo()).
 *
 * - sizes: the header caps the logo at 80px of height (max-h-20, the
 *   'height' of the custom-logo support in setup.php) with an automatic
 *   width, so its rendered width is 80px times the image ratio (240px for
 *   the recommended 240×80). Core would use the full image width
 *   (e.g. "(max-width: 480px) 100vw, 480px") and let the browser download
 *   a larger variant than needed.
 * - fetchpriority: "auto" (browser default) instead of the "high" core
 *   gives to the first image over 50000px² it meets. The logo is small and
 *   rarely the LCP element; images explicitly marked "high" (x-ui.image
 *   with eager) keep the priority anyway. "auto" rather than removing the
 *   attribute: without it core would add "high" again
 *   (wp_maybe_add_fetchpriority_high_attr()). A value already set by
 *   another filter is left untouched.
 *
 * @param  array<string, mixed>  $attr  Image attributes.
 * @param  int|string  $attachment_id  Logo attachment ID (theme mod).
 * @return array<string, mixed>
 */
function custom_logo_image_attributes(array $attr, int|string $attachment_id): array
{
    $max_height = 80;
    $image = wp_get_attachment_image_src((int) $attachment_id, 'full');

    if ($image && empty($attr['sizes']) && $image[1] > 0 && $image[2] > 0) {
        $width = (int) min($image[1], ceil($max_height * $image[1] / $image[2]));
        $attr['sizes'] = sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $width);
    }

    $attr['fetchpriority'] ??= 'auto';

    return $attr;
}
add_filter('get_custom_logo_image_attributes', __NAMESPACE__.'\\custom_logo_image_attributes', 10, 2);
