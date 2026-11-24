# Convenzioni

Convenzioni adottate per questo tema. Gli strumenti (build, lint, qualità PHP, script di traduzione) sono descritti nel [README](README.md); il setup del progetto nel [README del progetto](../../../../README.md).

- [Blocchi ACF](#blocchi-acf)
- [Asset dei blocchi](#asset-dei-blocchi)
- [Nuovo blocco con il wizard](#nuovo-blocco-con-il-wizard)
- [Checklist per un nuovo blocco](#checklist-per-un-nuovo-blocco)
- [Internazionalizzazione](#internazionalizzazione)
- [PHP](#php)
- [Sicurezza ed escaping](#sicurezza-ed-escaping)
- [Componenti](#componenti)
- [Styleguide](#styleguide)
- [Rinominare il tema](#rinominare-il-tema)

## Blocchi ACF

Ogni blocco è un blocco ACF (`acf/<slug>`) diviso in tre file, più due asset facoltativi. Implementazione di riferimento: il blocco `only-text`.

| File                                             | Ruolo                                                                                            |
| ------------------------------------------------ | ------------------------------------------------------------------------------------------------ |
| `resources/views/blocks/<slug>/block.json`       | Metadati del blocco, registrato da `app/blocks.php` tramite il manifest dei blocchi              |
| `resources/views/blocks/<slug>/<slug>.blade.php` | View del blocco: prepara i dati, nessun markup                                                   |
| `resources/views/modules/<slug>.blade.php`       | Modulo: il markup, riceve dati pronti da stampare                                                |
| `resources/views/blocks/<slug>/view.js`          | Facoltativo: script module del frontend, caricato solo dove il blocco viene renderizzato         |
| `resources/views/blocks/<slug>/style.css`        | Facoltativo: foglio di stile del frontend, caricato solo dove il blocco viene renderizzato       |
| `resources/views/blocks/blocks-manifest.php`     | Generato da tutti i `block.json` (`npm run blocks:manifest`): versionato, non modificarlo a mano |

**Manifest dei blocchi**: `app/blocks.php` registra i blocchi con `wp_register_block_types_from_metadata_collection()` (WordPress 6.8+), leggendo i metadati di ogni blocco da `blocks-manifest.php` invece di cercare le cartelle con un glob e decodificare ogni `block.json` a ogni richiesta (senza la funzione o senza il manifest ripiega su un `register_block_type()` per cartella). La registrazione passa comunque da `register_block_type_from_metadata()` e dai suoi filtri, quindi ACF e le traduzioni (`textdomain`) funzionano come con `block.json`. Vengono registrati solo i blocchi elencati nel manifest, con i metadati che contiene: Vite lo riscrive a ogni build e, con `npm run dev`, ogni volta che un `block.json` viene aggiunto, modificato o rimosso; altrimenti esegui `npm run blocks:manifest`. Committalo insieme alle modifiche ai `block.json`: la CI fallisce se non è aggiornato (`npm run blocks:manifest:check`).

**`block.json`**

- `"name": "acf/<slug>"`, `"textdomain": "sage"`, `title`, `description` e `keywords` in italiano;
- `"supports": { "anchor": true, "align": false }`:
  - `align: false`: i blocchi del tema non usano l'allineamento dei blocchi (la sezione è già `w-full` con il suo `container`), quindi la toolbar di allineamento è nascosta (ACF registra `align: true` di default);
  - i blocchi del tema non usano InnerBlocks, quindi niente `supports.jsx` in `block.json`: ACF lo registra comunque come `true` di default, ma con `blockVersion` 3 l'editor lo ignora, quindi non va dichiarato (nemmeno come `false`). Il render callback non passa alle view il `$content` delle InnerBlocks; il campo ACF `content` è un'altra cosa e si legge con `get_field()`;
- `"acf": { "blockVersion": 3, "renderCallback": "App\\Blocks\\BlockRenderer::render" }`: un unico render callback per tutti i blocchi. Nell'editor mostra il `<x-acf.placeholder>` (nome del blocco, descrizione, campo titolo) invece di un'anteprima; nel frontend renderizza la view `blocks.<slug>.<slug>` (`blocks/<slug>/<slug>.blade.php`) con `$block` (il nome del file deve coincidere con lo slug, il prefisso `acf/` di `name` viene tolto), e nulla se la view manca;
- `category`: una delle categorie del tema registrate in `app/blocks.php` (`carousels`, `dynamic-content`, `static-content`, `commons`, `heroes`, `dividers`), mostrate per prime nell'inserter.

I blocchi ACF sono consentiti in ogni editor; i blocchi core solo nei post type di `sage/core_blocks_post_types` (`post`, `faq`) e negli editor dei widget e del sito; gli altri blocchi (es. di terze parti) solo se elencati da `sage/extra_blocks` (`app/blocks.php`).

**View del blocco** (`blocks/<slug>/<slug>.blade.php`): un unico blocco `@php`, racchiuso fra `{{-- blade-formatter-disable --}}`/`{{-- blade-formatter-enable --}}`, che

- chiama `\App\Blocks\BlockRenderer::data($block)`, che restituisce i dati comuni raggruppati in `settings` (`id`, `background_color`, `text_color`), `contents` (`title`, `title_formatting`, `title_tag`, `next_heading`, `content`, `buttons`) e `spacings` (`padding`, `margin` come classi Tailwind);
- legge e valida i campi specifici del blocco (`get_field()`), sanifica URL e colori (vedi [Sicurezza ed escaping](#sicurezza-ed-escaping)) e scarta le voci non valide;
- è seguito da `@include('modules.<slug>', [...])`, che passa solo ciò che il modulo stampa.

**Modulo** (`modules/<slug>.blade.php`): un'intestazione `{{-- --}}` con il `@var` di ogni variabile, poi:

```blade
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
    ...
  </div>
</section>
```

- `id`: `settings.id` è l'**anchor** del blocco se impostato, altrimenti l'id del blocco ACF (`block_…`), quindi ogni sezione ha un id;
- la `<section>` esterna ha sempre `w-full overflow-x-clip` più le classi di padding/margin del blocco; il contenuto sta in `<div class="container mx-auto px-6 3xl:px-0">`;
- le classi si compongono con `@class(array_filter([...]))` (i preset vuoti vengono scartati); un `@class` su più righe va racchiuso fra i marcatori di blade-formatter, dentro il tag, con l'array a 2 spazi;
- titoli: il titolo del blocco usa `<x-ui.title :tag="$contents['title_tag']">` (`title_formatting` di ACF, h1–h6/p, default `h2`); i titoli figli (sottotitoli, titoli delle card) usano `$contents['next_heading']`, calcolato da `\App\get_next_heading_level($title_tag)` (un livello sotto, `p` dopo `h6`). Per i livelli annidati passa `next_heading` più in basso con `\App\get_next_heading_level($contents['next_heading'])`.

**Field group**: stanno in `acf-json/` (local JSON di ACF, salvati lì da `acf/settings/save_json` in `app/setup.php`). Etichette, istruzioni e scelte sono in **italiano** (tradotte a runtime, vedi [Internazionalizzazione](#internazionalizzazione)). Ci sono due tipi di gruppi:

| Gruppo                            | File                       | Posizione                                    | Campi                                                                                                                           |
| --------------------------------- | -------------------------- | -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| _Impostazioni blocco_ (condiviso) | `group_69c1778d2ba98.json` | _Blocco è uguale a Tutti_                    | accordion _Spaziature e colori_, `padding`, `padding_dimension`, `margin`, `margin_dimension`, `background_color`, `text_color` |
| _Blocco: …_ (uno per blocco)      | `group_<id>.json`          | _Blocco è uguale a_ il blocco (`acf/<slug>`) | i campi del blocco, es. _Blocco: Solo testo_ (`group_6b0457c69122b.json`): `title`, `title_formatting`, `content`, `buttons`    |

- il gruppo condiviso ha `menu_order` 100, i gruppi dei blocchi 0: nel form del blocco i campi del blocco vengono prima, le impostazioni condivise per ultime, in un accordion chiuso;
- sono condivisi solo spaziature e colori: un nuovo blocco non riceve nient'altro. `BlockRenderer::data()` legge comunque `title`, `title_formatting`, `content` e `buttons` per nome (`null`/`[]` quando il blocco non ha quei campi): per usarli in un nuovo blocco crea nel suo gruppo campi **con gli stessi nomi** (chiavi nuove: una chiave di campo appartiene a un solo gruppo), oppure un _Clone_ seamless dei campi di _Solo testo_;
- **non cambiare mai la chiave o il nome di un campo esistente**, nemmeno spostandolo in un altro gruppo: i dati dei blocchi in `post_content` salvano ogni valore per nome con un riferimento `_<name>` → chiave del campo, e `get_field()` e il form del blocco risolvono il campo da quella chiave (`acf_get_field()` lo trova in qualunque gruppo). Una chiave o un nome nuovi rendono orfani i valori salvati. I campi si spostano fra gruppi modificando il JSON (o con _Sposta_ nella schermata del field group), mantenendo chiave e nome, e aggiornando `modified`;
- i gruppi esistono solo in `acf-json/` (non nel database): _Sincronizzazione disponibile_ in _ACF → Gruppi di campi_ li elenca perché non c'è una copia nel database; sincronizzarli li importa con le stesse chiavi (senza duplicati), ma non è necessario.

## Asset dei blocchi

La maggior parte dei blocchi non ha bisogno di asset propri: bastano le classi Tailwind nel modulo e `resources/js/app.js`. Quando un blocco ha bisogno di JS o CSS propri, mettili nella cartella del blocco:

- `view.js`: caricato come script module (`wp_enqueue_script_module()`, `type="module"` nel footer, handle `sage-block-<slug>`). Viene eseguito una volta per pagina anche con più istanze del blocco: selezionale tutte (`document.querySelectorAll('.cards')`). Non importare CSS da qui (in build Vite produrrebbe un file separato che non viene caricato): usa `style.css`;
- `style.css`: caricato come foglio di stile (`wp_enqueue_style()`, handle `sage-block-<slug>`); da WordPress 6.9 gli stili caricati durante il rendering vengono spostati nell'`<head>`. Le utility Tailwind sono già in `app.css`: usalo per ciò che non riescono a esprimere. Per usare i token del tema inizia il file con `@reference "../../../css/app.css";`.

Entrambi sono entry di Vite (`vite.config.js` cerca `resources/views/blocks/*/{view.js,style.css}` con un glob all'avvio: dopo aver aggiunto il primo asset di un blocco riavvia `npm run dev`, poi esegui `npm run build`) e vengono caricati da `App\Blocks\BlockAssets`, chiamato da `BlockRenderer::render()`, solo quando la view del blocco viene renderizzata nel frontend: le pagine senza il blocco non li caricano, e nell'editor (placeholder) non viene caricato nulla. Con il dev server arrivano da Vite (una modifica ricarica la pagina); un asset assente dal manifest della build viene saltato.

## Nuovo blocco con il wizard

Un nuovo blocco parte da `npm run make:block` (`resources/scripts/make-block.js`, test in `make-block.test.js`), che genera i file seguendo queste convenzioni. Chiede solo i valori non passati come flag:

| Domanda / flag                                     | Note                                                                                                                                                                  |
| -------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `--title`                                          | Titolo in italiano                                                                                                                                                    |
| `--slug`                                           | Proposto dal titolo (kebab-case); rifiutato se esiste già una cartella in `resources/views/blocks/` o un field group con location `acf/<slug>`: niente sovrascritture |
| `--description`, `--keywords`                      | In italiano; le parole chiave sono proposte dal titolo (separate da virgola)                                                                                          |
| `--category`                                       | Una delle categorie registrate in `app/blocks.php` (lette dal file)                                                                                                   |
| `--icon`                                           | Slug [Phosphor](https://phosphoricons.com), peso **fisso `fill`**, con suggerimenti per i refusi; vuoto = casuale fra le icone non usate dagli altri blocchi          |
| `--fields`                                         | Campi ACF, uno alla volta nel wizard (vedi sotto)                                                                                                                     |
| `--view-js`/`--no-view-js`, `--style`/`--no-style` | [Asset del blocco](#asset-dei-blocchi) facoltativi                                                                                                                    |
| `--yes`, `--dry-run`, `--no-translate`             | Salta la conferma; mostra i file senza scriverli; non esegue gli script di traduzione                                                                                 |

Senza un terminale interattivo (script, agenti, CI) non c'è nessuna domanda: servono almeno `--title`, `--description`, `--category` e `--yes` (o `--dry-run`), gli altri valori prendono il default. Aiuto: `npm run make:block -- --help`. Un valore che inizia con `-` va passato nella forma `--title="-…"` (con `=`), altrimenti viene letto come un flag. Se dal titolo non si ricava uno slug (nessuna lettera a-z né cifra, es. `"€€"`), passalo con `--slug`. Titolo, descrizione, etichette e istruzioni sono testo su una riga: i caratteri di controllo (a capo, tabulazioni) vengono rifiutati. Un'icona già usata da un altro blocco è ammessa, con un avviso che dice quale blocco la usa.

```bash
npm run make:block -- --title "Card in evidenza" --description "Elenco di card con immagine" \
  --category static-content --icon cards \
  --fields "Titolo=title:text!,Card=cards:repeater[Titolo:text!,Immagine:image,Link:link]" --yes
```

**Formato di `--fields`**: campi separati da virgole, ognuno `<Etichetta>[=<name>]:<tipo>[!]`:

- l'etichetta è in italiano; il `name` (snake_case, univoco) se omesso è l'etichetta convertita (`Testo del pulsante` → `testo_del_pulsante`);
- `!` = obbligatorio;
- tipi: `text`, `textarea`, `wysiwyg`, `image` (ID), `link`, `true_false`, `select`, `repeater`;
- `select` con le scelte fra tonde separate da `|`: `Stile:select(Chiaro|Scuro)` o `Stile:select(chiaro=Chiaro|scuro=Scuro)`;
- `repeater` con i sotto-campi fra quadre, con i tipi semplici (`text`, `textarea`, `wysiwyg`, `image`, `link`, `true_false`): `Voci:repeater[Testo:text!,Immagine:image]`;
- un campo `title` (di tipo `text`) è il titolo del blocco: il wizard aggiunge anche `title_formatting` (come in _Solo testo_), il modulo lo stampa con `<x-ui.title :tag="$contents['title_tag']">` e il placeholder dell'editor lo mostra. Sono riservati i nomi del gruppo condiviso (`padding`, `margin`, …), `title_formatting`, `buttons` e le variabili della view (`block`, `data`, `settings`, `contents`, `spacings`, `section_style`, `to_link`, `row`, `rows`, `item`, …).

**Cosa genera**:

- `block.json` come al punto 1 della checklist, con `icon` = SVG Phosphor fill (`getIconSvg()`);
- la view, che legge e valida ogni campo (`sanitize_url()` e target `_self`/`_blank` per i link, ID intero per le immagini, valori ammessi per i select, righe vuote del repeater scartate), e il modulo con `<section>`/`container`, `<x-ui.title>` (`title_tag` per il titolo; per il primo campo testo di ogni voce del repeater `next_heading`, oppure `title_tag` se il blocco non ha un campo `title`, così i livelli dei titoli non saltano), `<x-ui.image>` e `<x-ui.button>`. `true_false` e `select` non hanno markup: sono elencati in un commento come varianti da usare;
- `acf-json/group_<id>.json` (_Blocco: <titolo>_, location `acf/<slug>`, ordine 0, chiavi nel formato di `uniqid()` univoche rispetto a quelle esistenti, `modified`), senza i campi del gruppo condiviso;
- `view.js` (con `data-block="<slug>"` sulla `<section>`) e `style.css` minimi e commentati, se richiesti;
- titolo ed etichette finiscono anche nei commenti dei file generati (docblock, `{{-- --}}`, `@var`): lì sono ridotti a una riga e senza le sequenze che chiudono un commento o aprono codice (`*/`, `?>`, `--}}`, `{{`, `{!!`, `@`, …), così nessun testo diventa codice eseguibile;
- tutti i file formattati con Prettier e blade-formatter (un errore di formattazione interrompe il wizard senza scrivere nulla); i file sono scritti senza sovrascrivere e, se una scrittura fallisce, quelli già creati vengono rimossi; poi `npm run translate` e `translate:compile`, il manifest dei blocchi e quello delle icone. Il blocco è subito nell'inserter: i blocchi `acf/*` sono sempre ammessi (`allowed_block_types`).

**Cosa resta manuale**: le traduzioni in `en_US.po` (il wizard elenca le stringhe con `msgstr` vuoto; poi `npm run translate:compile`), il markup e lo stile definitivi del modulo, le istruzioni, larghezze e opzioni dei campi in ACF, la logica non banale in `app/` con i test (punto 7), la verifica in una pagina (punto 10). L'icona dei blocchi già esistenti si riconosce confrontando il loro SVG con quelli del pacchetto (nessun metadato in più nel `block.json`).

## Checklist per un nuovo blocco

Slug di esempio: `cards`. Il punto di partenza è `npm run make:block` ([wizard](#nuovo-blocco-con-il-wizard)), che esegue i punti 1, 2 (con il field group in `acf-json/`), 3, 4, 5, 6 e 8 (salvo la traduzione in `en_US.po`); la checklist resta il riferimento per controllare il risultato e per i blocchi scritti a mano, copiando da `only-text`.

1. **Metadati**: crea `resources/views/blocks/cards/block.json` con `"name": "acf/cards"`, `title`/`description`/`keywords` in italiano, una `category` del tema, `icon` = SVG di un'icona Phosphor con peso `fill`, `"textdomain": "sage"`, `"apiVersion": 3`, `supports` con solo `"anchor": true` e `"align": false` (niente `supports.jsx`: i blocchi del tema non usano InnerBlocks e `blockVersion` 3 lo ignora), `acf.blockVersion: 3` e `acf.renderCallback: "App\\Blocks\\BlockRenderer::render"`. Non serve registrarlo in PHP: `app/blocks.php` registra i blocchi del manifest, che `npm run dev`/`npm run build` (o `npm run blocks:manifest`) rigenerano dai `block.json`. Committa anche `resources/views/blocks/blocks-manifest.php`.
2. **Campi**: in locale, in _ACF → Gruppi di campi_, crea un gruppo con il nome del blocco (es. _Blocco: Card_) con etichette in italiano, posizione _Blocco è uguale a_ il nuovo blocco (`acf/cards`) e ordine 0. Spaziature e colori arrivano dal gruppo condiviso _Impostazioni blocco_: non aggiungerli. Per titolo, testo o pulsanti usa i nomi `title`, `title_formatting`, `content`, `buttons` (letti da `BlockRenderer::data()`). Il salvataggio scrive `acf-json/group_*.json`: committalo. Non cambiare mai chiave o nome dei campi già usati da blocchi salvati (vedi [Blocchi ACF](#blocchi-acf)).
3. **View del blocco**: crea `resources/views/blocks/cards/cards.blade.php`: `BlockRenderer::data($block)`, lettura e validazione dei campi del blocco, sanificazione di URL (`sanitize_url()`) e colori (`sanitize_hex_color()`), poi `@include('modules.cards', [...])`.
4. **Asset (facoltativi)**: solo se il blocco ha bisogno di JS o CSS propri, aggiungi `resources/views/blocks/cards/view.js` e/o `style.css` (vedi [Asset dei blocchi](#asset-dei-blocchi)), poi riavvia `npm run dev`.
5. **Modulo**: crea `resources/views/modules/cards.blade.php` con l'intestazione `@var`, il markup `<section id … class="w-full overflow-x-clip …">` e `<div class="container mx-auto px-6 3xl:px-0">`, `<x-ui.title :tag>` per il titolo e `next_heading` per i titoli figli. Usa i [componenti](#componenti) per pulsanti, immagini e titoli.
6. **Stringhe**: ogni stringa visibile in italiano con il text domain `sage` (`{{ __('Leggi di più', 'sage') }}`).
7. **Logica e test**: sposta la preparazione dei dati non banale (query, mappature) in una classe in `app/` (es. `App\Queries\…` o un metodo statico in `App\Blocks\`), con un test Pest in `tests/Unit/` che simula `get_field()` con Brain Monkey (vedi `tests/Unit/BlockRendererTest.php`). Esegui `composer test` e `composer analyse`.
8. **Traduzioni**: `npm run translate` (block.json, Blade e il nuovo gruppo ACF finiscono in `sage.pot` e nei `.po`), traduci le nuove voci in `resources/lang/en_US.po`, poi `npm run translate:compile`.
9. **Lint**: `npm run format` (o `npm run format:blade`), poi `npm run lint` e `composer lint`.
10. **Verifica**: `npm run build` (o `npm run dev`), inserisci il blocco in una pagina: placeholder nell'editor; markup, anchor, spaziature e livelli dei titoli nel frontend; con il sito in inglese (`wp site switch-language en_US`) le etichette sono tradotte.

## Internazionalizzazione

- Lingua sorgente: **italiano**. Ogni stringa del tema (PHP, Blade, `block.json`, `theme.json`, `style.css`, field group ACF) è scritta in italiano con il text domain `sage`; l'inglese è una traduzione (`resources/lang/en_US.po`).
- Usa `__()`, `_x()` (con contesto per le stringhe brevi o ambigue), `_n()` e un commento `/* translators: */` per i segnaposto. In Blade: `{{ __('…', 'sage') }}`.
- I field group ACF restano in italiano in `acf-json/`: ACF li traduce a runtime tramite `acf/settings/l10n_textdomain` (`sage`, disattivato mentre si modificano i field group).
- Dopo aver aggiunto o modificato stringhe: `npm run translate`, aggiorna `en_US.po`, `npm run translate:compile`. Committa `.pot`, `.po`, `.mo` e `.l10n.php` (`acf-json.php` è generato e in `.gitignore`). Dettagli in [Traduzioni](README.md#traduzioni).
- Anche commenti, docblock e descrizioni dei test sono in italiano; restano in inglese i file upstream (core, Bedrock) e i termini tecnici.

## PHP

- Namespace `App`, autoload PSR-4 da `app/` (`composer.json`). Ogni file inizia con `declare(strict_types=1);`.
- Classi, una per file:
  - `App\PostTypes\*`: custom post type, con un `register()` statico che chiama `register_post_type()`; elencati in `$postTypes` di `App\Providers\ThemeServiceProvider`, che li aggancia a `init` (tassonomie: `$taxonomies`, stesso contratto, registrate prima);
  - `App\Queries\*`: metodi statici di query che restituiscono array semplici per le view (`Posts::data()`, `Posts::latest()`, `Posts::paginated()`, `Pages::children()`);
  - `App\Blocks\*`: rendering dei blocchi e dati comuni (`BlockRenderer`), asset per blocco (`BlockAssets`);
  - `App\Navigation\*`: helper di navigazione (`Breadcrumbs`);
  - `App\View\Composers\*`: view composer di Acorn. I loro metodi pubblici arrivano alle view come variabili lazy (`InvokableComponentVariable`): stampati con `{{ }}` vengono calcolati solo quando servono, ma in una condizione sono sempre truthy, quindi vanno chiamati (`$customLogo()`) per testarne il valore. `App` (tutte le view) fornisce `$siteName` (testo semplice, da stampare con `{{ }}`), `$customLogo` (markup di `get_custom_logo()` o `''`; l'header ripiega sul nome del sito) e `$isFrontPage` (prima pagina della front page: `aria-current="page"` sul link di fallback alla home, come fa il core sul link del logo); `Post` fornisce titolo e paginazione a `partials.page-header` e `partials.content*`, `Comments` i dati di `partials.comments`;
  - `App\Styleguide`: la route `/styleguide/`, solo in development (vedi [Styleguide](#styleguide)).
- Hook e funzioni senza classe stanno nei file caricati da `functions.php` (`setup`, `filters`, `blocks`, `helpers`, `svg`, `rewrites`). Le funzioni helper stanno in `app/helpers.php`, namespace `App`, e in Blade si chiamano con il nome completo: `\App\get_next_heading_level()`, `\App\get_trimmed_excerpt()`, `\App\random_string_for_id()`, `\App\get_fallback_image_url()`.
- I filtri del tema hanno il prefisso `sage/` (elenco nel [README](README.md#filtri-del-tema)).
- Tipi: tipi nativi dove WordPress chiama con tipi noti, array shape in PHPDoc altrove. PHPStan livello 6 deve passare (`composer analyse`); test unitari con Pest e Brain Monkey (`composer test`). Pint (preset `laravel`) formatta `app/` (`composer lint:fix`).

## Sicurezza ed escaping

- `{{ }}` di Blade fa l'escape con `htmlspecialchars()`: al suo interno usa `sanitize_url()` per gli URL, **non** `esc_url()` (che codificherebbe `&` come `&#038;`, poi codificato una seconda volta da Blade). `esc_url()`/`esc_attr()` servono al PHP che stampa direttamente (`printf` in `app/`).
- I colori da ACF o dalle props passano da `sanitize_hex_color()`; i valori non validi diventano `''`/`null` e non viene stampato nulla.
- I target dei link sono limitati a `_self`/`_blank`; `_blank` aggiunge `rel="noopener noreferrer"` (`<x-ui.button>`).
- `{!! !!}` solo per HTML affidabile: il contenuto wysiwyg di ACF, già filtrato da WordPress, o markup costruito dal tema.
- I tag dei titoli sono limitati ai valori ammessi (`<x-ui.title>`, `BlockRenderer::data()`), mai stampati da input grezzo.
- Gli upload SVG vengono sanificati con `enshrined/svg-sanitize` e limitati a `sage/svg_upload_capability` (`app/svg.php`).

## Componenti

Componenti Blade anonimi in `resources/views/components/`, raggruppati per cartella (la cartella è il prefisso del tag: `components/ui/button.blade.php` → `<x-ui.button>`); la documentazione completa delle props è nel commento in testa a ogni file. Gli attributi aggiuntivi (`class`, `id`, `data-*`) vengono uniti all'elemento radice.

| Cartella   | Contenuto                                                                |
| ---------- | ------------------------------------------------------------------------ |
| `ui/`      | Elementi generici, che non sanno nulla del contenuto                     |
| `content/` | Elementi legati al contenuto corrente, pagina o articolo (o a un elenco) |
| `acf/`     | Helper dei blocchi ACF usati da `BlockRenderer`                          |

| Componente                        | Uso                                                                                      | Props principali                                                                                                           |
| --------------------------------- | ---------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `<x-ui.button>`                   | Link o pulsante disabilitato; non stampa nulla se `link` non è valido e non è `disabled` | `link`, `target` (`_self`/`_blank`), `type` (`primary`, `secondary`, `white`, `black`), `label`, `disabled`, `customColor` |
| `<x-ui.title>`                    | Titolo con tag fra quelli ammessi                                                        | `tag` (h1–h6, `p`, `span`; default `h2`), `textColor`                                                                      |
| `<x-ui.alert>`                    | Riquadro di avviso, nulla senza contenuto                                                | `type` (`success`, `caution`, `warning`, default informativo), `message` (o lo slot), `role` (`status`/`alert`)            |
| `<x-ui.icon>`                     | Icona Phosphor come SVG inline, decorativa salvo `label` ([Icone](#icone))               | `name`, `weight` (`thin`, `light`, `regular` default, `bold`, `fill`, `duotone`), `label`                                  |
| `<x-ui.image>`                    | Immagine allegata (`wp_get_attachment_image()`)                                          | `id`, `size` (default `large`), `sizes`, `eager`, `alt`, `fallback`, `img-id`                                              |
| `<x-content.share>`               | Link di condivisione social + pulsante Web Share API                                     | `title`, `link` (default: post corrente), `facebook`, `twitter` (X), `linkedin`, `whatsapp`                                |
| `<x-content.breadcrumbs>`         | Breadcrumb con JSON-LD                                                                   | `items` (default `Breadcrumbs::items()`), `jsonld` (default `true`)                                                        |
| `<x-content.carousel-navigation>` | Pulsanti precedente/successivo di un carosello                                           | `id` (prefisso delle classi `-prev`/`-next`)                                                                               |
| `<x-acf.placeholder>`             | Placeholder dei blocchi ACF nell'editor (usato da `BlockRenderer`)                       | `block`, `title`, `message`                                                                                                |

Un nuovo componente va nella cartella che corrisponde al suo ruolo (nessun componente nella radice di `components/`), è documentato nel suo commento di testa e in questa tabella e ha un esempio nella [styleguide](#styleguide).

### Icone

Le icone sono quelle di [Phosphor](https://phosphoricons.com) (pacchetto npm `@phosphor-icons/core`, SVG ufficiali; niente web font né sprite), stampate inline da `<x-ui.icon>` tramite `App\Icons\Phosphor`.

```blade
<x-ui.icon name="arrow-right" />
<x-ui.icon name="heart" weight="fill" class="size-6 text-primary-500" />
<x-ui.icon name="warning" weight="bold" :label="__('Attenzione', 'sage')" />
```

- **Nomi**: gli slug di phosphoricons.com (`magnifying-glass`, `caret-down`): solo minuscole, cifre e trattini, validati con una regex prima di qualsiasi accesso ai file.
- **Pesi**: `thin`, `light`, `regular` (default), `bold`, `fill`, `duotone` (la parte secondaria del duotone ha `opacity="0.2"` sullo stesso colore).
- **Dimensione e colore**: `1em` di default, così l'icona segue il `font-size` del testo o del pulsante in cui si trova; `width`/`height` sono attributi di presentazione, quindi qualsiasi classe (`size-5`, `w-5 h-auto`) li sostituisce. L'`<svg>` ha l'attributo `data-icon`, agganciato da una regola di base in `resources/css/app.css` (e `editor.css`): `display: inline-block` con `vertical-align: -0.125em`, così senza classi l'icona resta sulla riga del testo (il preflight di Tailwind rende gli svg `display: block`); `block`, `flex` o `align-*` la sostituiscono. Il colore è `currentColor`: si cambia con `text-*` (o `fill-*`).
- **Accessibilità**: senza `label` l'icona è decorativa (`aria-hidden="true" focusable="false"`), ed è il caso normale: accanto a un testo, o dentro un link/pulsante che ha già un nome (`aria-label` sul pulsante, come in `<x-content.carousel-navigation>`). Con `label` (un'icona che da sola porta un significato) diventa `role="img"` con `aria-label` e `<title>`.
- **Attributi**: solo `name` e `weight` vengono validati; gli altri (`class`, `id`, `data-*`, anche `on*`) arrivano all'`<svg>` così come sono, solo escapati, come in ogni componente Blade. Non passare mai a `<x-ui.icon>` attributi o chiavi presi da input utente o da campi ACF.
- **Solo le icone usate**: in produzione `node_modules` non c'è, quindi le icone arrivano da `resources/icons/phosphor.php`, generato da `npm run icons` (e da Vite in build e in dev) e versionato. Lo script trova da solo i tag `<x-ui.icon>` con `name` e `weight` **statici** nelle view; i commenti Blade (`{{-- --}}`) e HTML (`<!-- -->`) vengono ignorati, i blocchi `@php` no (un tag scritto lì dentro viene contato).
- **Icone dinamiche**: se il nome o il peso arrivano da una variabile (`:name="$icon"`, `name="{{ $icon }}"`: un valore con `{{` o `{!!` è dinamico e lo scanner lo salta; un campo ACF; una chiamata a `App\Icons\Phosphor::svg()` da PHP), elenca le combinazioni possibili in `resources/icons/allowlist.json`, un array JSON di `"nome"` (peso regular) o `"nome:peso"`, es. `["phone", "envelope-simple:bold"]`, poi `npm run icons`.
- **Errori**: un nome o un peso inesistente nelle view o nell'allowlist fa fallire `npm run icons` e la build, con il file, la riga e dei suggerimenti. A runtime, un'icona non valida o assente dal manifest non stampa nulla negli ambienti diversi da development; in development il componente la legge da `node_modules` se esiste e segnala il problema senza interrompere la pagina: una riga in `error_log` (`logs/debug.log`), un avviso in Query Monitor (azione `qm/warning`) e un commento HTML `<!-- x-ui.icon: … -->`. Niente `trigger_error`/`wp_trigger_error`: con `WP_DEBUG` il gestore degli errori di Acorn trasforma ogni warning in eccezione e mostrerebbe la pagina d'errore.
- **SVG fidati**: le icone vengono dal pacchetto e dal manifest versionato, non dagli utenti: non passano dal sanitizer degli upload SVG di `app/svg.php`, i cui filtri riguardano solo i file caricati nella libreria media.

`App\Icons\Phosphor` (PHP) e `resources/scripts/icons.js` (Node) espongono anche il catalogo, per il wizard dei nuovi blocchi (M47) e per l'icona dei `block.json`:

| Operazione                     | PHP (`App\Icons\Phosphor`)                                      | Node (`resources/scripts/icons.js`)                    |
| ------------------------------ | --------------------------------------------------------------- | ------------------------------------------------------ |
| Slug disponibili               | `names()` (dal pacchetto; dal manifest se il pacchetto non c'è) | `listIconNames()`, `listIcons()` (con categorie e tag) |
| Esiste nome + peso             | `exists($name, $weight)`                                        | `iconExists(name, weight)`                             |
| Stampabile in questo ambiente  | `available($name, $weight)`                                     | —                                                      |
| SVG del pacchetto (block.json) | `source($name, $weight)`                                        | `getIconSvg(name, weight)`                             |
| SVG con attributi del tema     | `svg($name, $weight, $attributes, $label)`                      | —                                                      |
| Suggerimenti per un refuso     | —                                                               | `suggestIcons(query)`                                  |
| Icona casuale                  | `random($exclude)`                                              | `randomIcon({ exclude, random })`                      |

L'icona di un blocco va nel `block.json` come SVG inline (`"icon": "<svg …>…</svg>"`, formato accettato da ACF), sempre con il peso `fill` (`getIconSvg(name, 'fill')`, come fa `npm run make:block`; `only-text` usa `article`), quindi non passa dal manifest delle icone.

## Styleguide

`/styleguide/` mostra i design token (colori, famiglie e dimensioni dei font, letti da `public/build/assets/theme.json`, generato dalla build dal `@theme` di `resources/css/theme.css`), i titoli h1–h6, link e focus, e tutti i componenti con le varianti principali su dati di esempio statici. View: `resources/views/styleguide.blade.php`; logica: `App\Styleguide` (`app/Styleguide.php`), agganciata da `ThemeServiceProvider::boot()`.

- **Solo in development**: la regola di rewrite, la query var `sage_styleguide` e il template vengono registrati solo con `WP_ENV === 'development'` (nessun filtro per attivarla altrove). In tutti gli altri ambienti `/styleguide/` è un normale 404 e `?sage_styleguide=1` viene ignorato.
- **Nessuna pagina nel database**: una regola di rewrite (`^styleguide/?$`) renderizza la view con il layout del tema; la query principale non esegue nessuna query sui post, la pagina è `noindex, nofollow` (`wp_robots`), ha il titolo _Styleguide_ e la classe `styleguide` sul body. Nessun link del sito porta lì.
- **Regole di rewrite**: sono salvate nel database, quindi non vengono rigenerate a ogni richiesta. Su `init` il tema confronta le regole salvate con l'ambiente e le rigenera una volta quando non corrispondono: la prima richiesta in development aggiunge la regola, la prima richiesta (o il primo comando WP-CLI) in un altro ambiente rimuove una regola rimasta lì, ad esempio da un database copiato dal development. L'opzione `sage_styleguide_rewrite` ricorda l'ultima rigenerazione, così una regola rimossa da qualcos'altro non provoca una rigenerazione a ogni richiesta. Con i permalink semplici non ci sono regole salvate: usa `/?sage_styleguide=1`. Se in development la pagina dà 404, esegui `wp rewrite flush`.
- **Foglio di stile proprio**: le utility usate solo dalla view sono compilate in `resources/css/styleguide.css` (entry di Vite con `tailwindcss/utilities` e `@source` sulla sola view), caricato da `Styleguide::enqueueAssets()` solo sulla styleguide. `app.css` ed `editor.css` escludono la view (`@source not '../views/styleguide.blade.php'`), così le sue classi non arrivano mai nei fogli di stile di produzione. I componenti mostrati continuano a usare le classi compilate in `app.css`. Entrambi escludono anche `resources/lang/`: le traduzioni sono testo, e una parola inglese che è anche il nome di una utility la aggiungerebbe ai fogli di stile.
- **Titoli**: i titoli di esempio (h1–h6 e `<x-ui.title>`) hanno `role="presentation"`, così la struttura dei titoli della pagina è un h1 e un h2 per sezione.
- **Contrasto**: ogni colore della palette mostra il suo contrasto WCAG 2.2 con il bianco e con il nero e il livello raggiunto (AA/AAA, testo normale e grande), calcolato da `Styleguide::contrasts()` (hex e oklch di `theme.json`, convertiti in sRGB). La pagina non esegue query sui contenuti: `<x-ui.image>` mostra il fallback del tema.
- Quando aggiungi o modifichi un componente o un token, aggiorna la styleguide: è il controllo visivo rapido del tema (`npm run dev`, poi `https://<sito>/styleguide/`).

## Rinominare il tema

Il nome della cartella è lo slug del tema per WordPress. Per rinominare `sage` in `<nuovo>`:

1. Rinomina `web/app/themes/sage` in `web/app/themes/<nuovo>`. La base degli asset di Vite deriva dal nome della cartella (`vite.config.js`): non c'è niente da cambiare. `VITE_THEME_SLUG` serve solo per un tema installato come **symlink** con una cartella di destinazione dal nome diverso, e si passa come variabile di shell (`VITE_THEME_SLUG=<nuovo> npm run build`), non in `.env`.
2. Aggiorna i percorsi fuori dal tema: `--working-dir=web/app/themes/sage` negli script del `composer.json` della root, `working_dir` e `restart_when_changed` in `solo.yml`, `.github/workflows/ci.yml` (working directory, `node-version-file`, `cache-dependency-path`), `.github/dependabot.yml` (directory), `WP_DEFAULT_THEME` in `config/application.php` e i percorsi nei due README (`grep -rn "themes/sage" --exclude-dir=vendor --exclude-dir=node_modules .`).
3. Aggiorna l'identità del tema: `Theme Name` in `style.css`, `name` in `composer.json` (`toms15/sage`) e in `package.json` (`sage`).
4. Text domain (facoltativo, può restare `sage`): sostituiscilo in ogni chiamata `__()`/`_x()`, in `"textdomain"` dei `block.json`, in `Text Domain` di `style.css`, in `load_theme_textdomain()` e nel filtro `acf/settings/l10n_textdomain` di `app/setup.php`, poi ricostruisci il catalogo (`npm run translate`, `npm run translate:compile`). I prefissi dei filtri `sage/` e `sage.pot` sono nomi, non il text domain: possono restare.
5. `composer dump-autoload`, `npm run build`, `wp theme activate <nuovo>`, `wp acorn optimize:clear`.
