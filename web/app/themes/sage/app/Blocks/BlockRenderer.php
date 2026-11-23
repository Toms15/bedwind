<?php

declare(strict_types=1);

namespace App\Blocks;

/**
 * Rendering of the theme ACF blocks.
 *
 * render() is the shared ACF render callback (block.json
 * "acf.renderCallback": "App\\Blocks\\BlockRenderer::render"); data() prepares
 * the common block data for the block templates (resources/views/blocks).
 */
class BlockRenderer
{
    /**
     * Render callback shared by every ACF block.
     *
     * In the editor: no preview, a "Modifica blocco" placeholder for every block.
     * On the frontend: the view blocks/<slug>/<slug> with $block (slug and
     * block.json "name" "acf/<slug>" must match); nothing if the view is missing.
     *
     * ACF calls it with ($block, $content, $is_preview, $post_id, $wp_block,
     * $context). Only the first three are declared, the others are unused:
     * $post_id comes from get_the_ID() (int|false) and $context can be false,
     * so declaring them would only add TypeError risks.
     * $content is the InnerBlocks HTML: the theme blocks do not use InnerBlocks
     * (no supports.jsx), so it is not passed to the views. Not to be confused
     * with the ACF "content" field, read by data() with get_field().
     *
     * @param  array<string, mixed>  $block  The ACF block data (name, id, anchor, ...).
     * @param  string  $content  The InnerBlocks HTML (unused).
     * @param  bool  $is_preview  Whether the block is rendered in the editor.
     */
    public static function render(array $block, string $content = '', bool $is_preview = false): void
    {
        $slug = self::slug($block);
        $block['slug'] = $slug;

        if ($is_preview) {
            echo view('components.acf.placeholder', [
                'block' => $block,
                'title' => get_field('title'),
            ])->render();

            return;
        }

        $view = "blocks.{$slug}.{$slug}";

        if ($slug !== '' && view()->exists($view)) {
            echo view($view, ['block' => $block])->render();
        }
    }

    /**
     * Block slug: the block name without the leading "acf/" (only the prefix).
     *
     * @param  array<string, mixed>  $block  The ACF block data.
     */
    private static function slug(array $block): string
    {
        $name = is_string($block['name'] ?? null) ? $block['name'] : '';

        return str_starts_with($name, 'acf/') ? substr($name, 4) : $name;
    }

