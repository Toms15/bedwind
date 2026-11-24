<?php

use function App\get_attachment_id;

/**
 * Un allegato (o un altro post) come lo restituisce ACF con il formato "object"
 * (stub di WP_Post: tests/Stubs/wordpress.php).
 */
function fake_attachment(int $id, string $post_type = 'attachment'): WP_Post
{
    $post = new WP_Post;
    $post->ID = $id;
    $post->post_type = $post_type;

    return $post;
}

describe('get_attachment_id()', function () {
    test('un ID intero positivo resta invariato', function () {
        expect(get_attachment_id(42))->toBe(42);
    });

    test('una stringa di sole cifre diventa un intero', function (string $value) {
        expect(get_attachment_id($value))->toBe(42);
    })->with(['42', ' 42 ', '042']);

    test('una stringa di cifre al limite degli int resta valida', function () {
        expect(get_attachment_id((string) PHP_INT_MAX))->toBe(PHP_INT_MAX);
    });

    test("ricava l'ID dal formato Array di ACF (chiave ID o id)", function (array $value) {
        expect(get_attachment_id($value))->toBe(42);
    })->with([
        'chiave ID' => [['ID' => 42, 'id' => 42, 'url' => 'https://example.test/a.jpg']],
        'solo id' => [['id' => 42]],
        'id come stringa' => [['id' => '42']],
    ]);

    test("ricava l'ID da un WP_Post di tipo attachment", function () {
        expect(get_attachment_id(fake_attachment(42)))->toBe(42);
    });

    test('un WP_Post che non è un allegato vale come ID vuoto', function () {
        expect(get_attachment_id(fake_attachment(42, 'post')))->toBe(0);
    });

    test('tutti gli altri valori valgono come ID vuoto', function (mixed $value) {
        expect(get_attachment_id($value))->toBe(0);
    })->with([
        'null' => [null],
        'zero' => [0],
        'negativo' => [-42],
        'stringa vuota' => [''],
        'stringa non numerica' => ['abc'],
        'cifre e lettere' => ['42abc'],
        'decimale in stringa' => ['42.5'],
        'stringa negativa' => ['-42'],
        'solo zeri' => ['000'],
        'cifre oltre il range degli int' => ['99999999999999999999'],
        'oltre il range con zeri iniziali' => ['0009223372036854775808'],
        'float' => [42.0],
        'true' => [true],
        'false' => [false],
        'array vuoto' => [[]],
        'array senza ID' => [['url' => 'https://example.test/a.jpg']],
        'ID annidato' => [['ID' => ['ID' => 42]]],
        'ID non valido' => [['ID' => 'x']],
        'oggetto qualsiasi' => [new stdClass],
    ]);
});
