{{--
  Styleguide del tema (/styleguide/), solo con WP_ENV === 'development': vedi App\Styleguide.

  Token (colori, font, dimensioni del testo) letti dal theme.json generato dalla build
  (public/build/assets/theme.json, dal @theme di resources/css/theme.css); componenti
  con dati di esempio statici (nessuna query sui contenuti). Non è collegata da nessuna
  parte del sito ed è noindex.

  Le utility usate solo qui stanno in resources/css/styleguide.css (esclusa da app.css
  ed editor.css), accodato solo su questa pagina da App\Styleguide::enqueueAssets().
  I titoli dimostrativi hanno role="presentation": lo stile resta, ma la struttura
  reale della pagina è un solo h1 e un h2 per sezione.
--}}
{{-- blade-formatter-disable --}}
@php
  $tokens = \App\Styleguide::tokens();

  $sections = [
    'colori' => __('Colori', 'sage'),
    'tipografia' => __('Tipografia', 'sage'),
    'titoli' => __('Titoli', 'sage'),
    'link' => __('Link e focus', 'sage'),
    'button' => 'x-ui.button',
    'title' => 'x-ui.title',
    'alert' => 'x-ui.alert',
    'image' => 'x-ui.image',
    'share' => 'x-content.share',
    'breadcrumbs' => 'x-content.breadcrumbs',
    'carousel-navigation' => 'x-content.carousel-navigation',
    'placeholder' => 'x-acf.placeholder',
  ];

  $sample = __('Testo di esempio per la styleguide del tema.', 'sage');

  $missing = __('Token non disponibili: esegui npm run build per generare public/build/assets/theme.json.', 'sage');

  $against = [
    'white' => __('Su bianco', 'sage'),
    'black' => __('Su nero', 'sage'),
  ];

  $breadcrumbs = [
    ['label' => __('Home', 'sage'), 'url' => home_url('/')],
    ['label' => __('Sezione', 'sage'), 'url' => home_url('/')],
    ['label' => __('Pagina corrente', 'sage'), 'url' => null],
  ];
@endphp
{{-- blade-formatter-enable --}}

@extends('layouts.app')

