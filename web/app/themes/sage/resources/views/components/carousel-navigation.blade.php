@props([
  'id',
])

<div {{ $attributes->merge(['class' => 'flex items-center justify-start gap-2']) }}>
  <button
    type="button"
    class="{{ $id }}-prev w-10 h-10 flex items-center justify-center rounded-full bg-black-950/30 backdrop-blur-md cursor-pointer [&.swiper-button-disabled]:pointer-events-none [&.swiper-button-disabled]:opacity-50"
    aria-label="{{ __('Previous slide', 'sage') }}"
  >
    <svg
      class="w-5 h-auto fill-white rotate-180"
      viewBox="0 0 256 256"
      aria-hidden="true"
      focusable="false"
    >
      <path
        d="M221.66,133.66l-72,72a8,8,0,0,1-11.32-11.32L196.69,136H40a8,8,0,0,1,0-16H196.69L138.34,61.66a8,8,0,0,1,11.32-11.32l72,72A8,8,0,0,1,221.66,133.66Z"
      />
    </svg>
  </button>

  <button
    type="button"
    class="{{ $id }}-next w-10 h-10 flex items-center justify-center rounded-full bg-black-950/30 backdrop-blur-md cursor-pointer [&.swiper-button-disabled]:pointer-events-none [&.swiper-button-disabled]:opacity-50"
    aria-label="{{ __('Next slide', 'sage') }}"
  >
    <svg
      class="w-5 h-auto fill-white"
      viewBox="0 0 256 256"
      aria-hidden="true"
      focusable="false"
    >
      <path
        d="M221.66,133.66l-72,72a8,8,0,0,1-11.32-11.32L196.69,136H40a8,8,0,0,1,0-16H196.69L138.34,61.66a8,8,0,0,1,11.32-11.32l72,72A8,8,0,0,1,221.66,133.66Z"
      />
    </svg>
  </button>
</div>
