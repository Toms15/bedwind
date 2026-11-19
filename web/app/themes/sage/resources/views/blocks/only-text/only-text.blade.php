@php
  /**
   * Block Name: Only text
   *
   * This is the template that displays the Only text block.
   */

  $data = get_block_data($block);
  $settings = $data['settings'];
  $contents = $data['contents'];
  $spacings = $data['spacings'];

  $title_tag = in_array($contents['title_formatting'], ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'], true)
    ? $contents['title_formatting']
    : 'h2';

  $section_style = implode(' ', array_filter([
    $settings['background_color'] ? 'background-color: '.$settings['background_color'].';' : null,
    $settings['text_color'] ? 'color: '.$settings['text_color'].';' : null,
  ]));

  $buttons = [];
  foreach ($contents['buttons'] as $row) {
    $link = $row['button'] ?? null;
    $url = is_array($link) && ! empty($link['url']) ? sanitize_url($link['url']) : '';

    if (! $url) {
      continue;
    }

    $buttons[] = [
      'url' => $url,
      'target' => in_array($link['target'] ?? '', ['_self', '_blank'], true) ? $link['target'] : '_self',
      'label' => ! empty($link['title']) ? $link['title'] : $link['url'],
    ];
  }
@endphp

<section
  id="{{ $settings['id'] }}"
  @class(array_filter(['w-full overflow-x-clip', $spacings['padding'], $spacings['margin']]))
  @if ($section_style)style="{{ $section_style }}"@endif
>
  <div class="container mx-auto px-6 3xl:px-0">
    @if ($contents['title'])
      <x-title :tag="$title_tag" class="mb-6">{{ $contents['title'] }}</x-title>
    @endif

    @if ($contents['content'])
      <div>
        {!! $contents['content'] !!}
      </div>
    @endif

    @if ($buttons)
      <div class="mt-8 flex flex-wrap gap-4">
        @foreach ($buttons as $button)
          <x-button :link="$button['url']" :target="$button['target']" :label="$button['label']">
            {{ $button['label'] }}
          </x-button>
        @endforeach
      </div>
    @endif
  </div>
</section>
