<?php

declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\Vite;
use WP_Query;

/**
 * Styleguide of the theme at /styleguide/, only in development.
 *
 * With WP_ENV === 'development' the theme registers a rewrite rule and a
 * query var for /styleguide/ and renders resources/views/styleguide.blade.php
 * (theme layout, noindex, no database page, no main query). In every other
 * environment nothing is registered: /styleguide/ is a regular 404.
 *
 * Rewrite rules are stored in the database, so they are not flushed on every
 * request: on "init" the stored rules are compared with the environment and
 * flushed once when they disagree (rule missing in development, still there
 * elsewhere, e.g. after copying the database). `wp rewrite flush` also works.
 *
 * The utilities used only by the view are compiled in a dedicated entry
 * (resources/css/styleguide.css, excluded from app.css and editor.css), enqueued
 * only on the styleguide, so they never reach the production stylesheets.
 */
final class Styleguide
{
    /** Path of the styleguide, without slashes. */
    public const PATH = 'styleguide';

    /** Query var set by the rewrite rule. */
    public const QUERY_VAR = 'sage_styleguide';

    /** Rewrite rule regex, also the key of the rule in the stored rules. */
    public const RULE = '^styleguide/?$';

    /** Environment ('on'/'off') of the last flush done by the theme. */
    public const FLUSH_OPTION = 'sage_styleguide_rewrite';

    /** Vite entry with the utilities of the view, enqueued only here. */
    public const STYLE = 'resources/css/styleguide.css';

    /** Stylesheet handle of STYLE. */
    public const HANDLE = 'sage-styleguide';

    /** WCAG 2.2 minimum contrast: [AA, AAA] for normal and large text. */
    public const WCAG = [
        'normal' => ['AA' => 4.5, 'AAA' => 7.0],
        'large' => ['AA' => 3.0, 'AAA' => 4.5],
    ];

    /**
     * Whether the styleguide is available: only with WP_ENV === 'development'.
     *
     * @param  string|null  $env  Environment to check, default: WP_ENV.
     */
    public static function enabled(?string $env = null): bool
    {
        $env ??= defined('WP_ENV') ? (string) constant('WP_ENV') : '';

        return $env === 'development';
    }

