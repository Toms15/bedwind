<?php

declare(strict_types=1);

namespace App\Providers;

use App\PostTypes\Faq;
use Roots\Acorn\Sage\SageServiceProvider;

class ThemeServiceProvider extends SageServiceProvider
{
    /**
     * Custom post types registered by the theme.
     *
     * Each class exposes a static register() method that calls
     * register_post_type(); it runs on "init".
     *
     * @var array<int, class-string>
     */
    protected array $postTypes = [
        Faq::class,
    ];

    /**
     * Custom taxonomies registered by the theme.
     *
     * Same contract as $postTypes (static register() calling
     * register_taxonomy()). Taxonomies are registered before the post
     * types, at the same "init" priority, so a post type can reference them.
     *
     * @var array<int, class-string>
     */
    protected array $taxonomies = [];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        parent::register();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        foreach ([...$this->taxonomies, ...$this->postTypes] as $class) {
            add_action('init', [$class, 'register']);
        }
    }
}
