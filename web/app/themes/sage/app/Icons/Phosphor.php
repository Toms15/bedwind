<?php

declare(strict_types=1);

namespace App\Icons;

/**
 * Icone Phosphor (https://phosphoricons.com, pacchetto npm @phosphor-icons/core).
 *
 * Usata dal componente <x-ui.icon> (resources/views/components/ui/icon.blade.php)
 * per stampare l'SVG inline, e dal wizard dei nuovi blocchi (M47) per elencare,
 * validare e scegliere l'icona del block.json.
 *
 * Da dove arrivano gli SVG:
 *  - resources/icons/phosphor.php: manifest generato da resources/scripts/icons.js
 *    (npm run icons, e Vite in build e in dev) con le sole icone usate nelle view
 *    o elencate in resources/icons/allowlist.json. È versionato e caricato con
 *    include (OPcache lo tiene in memoria): è l'unica fonte negli ambienti diversi
 *    da development, dove node_modules non viene deployato.
 *  - node_modules/@phosphor-icons/core/assets: il pacchetto completo, usato solo in
 *    development (WP_ENV === 'development') per le icone che mancano dal manifest,
 *    con una segnalazione: in produzione quelle icone non verrebbero stampate.
 *
 * Le icone del pacchetto sono fidate (asset versionati, non contenuto caricato
 * dagli utenti): non passano dal sanitizer degli upload SVG di app/svg.php, che
 * riguarda solo i file caricati nella libreria media. Per lo stesso motivo nessun
 * filtro di app/svg.php (upload_mimes, prefiltri di caricamento, wp_upload_bits,
 * metadati degli allegati) le tocca. Il nome viene comunque validato contro
 * NAME_PATTERN prima di comporre qualsiasi percorso, così un nome arbitrario non
 * può uscire dalla cartella degli asset.
 *
 * Solo name e weight vengono validati: gli altri attributi (class, id, data-*,
 * anche on*) arrivano all'<svg> così come sono, solo escapati, come in ogni
 * componente Blade. Non passare mai a <x-ui.icon> attributi o chiavi presi da
 * input utente o da campi ACF.
 */
class Phosphor
{
    /** Pesi di Phosphor. */
    public const WEIGHTS = ['thin', 'light', 'regular', 'bold', 'fill', 'duotone'];

    public const DEFAULT_WEIGHT = 'regular';

    /** Slug ammessi: minuscole, cifre e trattini. Uguale a NAME_PATTERN di resources/scripts/icons.js. */
    public const NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** viewBox comune a tutte le icone Phosphor. */
    public const VIEWBOX = '0 0 256 256';

    /** Attributi gestiti dal componente: quelli passati dall'esterno vengono ignorati. */
    private const RESERVED = ['xmlns', 'viewbox', 'fill', 'role', 'aria-hidden', 'aria-label', 'focusable', 'data-icon'];

    private static ?string $manifestPath = null;

    private static ?string $packagePath = null;

    private static ?string $environment = null;

    /** @var array<string, array<string, string>>|null */
    private static ?array $manifest = null;

    /**
     * Contenuto delle icone lette dal pacchetto in questa richiesta ("peso/nome" => svg o null).
     *
     * @var array<string, string|null>
     */
    private static array $packageCache = [];

    /** @var list<string>|null */
    private static ?array $names = null;

    /**
     * Sostituisce manifest, cartella del pacchetto e ambiente (null: i default) e svuota le cache.
     * Serve ai test; il tema usa sempre i default.
     */
    public static function configure(?string $manifest = null, ?string $package = null, ?string $environment = null): void
    {
        self::$manifestPath = $manifest;
        self::$packagePath = $package;
        self::$environment = $environment;
        self::$manifest = null;
        self::$packageCache = [];
        self::$names = null;
    }

    public static function isValidName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    public static function isValidWeight(string $weight): bool
    {
        return in_array($weight, self::WEIGHTS, true);
    }

    /**
     * Indica se l'icona esiste nel set installato (il pacchetto, oppure il manifest dove il pacchetto non c'è).
     */
    public static function exists(string $name, string $weight = self::DEFAULT_WEIGHT): bool
    {
        if (! self::isValidName($name) || ! self::isValidWeight($weight)) {
            return false;
        }

        return is_dir(self::packagePath()) ? is_file(self::packageFile($name, $weight)) : isset(self::manifest()[$weight][$name]);
    }

    /**
     * Indica se l'icona si può stampare in questo ambiente (manifest, più il pacchetto in development).
     */
    public static function available(string $name, string $weight = self::DEFAULT_WEIGHT): bool
    {
        return self::inner($name, $weight) !== null;
    }

