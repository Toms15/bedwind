<?php

declare(strict_types=1);

namespace App\View\Composers;

use Roots\Acorn\View\Composer;

class Comments extends Composer
{
    /**
     * Elenco delle view servite da questo composer.
     *
     * @var array<string>
     */
    protected static $views = [
        'partials.comments',
    ];

    /**
     * Il titolo dei commenti.
     */
    public function title(): string
    {
        return sprintf(
            /* translators: 1: numero di commenti ("Una" se 1), 2: titolo del post. */
            _nx('%1$s risposta a &ldquo;%2$s&rdquo;', '%1$s risposte a &ldquo;%2$s&rdquo;', get_comments_number(), 'comments title', 'sage'),
            (int) get_comments_number() === 1 ? _x('Una', 'comments title', 'sage') : number_format_i18n(get_comments_number()),
            get_the_title()
        );
    }

    /**
     * Restituisce i commenti.
     */
    public function responses(): ?string
    {
        if (! have_comments()) {
            return null;
        }

        return wp_list_comments([
            'style' => 'ol',
            'short_ping' => true,
            'echo' => false,
        ]);
    }

    /**
     * Il link ai commenti precedenti.
     */
    public function previous(): ?string
    {
        if (! get_previous_comments_link()) {
            return null;
        }

        return get_previous_comments_link(
            __('&larr; Commenti meno recenti', 'sage')
        );
    }

    /**
     * Il link ai commenti successivi.
     */
    public function next(): ?string
    {
        if (! get_next_comments_link()) {
            return null;
        }

        return get_next_comments_link(
            __('Commenti più recenti &rarr;', 'sage')
        );
    }

    /**
     * Indica se i commenti sono paginati.
     */
    public function paginated(): bool
    {
        return get_comment_pages_count() > 1 && get_option('page_comments');
    }

    /**
     * Indica se i commenti sono chiusi.
     */
    public function closed(): bool
    {
        return ! comments_open() && get_comments_number() != '0' && post_type_supports(get_post_type(), 'comments');
    }
}
