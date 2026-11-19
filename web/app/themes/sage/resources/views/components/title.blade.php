@props([
  'tag' => 'p',
  'class' => '',
  'textColor' => ''
])

@php
  // Only safe text tags can be printed: anything else falls back to h2.
  $tag = in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span'], true) ? $tag : 'h2';

  $attributes = $attributes->merge([
    'class' => $class,
    'style' => $textColor ? "color: {$textColor}" : null,
  ]);
@endphp

<{{ $tag }} {{ $attributes }}>
  {{ $slot }}
</{{ $tag }}>
