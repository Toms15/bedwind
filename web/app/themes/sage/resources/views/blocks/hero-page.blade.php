<section class="hero-page bg-gradient-to-r from-cafe to-bookshop text-white py-20">
  <div class="container mx-auto px-4 text-center">
    <h1 class="text-h1 text-black-100">{{ get_field('title') }}</h1>
    <p class="text-x2 mb-8">{{ get_field('subtitle') }}</p>
  </div>
</section>

@once
  @push('scripts')
    @vite(['resources/js/blocks/hero-page.js'])
  @endpush
@endonce
