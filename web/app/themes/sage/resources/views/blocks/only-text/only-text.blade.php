{{-- blade-formatter-disable --}}
@php
  /**
   * Block Name: Only text
   *
   * Prepara i dati del blocco Only text; il markup sta in modules/only-text.
   */

  $data = \App\Blocks\BlockRenderer::data($block);
  $settings = $data['settings'];
  $contents = $data['contents'];
  $spacings = $data['spacings'];

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
{{-- blade-formatter-enable --}}

@include('modules.only-text', [
  'settings' => $settings,
  'contents' => $contents,
  'spacings' => $spacings,
  'section_style' => $section_style,
  'buttons' => $buttons,
])
