@props([
  'tag' => 'p',
  'class' => '',
  'textColor' => ''
])

@php
  $attributes = $attributes->merge([
      'class' => $class,
      'style' => $textColor ? "color: {$textColor}" : null,
  ]);
@endphp

<{{ $tag }} {{ $attributes }}>
    {{ $slot }}
</{{ $tag }}>
