<?php

/**
 * Theme filters.
 */

namespace App;

/**
 * Add "… Continued" to the excerpt.
 *
 * @return string
 */
add_filter('excerpt_more', function () {
    return sprintf(' &hellip; <a href="%s">%s</a>', get_permalink(), __('Continued', 'sage'));
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
