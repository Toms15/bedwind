<?php

use Brain\Monkey\Functions;

use function App\get_trimmed_excerpt;
use function App\random_string_for_id;

describe('random_string_for_id()', function () {
    it('has the requested length and only lowercase letters', function (int $length) {
        expect(random_string_for_id($length))
            ->toHaveLength($length)
            ->toMatch('/^[a-z]+$/');
    })->with([1, 5, 32]);

    it('defaults to 5 characters', function () {
        expect(random_string_for_id())->toHaveLength(5);
    });

    it('returns at least one character', function (int $length) {
        expect(random_string_for_id($length))->toMatch('/^[a-z]$/');
    })->with([0, -3]);
});

/**
 * A post with the properties read by get_trimmed_excerpt(). WordPress is not
 * loaded, so a minimal WP_Post class is declared when missing.
 */
function fake_post(string $excerpt = '', string $content = ''): WP_Post
{
    if (! class_exists('WP_Post')) {
        eval('final class WP_Post { public $ID = 0; public $post_excerpt = ""; public $post_content = ""; }');
    }

    $post = new WP_Post;
    $post->ID = 42;
    $post->post_excerpt = $excerpt;
    $post->post_content = $content;

    return $post;
}

describe('get_trimmed_excerpt()', function () {
    beforeEach(function () {
        // wp_trim_words() stand-in: first $num words, '…' when cut.
        Functions\when('wp_trim_words')->alias(function (string $text, int $num, string $more) {
            $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

            return count($words) > $num
                ? implode(' ', array_slice($words, 0, $num)).$more
                : implode(' ', $words);
        });
        Functions\when('strip_shortcodes')->returnArg();
        Functions\when('wp_strip_all_tags')->alias(fn (string $text) => strip_tags($text));
    });

    it('returns an empty string when the post does not exist', function () {
        Functions\when('get_post')->justReturn(null);

        expect(get_trimmed_excerpt(10, 999))->toBe('');
    });

    it('prefers the ACF excerpt field', function () {
        Functions\when('get_post')->justReturn(fake_post('Manual excerpt', 'Content'));
        Functions\expect('get_field')->once()->with('excerpt', 42)->andReturn('One two three four');

        expect(get_trimmed_excerpt(2))->toBe('One two…');
    });

    it('falls back to the manual excerpt', function () {
        Functions\when('get_post')->justReturn(fake_post('Manual excerpt here', 'Content'));
        Functions\when('get_field')->justReturn('   ');

        expect(get_trimmed_excerpt(10))->toBe('Manual excerpt here');
    });

    it('falls back to the content without tags', function () {
        Functions\when('get_post')->justReturn(fake_post('', '<p>Some <strong>rich</strong> content</p>'));
        Functions\when('get_field')->justReturn(false);

        expect(get_trimmed_excerpt(10))->toBe('Some rich content');
    });
});