    /**
     * Hook the styleguide (called by ThemeServiceProvider::boot()).
     *
     * The rewrite sync runs in every environment, to remove a rule left in
     * the database by development; everything else only in development.
     *
     * @param  string|null  $env  Environment, default: WP_ENV.
     */
    public static function register(?string $env = null): void
    {
        $enabled = self::enabled($env);

        add_action('init', fn () => self::syncRewriteRules($enabled), 99);

        if (! $enabled) {
            return;
        }

        add_action('init', [self::class, 'addRewriteRule']);
        add_filter('query_vars', [self::class, 'queryVars']);
        add_action('parse_query', [self::class, 'parseQuery']);
        add_filter('posts_pre_query', [self::class, 'skipMainQuery'], 10, 2);
        add_filter('pre_handle_404', [self::class, 'skip404'], 10, 2);
        add_filter('template_include', [self::class, 'template'], 99);
        add_filter('wp_robots', [self::class, 'robots']);
        add_filter('document_title_parts', [self::class, 'documentTitle']);
        add_filter('body_class', [self::class, 'bodyClass']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    /**
     * Enqueue the stylesheet of the view, only on the styleguide. Skipped when
     * the entry is missing from the Vite manifest (not built yet), like the
     * block assets: the page still works with app.css only.
     */
    public static function enqueueAssets(): void
    {
        if (! self::isRequest()) {
            return;
        }

        try {
            $url = Vite::asset(self::STYLE);
        } catch (\Throwable) {
            return;
        }

        // null version: the build file is hashed, the dev server needs none.
        wp_enqueue_style(self::HANDLE, $url, [], null);
    }

    public static function addRewriteRule(): void
    {
        add_rewrite_rule(self::RULE, 'index.php?'.self::QUERY_VAR.'=1', 'top');
    }

    /**
     * Flush the rewrite rules when the stored ones disagree with $enabled.
     *
     * Nothing happens when the rules are not stored (plain permalinks, or not
     * generated yet: WordPress builds them on demand with the current rule).
     * FLUSH_OPTION remembers the state of the last flush: if the rule is still
     * wrong after flushing for the same state (e.g. a plugin filters it out),
     * the theme does not flush again on every request.
     */
    public static function syncRewriteRules(bool $enabled): void
    {
        $rules = get_option('rewrite_rules');

        if (! is_array($rules) || $rules === []) {
            return;
        }

        $state = $enabled ? 'on' : 'off';
        $flushed = get_option(self::FLUSH_OPTION);

        if (array_key_exists(self::RULE, $rules) === $enabled) {
            // In sync (e.g. after `wp rewrite flush`): forget an old state,
            // so a later mismatch for it is flushed again.
            if ($flushed !== false && $flushed !== $state) {
                delete_option(self::FLUSH_OPTION);
            }

            return;
        }

        if ($flushed === $state) {
            return;
        }

        update_option(self::FLUSH_OPTION, $state);
        flush_rewrite_rules(false);
    }

    /**
     * @param  array<int, string>  $vars
     * @return array<int, string>
     */
    public static function queryVars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    /**
     * Whether the current request (or the given query) is the styleguide.
     */
    public static function isRequest(?WP_Query $query = null): bool
    {
        $query ??= $GLOBALS['wp_query'] ?? null;

        return $query instanceof WP_Query
            && $query->is_main_query()
            && (string) $query->get(self::QUERY_VAR) === '1';
    }

    /**
     * Not the blog home: WP_Query flags a request with only a custom query
     * var as is_home.
     */
    public static function parseQuery(WP_Query $query): void
    {
        if (self::isRequest($query)) {
            $query->is_home = false;
            $query->is_404 = false;
        }
    }

    /**
     * Skip the posts query of the main query: the styleguide shows no posts.
     *
     * @param  array<int, mixed>|null  $posts
     * @return array<int, mixed>|null
     */
    public static function skipMainQuery(?array $posts, WP_Query $query): ?array
    {
        return self::isRequest($query) ? [] : $posts;
    }

    /**
     * No posts must not turn the styleguide into a 404.
     */
    public static function skip404(bool $preempt, WP_Query $query): bool
    {
        return self::isRequest($query) ? true : $preempt;
    }

    public static function template(string $template): string
    {
        if (! self::isRequest()) {
            return $template;
        }

        // Acorn (template_include, priority 100) renders the Blade view.
        return get_theme_file_path('resources/views/styleguide.blade.php');
    }

    /**
     * @param  array<string, bool|string>  $robots
     * @return array<string, bool|string>
     */
    public static function robots(array $robots): array
    {
        if (self::isRequest()) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            unset($robots['follow']);
        }

        return $robots;
    }

    /**
     * @param  array<string, string>  $parts
     * @return array<string, string>
     */
    public static function documentTitle(array $parts): array
    {
        if (self::isRequest()) {
            $parts['title'] = __('Styleguide', 'sage');
        }

        return $parts;
    }

    /**
     * @param  array<int, string>  $classes
     * @return array<int, string>
     */
    public static function bodyClass(array $classes): array
    {
        if (self::isRequest()) {
            $classes[] = 'styleguide';
        }

        return $classes;
    }

    /**
     * Design tokens from the theme.json generated by the build
     * (public/build/assets/theme.json, from the @theme of theme.css).
     *
     * @param  string|null  $path  theme.json path, default: the build one.
     * @return array{colors: list<array{name: string, slug: string, color: string}>, fonts: list<array{name: string, slug: string, family: string}>, sizes: list<array{name: string, slug: string, size: string}>}
     */
    public static function tokens(?string $path = null): array
    {
        $path ??= get_theme_file_path('public/build/assets/theme.json');
        $json = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        $settings = is_array($json) && is_array($json['settings'] ?? null) ? $json['settings'] : [];

        $list = function (mixed $items, string $key, string $as): array {
            $out = [];

            foreach (is_array($items) ? $items : [] as $item) {
                if (! is_array($item) || ! is_string($item['slug'] ?? null) || ! is_string($item[$key] ?? null)) {
                    continue;
                }

                $out[] = [
                    'name' => is_string($item['name'] ?? null) ? $item['name'] : $item['slug'],
                    'slug' => $item['slug'],
                    $as => $item[$key],
                ];
            }

            return $out;
        };

        /** @var array{colors: list<array{name: string, slug: string, color: string}>, fonts: list<array{name: string, slug: string, family: string}>, sizes: list<array{name: string, slug: string, size: string}>} */
        return [
            'colors' => $list($settings['color']['palette'] ?? null, 'color', 'color'),
            'fonts' => $list($settings['typography']['fontFamilies'] ?? null, 'fontFamily', 'family'),
            'sizes' => $list($settings['typography']['fontSizes'] ?? null, 'size', 'size'),
        ];
    }

    /**
     * WCAG 2.2 contrast ratio (1–21) between two colors, null when either
     * cannot be parsed (see luminance()).
     */
    public static function contrast(string $color, string $against): ?float
    {
        $a = self::luminance($color);
        $b = self::luminance($against);

        if ($a === null || $b === null) {
            return null;
        }

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * WCAG levels met by a contrast ratio, for normal and large text
     * (large: at least 24px, or 18.66px bold): 'AAA', 'AA' or null.
     * The ratio is compared unrounded, as WCAG requires.
     *
     * @return array{normal: 'AAA'|'AA'|null, large: 'AAA'|'AA'|null}
     */
    public static function wcag(float $ratio): array
    {
        $level = function (array $min) use ($ratio): ?string {
            return match (true) {
                $ratio >= $min['AAA'] => 'AAA',
                $ratio >= $min['AA'] => 'AA',
                default => null,
            };
        };

        /** @var array{normal: 'AAA'|'AA'|null, large: 'AAA'|'AA'|null} */
        return [
            'normal' => $level(self::WCAG['normal']),
            'large' => $level(self::WCAG['large']),
        ];
    }

    /**
     * Contrast of a color with white and black, with the WCAG levels met.
     * Empty when the color cannot be parsed.
     *
     * @return array<'white'|'black', array{ratio: float, normal: 'AAA'|'AA'|null, large: 'AAA'|'AA'|null}>
     */
    public static function contrasts(string $color): array
    {
        $out = [];

        foreach (['white' => '#fff', 'black' => '#000'] as $name => $against) {
            $ratio = self::contrast($color, $against);

            if ($ratio !== null) {
                $out[$name] = ['ratio' => $ratio] + self::wcag($ratio);
            }
        }

        return $out;
    }

    /**
     * WCAG relative luminance (0–1) of an opaque color, or null.
     *
     * Accepted: #rgb, #rrggbb and oklch(L C H) as written in theme.json by
     * the build (L as number or percentage, C as number or percentage of 0.4,
     * hue in degrees, "none" as 0, optional "/ 1" alpha). oklch is converted
     * to linear sRGB and clipped to the sRGB gamut, close to what browsers do
     * for the colors of the palette. Other formats and transparent colors
     * return null.
     */
    public static function luminance(string $color): ?float
    {
        $rgb = self::linearHex($color) ?? self::linearOklch($color);

        if ($rgb === null) {
            return null;
        }

        [$r, $g, $b] = array_map(fn (float $c): float => min(1.0, max(0.0, $c)), $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * @return array{float, float, float}|null Linear sRGB channels.
     */
    private static function linearHex(string $color): ?array
    {
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($color), $m) !== 1) {
            return null;
        }

        $hex = strlen($m[1]) === 3
            ? $m[1][0].$m[1][0].$m[1][1].$m[1][1].$m[1][2].$m[1][2]
            : $m[1];

        $linear = function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return [$linear(substr($hex, 0, 2)), $linear(substr($hex, 2, 2)), $linear(substr($hex, 4, 2))];
    }

    /**
     * @return array{float, float, float}|null Linear sRGB channels (not clipped).
     */
    private static function linearOklch(string $color): ?array
    {
        $number = '(none|\d*\.?\d+)(%?)';
        $pattern = "/^oklch\(\s*{$number}\s+{$number}\s+(none|\d*\.?\d+)(?:deg)?\s*(?:\/\s*(\d*\.?\d+)(%?)\s*)?\)$/i";

        if (preg_match($pattern, trim($color), $m) !== 1) {
            return null;
        }

        $value = fn (string $v): float => strtolower($v) === 'none' ? 0.0 : (float) $v;

        if (isset($m[6]) && $m[6] !== '' && $value($m[6]) / ($m[7] === '%' ? 100 : 1) < 1) {
            return null;
        }

        $l = $value($m[1]) / ($m[2] === '%' ? 100 : 1);
        $c = $m[4] === '%' ? $value($m[3]) / 100 * 0.4 : $value($m[3]);
        $h = deg2rad($value($m[5]));

        $a = $c * cos($h);
        $b = $c * sin($h);

        // OKLab -> LMS (cubed) -> linear sRGB (Björn Ottosson).
        $l_ = ($l + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m_ = ($l - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s_ = ($l - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            4.0767416621 * $l_ - 3.3077115913 * $m_ + 0.2309699292 * $s_,
            -1.2684380046 * $l_ + 2.6097574011 * $m_ - 0.3413193965 * $s_,
            -0.0041960863 * $l_ - 0.7034186147 * $m_ + 1.7076147010 * $s_,
        ];
    }
}
