<?php

use function App\get_next_heading_level;

it('returns the next heading level', function (string $current, string $next) {
    expect(get_next_heading_level($current))->toBe($next);
})->with([
    ['h1', 'h2'],
    ['h2', 'h3'],
    ['h3', 'h4'],
    ['h4', 'h5'],
    ['h5', 'h6'],
]);

it('returns a paragraph after h6 and after p', function (string $current) {
    expect(get_next_heading_level($current))->toBe('p');
})->with(['h6', 'p']);

it('treats invalid values as h2', function (?string $current) {
    expect(get_next_heading_level($current))->toBe('h3');
})->with([null, '', 'h', 'h0', 'h7', 'h9', 'span', 'div', 'H2']);

it('goes down by the given increment', function (string $current, int $increment, string $next) {
    expect(get_next_heading_level($current, $increment))->toBe($next);
})->with([
    ['h1', 2, 'h3'],
    ['h2', 4, 'h6'],
    ['h3', 3, 'h6'],
    ['h5', 2, 'p'],
    ['h2', 10, 'p'],
]);

it('goes down at least one level', function (int $increment) {
    expect(get_next_heading_level('h2', $increment))->toBe('h3');
})->with([0, -1, -10]);
