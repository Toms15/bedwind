<?php

/*
|--------------------------------------------------------------------------
| Stub delle classi di WordPress
|--------------------------------------------------------------------------
|
| Fake minimi delle classi di WordPress usate dal codice testato, perché
| nei test unitari WordPress non viene caricato.
|
*/

if (! class_exists('WP_Post')) {
    final class WP_Post
    {
        public int $ID = 0;

        public string $post_excerpt = '';

        public string $post_content = '';

        public string $post_type = 'post';
    }
}

if (! class_exists('WP_Block_Editor_Context')) {
    final class WP_Block_Editor_Context
    {
        public string $name = 'core/edit-post';

        public ?WP_Post $post = null;

        /** @param array{name?: string, post?: WP_Post} $settings */
        public function __construct(array $settings = [])
        {
            if (isset($settings['name'])) {
                $this->name = $settings['name'];
            }
            if (isset($settings['post'])) {
                $this->post = $settings['post'];
            }
        }
    }
}

if (! class_exists('WP_Block_Type_Registry')) {
    final class WP_Block_Type_Registry
    {
        /** @var array<string, object> */
        public array $registered = [];

        private static ?self $instance = null;

        public static function get_instance(): self
        {
            return self::$instance ??= new self;
        }

        /** @return array<string, object> */
        public function get_all_registered(): array
        {
            return $this->registered;
        }
    }
}

if (! class_exists('WP_Query')) {
    class WP_Query
    {
        public bool $is_home = true;

        public bool $is_404 = false;

        /** @param array<string, mixed> $vars */
        public function __construct(public array $vars = [], public bool $main = true) {}

        public function get(string $var): mixed
        {
            return $this->vars[$var] ?? '';
        }

        public function is_main_query(): bool
        {
            return $this->main;
        }
    }
}