    /**
     * Retrieves and prepares the common data for a block.
     *
     * Groups block data into settings, contents, and spacings.
     * Padding and margin values are converted into their corresponding
     * Tailwind classes by self::padding() and self::margin().
     *
     * settings.id is the block anchor when set, otherwise the ACF block id
     * (e.g. "block_abc123"), so every block section always has an id.
     * Colors are validated with sanitize_hex_color(): invalid values become ''.
     *
     * Heading convention for every block:
     * - contents.title_tag is the tag of the block title (ACF title_formatting,
     *   whitelisted to h1..h6/p, default 'h2'): <x-title :tag="$contents['title_tag']">.
     * - contents.next_heading is the tag for the block's child headings
     *   (subtitles, card titles, ...), from \App\get_next_heading_level(title_tag).
     *
     * @param  array<string, mixed>  $block  The ACF block data.
     * @return array{
     *     settings: array{
     *         id: string,
     *         background_color: string,
     *         text_color: string
     *     },
     *     contents: array{
     *         title: mixed,
     *         title_formatting: mixed,
     *         title_tag: 'h1'|'h2'|'h3'|'h4'|'h5'|'h6'|'p',
     *         next_heading: 'h2'|'h3'|'h4'|'h5'|'h6'|'p',
     *         content: mixed,
     *         buttons: list<array{button: array{url?: string, title?: string, target?: string}|mixed}>
     *     },
     *     spacings: array{
     *         padding: mixed,
     *         margin: mixed
     *     }
     * } The prepared block data grouped by type.
     */
    public static function data(array $block): array
    {
        $padding = get_field('padding');
        $padding_dimension = get_field('padding_dimension');

        $margin = get_field('margin');
        $margin_dimension = get_field('margin_dimension');

        $custom_padding = $padding !== 'no'
            ? 'custom-'.$padding.'-'.$padding_dimension
            : 'no-padding';

        $custom_margin = $margin !== 'no'
            ? 'custom-'.$margin.'-'.$margin_dimension
            : 'no-margin';

        // Repeater: false/null when there are no rows.
        $buttons = get_field('buttons');
        $buttons = is_array($buttons) ? array_values($buttons) : [];

        $title_formatting = get_field('title_formatting');
        $title_tag = in_array($title_formatting, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'], true)
            ? $title_formatting
            : 'h2';

        return [
            'settings' => [
                'id' => ! empty($block['anchor']) ? $block['anchor'] : ($block['id'] ?? ''),
                'background_color' => sanitize_hex_color((string) get_field('background_color')) ?: '',
                'text_color' => sanitize_hex_color((string) get_field('text_color')) ?: '',
            ],

            'contents' => [
                'title' => get_field('title'),
                'title_formatting' => $title_formatting,
                'title_tag' => $title_tag,
                'next_heading' => \App\get_next_heading_level($title_tag),
                'content' => get_field('content'),
                'buttons' => $buttons,
            ],

            'spacings' => [
                'padding' => self::padding($custom_padding),
                'margin' => self::margin($custom_margin),
            ],
        ];
    }

    /**
     * Tailwind classes of a padding preset (e.g. 'custom-py-m').
     */
    private static function padding(string $custom_padding): string
    {
        return match ($custom_padding) {
            'no-padding' => '',
            'custom-py-xl' => 'py-9 lg:py-16 xl:py-20',
            'custom-py-l' => 'py-7 lg:py-12 xl:py-16',
            'custom-py-m' => 'py-5 lg:py-8 xl:py-10',
            'custom-py-s' => 'py-3 lg:py-6',
            'custom-pt-xl' => 'pt-9 lg:pt-16 xl:pt-20',
            'custom-pt-l' => 'pt-7 lg:pt-12 xl:pt-16',
            'custom-pt-m' => 'pt-5 lg:pt-8 xl:pt-10',
            'custom-pt-s' => 'pt-3 lg:pt-6',
            'custom-pb-xl' => 'pb-9 lg:pb-16 xl:pb-20',
            'custom-pb-l' => 'pb-7 lg:pb-12 xl:pb-16',
            'custom-pb-m' => 'pb-5 lg:pb-8 xl:pb-10',
            'custom-pb-s' => 'pb-3 lg:pb-6',
            default => '',
        };
    }

    /**
     * Tailwind classes of a margin preset (e.g. 'custom-mt-s').
     */
    private static function margin(string $custom_margin): string
    {
        return match ($custom_margin) {
            'no-margin' => '',
            'custom-my-xl' => 'my-9 lg:my-16 xl:my-20',
            'custom-my-l' => 'my-7 lg:my-12 xl:my-16',
            'custom-my-m' => 'my-5 lg:my-8 xl:my-10',
            'custom-my-s' => 'my-3 lg:my-6',
            'custom-mt-xl' => 'mt-9 lg:mt-16 xl:mt-20',
            'custom-mt-l' => 'mt-7 lg:mt-12 xl:mt-16',
            'custom-mt-m' => 'mt-5 lg:mt-8 xl:mt-10',
            'custom-mt-s' => 'mt-3 lg:mt-6',
            'custom-mb-xl' => 'mb-9 lg:mb-16 xl:mb-20',
            'custom-mb-l' => 'mb-7 lg:mb-12 xl:mb-16',
            'custom-mb-m' => 'mb-5 lg:mb-8 xl:mb-10',
            'custom-mb-s' => 'mb-3 lg:mb-6',
            default => '',
        };
    }
}
