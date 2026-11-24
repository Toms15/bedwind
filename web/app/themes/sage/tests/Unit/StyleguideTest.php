<?php

use App\Styleguide;
use Brain\Monkey\Functions;
use Illuminate\Support\Facades\Vite;

afterEach(function () {
    unset($GLOBALS['wp_query']);
});

/**
 * Regole di rewrite salvate e FLUSH_OPTION, con il flush registrato.
 *
 * @param  array<string, string>|string  $rules
 * @return ArrayObject<string, mixed>
 */
function fake_rewrite_state(array|string $rules, string|false $flushed = false): ArrayObject
{
    $state = new ArrayObject(['flushes' => 0, 'option' => $flushed]);

    Functions\when('get_option')->alias(fn (string $name) => match ($name) {
        'rewrite_rules' => $rules,
        Styleguide::FLUSH_OPTION => $state['option'],
    });
    Functions\when('update_option')->alias(function (string $name, string $value) use ($state) {
        $state['option'] = $value;

        return true;
    });
    Functions\when('delete_option')->alias(function () use ($state) {
        $state['option'] = false;

        return true;
    });
    Functions\when('flush_rewrite_rules')->alias(function () use ($state) {
        $state['flushes']++;
    });

    return $state;
}

describe('enabled()', function () {
    test('restituisce true solo in development', function (string $env, bool $expected) {
        expect(Styleguide::enabled($env))->toBe($expected);
    })->with([
        ['development', true],
        ['staging', false],
        ['production', false],
        ['', false],
        ['Development', false],
    ]);
});

describe('register()', function () {
    test('aggancia solo la sincronizzazione delle rewrite fuori da development', function (string $env) {
        Styleguide::register($env);

        expect(has_action('init'))->toBeTrue()
            ->and(has_action('init', [Styleguide::class, 'addRewriteRule']))->toBeFalse()
            ->and(has_filter('query_vars', [Styleguide::class, 'queryVars']))->toBeFalse()
            ->and(has_filter('template_include', [Styleguide::class, 'template']))->toBeFalse()
            ->and(has_filter('wp_robots', [Styleguide::class, 'robots']))->toBeFalse();
    })->with(['staging', 'production', '']);

    test('aggancia la route in development', function () {
        Styleguide::register('development');

        expect(has_action('init', [Styleguide::class, 'addRewriteRule']))->not->toBeFalse()
            ->and(has_filter('query_vars', [Styleguide::class, 'queryVars']))->not->toBeFalse()
            ->and(has_action('parse_query', [Styleguide::class, 'parseQuery']))->not->toBeFalse()
            ->and(has_filter('posts_pre_query', [Styleguide::class, 'skipMainQuery']))->not->toBeFalse()
            ->and(has_filter('pre_handle_404', [Styleguide::class, 'skip404']))->not->toBeFalse()
            ->and(has_filter('template_include', [Styleguide::class, 'template']))->toBe(99)
            ->and(has_filter('wp_robots', [Styleguide::class, 'robots']))->not->toBeFalse();
    });
});

describe('syncRewriteRules()', function () {
    test('fa il flush una volta quando in development la regola manca', function () {
        $state = fake_rewrite_state(['^foo/?$' => 'index.php?foo=1']);

        Styleguide::syncRewriteRules(true);
        Styleguide::syncRewriteRules(true);

        expect($state['flushes'])->toBe(1)->and($state['option'])->toBe('on');
    });

    test('fa il flush una volta quando fuori da development la regola è rimasta', function () {
        $state = fake_rewrite_state([Styleguide::RULE => 'index.php?sage_styleguide=1'], 'on');

        Styleguide::syncRewriteRules(false);

        expect($state['flushes'])->toBe(1)->and($state['option'])->toBe('off');
    });

    test('non fa nulla quando le regole corrispondono all\'ambiente', function (bool $enabled, array $rules) {
        $state = fake_rewrite_state($rules);

        Styleguide::syncRewriteRules($enabled);

        expect($state['flushes'])->toBe(0)->and($state['option'])->toBeFalse();
    })->with([
        'development' => [true, [Styleguide::RULE => 'index.php?sage_styleguide=1']],
        'production' => [false, ['^foo/?$' => 'index.php?foo=1']],
    ]);

    test('non fa nulla senza regole salvate (permalink semplici)', function () {
        $state = fake_rewrite_state('');

        Styleguide::syncRewriteRules(true);

        expect($state['flushes'])->toBe(0);
    });

    test('dimentica uno stato di flush vecchio quando le regole sono allineate', function () {
        $state = fake_rewrite_state(['^foo/?$' => 'index.php?foo=1'], 'on');

        Styleguide::syncRewriteRules(false);

        expect($state['flushes'])->toBe(0)->and($state['option'])->toBeFalse();
    });
});

