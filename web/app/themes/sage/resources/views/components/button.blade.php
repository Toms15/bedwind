@props([
    'link' => null,
    'target' => '_self',
    'type' => 'simple',
    'label' => null,
    'disabled' => false,
    'customColor' => null,
])

@php
$class = match ($type) {
    'simple' => '...',
    'secondary' => '...',
    'white' => '...',
    'black' => '...',
    default => '...',
};
@endphp

@if($disabled)
    <button
        disabled
        class="w-auto inline-flex items-center justify-center {{ $class }}"
        @if(!empty($customColor))
            style="background-color: {{ $customColor }};"
        @endif
    >
        {{ $slot }}
    </button>
@else
    <a
        href="{{ $link }}"
        target="{{ $target }}"
        aria-label="{{ $label ?: $message }}"
        title="{{ $label ?: $message }}"
        class="w-auto inline-flex items-center justify-center {{ $class }}"
        @if(!empty($customColor))
            style="background-color: {{ $customColor }};"
        @endif
    >
        {{ $slot }}
    </a>
@endif
