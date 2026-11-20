{{--
  Componente titolo.

  Props:
  - tag (string)        h1–h6, p o span. Default e fallback per valori non ammessi: h2.
  - textColor (string)  Colore esadecimale (#rgb/#rrggbb), validato con
                        sanitize_hex_color(); valori non validi sono ignorati.

  Gli attributi extra (class, id, ...) vengono uniti all'elemento: le classi
  passate si sommano, lo style passato viene unito a quello del colore.
--}}
@props([
  'tag' => 'h2',
  'textColor' => null,
])

@php
  // Only safe text tags can be printed: anything else falls back to h2.
  $tag = in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span'], true) ? $tag : 'h2';

  $color = $textColor ? sanitize_hex_color($textColor) : null;

  if ($color) {
    $attributes = $attributes->merge(['style' => "color: {$color};"]);
  }
@endphp

<{{ $tag }} {{ $attributes }}>
  {{ $slot }}
</{{ $tag }}>
