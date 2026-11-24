{{--
  Modulo: Only text

  Markup del blocco Only text. I dati sono preparati in blocks/only-text.

  @var array $settings Impostazioni del blocco (id, colori).
  @var array $contents Contenuti del blocco (title, title_tag, next_heading, content).
  @var array $spacings Classi di padding e margin.
  @var string $section_style Stile inline della section ('' se assente).
  @var list<array{url: string, target: string, label: string}> $buttons Pulsanti validi.
--}}

<section
  id="{{ $settings['id'] }}"
  {{-- blade-formatter-disable --}}
  @class(array_filter([
    'w-full overflow-x-clip',
    $spacings['padding'],
    $spacings['margin'],
  ]))
  {{-- blade-formatter-enable --}}
  @if ($section_style) style="{{ $section_style }}" @endif>
  <div class="container mx-auto px-6 3xl:px-0">
    @if ($contents['title'])
      <x-ui.title :tag="$contents['title_tag']" class="mb-6">{{ $contents['title'] }}</x-ui.title>
    @endif

    @if ($contents['content'])
      <div>
        {!! $contents['content'] !!}
      </div>
    @endif

    @if ($buttons)
      <div class="mt-8 flex flex-wrap gap-4">
        @foreach ($buttons as $button)
          <x-ui.button :link="$button['url']" :target="$button['target']" :label="$button['label']">
            {{ $button['label'] }}
          </x-ui.button>
        @endforeach
      </div>
    @endif
  </div>
</section>
