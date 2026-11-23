<?php

use Brain\Monkey\Filters;

use function App\allowed_block_types;

beforeEach(function () {
    // app/blocks.php only registers hooks (Brain Monkey) and defines the function.
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
 * Editor context of the given post type, or of a non-post editor.
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
    it('keeps false', function () {
        expect(allowed_block_types(false, editor_context('page')))->toBeFalse();
    });

    it('allows only ACF blocks in pages', function () {
        expect(allowed_block_types(true, editor_context('page')))->toBe(['acf/only-text']);
    });

    it('allows core and ACF blocks in the core post types', function (string $post_type) {
        expect(allowed_block_types(true, editor_context($post_type)))
            ->toBe(['core/paragraph', 'core/block', 'acf/only-text']);
    })->with(['post', 'faq']);

    it('allows core and ACF blocks in the non-content editors', function (string $name) {
        expect(allowed_block_types(true, editor_context(null, $name)))
            ->toBe(['core/paragraph', 'core/block', 'acf/only-text']);
    })->with(['core/edit-widgets', 'core/customize-widgets', 'core/edit-site']);

    it('allows only ACF blocks in a post editor without a post', function () {
        expect(allowed_block_types(true, editor_context(null)))->toBe(['acf/only-text']);
    });

    it('reads the core post types from sage/core_blocks_post_types', function () {
        Filters\expectApplied('sage/core_blocks_post_types')->twice()->with(['post', 'faq'])->andReturn(['page']);

        expect(allowed_block_types(true, editor_context('page')))
            ->toBe(['core/paragraph', 'core/block', 'acf/only-text'])
            ->and(allowed_block_types(true, editor_context('post')))->toBe(['acf/only-text']);
    });

    it('adds the blocks of sage/extra_blocks in every editor', function () {
        Filters\expectApplied('sage/extra_blocks')->andReturn(['my-plugin/form']);

        expect(allowed_block_types(true, editor_context('page')))
            ->toBe(['acf/only-text', 'my-plugin/form'])
            ->and(allowed_block_types(true, editor_context('post')))
            ->toBe(['core/paragraph', 'core/block', 'acf/only-text', 'my-plugin/form']);
    });

    it('only narrows an incoming array', function () {
        Filters\expectApplied('sage/extra_blocks')->andReturn(['my-plugin/form', 'third/missing']);

        expect(allowed_block_types(
            ['acf/only-text', 'core/heading', 'my-plugin/form', 'other/x', 42],
            editor_context('page')
        ))->toBe(['acf/only-text', 'my-plugin/form']);
    });
});
