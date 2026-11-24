<?php

declare(strict_types=1);

namespace App\View\Composers;

use Roots\Acorn\View\Composer;

class App extends Composer
{
    /**
     * Elenco delle view servite da questo composer.
     *
     * @var array<string>
     */
    protected static $views = [
        '*',
    ];

    /**
     * Restituisce il nome del sito come testo semplice, di cui la view fa l'escape ({{ }}).
     *
     * L'opzione blogname è salvata con l'escape HTML e i filtri 'display'
     * aggiungono entity (wptexturize): vengono decodificate, così Blade non ne
     * fa l'escape una seconda volta.
     */
    public function siteName(): string
    {
        return html_entity_decode(get_bloginfo('name', 'display'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Restituisce il markup del custom logo (link alla home con l'immagine),
     * o una stringa vuota se non c'è un logo: in quel caso l'header mostra il
     * nome del sito.
     *
     * Il markup arriva da get_custom_logo(), con l'escape già fatto da WordPress;
     * l'alt dell'immagine ripiega sul nome del sito se l'allegato non ne ha uno.
     */
    public function customLogo(): string
    {
        if (! has_custom_logo()) {
            return '';
        }

        return get_custom_logo();
    }

    /**
     * Indica se la pagina corrente è la prima pagina della front page: in quel
     * caso l'header marca il link alla home con aria-current="page", come fa
     * get_custom_logo() per il link del logo.
     */
    public function isFrontPage(): bool
    {
        return is_front_page() && ! is_paged();
    }
}
