{{--
  Componente immagine responsive, basato su wp_get_attachment_image().

  Props:
  - id (int|null)        ID dell'allegato immagine.
  - size (string|array)  Dimensione registrata o [larghezza, altezza]. Default 'large'.
  - sizes (string|null)  Attributo sizes personalizzato (es. "(min-width: 64rem) 50vw, 100vw").
                         Senza, WP calcola quello della dimensione richiesta.
  - eager (bool)         Immagine sopra la piega/LCP: loading="eager",
                         fetchpriority="high", decoding="async". Usalo su una
                         sola immagine per pagina.
  - alt (string|null)    Sovrascrive il testo alternativo dell'allegato
                         ('' per un'immagine decorativa).
  - fallback (bool)      Con id vuoto o non immagine stampa resources/images/fallback.svg
                         (alt "", 1280×720, lazy). false: non stampa nulla. Default true.
  - img-id (string|null) Attributo HTML id dell'<img>: `id` è già l'ID dell'allegato.

  Gli attributi extra (class, data-*, ...) vengono passati all'<img>. Il merge
  delle classi con "attachment-{size} size-{size}" vale solo con size stringa.

  Cosa fa già WordPress (7.1) e quindi il componente non forza senza eager:
  - wp_get_attachment_image() genera srcset, sizes, width/height e decoding="async".
  - wp_get_loading_optimization_attributes() decide loading/fetchpriority:
    in genere loading="lazy"; fetchpriority="high" sulla prima immagine grande
    (≥ 50.000 px²) che stima sopra la piega; con eager il flag viene consumato,
    quindi WP non dà priorità alta a un'altra immagine.
  - Con loading="lazy" WP antepone "auto, " a sizes (filtro wp_img_tag_add_auto_sizes),
    anche a un sizes personalizzato: il browser usa la larghezza reale del layout.
--}}
{{-- blade-formatter-disable --}}
@props([
  'id' => null,
  'size' => 'large',
  'sizes' => null,
  'eager' => false,
  'alt' => null,
  'fallback' => true,
  'imgId' => null,
])

@php
  $id = (int) $id;

  // Sull'<img> si possono stampare solo attributi scalari.
  $attrs = array_filter($attributes->getAttributes(), 'is_scalar');

  // Una classe personalizzata sostituisce quella di default di WP: si mantiene "attachment-{size} size-{size}".
  if (! empty($attrs['class']) && is_string($size)) {
    $attrs['class'] = "attachment-{$size} size-{$size} {$attrs['class']}";
  }

  if ($imgId) {
    $attrs['id'] = $imgId;
  }

  if ($sizes) {
    $attrs['sizes'] = $sizes;
  }

  if ($alt !== null) {
    $attrs['alt'] = $alt;
  }

  if ($eager) {
    $attrs = array_merge($attrs, [
      'loading' => 'eager',
      'fetchpriority' => 'high',
      'decoding' => 'async',
    ]);
  }

  $html = $id && wp_attachment_is_image($id)
    ? wp_get_attachment_image($id, $size, false, $attrs)
    : '';

  $fallbackUrl = ! $html && $fallback ? \App\get_fallback_image_url() : '';
@endphp
{{-- blade-formatter-enable --}}

@if ($html)
  {!! $html !!}
@elseif ($fallbackUrl)
  <img
    src="{{ $fallbackUrl }}"
    width="1280"
    height="720"
    alt=""
    loading="{{ $eager ? 'eager' : 'lazy' }}"
    decoding="async"
    @if ($eager) fetchpriority="high" @endif
    @if ($imgId) id="{{ $imgId }}" @endif
    {{ $attributes->except(['alt', 'src', 'width', 'height', 'loading', 'decoding', 'sizes', 'srcset']) }}>
@endif
