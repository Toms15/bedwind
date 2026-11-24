<?php

use Brain\Monkey\Functions;

use function App\get_trimmed_excerpt;
use function App\random_string_for_id;

describe('random_string_for_id()', function () {
    test('ha la lunghezza richiesta e solo lettere minuscole', function (int $length) {
        expect(random_string_for_id($length))
            ->toHaveLength($length)
            ->toMatch('/^[a-z]+$/');
    })->with([1, 5, 32]);

    test('ha 5 caratteri di default', function () {
        expect(random_string_for_id())->toHaveLength(5);
    });

    test('restituisce almeno un carattere', function (int $length) {
        expect(random_string_for_id($length))->toMatch('/^[a-z]$/');
    })->with([0, -3]);
});

/**
 * Un post con le proprietà lette da get_trimmed_excerpt() (stub di WP_Post:
 * tests/Stubs/wordpress.php).
 */
function fake_post(string $excerpt = '', string $content = ''): WP_Post
{
    $post = new WP_Post;
    $post->ID = 42;
    $post->post_excerpt = $excerpt;
    $post->post_content = $content;

    return $post;
}

describe('get_trimmed_excerpt()', function () {
    beforeEach(function () {
        // Sostituto di wp_trim_words(): prime $num parole, '…' se tagliato.
        Functions\when('wp_trim_words')->alias(function (string $text, int $num, string $more) {
            $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

            return count($words) > $num
                ? implode(' ', array_slice($words, 0, $num)).$more
                : implode(' ', $words);
        });
        Functions\when('strip_shortcodes')->returnArg();
        Functions\when('wp_strip_all_tags')->alias(fn (string $text) => strip_tags($text));
    });

    test('restituisce una stringa vuota quando il post non esiste', function () {
        Functions\when('get_post')->justReturn(null);

        expect(get_trimmed_excerpt(10, 999))->toBe('');
    });

    test('preferisce il campo ACF excerpt', function () {
        Functions\when('get_post')->justReturn(fake_post('Manual excerpt', 'Content'));
        Functions\expect('get_field')->once()->with('excerpt', 42)->andReturn('One two three four');

        expect(get_trimmed_excerpt(2))->toBe('One two…');
    });

    test('ripiega sull\'excerpt manuale', function () {
        Functions\when('get_post')->justReturn(fake_post('Manual excerpt here', 'Content'));
        Functions\when('get_field')->justReturn('   ');

        expect(get_trimmed_excerpt(10))->toBe('Manual excerpt here');
    });

    test('ripiega sul contenuto senza tag', function () {
        Functions\when('get_post')->justReturn(fake_post('', '<p>Some <strong>rich</strong> content</p>'));
        Functions\when('get_field')->justReturn(false);

        expect(get_trimmed_excerpt(10))->toBe('Some rich content');
    });
});
