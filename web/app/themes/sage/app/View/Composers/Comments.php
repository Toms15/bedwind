<?php

declare(strict_types=1);

namespace App\View\Composers;

use Roots\Acorn\View\Composer;

class Comments extends Composer
{
    /**
     * List of views served by this composer.
     *
     * @var array<string>
     */
    protected static $views = [
        'partials.comments',
    ];

    /**
     * The comment title.
     */
    public function title(): string
    {
        return sprintf(
            /* translators: 1: number of comments ("Una" when 1), 2: post title. */
            _nx('%1$s risposta a &ldquo;%2$s&rdquo;', '%1$s risposte a &ldquo;%2$s&rdquo;', get_comments_number(), 'comments title', 'sage'),
            (int) get_comments_number() === 1 ? _x('Una', 'comments title', 'sage') : number_format_i18n(get_comments_number()),
            get_the_title()
        );
    }

    /**
     * Retrieve the comments.
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
     * The previous comments link.
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
     * The next comments link.
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
     * Determine if the comments are paginated.
     */
    public function paginated(): bool
    {
        return get_comment_pages_count() > 1 && get_option('page_comments');
    }

    /**
     * Determine if the comments are closed.
     */
    public function closed(): bool
    {
        return ! comments_open() && get_comments_number() != '0' && post_type_supports(get_post_type(), 'comments');
    }
}
