<?php

declare(strict_types=1);

namespace App\Providers;

use App\PostTypes\Faq;
use App\Styleguide;
use Roots\Acorn\Sage\SageServiceProvider;

class ThemeServiceProvider extends SageServiceProvider
{
    /**
     * Custom post type registrati dal tema.
     *
     * Ogni classe espone un metodo statico register() che chiama
     * register_post_type(); gira su "init".
     *
     * @var array<int, class-string>
     */
    protected array $postTypes = [
        Faq::class,
    ];

    /**
     * Tassonomie personalizzate registrate dal tema.
     *
     * Stesso contratto di $postTypes (register() statico che chiama
     * register_taxonomy()). Le tassonomie sono registrate prima dei post
     * type, alla stessa priorità di "init", così un post type può farvi riferimento.
     *
     * @var array<int, class-string>
     */
    protected array $taxonomies = [];

    /**
     * Registra i servizi dell'applicazione.
     *
     * @return void
     */
    public function register()
    {
        parent::register();
    }

    /**
     * Avvia i servizi dell'applicazione.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        foreach ([...$this->taxonomies, ...$this->postTypes] as $class) {
            add_action('init', [$class, 'register']);
        }

        // /styleguide/ solo in development; altrove rimuove soltanto una
        // regola di rewrite rimasta (vedi App\Styleguide).
        Styleguide::register();
    }
}
