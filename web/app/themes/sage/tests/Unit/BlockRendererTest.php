<?php

use App\Blocks\BlockRenderer;
use Brain\Monkey\Functions;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * Mock di get_field() con i valori dei campi del blocco dati (campi mancanti: null)
 * e di sanitize_hex_color() con un validatore #rgb/#rrggbb minimo.
 *
 * @param  array<string, mixed>  $fields
 */
function fake_block_fields(array $fields): void
{
    Functions\when('get_field')->alias(fn (string $name) => $fields[$name] ?? null);
    Functions\when('sanitize_hex_color')->alias(
        fn (string $color) => preg_match('/^#([a-f0-9]{3}){1,2}$/i', $color) ? $color : null
    );
}

describe('spacings', function () {
    test('converte i preset di padding in classi Tailwind', function (string $padding, string $dimension, string $classes) {
        fake_block_fields(['padding' => $padding, 'padding_dimension' => $dimension, 'margin' => 'no']);

        expect(BlockRenderer::data([])['spacings']['padding'])->toBe($classes);
    })->with([
        ['py', 'xl', 'py-9 lg:py-16 xl:py-20'],
        ['py', 's', 'py-3 lg:py-6'],
        ['pt', 'm', 'pt-5 lg:pt-8 xl:pt-10'],
        ['pb', 'l', 'pb-7 lg:pb-12 xl:pb-16'],
        ['no', 'xl', ''],
        ['py', 'xxl', ''],
    ]);

    test('converte i preset di margin in classi Tailwind', function (string $margin, string $dimension, string $classes) {
        fake_block_fields(['padding' => 'no', 'margin' => $margin, 'margin_dimension' => $dimension]);

        expect(BlockRenderer::data([])['spacings']['margin'])->toBe($classes);
    })->with([
        ['my', 'xl', 'my-9 lg:my-16 xl:my-20'],
        ['mt', 's', 'mt-3 lg:mt-6'],
        ['mb', 'm', 'mb-5 lg:mb-8 xl:mb-10'],
        ['no', 'xl', ''],
        ['mx', 'm', ''],
    ]);
});

describe('settings', function () {
    test('usa l\'anchor come id, altrimenti l\'id del blocco ACF', function () {
        fake_block_fields([]);

        expect(BlockRenderer::data(['anchor' => 'intro', 'id' => 'block_1'])['settings']['id'])->toBe('intro')
            ->and(BlockRenderer::data(['anchor' => '', 'id' => 'block_1'])['settings']['id'])->toBe('block_1')
            ->and(BlockRenderer::data([])['settings']['id'])->toBe('');
    });

    test('scarta i colori non validi', function () {
        fake_block_fields(['background_color' => '#112233', 'text_color' => 'red']);

        expect(BlockRenderer::data([])['settings'])
            ->background_color->toBe('#112233')
            ->text_color->toBe('');
    });
});

describe('contents', function () {
    test('limita il tag del titolo ai valori ammessi e calcola il titolo successivo', function (mixed $formatting, string $tag, string $next) {
        fake_block_fields(['title_formatting' => $formatting]);

        expect(BlockRenderer::data([])['contents'])
            ->title_tag->toBe($tag)
            ->next_heading->toBe($next);
    })->with([
        ['h1', 'h1', 'h2'],
        ['h3', 'h3', 'h4'],
        ['h6', 'h6', 'p'],
        ['p', 'p', 'p'],
        ['div', 'h2', 'h3'],
        [null, 'h2', 'h3'],
    ]);

    test('normalizza il repeater dei pulsanti in una lista', function () {
        fake_block_fields(['buttons' => [3 => ['button' => ['url' => '/a']], 7 => ['button' => ['url' => '/b']]]]);
        expect(BlockRenderer::data([])['contents']['buttons'])->toBe([
            ['button' => ['url' => '/a']],
            ['button' => ['url' => '/b']],
        ]);

        fake_block_fields(['buttons' => false]);
        expect(BlockRenderer::data([])['contents']['buttons'])->toBe([]);
    });
});

/**
 * Mock della view factory di Acorn (risolta da view() tramite app()): le view
 * date esistono e si renderizzano come "<name>|<json dei dati>"; le altre non esistono.
 *
 * @param  list<string>  $views
 */
function fake_views(array $views): void
{
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('exists')->andReturnUsing(fn (string $name) => in_array($name, $views, true));
    $factory->shouldReceive('make')->andReturnUsing(function (string $name, array $data) {
        $view = Mockery::mock(View::class);
        $view->shouldReceive('render')->andReturn($name.'|'.json_encode($data));

        return $view;
    });
    $factory->shouldNotReceive('file');

    Functions\when('app')->justReturn($factory);

    // Nessun blocco ha asset (view.js/style.css): vedi BlockAssetsTest.
    Functions\when('get_theme_file_path')->alias(fn (string $path) => '/nonexistent/'.$path);
}

/**
 * Output di BlockRenderer::render().
 *
 * @param  array<string, mixed>  $block
 */
function render_block(array $block, bool $is_preview = false): string
{
    ob_start();
    BlockRenderer::render($block, '<p>inner blocks</p>', $is_preview);

    return (string) ob_get_clean();
}

describe('render', function () {
    test('renderizza la view del blocco per nome, solo con il blocco e il suo slug', function () {
        fake_views(['blocks.only-text.only-text']);

        expect(render_block(['name' => 'acf/only-text', 'id' => 'block_1']))
            ->toBe('blocks.only-text.only-text|'.json_encode(['block' => ['name' => 'acf/only-text', 'id' => 'block_1', 'slug' => 'only-text']]));
    });

    test('toglie solo il prefisso acf/ iniziale', function () {
        // Un namespace che finisce in "acf" (es. un "my-acf/cards" di terze parti):
        // rimuovere "acf/" in qualsiasi punto lo trasformerebbe in "my-cards".
        fake_views(['blocks.cards.cards', 'blocks.my-cards.my-cards']);

        expect(render_block(['name' => 'acf/cards']))->toStartWith('blocks.cards.cards|')
            ->and(render_block(['name' => 'my-acf/cards']))->toBe('');
    });

    test('non renderizza nulla quando la view non esiste', function () {
        fake_views([]);

        expect(render_block(['name' => 'acf/missing']))->toBe('')
            ->and(render_block([]))->toBe('');
    });

    test('carica gli asset del blocco solo quando la view è renderizzata nel frontend', function () {
        fake_views(['components.acf.placeholder', 'blocks.only-text.only-text']);
        fake_block_fields([]);
        Functions\expect('wp_enqueue_style')->never();

        $checked = [];
        Functions\when('get_theme_file_path')->alias(function (string $path) use (&$checked) {
            $checked[] = $path;

            return '/nonexistent/'.$path;
        });

        render_block(['name' => 'acf/only-text'], true);
        render_block(['name' => 'acf/missing']);
        expect($checked)->toBe([]);

        render_block(['name' => 'acf/only-text']);
        expect($checked)->toBe([
            'resources/views/blocks/only-text/style.css',
            'resources/views/blocks/only-text/view.js',
        ]);
    });

    test('renderizza nell\'editor il placeholder con il campo title', function () {
        fake_views(['components.acf.placeholder', 'blocks.only-text.only-text']);
        fake_block_fields(['title' => 'Intro']);

        expect(render_block(['name' => 'acf/only-text'], true))
            ->toBe('components.acf.placeholder|'.json_encode([
                'block' => ['name' => 'acf/only-text', 'slug' => 'only-text'],
                'title' => 'Intro',
            ]));
    });
});
