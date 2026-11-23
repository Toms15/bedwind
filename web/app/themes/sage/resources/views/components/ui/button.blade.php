{{--
  Componente pulsante/link.

  Props:
  - link (string|null)        URL di destinazione. Viene sanificato con sanitize_url():
                              se risulta vuoto (assente o con protocollo non ammesso,
                              es. javascript:) e il pulsante non è disabled, il
                              componente non renderizza nulla (con WP_DEBUG attivo
                              emette solo un commento HTML diagnostico).
  - target (string)           '_self' (default) o '_blank'. Altri valori → '_self'.
                              Con '_blank' viene aggiunto rel="noopener noreferrer".
  - type (string)             Variante: 'primary' (default), 'secondary', 'white', 'black'.
                              'simple' è un alias retrocompatibile di 'primary'.
  - label (string|null)       Nome accessibile alternativo. Emesso come aria-label solo
                              se diverso dal testo visibile dello slot (WCAG 2.5.3).
  - disabled (bool)           Renderizza un <button type="button" disabled aria-disabled="true">.
  - customColor (string|null) Colore di sfondo esadecimale (#rgb/#rrggbb), validato
                              con sanitize_hex_color(); valori non validi sono ignorati.

  Gli attributi extra (class, data-*, id, ...) vengono uniti all'elemento.
  Gli attributi gestiti dal componente (href, target, rel, type, disabled,
  aria-disabled) passati dall'esterno vengono ignorati per evitare duplicati.
--}}
{{-- blade-formatter-disable --}}
@props([
  'link' => null,
  'target' => '_self',
  'type' => 'primary',
  'label' => null,
  'disabled' => false,
  'customColor' => null,
])

@php
  $variant = match ($type) {
    'secondary' => 'border border-current bg-transparent text-current hover:bg-black/5',
    'white' => 'bg-white text-black hover:bg-white/90',
    'black' => 'bg-black text-white hover:bg-black/80',
    default => 'bg-primary-500 text-white hover:bg-primary-500/90',
  };

  $classes = [
    'inline-flex items-center justify-center gap-2 px-6 py-3 rounded font-medium no-underline transition-colors',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current',
    $variant,
    'cursor-not-allowed opacity-50' => $disabled,
  ];

  $url = $link ? sanitize_url((string) $link) : '';
  $target = $target === '_blank' ? '_blank' : '_self';

  // Lo slot arriva già escapato (es. "&amp;"), il label no: si confrontano
  // entrambi come testo semplice decodificato e con spazi compressi.
  $normalize = fn (string $value): string => trim((string) preg_replace(
    '/\s+/u',
    ' ',
    html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
  ));

  $text = $normalize((string) $slot);
  $label = is_string($label) ? $normalize($label) : '';
  $ariaLabel = $label !== '' && $label !== $text ? $label : null;

  $color = $customColor ? sanitize_hex_color($customColor) : null;

  $attributes = $attributes
    ->except(['href', 'target', 'rel', 'type', 'disabled', 'aria-disabled'])
    ->class($classes)
    ->merge(array_filter([
      'aria-label' => $ariaLabel,
      'style' => $color ? "background-color: {$color};" : null,
    ]));
@endphp
{{-- blade-formatter-enable --}}

@if ($disabled)
  <button type="button" disabled aria-disabled="true" {{ $attributes }}>
    {{ $slot }}
  </button>
@elseif ($url)
  <a
    href="{{ $url }}"
    @if ($target === '_blank') target="_blank" rel="noopener noreferrer" @endif
    {{ $attributes }}>
    {{ $slot }}
  </a>
@elseif (defined('WP_DEBUG') && WP_DEBUG)
  <!-- x-ui.button: link vuoto o non valido -->
@endif
