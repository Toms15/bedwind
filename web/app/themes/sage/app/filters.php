<?php

/**
 * Theme filters.
 */

namespace App;

/**
 * Append "… Continua a leggere" to automatic excerpts (the_excerpt()).
 *
 * The post title is added for screen readers only, so every link has a
 * unique, descriptive name. The excerpt length is set in app/helpers.php.
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
 * Remove core's default link underline from global styles.
 *
 * Core theme.json sets styles.elements.link.typography.textDecoration,
 * printed as `a:where(:not(.wp-element-button)){text-decoration:underline}`
 * outside any cascade layer, so it beats Tailwind utilities like
 * `no-underline`. The underline is restored in `@layer base` (app.css and
 * editor.css), where utilities can override it.
 *
 * update_with() merges and cannot remove keys, so a new object is returned.
 *
 * @param  \WP_Theme_JSON_Data  $theme_json
 * @return \WP_Theme_JSON_Data
 */
add_filter('wp_theme_json_data_default', function ($theme_json) {
    $data = $theme_json->get_data();

    if (! isset($data['styles']['elements']['link']['typography']['textDecoration'])) {
        return $theme_json;
    }

    unset($data['styles']['elements']['link']['typography']['textDecoration']);

    if (empty($data['styles']['elements']['link']['typography'])) {
        unset($data['styles']['elements']['link']['typography']);
    }

    return new \WP_Theme_JSON_Data($data, 'default');
});