describe('richiesta', function () {
    test('riconosce solo la main query con la query var', function () {
        expect(Styleguide::isRequest(new WP_Query([Styleguide::QUERY_VAR => '1'])))->toBeTrue()
            ->and(Styleguide::isRequest(new WP_Query([Styleguide::QUERY_VAR => '1'], false)))->toBeFalse()
            ->and(Styleguide::isRequest(new WP_Query))->toBeFalse()
            ->and(Styleguide::isRequest())->toBeFalse();
    });

    test('non è la home del blog, non esegue la query dei post e non è un 404', function () {
        $query = new WP_Query([Styleguide::QUERY_VAR => '1']);
        Styleguide::parseQuery($query);

        expect($query->is_home)->toBeFalse()
            ->and(Styleguide::skipMainQuery(null, $query))->toBe([])
            ->and(Styleguide::skip404(false, $query))->toBeTrue()
            ->and(Styleguide::skipMainQuery(null, new WP_Query))->toBeNull()
            ->and(Styleguide::skip404(false, new WP_Query))->toBeFalse();
    });

    test('renderizza la view con noindex, nofollow', function () {
        $GLOBALS['wp_query'] = new WP_Query([Styleguide::QUERY_VAR => '1']);
        Functions\when('get_theme_file_path')->alias(fn (string $path) => "/theme/{$path}");
        Functions\when('__')->returnArg();

        expect(Styleguide::template('/theme/index.php'))->toBe('/theme/resources/views/styleguide.blade.php')
            ->and(Styleguide::robots(['follow' => true, 'max-image-preview' => 'large']))
            ->toBe(['max-image-preview' => 'large', 'noindex' => true, 'nofollow' => true])
            ->and(Styleguide::documentTitle(['title' => 'Home']))->toBe(['title' => 'Styleguide'])
            ->and(Styleguide::bodyClass(['home']))->toBe(['home', 'styleguide']);
    });

    test('lascia invariate le altre richieste', function () {
        $GLOBALS['wp_query'] = new WP_Query;

        expect(Styleguide::template('/theme/index.php'))->toBe('/theme/index.php')
            ->and(Styleguide::robots(['follow' => true]))->toBe(['follow' => true])
            ->and(Styleguide::bodyClass(['home']))->toBe(['home']);
    });
});

describe('tokens()', function () {
    test('legge palette, famiglie di font e dimensioni del testo dal theme.json', function () {
        $path = tempnam(sys_get_temp_dir(), 'sage-theme-json');
        file_put_contents($path, json_encode(['settings' => [
            'color' => ['palette' => [
                ['name' => 'Black', 'slug' => 'black', 'color' => '#000'],
                ['slug' => 'white', 'color' => '#fff'],
                ['name' => 'Invalid'],
            ]],
            'typography' => [
                'fontFamilies' => [['name' => 'body', 'slug' => 'body', 'fontFamily' => 'system-ui']],
                'fontSizes' => [['name' => 'small', 'slug' => 'small', 'size' => '.875rem']],
            ],
        ]]));

        expect(Styleguide::tokens($path))->toBe([
            'colors' => [
                ['name' => 'Black', 'slug' => 'black', 'color' => '#000'],
                ['name' => 'white', 'slug' => 'white', 'color' => '#fff'],
            ],
            'fonts' => [['name' => 'body', 'slug' => 'body', 'family' => 'system-ui']],
            'sizes' => [['name' => 'small', 'slug' => 'small', 'size' => '.875rem']],
        ]);

        unlink($path);
    });

    test('restituisce liste vuote senza una build', function () {
        expect(Styleguide::tokens('/missing/theme.json'))
            ->toBe(['colors' => [], 'fonts' => [], 'sizes' => []]);
    });
});

