<?php

use App\View\Composers\App;
use Brain\Monkey\Functions;

describe('App composer', function () {
    it('returns the custom logo markup when a logo is set', function () {
        $markup = '<a href="https://example.test/" class="custom-logo-link" rel="home"><img class="custom-logo" alt="Sito"></a>';

        Functions\when('has_custom_logo')->justReturn(true);
        Functions\expect('get_custom_logo')->once()->andReturn($markup);

        expect((new App)->customLogo())->toBe($markup);
    });

    it('returns an empty string without a custom logo, so the header falls back to the site name', function () {
        Functions\when('has_custom_logo')->justReturn(false);
        Functions\expect('get_custom_logo')->never();

        expect((new App)->customLogo())->toBe('');
    });

    it('returns the site name as plain text, decoding the entities of the display filters', function () {
        Functions\expect('get_bloginfo')->once()->with('name', 'display')->andReturn('Rossi &amp; Figli &#8211; l&#8217;officina');

        expect((new App)->siteName())->toBe('Rossi & Figli – l’officina');
    });

    it('marks the first page of the front page as current', function (bool $front, bool $paged, bool $expected) {
        Functions\when('is_front_page')->justReturn($front);
        Functions\when('is_paged')->justReturn($paged);

        expect((new App)->isFrontPage())->toBe($expected);
    })->with([
        'front page' => [true, false, true],
        'front page, page 2' => [true, true, false],
        'other page' => [false, false, false],
    ]);
});
