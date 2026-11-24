<?php

declare(strict_types=1);

namespace App\Blocks;

/**
 * Render dei blocchi ACF del tema.
 *
 * render() è la render callback ACF condivisa (block.json
 * "acf.renderCallback": "App\\Blocks\\BlockRenderer::render"); data() prepara
 * i dati comuni dei blocchi per i template dei blocchi (resources/views/blocks).
 */
class BlockRenderer
{
    /**
     * Render callback condivisa da tutti i blocchi ACF.
     *
     * Nell'editor: nessuna anteprima, un placeholder "Modifica blocco" per ogni blocco.
     * Nel frontend: la view blocks/<slug>/<slug> con $block (lo slug e il "name"
     * "acf/<slug>" del block.json devono corrispondere); nulla se la view manca.
     * I file opzionali blocks/<slug>/{view.js,style.css} vengono caricati solo
     * quando la view è renderizzata (BlockAssets).
     *
     * ACF la chiama con ($block, $content, $is_preview, $post_id, $wp_block,
     * $context). Sono dichiarati solo i primi tre, gli altri non servono:
     * $post_id arriva da get_the_ID() (int|false) e $context può essere false,
     * quindi dichiararli aggiungerebbe solo rischi di TypeError.
     * $content è l'HTML delle InnerBlocks: i blocchi del tema non usano InnerBlocks
     * (niente supports.jsx), quindi non viene passato alle view. Da non confondere
     * con il campo ACF "content", letto da data() con get_field().
     *
     * @param  array<string, mixed>  $block  I dati del blocco ACF (name, id, anchor, ...).
     * @param  string  $content  L'HTML delle InnerBlocks (non usato).
     * @param  bool  $is_preview  Indica se il blocco è renderizzato nell'editor.
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
            BlockAssets::enqueue($slug);

            echo view($view, ['block' => $block])->render();
        }
    }

    /**
     * Slug del blocco: il nome del blocco senza il "acf/" iniziale (solo il prefisso).
     *
     * @param  array<string, mixed>  $block  I dati del blocco ACF.
     */
    private static function slug(array $block): string
    {
        $name = is_string($block['name'] ?? null) ? $block['name'] : '';

        return str_starts_with($name, 'acf/') ? substr($name, 4) : $name;
    }

    /**
     * Recupera e prepara i dati comuni di un blocco.
     *
     * Raggruppa i dati del blocco in settings, contents e spacings.
     * I valori di padding e margin sono convertiti nelle corrispondenti
     * classi Tailwind da self::padding() e self::margin().
     *
     * settings.id è l'anchor del blocco se impostata, altrimenti l'id del blocco
     * ACF (es. "block_abc123"), così ogni section di blocco ha sempre un id.
     * I colori sono validati con sanitize_hex_color(): i valori non validi diventano ''.
     *
     * Convenzione dei titoli per ogni blocco:
     * - contents.title_tag è il tag del titolo del blocco (title_formatting ACF,
     *   limitato a h1..h6/p, default 'h2'): <x-ui.title :tag="$contents['title_tag']">.
     * - contents.next_heading è il tag per i titoli figli del blocco
     *   (sottotitoli, titoli delle card, ...), da \App\get_next_heading_level(title_tag).
     *
     * @param  array<string, mixed>  $block  I dati del blocco ACF.
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
     * } I dati del blocco preparati, raggruppati per tipo.
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

        // Repeater: false/null quando non ci sono righe.
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
     * Classi Tailwind di un preset di padding (es. 'custom-py-m').
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
     * Classi Tailwind di un preset di margin (es. 'custom-mt-s').
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
