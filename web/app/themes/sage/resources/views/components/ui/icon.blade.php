{{--
  Icona Phosphor (https://phosphoricons.com) come SVG inline: vedi App\Icons\Phosphor.

  Uso:
    <x-ui.icon name="arrow-right" />
    <x-ui.icon name="heart" weight="fill" class="size-6 text-primary-500" />
    <x-ui.icon name="warning" label="Attenzione" />

  Props:
  - name (string)        Slug dell'icona (es. arrow-right, magnifying-glass): solo
                         minuscole, cifre e trattini. Catalogo: phosphoricons.com.
  - weight (string)      'thin', 'light', 'regular' (default), 'bold', 'fill', 'duotone'.
  - label (string|null)  Nome accessibile. Senza label l'icona è decorativa
                         (aria-hidden="true", focusable="false"): usala così accanto
                         a un testo o dentro un elemento che ha già un nome
                         (aria-label del pulsante). Con label diventa role="img" con
                         aria-label e <title>.

  Dimensione: 1em di default (segue il font-size del contesto); width e height sono
  attributi di presentazione, quindi qualsiasi classe (size-6, w-5 h-auto) li
  sostituisce. L'svg ha data-icon: la regola di base di app.css lo rende
  inline-block allineato al testo (block, flex o align-* la sostituiscono).
  Colore: fill="currentColor", si cambia con le classi text-*.
  Gli attributi extra (class, id, data-*, ...) vengono uniti all'<svg>; xmlns,
  viewBox, fill, role, aria-hidden, aria-label e focusable sono gestiti dal
  componente e quelli passati dall'esterno vengono ignorati.
  Solo name e weight sono validati: gli altri attributi (anche on*) passano così
  come sono, solo escapati. Non passare mai attributi o chiavi presi da input
  utente o da campi ACF.

  Solo le icone usate entrano nel tema (resources/icons/phosphor.php, generato da
  npm run icons): i tag con name e weight statici vengono trovati da soli, le
  icone dinamiche (:name="$icon", name="{{ $icon }}") vanno elencate in
  resources/icons/allowlist.json.
  Nome o peso non validi: nessun output in produzione; in development una riga in
  error_log, un avviso in Query Monitor e un commento HTML, senza interrompere
  la pagina.
--}}
{{-- blade-formatter-disable --}}
@props([
  'name' => '',
  'weight' => App\Icons\Phosphor::DEFAULT_WEIGHT,
  'label' => null,
])
{{-- blade-formatter-enable --}}
{!! App\Icons\Phosphor::render(
    (string) $name,
    (string) $weight,
    $attributes->getAttributes(),
    $label === null ? null : (string) $label,
) !!}
