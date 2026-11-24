<?php

use Brain\Monkey\Functions;

use function App\custom_logo_image_attributes;

beforeEach(function () {
    // app/filters.php registra solo hook (Brain Monkey) e definisce funzioni.
    require_once dirname(__DIR__, 2).'/app/filters.php';
});

describe('attributi dell\'immagine del custom logo', function () {
    test('dimensiona il logo sull\'altezza di 80px dell\'header e non forza fetchpriority high', function () {
        Functions\expect('wp_get_attachment_image_src')->once()->with(12, 'full')->andReturn(['logo.png', 480, 160, false]);

        expect(custom_logo_image_attributes(['class' => 'custom-logo', 'loading' => false], 12))->toBe([
            'class' => 'custom-logo',
            'loading' => false,
            'sizes' => '(max-width: 240px) 100vw, 240px',
            'fetchpriority' => 'auto',
        ]);
    });

    test('segue il rapporto dell\'immagine dei logo flessibili', function () {
        Functions\when('wp_get_attachment_image_src')->justReturn(['logo.svg', 1000, 125, false]);

        expect(custom_logo_image_attributes([], '7')['sizes'])->toBe('(max-width: 640px) 100vw, 640px');
    });

    test('non ingrandisce i logo più bassi dell\'header', function () {
        Functions\when('wp_get_attachment_image_src')->justReturn(['logo.png', 120, 40, false]);

        expect(custom_logo_image_attributes([], 7)['sizes'])->toBe('(max-width: 120px) 100vw, 120px');
    });

    test('mantiene sizes e fetchpriority impostati da altri filtri', function () {
        Functions\when('wp_get_attachment_image_src')->justReturn(['logo.png', 480, 160, false]);

        expect(custom_logo_image_attributes(['sizes' => '100px', 'fetchpriority' => 'low'], 7))
            ->toMatchArray(['sizes' => '100px', 'fetchpriority' => 'low']);
    });

    test('lascia sizes al core quando l\'immagine non si può leggere', function () {
        Functions\when('wp_get_attachment_image_src')->justReturn(false);

        expect(custom_logo_image_attributes([], 7))->toBe(['fetchpriority' => 'auto']);
    });
});
