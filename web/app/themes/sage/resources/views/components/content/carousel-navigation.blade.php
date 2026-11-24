{{-- blade-formatter-disable --}}
@props([
  'id',
])
{{-- blade-formatter-enable --}}

<div {{ $attributes->merge(['class' => 'flex items-center justify-start gap-2']) }}>
  <button
    type="button"
    class="{{ $id }}-prev w-10 h-10 flex items-center justify-center rounded-full bg-black-950/30 backdrop-blur-md cursor-pointer [&.swiper-button-disabled]:pointer-events-none [&.swiper-button-disabled]:opacity-50"
    aria-label="{{ __('Slide precedente', 'sage') }}"
  >
    <x-ui.icon name="arrow-left" class="w-5 h-auto fill-white" />
  </button>

  <button
    type="button"
    class="{{ $id }}-next w-10 h-10 flex items-center justify-center rounded-full bg-black-950/30 backdrop-blur-md cursor-pointer [&.swiper-button-disabled]:pointer-events-none [&.swiper-button-disabled]:opacity-50"
    aria-label="{{ __('Slide successiva', 'sage') }}"
  >
    <x-ui.icon name="arrow-right" class="w-5 h-auto fill-white" />
  </button>
</div>
