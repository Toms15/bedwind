<?php

use Brain\Monkey\Functions;

use function App\custom_logo_image_attributes;

beforeEach(function () {
    // app/filters.php only registers hooks (Brain Monkey) and defines functions.
    require_once dirname(__DIR__, 2).'/app/filters.php';
});

describe('custom logo image attributes', function () {
    it('sizes the logo to the 80px header height and does not force fetchpriority high', function () {
        Functions\expect('wp_get_attachment_image_src')->once()->with(12, 'full')->andReturn(['logo.png', 480, 160, false]);

        expect(custom_logo_image_attributes(['class' => 'custom-logo', 'loading' => false], 12))->toBe([
            'class' => 'custom-logo',
            'loading' => false,
            'sizes' => '(max-width: 240px) 100vw, 240px',
            'fetchpriority' => 'auto',
        ]);
    });

    it('follows the image ratio of flexible logos', function () {
        Functions\when('wp_get_attachment_image_src')->justReturn(['logo.svg', 1000, 125, false]);

        expect(custom_logo_image_attributes([], '7')['sizes'])->toBe('(max-width: 640px) 100vw, 640px');
    });

    it('does not upscale logos shorter than the header', function () {
        Functions\when('wp_get_attachment_image_src')->justReturn(['logo.png', 120, 40, false]);

        expect(custom_logo_image_attributes([], 7)['sizes'])->toBe('(max-width: 120px) 100vw, 120px');
    });

    it('keeps sizes and fetchpriority set by other filters', function () {
        Functions\when('wp_get_attachment_image_src')->justReturn(['logo.png', 480, 160, false]);

        expect(custom_logo_image_attributes(['sizes' => '100px', 'fetchpriority' => 'low'], 7))
            ->toMatchArray(['sizes' => '100px', 'fetchpriority' => 'low']);
    });

    it('leaves sizes to core when the image cannot be read', function () {
        Functions\when('wp_get_attachment_image_src')->justReturn(false);

        expect(custom_logo_image_attributes([], 7))->toBe(['fetchpriority' => 'auto']);
    });
});
