<?php

add_filter('get_archives_link', function ($link_html) {
    return preg_replace(
        '~'.home_url().'/(\d{4})/~',
        home_url('/news/archivio/$1/'),
        $link_html
    );
});

add_action('init', function () {
    add_rewrite_rule(
        '^news/archivio/([0-9]{4})/?$',
        'index.php?year=$matches[1]',
        'top'
    );
});

add_filter('get_the_archive_title', function ($title) {
    if (is_year()) {
        $title = __('Archivio', 'sage').' '.get_query_var('year');
    }

    return $title;
});
