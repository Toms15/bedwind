<?php

function custom_breadcrumbs(): void
{
    $item_classes = 'mt-px underline-offset-2 inline-flex items-center justify-center text-small text-black-950 font-body font-normal hover:!underline hover:text-primary-500 focus:text-primary-500 focus:!underline';
    $separator = '<div class="mx-2 text-small text-black-700 font-body font-normal">//</div>';

    $link = function (string $label, string $url, string $classes = ''): void {
        echo sprintf(
            '<a class="%s" href="%s">%s</a>',
            esc_attr($classes),
            esc_url($url),
            esc_html($label)
        );
    };

    $current = function (string $label, string $classes = ''): void {
        echo sprintf(
            '<span class="%s">%s</span>',
            esc_attr($classes),
            esc_html($label)
        );
    };

    // Home
    $link(
        __('Home', 'sage'),
        home_url(),
        $item_classes
    );

    /*
     * Single post
     */
    if (is_singular('post')) {
        $terms = get_the_category();

        if (!empty($terms) && !is_wp_error($terms)) {
            $parents = [];
            $children = [];

            foreach ($terms as $term) {
                if ($term->parent) {
                    $children[$term->term_id] = $term;

                    $parent = get_term($term->parent, 'category');

                    if ($parent && !is_wp_error($parent)) {
                        $parents[$parent->term_id] = $parent;
                    }
                } else {
                    $parents[$term->term_id] = $term;
                }
            }

            foreach ($parents as $parent) {
                echo $separator;

                $link(
                    $parent->name,
                    get_category_link($parent->term_id),
                    $item_classes
                );
            }

            foreach ($children as $child) {
                echo $separator;

                $link(
                    $child->name,
                    get_category_link($child->term_id),
                    $item_classes
                );
            }
        }

        echo $separator;

        $current(
            get_the_title(),
            'text-small text-black-950 font-body font-normal'
        );

        return;
    }

    /*
     * Single event
     */
    if (is_singular('event')) {
        echo $separator;

        $current(
            __('Event', 'sage'),
            'text-small text-black-950 font-body font-normal'
        );

        echo $separator;

        $current(
            get_the_title(),
            'text-small text-black-950 font-body font-normal'
        );

        return;
    }

    /*
     * Single person
     */
    if (is_singular('team')) {
        echo $separator;

        $current(
            __('Team', 'sage'),
            'text-small text-black-950 font-body font-normal'
        );

        echo $separator;

        $current(
            get_the_title(),
            'text-small text-black-950 font-body font-normal'
        );

        return;
    }

    /*
     * Single project
     */
    if (is_singular('project')) {
        echo $separator;

        $current(
            __('Projects', 'sage'),
            'text-small text-black-950 font-body font-normal'
        );

        echo $separator;

        $current(
            get_the_title(),
            'text-small text-black-950 font-body font-normal'
        );

        return;
    }

    /*
     * Category
     */
    if (is_category()) {
        echo $separator;

        $link(
            __('News', 'sage'),
            home_url('/news'),
            '!underline underline-offset-2 text-inherit font-bold inline-flex items-center justify-start truncate rounded focus:px-1 hover:underline-offset-4 focus:!outline-2 outline-white'
        );

        echo $separator;

        $current(
            __('Categoria', 'sage'),
            'font-normal text-inherit h-6 inline-flex items-center justify-start'
        );

        echo $separator;

        $current(
            single_cat_title('', false),
            'font-normal text-inherit h-6 inline-flex items-center justify-start max-w-[300px] md:max-w-full truncate'
        );

        return;
    }

    /*
     * Page
     */
    if (is_page()) {
        $ancestors = get_post_ancestors(get_the_ID());

        if (!empty($ancestors)) {
            foreach (array_reverse($ancestors) as $ancestor_id) {
                echo $separator;

                $link(
                    get_the_title($ancestor_id),
                    get_permalink($ancestor_id),
                    'mt-px underline-offset-2 inline-flex items-center justify-center text-small text-black font-body font-bold hover:!underline focus:text-primary-500 focus:!underline'
                );
            }
        }

        echo $separator;

        $current(
            get_the_title(),
            'mt-px font-bold text-small text-primary-500 font-body inline-flex items-center justify-start max-w-[300px] md:max-w-full truncate'
        );
    }
}