@section('content')
  <div class="container mx-auto px-6 3xl:px-0 py-12 flex flex-col gap-12">
    <header class="flex flex-col gap-4">
      <h1 class="text-4xl font-body font-medium">{{ __('Styleguide', 'sage') }}</h1>
      <p class="text-small">
        {{ __('Token e componenti del tema. Pagina disponibile solo in sviluppo (WP_ENV development), esclusa dai motori di ricerca.', 'sage') }}
      </p>
      <nav aria-label="{{ __('Sezioni della styleguide', 'sage') }}">
        <ul class="flex flex-wrap items-center gap-x-4 gap-y-1 text-small" role="list">
          @foreach ($sections as $id => $label)
            <li><a href="#{{ $id }}">{{ $label }}</a></li>
          @endforeach
        </ul>
      </nav>
    </header>

    <section id="colori" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium">{{ __('Colori', 'sage') }}</h2>
      <p class="text-small">
        {{ __('Rapporto di contrasto WCAG 2.2 di ogni colore con bianco e nero. Testo normale: AA da 4.5:1, AAA da 7:1; testo grande (da 24px, o 18.66px in grassetto): AA da 3:1, AAA da 4.5:1. I colori oklch sono convertiti in sRGB.', 'sage') }}
      </p>
      @if ($tokens['colors'])
        <ul class="grid grid-cols-2 md:grid-cols-4 gap-4" role="list">
          @foreach ($tokens['colors'] as $color)
            <li class="flex flex-col gap-2">
              <span class="h-16 rounded border border-slate-400" style="background-color: {{ $color['color'] }};"></span>
              <span class="text-small font-medium">{{ $color['name'] }}</span>
              <code class="text-small">--color-{{ $color['slug'] }}: {{ $color['color'] }}</code>
              <dl class="text-small">
                @forelse (\App\Styleguide::contrasts($color['color']) as $name => $contrast)
                  <div>
                    <dt class="inline">{{ $against[$name] }}:</dt>
                    <dd class="inline">
                      {{ number_format_i18n(floor($contrast['ratio'] * 100) / 100, 2) }}:1 –
                      {{ __('normale', 'sage') }} {{ $contrast['normal'] ?? __('no', 'sage') }},
                      {{ __('grande', 'sage') }} {{ $contrast['large'] ?? __('no', 'sage') }}
                    </dd>
                  </div>
                @empty
                  <div>
                    <dt class="inline">{{ __('Contrasto', 'sage') }}:</dt>
                    <dd class="inline">{{ __('formato colore non supportato', 'sage') }}</dd>
                  </div>
                @endforelse
              </dl>
            </li>
          @endforeach
        </ul>
      @else
        <x-ui.alert type="caution">{{ $missing }}</x-ui.alert>
      @endif
    </section>

    <section id="tipografia" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium">{{ __('Tipografia', 'sage') }}</h2>

      @if ($tokens['fonts'] || $tokens['sizes'])
        <h3 class="text-xl font-medium">{{ __('Famiglie', 'sage') }}</h3>
        <ul class="flex flex-col gap-4" role="list">
          @foreach ($tokens['fonts'] as $font)
            <li class="flex flex-col gap-1">
              <code class="text-small">--font-{{ $font['slug'] }}</code>
              <span class="text-xl" style="font-family: {{ $font['family'] }};">{{ $sample }}</span>
            </li>
          @endforeach
        </ul>

        <h3 class="text-xl font-medium">{{ __('Dimensioni', 'sage') }}</h3>
        <p class="text-small">
          {{ __('Alcune dimensioni possono avere lo stesso valore (es. sm e small, 0.875rem): small è il token del tema, sm il default di Tailwind.', 'sage') }}
        </p>
        <ul class="flex flex-col gap-4" role="list">
          @foreach ($tokens['sizes'] as $size)
            <li class="flex flex-col gap-1">
              <code class="text-small">text-{{ $size['slug'] }} ({{ $size['size'] }})</code>
              <span class="font-body" style="font-size: {{ $size['size'] }};">{{ $sample }}</span>
            </li>
          @endforeach
        </ul>
      @else
        <x-ui.alert type="caution">{{ $missing }}</x-ui.alert>
      @endif
    </section>

    <section id="titoli" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium">{{ __('Titoli', 'sage') }}</h2>
      <p class="text-small">
        {{ __('Elementi h1–h6 senza classi, come li stampa il contenuto. Sono esempi con role="presentation": non fanno parte della struttura dei titoli della pagina.', 'sage') }}
      </p>
      <div class="flex flex-col gap-2">
        <h1 role="presentation">{{ __('Titolo h1', 'sage') }}</h1>
        <h2 role="presentation">{{ __('Titolo h2', 'sage') }}</h2>
        <h3 role="presentation">{{ __('Titolo h3', 'sage') }}</h3>
        <h4 role="presentation">{{ __('Titolo h4', 'sage') }}</h4>
        <h5 role="presentation">{{ __('Titolo h5', 'sage') }}</h5>
        <h6 role="presentation">{{ __('Titolo h6', 'sage') }}</h6>
      </div>
    </section>

    <section id="link" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium">{{ __('Link e focus', 'sage') }}</h2>
      <p>{{ __('Premi Tab per vedere il focus visibile su link e pulsanti.', 'sage') }}</p>
      <ul class="flex flex-col gap-2" role="list">
        <li><a href="#link">{{ __('Link di esempio nel testo', 'sage') }}</a></li>
        <li>
          <a
            href="{{ home_url('/' . \App\Styleguide::PATH . '/') }}">{{ __('Link visitato (questa pagina)', 'sage') }}</a>
          <span class="text-small">{{ __('– il tema non definisce uno stile :visited.', 'sage') }}</span>
        </li>
        <li>
          <a href="#link" class="no-underline">{{ __('Link con no-underline', 'sage') }}</a>
          <span
            class="text-small">{{ __('– la sottolineatura è nel layer base: senza, il link va distinto in altro modo (WCAG 1.4.1).', 'sage') }}</span>
        </li>
      </ul>
    </section>

    <section id="button" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium"><code>&lt;x-ui.button&gt;</code></h2>
      <div class="flex flex-wrap items-center gap-4">
        <x-ui.button link="#button">{{ __('Primario', 'sage') }}</x-ui.button>
        <x-ui.button link="#button" type="secondary">{{ __('Secondario', 'sage') }}</x-ui.button>
        <x-ui.button link="#button" type="black">{{ __('Nero', 'sage') }}</x-ui.button>
        <x-ui.button link="#button" target="_blank">{{ __('Nuova scheda', 'sage') }}</x-ui.button>
        <x-ui.button disabled>{{ __('Disabilitato', 'sage') }}</x-ui.button>
        <x-ui.button link="#button" type="black" custom-color="#7c3aed">customColor #7c3aed</x-ui.button>
      </div>
      <div class="flex flex-wrap items-center gap-4 p-6 bg-black-950">
        <x-ui.button link="#button" type="white">{{ __('Bianco', 'sage') }}</x-ui.button>
        <x-ui.button link="#button" type="secondary" class="text-white">{{ __('Secondario', 'sage') }}</x-ui.button>
        <x-ui.button type="white" disabled>{{ __('Disabilitato', 'sage') }}</x-ui.button>
      </div>
    </section>

    <section id="title" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium"><code>&lt;x-ui.title&gt;</code></h2>
      @foreach (['h2', 'h3', 'h4', 'p'] as $tag)
        <x-ui.title :tag="$tag" class="text-xl font-medium" role="presentation">
          {{ __('Titolo', 'sage') }} ({{ $tag }})
        </x-ui.title>
      @endforeach
    </section>

    <section id="alert" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium"><code>&lt;x-ui.alert&gt;</code></h2>
      <x-ui.alert>{{ __('Informazione (default).', 'sage') }}</x-ui.alert>
      <x-ui.alert type="success">{{ __('Operazione completata.', 'sage') }}</x-ui.alert>
      <x-ui.alert type="caution">{{ __('Attenzione: controlla i dati.', 'sage') }}</x-ui.alert>
      <x-ui.alert type="warning">{{ __('Errore: operazione non riuscita.', 'sage') }}</x-ui.alert>
      <p class="text-small">
        {{ __('role="status" è il default. role="alert" (live region assertiva) va passato solo per messaggi inseriti dinamicamente:', 'sage') }}
        <code>&lt;x-ui.alert type="warning" role="alert"&gt;…&lt;/x-ui.alert&gt;</code>
      </p>
    </section>

    <section id="image" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium"><code>&lt;x-ui.image&gt;</code></h2>
      <div class="grid md:grid-cols-2 gap-6">
        <figure class="flex flex-col gap-2">
          <x-ui.image :id="0" class="w-full h-auto" />
          <figcaption class="text-small">{{ __('Senza immagine: fallback del tema.', 'sage') }}</figcaption>
        </figure>
      </div>
    </section>

    <section id="share" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium"><code>&lt;x-content.share&gt;</code></h2>
      <x-content.share :link="home_url('/')" :title="__('Styleguide', 'sage')" />
    </section>

    <section id="breadcrumbs" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium"><code>&lt;x-content.breadcrumbs&gt;</code></h2>
      <x-content.breadcrumbs :items="$breadcrumbs" :jsonld="false" />
    </section>

    <section id="carousel-navigation" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium"><code>&lt;x-content.carousel-navigation&gt;</code></h2>
      <x-content.carousel-navigation id="styleguide-carousel" />
    </section>

    <section id="placeholder" class="flex flex-col gap-6">
      <h2 class="text-2xl font-medium"><code>&lt;x-acf.placeholder&gt;</code></h2>
      <x-acf.placeholder :block="[
          'title' => __('Solo testo', 'sage'),
          'description' => __('Titolo, testo e pulsanti.', 'sage'),
      ]" :title="__('Titolo della sezione', 'sage')" />
      <x-acf.placeholder :block="['title' => __('Solo testo', 'sage')]" />
    </section>
  </div>
@endsection
