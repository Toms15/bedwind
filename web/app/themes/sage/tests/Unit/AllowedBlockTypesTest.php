<?php

use Brain\Monkey\Filters;

use function App\allowed_block_types;

beforeEach(function () {
    // app/blocks.php registra solo hook (Brain Monkey) e definisce la funzione.
    require_once dirname(__DIR__, 2).'/app/blocks.php';

    WP_Block_Type_Registry::get_instance()->registered = array_fill_keys([
        'core/paragraph',
        'core/block',
        'acf/only-text',
        'my-plugin/form',
        'other/slider',
    ], new stdClass);
});

/**
 * Contesto dell'editor per il post type dato, o di un editor senza post.
 */
function editor_context(?string $post_type, string $name = 'core/edit-post'): WP_Block_Editor_Context
{
    $settings = ['name' => $name];

    if ($post_type !== null) {
        $settings['post'] = new WP_Post;
        $settings['post']->post_type = $post_type;
    }

    return new WP_Block_Editor_Context($settings);
}

describe('allowed_block_types', function () {
    test('restituisce false quando riceve false', function () {
        expect(allowed_block_types(false, editor_context('page')))->toBeFalse();
    });

    test('ammette solo i blocchi ACF nelle pagine', function () {
        expect(allowed_block_types(true, editor_context('page')))->toBe(['acf/only-text']);
    });

    test('ammette blocchi core e ACF nei post type con i blocchi core', function (string $post_type) {
        expect(allowed_block_types(true, editor_context($post_type)))
            ->toBe(['core/paragraph', 'core/block', 'acf/only-text']);
    })->with(['post', 'faq']);

    test('ammette blocchi core e ACF negli editor che non sono di contenuto', function (string $name) {
        expect(allowed_block_types(true, editor_context(null, $name)))
            ->toBe(['core/paragraph', 'core/block', 'acf/only-text']);
    })->with(['core/edit-widgets', 'core/customize-widgets', 'core/edit-site']);

    test('ammette solo i blocchi ACF in un post editor senza post', function () {
        expect(allowed_block_types(true, editor_context(null)))->toBe(['acf/only-text']);
    });

    test('legge i post type con i blocchi core da sage/core_blocks_post_types', function () {
        Filters\expectApplied('sage/core_blocks_post_types')->twice()->with(['post', 'faq'])->andReturn(['page']);

        expect(allowed_block_types(true, editor_context('page')))
            ->toBe(['core/paragraph', 'core/block', 'acf/only-text'])
            ->and(allowed_block_types(true, editor_context('post')))->toBe(['acf/only-text']);
    });

    test('aggiunge i blocchi di sage/extra_blocks in ogni editor', function () {
        Filters\expectApplied('sage/extra_blocks')->andReturn(['my-plugin/form']);

        expect(allowed_block_types(true, editor_context('page')))
            ->toBe(['acf/only-text', 'my-plugin/form'])
            ->and(allowed_block_types(true, editor_context('post')))
            ->toBe(['core/paragraph', 'core/block', 'acf/only-text', 'my-plugin/form']);
    });

    test('restringe soltanto un array in ingresso', function () {
        Filters\expectApplied('sage/extra_blocks')->andReturn(['my-plugin/form', 'third/missing']);

        expect(allowed_block_types(
            ['acf/only-text', 'core/heading', 'my-plugin/form', 'other/x', 42],
            editor_context('page')
        ))->toBe(['acf/only-text', 'my-plugin/form']);
    });
});
