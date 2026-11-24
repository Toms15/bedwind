<?php

declare(strict_types=1);

namespace App\View\Composers;

use Roots\Acorn\View\Composer;

class App extends Composer
{
    /**
     * List of views served by this composer.
     *
     * @var array<string>
     */
    protected static $views = [
        '*',
    ];

    /**
     * Retrieve the site name as plain text, to be escaped by the view ({{ }}).
     *
     * The blogname option is stored HTML-escaped and the 'display' filters
     * add entities (wptexturize): decode them, so Blade does not escape
     * them a second time.
     */
    public function siteName(): string
    {
        return html_entity_decode(get_bloginfo('name', 'display'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Retrieve the custom logo markup (link to the home page with the image),
     * or an empty string when no logo is set: the header then shows the site
     * name.
     *
     * The markup comes from get_custom_logo(), already escaped by WordPress;
     * the image alt falls back to the site name when the attachment has none.
     */
    public function customLogo(): string
    {
        if (! has_custom_logo()) {
            return '';
        }

        return get_custom_logo();
    }

    /**
     * Whether the current page is the first page of the front page: the
     * header then marks the home link with aria-current="page", as
     * get_custom_logo() does for the logo link.
     */
    public function isFrontPage(): bool
    {
        return is_front_page() && ! is_paged();
    }
}