describe('enqueueAssets()', function () {
    afterEach(function () {
        Vite::clearResolvedInstances();
    });

    /**
     * Facade Vite: asset() restituisce "build/<path>", o lancia un'eccezione se $missing.
     */
    function fake_styleguide_vite(bool $missing = false): void
    {
        Vite::swap(new class($missing)
        {
            public function __construct(private bool $missing) {}

            public function asset(string $path): string
            {
                if ($this->missing) {
                    throw new RuntimeException("Unable to locate file in Vite manifest: {$path}.");
                }

                return "build/{$path}";
            }
        });
    }

    test('carica il foglio di stile della view solo sulla styleguide', function () {
        fake_styleguide_vite();
        Functions\expect('wp_enqueue_style')->once()
            ->with(Styleguide::HANDLE, 'build/'.Styleguide::STYLE, [], null);

        $GLOBALS['wp_query'] = new WP_Query([Styleguide::QUERY_VAR => '1']);
        Styleguide::enqueueAssets();

        $GLOBALS['wp_query'] = new WP_Query;
        Styleguide::enqueueAssets();
    });

    test('salta il foglio di stile assente dal manifest di Vite', function () {
        fake_styleguide_vite(missing: true);
        Functions\expect('wp_enqueue_style')->never();

        $GLOBALS['wp_query'] = new WP_Query([Styleguide::QUERY_VAR => '1']);
        Styleguide::enqueueAssets();
    });

    test('è agganciato solo in development', function () {
        Styleguide::register('development');
        expect(has_action('wp_enqueue_scripts', [Styleguide::class, 'enqueueAssets']))->not->toBeFalse();
    });
});

describe('contrasto', function () {
    test('calcola la luminanza WCAG dei colori hex e oklch', function (string $color, float $expected) {
        expect(Styleguide::luminance($color))->toEqualWithDelta($expected, 0.0005);
    })->with([
        ['#000', 0.0],
        ['#fff', 1.0],
        ['#FFFFFF', 1.0],
        ['#808080', 0.2159],
        ['#3b82f6', 0.2355],
        ['oklch(0% 0 0)', 0.0],
        ['oklch(100% 0 0)', 1.0],
        ['oklch(1 0 0)', 1.0],
        ['oklch(37% 0 0)', 0.0507],
        ['oklch(62.3% .214 259.815)', 0.2295],
        ['oklch(62.8% 0.2577 29.23 / 1)', 0.2126],
        ['oklch(50% none none)', 0.125],
    ]);

    test('restituisce null per i formati che non sa leggere o per i colori trasparenti', function (string $color) {
        expect(Styleguide::luminance($color))->toBeNull()
            ->and(Styleguide::contrast($color, '#fff'))->toBeNull()
            ->and(Styleguide::contrasts($color))->toBe([]);
    })->with(['', 'red', '#12', '#12345', 'rgb(0 0 0)', 'oklch(50% 0 0 / .5)', 'oklch(50% 0 0 / 50%)', 'var(--color-black)']);

    test('calcola il rapporto di contrasto in entrambe le direzioni', function () {
        expect(Styleguide::contrast('#000', '#fff'))->toEqualWithDelta(21.0, 0.0001)
            ->and(Styleguide::contrast('#fff', '#000'))->toEqualWithDelta(21.0, 0.0001)
            ->and(Styleguide::contrast('#fff', '#fff'))->toEqualWithDelta(1.0, 0.0001)
            ->and(Styleguide::contrast('#767676', '#fff'))->toEqualWithDelta(4.54, 0.01)
            ->and(Styleguide::contrast('oklch(14.5% 0 0)', '#fff'))->toEqualWithDelta(19.79, 0.01);
    });

    test('converte il rapporto nei livelli WCAG per testo normale e grande', function (float $ratio, ?string $normal, ?string $large) {
        expect(Styleguide::wcag($ratio))->toBe(['normal' => $normal, 'large' => $large]);
    })->with([
        [21.0, 'AAA', 'AAA'],
        [7.0, 'AAA', 'AAA'],
        [6.99, 'AA', 'AAA'],
        [4.5, 'AA', 'AAA'],
        [4.49, null, 'AA'],
        [3.0, null, 'AA'],
        [2.99, null, null],
        [1.0, null, null],
    ]);

    test('restituisce il contrasto con il bianco e il nero', function () {
        $contrasts = Styleguide::contrasts('#767676');

        expect(array_keys($contrasts))->toBe(['white', 'black'])
            ->and($contrasts['white']['ratio'])->toEqualWithDelta(4.54, 0.01)
            ->and($contrasts['white']['normal'])->toBe('AA')
            ->and($contrasts['white']['large'])->toBe('AAA')
            ->and($contrasts['black']['ratio'])->toEqualWithDelta(4.62, 0.01)
            ->and($contrasts['black']['normal'])->toBe('AA');
    });
});