    /**
     * Contenuto dell'elemento <svg> dell'icona (path, opacity del duotone), o null se non disponibile.
     */
    public static function inner(string $name, string $weight = self::DEFAULT_WEIGHT): ?string
    {
        if (! self::isValidName($name) || ! self::isValidWeight($weight)) {
            return null;
        }

        return self::manifest()[$weight][$name] ?? (self::isDevelopment() ? self::fromPackage($name, $weight) : null);
    }

    /**
     * Indica se l'icona arriva dal manifest (quindi è disponibile anche in produzione).
     */
    public static function inManifest(string $name, string $weight = self::DEFAULT_WEIGHT): bool
    {
        return isset(self::manifest()[$weight][$name]);
    }

    /**
     * SVG completo e senza attributi aggiuntivi, come nel pacchetto, o null se
     * non disponibile. Utile per l'icona di un block.json (M47).
     */
    public static function source(string $name, string $weight = self::DEFAULT_WEIGHT): ?string
    {
        $inner = self::inner($name, $weight);

        return $inner === null ? null : sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s" fill="currentColor">%s</svg>', self::VIEWBOX, $inner);
    }

    /**
     * SVG inline dell'icona con gli attributi del componente, o stringa vuota se non disponibile.
     *
     * Senza label l'icona è decorativa (aria-hidden="true", focusable="false");
     * con label diventa role="img" con aria-label e <title>. Dimensione di default
     * 1em (width/height come attributi di presentazione: qualsiasi classe, es.
     * size-6, li sostituisce), colore currentColor. L'attributo data-icon aggancia
     * la regola di base di app.css: inline-block allineato al testo, così
     * un'icona senza classi resta sulla riga (il preflight di Tailwind rende gli
     * svg display: block).
     *
     * @param  array<string, mixed>  $attributes  Attributi extra (class, id, data-*, ...).
     */
    public static function svg(string $name, string $weight = self::DEFAULT_WEIGHT, array $attributes = [], ?string $label = null): string
    {
        $inner = self::inner($name, $weight);

        if ($inner === null) {
            return '';
        }

        $label = trim((string) $label);

        $defaults = [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'viewBox' => self::VIEWBOX,
            'width' => '1em',
            'height' => '1em',
            'fill' => 'currentColor',
            // Aggancio per la regola di base in resources/css/app.css (inline-block, allineata al testo).
            'data-icon' => true,
        ];

        $a11y = $label === ''
            ? ['aria-hidden' => 'true', 'focusable' => 'false']
            : ['role' => 'img', 'aria-label' => $label, 'focusable' => 'false'];

        $extra = array_filter(
            $attributes,
            fn (string $key): bool => ! in_array(strtolower($key), self::RESERVED, true),
            ARRAY_FILTER_USE_KEY
        );

        $title = $label === '' ? '' : '<title>'.esc_html($label).'</title>';

        return '<svg'.self::attributes([...$defaults, ...$extra, ...$a11y]).'>'.$title.$inner.'</svg>';
    }

