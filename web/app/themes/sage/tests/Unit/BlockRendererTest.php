<?php

use App\Blocks\BlockRenderer;
use Brain\Monkey\Functions;

/**
 * Mock get_field() with the given block field values (missing fields: null)
 * and sanitize_hex_color() with a minimal #rgb/#rrggbb validator.
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
    it('maps padding presets to Tailwind classes', function (string $padding, string $dimension, string $classes) {
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

    it('maps margin presets to Tailwind classes', function (string $margin, string $dimension, string $classes) {
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
    it('uses the anchor as id, the ACF block id otherwise', function () {
        fake_block_fields([]);

        expect(BlockRenderer::data(['anchor' => 'intro', 'id' => 'block_1'])['settings']['id'])->toBe('intro')
            ->and(BlockRenderer::data(['anchor' => '', 'id' => 'block_1'])['settings']['id'])->toBe('block_1')
            ->and(BlockRenderer::data([])['settings']['id'])->toBe('');
    });

    it('drops invalid colors', function () {
        fake_block_fields(['background_color' => '#112233', 'text_color' => 'red']);

        expect(BlockRenderer::data([])['settings'])
            ->background_color->toBe('#112233')
            ->text_color->toBe('');
    });
});

describe('contents', function () {
    it('whitelists the title tag and computes the next heading', function (mixed $formatting, string $tag, string $next) {
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

    it('normalizes the buttons repeater to a list', function () {
        fake_block_fields(['buttons' => [3 => ['button' => ['url' => '/a']], 7 => ['button' => ['url' => '/b']]]]);
        expect(BlockRenderer::data([])['contents']['buttons'])->toBe([
            ['button' => ['url' => '/a']],
            ['button' => ['url' => '/b']],
        ]);

        fake_block_fields(['buttons' => false]);
        expect(BlockRenderer::data([])['contents']['buttons'])->toBe([]);
    });
});
