{{--
  Breadcrumb del contenuto corrente, con JSON-LD BreadcrumbList.

  Uso: <x-content.breadcrumbs /> in qualsiasi template, layout o blocco.

  Props:
  - items (array|null) Voci [['label' => string, 'url' => ?string], ...].
                       Default: App\Navigation\Breadcrumbs::items() (casi gestiti
                       documentati nella classe, filtro 'sage/breadcrumbs').
                       Le voci passate a mano seguono lo stesso contratto
                       (App\Navigation\Breadcrumbs::normalize()): label in testo
                       semplice, l'ultima è la pagina corrente e non ha link.
                       Gli URL passano da sanitize_url(): se il risultato è
                       vuoto (es. javascript:) la voce è stampata senza link.
  - jsonld (bool)      Stampa lo <script type="application/ld+json">
                       BreadcrumbList. Default true; disattivarlo se un plugin
                       SEO genera già il proprio BreadcrumbList.

  Accessibilità (pattern WAI-ARIA APG / Bootstrap Italia): <nav> con
  aria-label, lista ordinata, separatori aria-hidden, aria-current="page"
  sull'ultima voce.

  JSON-LD secondo le linee guida Google (developers.google.com/search/docs/
  appearance/structured-data/breadcrumb): solo le voci con URL più l'ultima
  (le voci intermedie senza link restano nell'HTML ma non nel JSON-LD),
  position da 1, almeno due voci, 'item' con URL assoluto omesso sull'ultima
  voce (Google usa l'URL della pagina). Codificato con JSON_HEX_TAG: un
  titolo con </script> non può chiudere il tag.

  Nessun output se non ci sono voci (es. front page).
--}}
{{-- blade-formatter-disable --}}
@props([
  'items' => null,
  'jsonld' => true,
])

@php
  $items = array_map(
    fn (array $item): array => [...$item, 'url' => $item['url'] ? (sanitize_url($item['url']) ?: null) : null],
    App\Navigation\Breadcrumbs::normalize(is_array($items) ? $items : App\Navigation\Breadcrumbs::items())
  );
  $last = array_key_last($items);

  $schema = null;

  // JSON-LD: only items with a URL, plus the last one (Google requires
  // 'item' on every ListItem except the last). Positions are renumbered
  // from 1 and the "at least two items" check applies to this list.
  $ld = array_values(array_filter(
    $items,
    fn (array $item, int $index): bool => $index === $last || (bool) $item['url'],
    ARRAY_FILTER_USE_BOTH
  ));
  $ldLast = array_key_last($ld);

  if ($jsonld && count($ld) >= 2) {
    $schema = [
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => array_map(
        function (array $item, int $index) use ($ldLast): array {
          $element = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $item['label'],
          ];

          if ($index !== $ldLast) {
            $element['item'] = WP_Http::make_absolute_url($item['url'], home_url('/'));
          }

          return $element;
        },
        $ld,
        array_keys($ld)
      ),
    ];
  }
@endphp
{{-- blade-formatter-enable --}}

@if ($items)
  <nav {{ $attributes->class('text-small font-body text-black-700') }}
    aria-label="{{ __('Percorso di navigazione', 'sage') }}">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1" role="list">
      @foreach ($items as $index => $item)
        <li class="inline-flex items-center gap-x-2 min-w-0">
          @if ($index === $last)
            <span class="text-black-950 font-medium truncate" aria-current="page">{{ $item['label'] }}</span>
          @elseif ($item['url'])
            <a class="text-black-950 underline-offset-2 hover:text-primary-500 hover:underline-offset-4"
              href="{{ $item['url'] }}">{{ $item['label'] }}</a>
          @else
            <span>{{ $item['label'] }}</span>
          @endif

          @if ($index !== $last)
            <span aria-hidden="true">/</span>
          @endif
        </li>
      @endforeach
    </ol>
  </nav>

  @if ($schema)
    <script type="application/ld+json">{!! wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
  @endif
@endif
