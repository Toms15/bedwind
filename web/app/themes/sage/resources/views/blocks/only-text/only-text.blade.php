@php
  /**
   * Block Name: Only text
   *
   * This is the template that displays the Only text block.
   */

  $blockId = $block['anchor'] ?? '';
  $padding = get_field('padding');
  $padding_dimension = get_field('padding_dimension');
  $margin = get_field('margin');
  $margin_dimension = get_field('margin_dimension');
  $background_color = get_field('background_color');
  $text_color = get_field('text_color');
  $title = get_field('title');
  $title_formatting = get_field('title_formatting');
  $content = get_field('content');
  $button = get_field('button');

  if($padding !== 'no') {
      $custom_padding = 'custom-' . $padding . '-' . $padding_dimension;
  } else {
      $custom_padding = 'no-padding';
  }
  if($margin !== 'no') {
      $custom_margin = 'custom-' . $margin . '-' . $margin_dimension;
  } else {
      $custom_margin = 'no-margin';
  }

  $padding = get_custom_padding($custom_padding);
  $margin = get_custom_margin($custom_margin);

@endphp

@include('modules.contents.only-text', [
  'id' => $blockId,
  'padding' => $padding,
  'margin' => $margin,
  'background_color' => $background_color,
  'text_color' => $text_color,
  'title' => $title,
  'title_formatting' => $title_formatting,
  'content' => $content,
  'button' => $button,
])
