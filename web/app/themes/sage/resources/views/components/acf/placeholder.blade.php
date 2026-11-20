{{--
  Placeholder dei blocchi ACF nell'editor (anteprima del blocco).

  Props:
  - block (array)         Dati del blocco ACF: ne usa 'title' e 'description'.
  - title (string|null)   Titolo della sezione (campo ACF 'title'), mostrato come
                          testo semplice. Se vuoto si mostra il messaggio.
  - message (string|null) Testo mostrato quando manca il titolo. Default tradotto:
                          "Nessun titolo impostato per questa sezione.".
  - variant (string)      'default' oppure 'empty': sfondo a righe, solo nome del
                          blocco, senza descrizione né titolo.

  Il pulsante con data-acf-expanded-editor apre l'editor espanso (resources/js/editor.js).
--}}
{{-- blade-formatter-disable --}}
@props([
  'block' => [],
  'title' => null,
  'message' => null,
  'variant' => 'default',
])

@php
  $isEmpty = $variant === 'empty';
  $title = is_string($title) ? trim(wp_strip_all_tags($title)) : '';
  $message ??= __('Nessun titolo impostato per questa sezione.', 'sage');
@endphp
{{-- blade-formatter-enable --}}

<div
  {{ $attributes->class([
      'acf-block-placeholder border border-slate-400 p-6 flex items-start justify-start gap-3 flex-col',
      'bg-[repeating-linear-gradient(-45deg,_rgb(144_161_185_/_50%)_0,_rgb(144_161_185_/_50%)_4px,_transparent_0,_transparent_50%)] bg-[size:12px_12px]' => $isEmpty,
  ]) }}>
  @if (!empty($block['title']))
    <span
      class="bg-slate-400 w-auto inline-flex items-center justify-center h-6 px-2 rounded text-small text-black font-body font-medium">
      {{ $block['title'] }}
    </span>
  @endif

  @unless ($isEmpty)
    @if (!empty($block['description']))
      <span class="text-small text-black font-body font-medium">
        {{ $block['description'] }}
      </span>
    @endif

    @if ($title !== '')
      <span class="text-base text-black font-body font-normal">{{ $title }}</span>
    @else
      <span class="text-base text-slate-500 font-body font-normal">{{ $message }}</span>
    @endif
  @endunless

  <button
    type="button"
    class="components-button is-primary"
    data-acf-expanded-editor>
    {{ __('Modifica blocco', 'sage') }}
  </button>
</div>
