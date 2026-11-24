<?php

declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\Vite;
use WP_Query;

/**
 * Styleguide del tema su /styleguide/, solo in development.
 *
 * Con WP_ENV === 'development' il tema registra una regola di rewrite e una
 * query var per /styleguide/ e renderizza resources/views/styleguide.blade.php
 * (layout del tema, noindex, nessuna pagina nel database, nessuna main query).
 * In ogni altro ambiente non registra nulla: /styleguide/ è un normale 404.
 *
 * Le regole di rewrite sono salvate nel database, quindi non si fa il flush a
 * ogni richiesta: su "init" le regole salvate vengono confrontate con
 * l'ambiente e, se non corrispondono (regola assente in development, ancora
 * presente altrove, es. dopo una copia del database), si fa il flush una volta.
 * Funziona anche `wp rewrite flush`.
 *
 * Le utility usate solo dalla view sono compilate in una entry dedicata
 * (resources/css/styleguide.css, esclusa da app.css ed editor.css), caricata
 * solo sulla styleguide, così non finiscono mai nei fogli di stile di produzione.
 */
final class Styleguide
{
    /** Percorso della styleguide, senza slash. */
    public const PATH = 'styleguide';

    /** Query var impostata dalla regola di rewrite. */
    public const QUERY_VAR = 'sage_styleguide';

    /** Regex della regola di rewrite, anche chiave della regola fra quelle salvate. */
    public const RULE = '^styleguide/?$';

    /** Ambiente ('on'/'off') dell'ultimo flush fatto dal tema. */
    public const FLUSH_OPTION = 'sage_styleguide_rewrite';

    /** Entry Vite con le utility della view, caricata solo qui. */
    public const STYLE = 'resources/css/styleguide.css';

    /** Handle del foglio di stile di STYLE. */
    public const HANDLE = 'sage-styleguide';

    /** Contrasto minimo WCAG 2.2: [AA, AAA] per testo normale e grande. */
    public const WCAG = [
        'normal' => ['AA' => 4.5, 'AAA' => 7.0],
        'large' => ['AA' => 3.0, 'AAA' => 4.5],
    ];

    /**
     * Indica se la styleguide è disponibile: solo con WP_ENV === 'development'.
     *
     * @param  string|null  $env  Ambiente da verificare, default: WP_ENV.
     */
    public static function enabled(?string $env = null): bool
    {
        $env ??= defined('WP_ENV') ? (string) constant('WP_ENV') : '';

        return $env === 'development';
    }

    /**
     * Aggancia la styleguide agli hook (chiamato da ThemeServiceProvider::boot()).
     *
     * La sincronizzazione delle rewrite gira in ogni ambiente, per rimuovere una
     * regola lasciata nel database da development; tutto il resto solo in development.
     *
     * @param  string|null  $env  Ambiente, default: WP_ENV.
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
     * Carica il foglio di stile della view, solo sulla styleguide. Saltato se
     * la entry manca dal manifest di Vite (build non ancora fatta), come per gli
     * asset dei blocchi: la pagina funziona comunque con il solo app.css.
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

        // Versione null: il file di build ha l'hash nel nome, il dev server non ne ha bisogno.
        wp_enqueue_style(self::HANDLE, $url, [], null);
    }

    public static function addRewriteRule(): void
    {
        add_rewrite_rule(self::RULE, 'index.php?'.self::QUERY_VAR.'=1', 'top');
    }

    /**
     * Fa il flush delle regole di rewrite se quelle salvate non corrispondono a $enabled.
     *
     * Non succede nulla se le regole non sono salvate (permalink semplici, o non
     * ancora generate: WordPress le crea al bisogno con la regola corrente).
     * FLUSH_OPTION ricorda lo stato dell'ultimo flush: se la regola è ancora
     * sbagliata dopo un flush per lo stesso stato (es. un plugin la filtra via),
     * il tema non ripete il flush a ogni richiesta.
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
            // Allineate (es. dopo `wp rewrite flush`): dimentica uno stato vecchio,
            // così un successivo disallineamento per quello stato rifà il flush.
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
     * Indica se la richiesta corrente (o la query passata) è la styleguide.
     */
    public static function isRequest(?WP_Query $query = null): bool
    {
        $query ??= $GLOBALS['wp_query'] ?? null;

        return $query instanceof WP_Query
            && $query->is_main_query()
            && (string) $query->get(self::QUERY_VAR) === '1';
    }

    /**
     * Non è la home del blog: WP_Query segna come is_home una richiesta che
     * ha solo una query var personalizzata.
     */
    public static function parseQuery(WP_Query $query): void
    {
        if (self::isRequest($query)) {
            $query->is_home = false;
            $query->is_404 = false;
        }
    }

    /**
     * Salta la query dei post della main query: la styleguide non mostra post.
     *
     * @param  array<int, mixed>|null  $posts
     * @return array<int, mixed>|null
     */
    public static function skipMainQuery(?array $posts, WP_Query $query): ?array
    {
        return self::isRequest($query) ? [] : $posts;
    }

    /**
     * L'assenza di post non deve trasformare la styleguide in un 404.
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

        // Acorn (template_include, priorità 100) renderizza la view Blade.
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
     * Design token dal theme.json generato dalla build
     * (public/build/assets/theme.json, dal @theme di theme.css).
     *
     * @param  string|null  $path  Percorso del theme.json, default: quello della build.
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
     * Rapporto di contrasto WCAG 2.2 (1–21) fra due colori, null se uno dei
     * due non è interpretabile (vedi luminance()).
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
     * Livelli WCAG raggiunti da un rapporto di contrasto, per testo normale e
     * grande (grande: almeno 24px, o 18.66px in grassetto): 'AAA', 'AA' o null.
     * Il rapporto si confronta senza arrotondarlo, come richiede WCAG.
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
     * Contrasto di un colore con il bianco e il nero, con i livelli WCAG raggiunti.
     * Vuoto se il colore non è interpretabile.
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
     * Luminanza relativa WCAG (0–1) di un colore opaco, o null.
     *
     * Accettati: #rgb, #rrggbb e oklch(L C H) come li scrive la build nel
     * theme.json (L come numero o percentuale, C come numero o percentuale di 0.4,
     * tinta in gradi, "none" come 0, alpha "/ 1" opzionale). oklch viene convertito
     * in sRGB lineare e limitato al gamut sRGB, vicino a ciò che fanno i browser
     * per i colori della palette. Altri formati e colori trasparenti
     * restituiscono null.
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
     * @return array{float, float, float}|null Canali sRGB lineari.
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
     * @return array{float, float, float}|null Canali sRGB lineari (non limitati).
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

        // OKLab -> LMS (al cubo) -> sRGB lineare (Björn Ottosson).
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
