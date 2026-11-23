<?php

use App\Styleguide;
use Brain\Monkey\Functions;
use Illuminate\Support\Facades\Vite;

afterEach(function () {
    unset($GLOBALS['wp_query']);
});

/**
 * Stored rewrite rules and FLUSH_OPTION, with the flush recorded.
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
    it('is true only in development', function (string $env, bool $expected) {
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
    it('only hooks the rewrite sync outside development', function (string $env) {
        Styleguide::register($env);

        expect(has_action('init'))->toBeTrue()
            ->and(has_action('init', [Styleguide::class, 'addRewriteRule']))->toBeFalse()
            ->and(has_filter('query_vars', [Styleguide::class, 'queryVars']))->toBeFalse()
            ->and(has_filter('template_include', [Styleguide::class, 'template']))->toBeFalse()
            ->and(has_filter('wp_robots', [Styleguide::class, 'robots']))->toBeFalse();
    })->with(['staging', 'production', '']);

    it('hooks the route in development', function () {
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
    it('flushes once when the rule is missing in development', function () {
        $state = fake_rewrite_state(['^foo/?$' => 'index.php?foo=1']);

        Styleguide::syncRewriteRules(true);
        Styleguide::syncRewriteRules(true);

        expect($state['flushes'])->toBe(1)->and($state['option'])->toBe('on');
    });

    it('flushes once when the rule is left outside development', function () {
        $state = fake_rewrite_state([Styleguide::RULE => 'index.php?sage_styleguide=1'], 'on');

        Styleguide::syncRewriteRules(false);

        expect($state['flushes'])->toBe(1)->and($state['option'])->toBe('off');
    });

    it('does nothing when the rules match the environment', function (bool $enabled, array $rules) {
        $state = fake_rewrite_state($rules);

        Styleguide::syncRewriteRules($enabled);

        expect($state['flushes'])->toBe(0)->and($state['option'])->toBeFalse();
    })->with([
        'development' => [true, [Styleguide::RULE => 'index.php?sage_styleguide=1']],
        'production' => [false, ['^foo/?$' => 'index.php?foo=1']],
    ]);

    it('does nothing without stored rules (plain permalinks)', function () {
        $state = fake_rewrite_state('');

        Styleguide::syncRewriteRules(true);

        expect($state['flushes'])->toBe(0);
    });

    it('forgets an old flush state once the rules are in sync', function () {
        $state = fake_rewrite_state(['^foo/?$' => 'index.php?foo=1'], 'on');

        Styleguide::syncRewriteRules(false);

        expect($state['flushes'])->toBe(0)->and($state['option'])->toBeFalse();
    });
});

describe('request', function () {
    it('recognizes only the main query with the query var', function () {
        expect(Styleguide::isRequest(new WP_Query([Styleguide::QUERY_VAR => '1'])))->toBeTrue()
            ->and(Styleguide::isRequest(new WP_Query([Styleguide::QUERY_VAR => '1'], false)))->toBeFalse()
            ->and(Styleguide::isRequest(new WP_Query))->toBeFalse()
            ->and(Styleguide::isRequest())->toBeFalse();
    });

    it('is not the blog home, runs no posts query and is not a 404', function () {
        $query = new WP_Query([Styleguide::QUERY_VAR => '1']);
        Styleguide::parseQuery($query);

        expect($query->is_home)->toBeFalse()
            ->and(Styleguide::skipMainQuery(null, $query))->toBe([])
            ->and(Styleguide::skip404(false, $query))->toBeTrue()
            ->and(Styleguide::skipMainQuery(null, new WP_Query))->toBeNull()
            ->and(Styleguide::skip404(false, new WP_Query))->toBeFalse();
    });

    it('renders the view with noindex, nofollow', function () {
        $GLOBALS['wp_query'] = new WP_Query([Styleguide::QUERY_VAR => '1']);
        Functions\when('get_theme_file_path')->alias(fn (string $path) => "/theme/{$path}");
        Functions\when('__')->returnArg();

        expect(Styleguide::template('/theme/index.php'))->toBe('/theme/resources/views/styleguide.blade.php')
            ->and(Styleguide::robots(['follow' => true, 'max-image-preview' => 'large']))
            ->toBe(['max-image-preview' => 'large', 'noindex' => true, 'nofollow' => true])
            ->and(Styleguide::documentTitle(['title' => 'Home']))->toBe(['title' => 'Styleguide'])
            ->and(Styleguide::bodyClass(['home']))->toBe(['home', 'styleguide']);
    });

    it('leaves other requests untouched', function () {
        $GLOBALS['wp_query'] = new WP_Query;

        expect(Styleguide::template('/theme/index.php'))->toBe('/theme/index.php')
            ->and(Styleguide::robots(['follow' => true]))->toBe(['follow' => true])
            ->and(Styleguide::bodyClass(['home']))->toBe(['home']);
    });
});

describe('tokens()', function () {
    it('reads palette, font families and font sizes from theme.json', function () {
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

    it('returns empty lists without a build', function () {
        expect(Styleguide::tokens('/missing/theme.json'))
            ->toBe(['colors' => [], 'fonts' => [], 'sizes' => []]);
    });
});

describe('enqueueAssets()', function () {
    afterEach(function () {
        Vite::clearResolvedInstances();
    });

    /**
     * Vite facade: asset() returns "build/<path>", or throws when $missing.
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

    it('enqueues the stylesheet of the view only on the styleguide', function () {
        fake_styleguide_vite();
        Functions\expect('wp_enqueue_style')->once()
            ->with(Styleguide::HANDLE, 'build/'.Styleguide::STYLE, [], null);

        $GLOBALS['wp_query'] = new WP_Query([Styleguide::QUERY_VAR => '1']);
        Styleguide::enqueueAssets();

        $GLOBALS['wp_query'] = new WP_Query;
        Styleguide::enqueueAssets();
    });

    it('skips the stylesheet missing from the Vite manifest', function () {
        fake_styleguide_vite(missing: true);
        Functions\expect('wp_enqueue_style')->never();

        $GLOBALS['wp_query'] = new WP_Query([Styleguide::QUERY_VAR => '1']);
        Styleguide::enqueueAssets();
    });

    it('is hooked only in development', function () {
        Styleguide::register('development');
        expect(has_action('wp_enqueue_scripts', [Styleguide::class, 'enqueueAssets']))->not->toBeFalse();
    });
});

describe('contrast', function () {
    it('computes the WCAG luminance of hex and oklch colors', function (string $color, float $expected) {
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

    it('returns null for formats it cannot read or transparent colors', function (string $color) {
        expect(Styleguide::luminance($color))->toBeNull()
            ->and(Styleguide::contrast($color, '#fff'))->toBeNull()
            ->and(Styleguide::contrasts($color))->toBe([]);
    })->with(['', 'red', '#12', '#12345', 'rgb(0 0 0)', 'oklch(50% 0 0 / .5)', 'oklch(50% 0 0 / 50%)', 'var(--color-black)']);

    it('computes the contrast ratio in both directions', function () {
        expect(Styleguide::contrast('#000', '#fff'))->toEqualWithDelta(21.0, 0.0001)
            ->and(Styleguide::contrast('#fff', '#000'))->toEqualWithDelta(21.0, 0.0001)
            ->and(Styleguide::contrast('#fff', '#fff'))->toEqualWithDelta(1.0, 0.0001)
            ->and(Styleguide::contrast('#767676', '#fff'))->toEqualWithDelta(4.54, 0.01)
            ->and(Styleguide::contrast('oklch(14.5% 0 0)', '#fff'))->toEqualWithDelta(19.79, 0.01);
    });

    it('maps the ratio to the WCAG levels for normal and large text', function (float $ratio, ?string $normal, ?string $large) {
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

    it('gives the contrast with white and black', function () {
        $contrasts = Styleguide::contrasts('#767676');

        expect(array_keys($contrasts))->toBe(['white', 'black'])
            ->and($contrasts['white']['ratio'])->toEqualWithDelta(4.54, 0.01)
            ->and($contrasts['white']['normal'])->toBe('AA')
            ->and($contrasts['white']['large'])->toBe('AAA')
            ->and($contrasts['black']['ratio'])->toEqualWithDelta(4.62, 0.01)
            ->and($contrasts['black']['normal'])->toBe('AA');
    });
});
