<section class="hero-page bg-gradient-to-r from-blue-600 to-purple-700 text-white py-20">
  <div class="container mx-auto px-4 text-center">
    <h1 class="text-5xl font-bold mb-6">{{ get_field('title') }}</h1>
    <p class="text-xl mb-8">{{ get_field('subtitle') }}</p>
  </div>
</section>

@once
  @push('scripts')
    @vite(['resources/js/blocks/hero-page.js'])
  @endpush
@endonce
