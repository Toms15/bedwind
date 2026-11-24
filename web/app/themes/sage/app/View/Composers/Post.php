<?php

declare(strict_types=1);

namespace App\View\Composers;

use Roots\Acorn\View\Composer;

class Post extends Composer
{
    /**
     * Elenco delle view servite da questo composer.
     *
     * @var array<string>
     */
    protected static $views = [
        'partials.page-header',
        'partials.content',
        'partials.content-*',
    ];

    /**
     * Restituisce il titolo del post.
     */
    public function title(): string
    {
        if ($this->view->name() !== 'partials.page-header') {
            return get_the_title();
        }

        if (is_home()) {
            if ($home = get_option('page_for_posts', true)) {
                return get_the_title($home);
            }

            return __('Ultimi articoli', 'sage');
        }

        if (is_archive()) {
            return get_the_archive_title();
        }

        if (is_search()) {
            return sprintf(
                /* translators: %s: termini di ricerca. */
                __('Risultati della ricerca per %s', 'sage'),
                get_search_query()
            );
        }

        if (is_404()) {
            return __('Pagina non trovata', 'sage');
        }

        return get_the_title();
    }

    /**
     * Restituisce i link della paginazione.
     */
    public function pagination(): string
    {
        return wp_link_pages([
            'echo' => 0,
            'before' => '<p>'.__('Pagine:', 'sage'),
            'after' => '</p>',
        ]);
    }
}
