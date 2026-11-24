<?php

use App\View\Composers\App;
use Brain\Monkey\Functions;

describe('Composer App', function () {
    test('restituisce il markup del custom logo quando un logo è impostato', function () {
        $markup = '<a href="https://example.test/" class="custom-logo-link" rel="home"><img class="custom-logo" alt="Sito"></a>';

        Functions\when('has_custom_logo')->justReturn(true);
        Functions\expect('get_custom_logo')->once()->andReturn($markup);

        expect((new App)->customLogo())->toBe($markup);
    });

    test('restituisce una stringa vuota senza custom logo, così l\'header ripiega sul nome del sito', function () {
        Functions\when('has_custom_logo')->justReturn(false);
        Functions\expect('get_custom_logo')->never();

        expect((new App)->customLogo())->toBe('');
    });

    test('restituisce il nome del sito come testo semplice, decodificando le entity dei filtri display', function () {
        Functions\expect('get_bloginfo')->once()->with('name', 'display')->andReturn('Rossi &amp; Figli &#8211; l&#8217;officina');

        expect((new App)->siteName())->toBe('Rossi & Figli – l’officina');
    });

    test('marca come corrente la prima pagina della front page', function (bool $front, bool $paged, bool $expected) {
        Functions\when('is_front_page')->justReturn($front);
        Functions\when('is_paged')->justReturn($paged);

        expect((new App)->isFrontPage())->toBe($expected);
    })->with([
        'front page' => [true, false, true],
        'front page, pagina 2' => [true, true, false],
        'altra pagina' => [false, false, false],
    ]);
});
