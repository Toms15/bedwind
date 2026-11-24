<?php

use function App\get_next_heading_level;

test('get_next_heading_level() restituisce il livello di titolo successivo', function (string $current, string $next) {
    expect(get_next_heading_level($current))->toBe($next);
})->with([
    ['h1', 'h2'],
    ['h2', 'h3'],
    ['h3', 'h4'],
    ['h4', 'h5'],
    ['h5', 'h6'],
]);

test('get_next_heading_level() restituisce un paragrafo dopo h6 e dopo p', function (string $current) {
    expect(get_next_heading_level($current))->toBe('p');
})->with(['h6', 'p']);

test('get_next_heading_level() tratta i valori non validi come h2', function (?string $current) {
    expect(get_next_heading_level($current))->toBe('h3');
})->with([null, '', 'h', 'h0', 'h7', 'h9', 'span', 'div', 'H2']);

test('get_next_heading_level() scende dell\'incremento dato', function (string $current, int $increment, string $next) {
    expect(get_next_heading_level($current, $increment))->toBe($next);
})->with([
    ['h1', 2, 'h3'],
    ['h2', 4, 'h6'],
    ['h3', 3, 'h6'],
    ['h5', 2, 'p'],
    ['h2', 10, 'p'],
]);

test('get_next_heading_level() scende almeno di un livello', function (int $increment) {
    expect(get_next_heading_level('h2', $increment))->toBe('h3');
})->with([0, -1, -10]);