    /**
     * Output del componente <x-ui.icon>.
     *
     * Con nome o peso non validi, o icona non disponibile: in development una
     * segnalazione (vedi report()) e un commento HTML; negli altri ambienti
     * nessun output e nessun errore. In development un'icona assente dal manifest
     * viene stampata dal pacchetto, con una segnalazione perché in produzione
     * mancherebbe.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function render(string $name, string $weight = self::DEFAULT_WEIGHT, array $attributes = [], ?string $label = null): string
    {
        $svg = self::svg($name, $weight, $attributes, $label);

        if (! self::isDevelopment()) {
            return $svg;
        }

        if ($svg !== '' && self::inManifest($name, $weight)) {
            return $svg;
        }

        if ($svg !== '') {
            /* translators: 1: nome dell'icona, 2: peso. */
            $message = sprintf(__('L\'icona "%1$s" (%2$s) non è nel manifest resources/icons/phosphor.php e in produzione non verrà mostrata: usa un name statico in x-ui.icon oppure aggiungila a resources/icons/allowlist.json, poi esegui npm run icons.', 'sage'), $name, $weight);
        } elseif (! self::isValidWeight($weight)) {
            /* translators: 1: peso indicato, 2: elenco dei pesi validi. */
            $message = sprintf(__('Peso dell\'icona "%1$s" non valido: usa %2$s.', 'sage'), $weight, implode(', ', self::WEIGHTS));
        } else {
            /* translators: 1: nome dell'icona, 2: peso. */
            $message = sprintf(__('Icona Phosphor "%1$s" (%2$s) inesistente.', 'sage'), $name, $weight);
        }

        self::report($message);

        // "--" chiuderebbe il commento HTML.
        return $svg.'<!-- x-ui.icon: '.esc_html(str_replace('--', '- -', $message)).' -->';
    }

    /**
     * Segnala un problema di un'icona in development senza interrompere la pagina.
     *
     * Niente trigger_error/wp_trigger_error: il gestore degli errori di Acorn
     * (HandleExceptions, attivo con WP_DEBUG) trasforma ogni E_USER_WARNING in
     * ErrorException e mostrerebbe la pagina d'errore al posto dell'icona. Il
     * messaggio va invece in error_log (logs/debug.log in development) e nel
     * pannello di Query Monitor, se attivo (azione qm/warning); il commento HTML
     * lo aggiunge render().
     */
    private static function report(string $message): void
    {
        error_log('x-ui.icon: '.$message);

        do_action('qm/warning', 'x-ui.icon: '.$message);
    }

    /**
     * Slug di tutte le icone del set installato, in ordine alfabetico (dal
     * pacchetto; dal manifest dove il pacchetto non c'è).
     *
     * @return list<string>
     */
    public static function names(): array
    {
        if (self::$names !== null) {
            return self::$names;
        }

        $dir = self::packagePath().'/'.self::DEFAULT_WEIGHT;
        $files = is_dir($dir) ? glob($dir.'/*.svg') : false;

        if ($files) {
            $names = array_map(fn (string $file): string => basename($file, '.svg'), $files);
        } else {
            // Niente array_merge: rinumererebbe le chiavi intere (uno slug solo numerico).
            $names = [];

            foreach (self::manifest() as $icons) {
                foreach (array_keys($icons) as $name) {
                    $names[] = (string) $name;
                }
            }
        }

        $names = array_values(array_unique(array_filter($names, self::isValidName(...))));
        sort($names);

        return self::$names = $names;
    }

    /**
     * Slug di un'icona a caso del set installato, o null se il set è vuoto.
     *
     * @param  list<string>  $exclude  Slug da escludere (es. icone già usate da altri blocchi).
     */
    public static function random(array $exclude = []): ?string
    {
        $names = array_values(array_diff(self::names(), $exclude));

        return $names ? $names[array_rand($names)] : null;
    }

    /**
     * Attributi HTML escapati: true stampa il solo nome, false e null vengono
     * omessi, i nomi non validi in HTML sono scartati.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $key => $value) {
            if ($value === false || $value === null || ! preg_match('/^[^\s"\'>\/=]+$/', $key)) {
                continue;
            }

            if ($value === true) {
                $html .= ' '.$key;
            } elseif (is_scalar($value) || $value instanceof \Stringable) {
                $html .= sprintf(' %s="%s"', $key, esc_attr((string) $value));
            }
        }

        return $html;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function manifest(): array
    {
        if (self::$manifest === null) {
            $path = self::$manifestPath ?? self::themePath('resources/icons/phosphor.php');
            $data = is_file($path) ? include $path : [];
            self::$manifest = is_array($data) ? $data : [];
        }

        return self::$manifest;
    }

    private static function fromPackage(string $name, string $weight): ?string
    {
        $key = $weight.'/'.$name;

        if (! array_key_exists($key, self::$packageCache)) {
            $file = self::packageFile($name, $weight);
            $svg = is_file($file) ? file_get_contents($file) : false;

            self::$packageCache[$key] = $svg !== false && preg_match('#^\s*<svg\b[^>]*>(.*)</svg>\s*$#s', $svg, $match)
                ? $match[1]
                : null;
        }

        return self::$packageCache[$key];
    }

    /**
     * File dell'icona nel pacchetto: assets/<peso>/<nome>[-<peso>].svg (regular senza suffisso).
     * Chiamato solo con nome e peso già validati.
     */
    private static function packageFile(string $name, string $weight): string
    {
        $suffix = $weight === self::DEFAULT_WEIGHT ? '' : '-'.$weight;

        return self::packagePath()."/{$weight}/{$name}{$suffix}.svg";
    }

    private static function packagePath(): string
    {
        return self::$packagePath ?? self::themePath('node_modules/@phosphor-icons/core/assets');
    }

    private static function isDevelopment(): bool
    {
        $env = self::$environment ?? (defined('WP_ENV') ? (string) constant('WP_ENV') : '');

        return $env === 'development';
    }

    private static function themePath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }
}
