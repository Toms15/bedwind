# Migliorie al boilerplate Bedrock + Sage

> Analisi del 2026-11-18, fatta da tre subagenti: Bedrock/config, PHP del tema, frontend del tema.
> Ogni voce ha un ID `Mxx`. Lo stesso ID è usato nei todo di Solo (progetto `sage`) e nella scratchpad "Diario lavori".
> I riferimenti `BED-/PHP-/FE-` sono gli ID originali dei singoli report, utili per la tracciabilità.
> Percorsi del tema relativi a `web/app/themes/sage/`.

**Legenda.** Stato: ⬜ da fare · 🔄 in corso · ✅ fatto (con commit). Priorità: 🔴 Alta · 🟠 Media · 🟢 Bassa. Effort: S (< 1h) · M (mezza giornata) · L (1+ giorni).

## Riepilogo

| ID | Stato | Titolo | Area | Prio | Effort | Dipende da |
|---|---|---|---|---|---|---|
| M01 | ⬜ | Blocco `only-text`: view mancante, campi e categoria | Blocchi | 🔴 | S | – |
| M02 | ✅ | Rimuovere codice di test da layout, header, editor.css | Frontend | 🔴 | S | – |
| M03 | ⬜ | Componente `x-button` (variabile undefined, a11y, rel) | Frontend | 🔴 | S | – |
| M04 | ⬜ | Ripristinare focus visibile e sottolineatura link | A11y | 🔴 | S | – |
| M05 | ⬜ | Design token in `@theme` condivisi app/editor | Frontend | 🔴 | M | – |
| M06 | ✅ | Upload SVG sicuro | Sicurezza | 🔴 | M | – |
| M07 | ✅ | Hook `add_attachment` non sicuro | Sicurezza | 🔴 | S | – |
| M08 | ✅ | `development.php`: ripristinare `WP_DEBUG` | Config | 🔴 | S | – |
| M09 | ✅ | `npm update`: chiudere le vulnerabilità | Dipendenze | 🔴 | S | – |
| M10 | ✅ | ACF Pro gestito via Composer | Dipendenze | 🔴 | S | – |
| M11 | ⬜ | `.idea` fuori da git e `.gitignore` completo | Repo | 🟠 | S | – |
| M12 | ⬜ | `debug.log` fuori dalla webroot, `WP_DEBUG` esplicito in prod | Sicurezza | 🟠 | S | M08 |
| M13 | ⬜ | Aggiornare le dipendenze Composer e WP 7.x | Dipendenze | 🟠 | M | M10 |
| M14 | ⬜ | Upgrade major di Vite / `@roots/vite-plugin` | Dipendenze | 🟠 | M | M09 |
| M15 | ⬜ | Traduzioni core via Composer, rimozione di twentytwentyfive | Dipendenze | 🟢 | M | M13 |
| M16 | ⬜ | Metadati e branding (composer, style.css, LICENSE, vincoli PHP) | Repo | 🟢 | S | – |
| M17 | ⬜ | `excerpt_more` duplicato e helper deboli | PHP | 🟠 | S | – |
| M18 | ⬜ | Rewrite degli archivi (mensili, paginazione, flush) | PHP | 🟠 | M | – |
| M19 | ⬜ | CPT `faq`: configurazione coerente | PHP | 🟠 | S | – |
| M20 | ⬜ | `wp-embed` deregistrato male | PHP | 🟠 | S | – |
| M21 | ⬜ | Filtro `allowed_block_types_all` | Blocchi | 🟠 | S | – |
| M22 | ⬜ | `render_acf_block`: pulizia e tipizzazione | Blocchi | 🟢 | S | M01 |
| M23 | ⬜ | Query dei post: N+1, dimensione immagini, date | Performance | 🟠 | S | – |
| M24 | ⬜ | Componenti `x-alert`, `x-share`, `x-title`, placeholder ACF | Frontend | 🟠 | M | M05 |
| M25 | ⬜ | Breadcrumb: Composer + componente + JSON-LD | SEO/A11y | 🟠 | M | M28 |
| M26 | ⬜ | i18n completo (stringhe sorgente in inglese, `resources/lang`) | i18n | 🟠 | S | – |
| M27 | ⬜ | Landmark del layout, skip link, custom logo | A11y | 🟢 | S | M04 |
| M28 | ⬜ | Architettura: Service Provider e classi PSR-4 | Architettura | 🟠 | L | – |
| M29 | ⬜ | Registrazione dei blocchi via manifest, asset JS/CSS per blocco | Blocchi | 🟢 | M | M28, M22 |
| M30 | ⬜ | Separare i field group ACF comuni da quelli specifici | Blocchi | 🟢 | M | M01 |
| M31 | ⬜ | Struttura cartelle dei componenti e styleguide di sviluppo | Frontend | 🟢 | M | M24 |
| M32 | ⬜ | `base` di Vite indipendente dal nome del tema | DX | 🟠 | S | – |
| M33 | ⬜ | Strategia per font e immagini responsive | Performance | 🟠 | M | M05 |
| M34 | ⬜ | `editor.js`: meno fragile, meno costoso | Editor | 🟢 | S | – |
| M35 | ⬜ | PHPStan/Larastan + Pest + script Composer | Qualità | 🟠 | M | – |
| M36 | ⬜ | Lint e format frontend + `.nvmrc` | Qualità | 🟠 | M | – |
| M37 | ⬜ | CI su GitHub Actions + Dependabot | DX | 🟠 | M | M10, M35, M36 |
| M38 | ⬜ | Ambiente locale riproducibile, deploy, `solo.yml` | DX | 🟠 | M | – |
| M39 | ⬜ | README e documentazione delle convenzioni | Docs | 🟠 | S | M10, M38 |
| M40 | ⬜ | Permalink di default nel setup (404 e rewrite non funzionano) | Config | 🟠 | S | – |

**Ordine consigliato.** Fase 0 (M01–M10), poi Fase 1 (M11–M16), Fase 2 (M17–M27), Fase 3 (M28–M34), Fase 4 (M35–M39).

---

## Fase 0: bug bloccanti e sicurezza

### M01 – Blocco `only-text`: view mancante, campi e categoria 🔴 S
*Fonti: FE-01, PHP-01, PHP-09, FE-07, PHP-08*
- **Problema**
  - `resources/views/blocks/only-text/only-text.blade.php:36` fa `@include('modules.contents.only-text')`, ma `resources/views/modules/` non esiste. Sul frontend il blocco genera `View [modules.contents.only-text] not found`. Nell'editor l'errore non si vede, perché lì viene mostrato solo il placeholder.
  - Le righe 8–31 della view duplicano a mano la logica di `get_block_data()` (`app/helpers.php:180`).
  - L'helper legge il campo `buttons`, mentre il campo ACF si chiama `button`. Il campo `list_style` non viene mai letto.
  - `block.json:7` dichiara la categoria `"contents"`, che non è registrata. Quelle esistenti sono `carousels`, `dynamic-content`, `static-content`, `commons`, `heroes`, `dividers`. Manca anche `"textdomain"`.
- **Soluzione**
  - Mettere il markup direttamente nella view del blocco e usare `get_block_data($block)`.
  - Correggere `buttons` in `button` e gestire `list_style`.
  - Impostare `"category": "static-content"` e `"textdomain": "sage"`.

### M02 – Rimuovere codice di test 🔴 S
*Fonti: FE-05, PHP-02, FE-08*
- **Problema**
  - `layouts/app.blade.php:26-34` contiene un `<x-button :link="'Link here'">` dentro `<main>`, quindi compare su ogni pagina.
  - `sections/header.blade.php:6` contiene `<p class="text-3xl text-red-500">Test</p>`.
  - `resources/css/editor.css:3-28` ha stili per un campo `prova_colore` che non esiste.
- **Soluzione:** eliminare tutto. Per avere un catalogo dei componenti, vedi M31.

### M03 – Componente `x-button` 🔴 S
*Fonti: FE-02, PHP-03*
- **Problema** in `components/button.blade.php:34-37`:
  - `$label ?: $message` usa `$message`, che non è dichiarata tra le `@props`. Con `label` vuoto si ottiene "Undefined variable".
  - `aria-label` sovrascrive il testo visibile, violando WCAG 2.5.3.
  - Con `target="_blank"` manca `rel="noopener noreferrer"`.
  - Le varianti sono ancora i segnaposto `'...'`.
  - `$attributes` non viene unito, quindi le classi passate dall'esterno si perdono.
  - Il `<button>` non ha `type`.
- **Soluzione**
  ```blade
  <a href="{{ $link }}" @if($target==='_blank') target="_blank" rel="noopener noreferrer" @endif
     {{ $attributes->class(['inline-flex items-center justify-center', $variantClass]) }}
     @if($label) aria-label="{{ $label }}" @endif>{{ $slot }}</a>
  ```
  Definire varianti reali (primary, secondary, ghost) e aggiungere `type="button"` al `<button>`.

### M04 – Focus visibile e sottolineatura dei link 🔴 S
*Fonte: FE-03*
- **Problema:** `resources/css/app.css:14-16` applica `* { @apply !no-underline focus-visible:!ring-0 focus-visible:!shadow-none focus-visible:outline-0 }`. Il focus sparisce ovunque, compreso lo skip link (WCAG 2.4.7), e i link nel testo perdono la sottolineatura (WCAG 1.4.1).
- **Soluzione**
  ```css
  @layer base { :focus-visible { @apply outline-2 outline-offset-2 outline-current; } }
  ```
  Togliere la sottolineatura solo sui componenti che ne hanno bisogno.

### M05 – Design token in `@theme` condivisi 🔴 M
*Fonti: FE-04, FE-16, FE-08*
- **Problema**
  - Diverse classi usano token che non esistono: `font-body`, `text-small`, `text-primary-500`, `bg-black-950/30`, `text-black-700`. Si trovano in `share`, `carousel-navigation`, `acf-block-*` e `app/breadcrumbs.php`, mentre `@theme` in `app.css:5-9` è vuoto. Tailwind v4 quindi non genera quelle classi.
  - `theme.json` contiene 247 colori della palette Tailwind e solo i font di sistema.
  - `editor.css` non importa i token, quindi l'editor e il frontend divergono.
- **Soluzione:** creare `resources/css/theme.css` con i token, e importarlo sia da `app.css` sia da `editor.css`.
  ```css
  @theme {
    --color-*: initial;
    --color-white: #fff; --color-black: #000;
    --color-primary-500: oklch(...); --color-black-700: …; --color-black-950: …;
    --font-body: "Inter", ui-sans-serif, system-ui, sans-serif;
    --text-small: 0.875rem;
  }
  ```
  Aggiungere anche `layout.wideSize` e `spacingSizes` a `theme.json`.

### M06 – Upload SVG sicuro 🔴 M
*Fonte: PHP-11*
- **Problema:** `app/helpers.php:16-20` aggiunge `$mimes['svg']` per chiunque abbia `upload_files`. Un SVG con `<script>` diventa uno stored XSS. Le righe che abilitano `webp` e `ico` sono ridondanti, perché il core li consente già.
- **Soluzione:** abilitare gli SVG solo per `manage_options` e sanitizzarli in `wp_handle_upload_prefilter` con `enshrined/svg-sanitize`, oppure usare il plugin Safe SVG via Composer.

### M07 – Hook `add_attachment` non sicuro 🔴 S
*Fonte: PHP-12*
- **Problema:** `app/helpers.php:25-40` usa `$_REQUEST['name']` senza sanitizzazione e senza fallback.
  - Negli upload via REST, Gutenberg o WP-CLI la chiave non c'è. Ne risultano un warning e un `preg_replace(null)`, e titolo e alt vengono svuotati.
  - Il valore finisce in `_wp_attachment_image_alt` senza sanitizzazione.
  - L'hook si applica anche a PDF e video.
- **Soluzione**
  ```php
  add_action('add_attachment', function (int $id): void {
      if (! wp_attachment_is_image($id)) return;
      $name = sanitize_text_field(str_replace(['-', '_'], ' ',
          pathinfo(get_attached_file($id), PATHINFO_FILENAME)));
      wp_update_post(['ID' => $id, 'post_title' => $name]);
      update_post_meta($id, '_wp_attachment_image_alt', $name);
  });
  ```

### M08 – `development.php`: ripristinare `WP_DEBUG` 🔴 S
*Fonte: BED-02*
- **Problema:** la modifica non committata a `config/environments/development.php:12-14` imposta `WP_DEBUG` e `WP_DEBUG_DISPLAY` a `false`. Restano però `WP_DEBUG_LOG=true` e `display_errors=1`. La combinazione è incoerente, e in un boilerplate diventerebbe il default di ogni nuovo progetto.
- **Soluzione**
  ```php
  Config::define('WP_DEBUG', env('WP_DEBUG') ?? true);
  Config::define('WP_DEBUG_DISPLAY', env('WP_DEBUG_DISPLAY') ?? true);
  ```
  Le preferenze personali vanno in `.env.local`.

### M09 – `npm update`: vulnerabilità 🔴 S
*Fonte: FE-20 (patch)*
- **Problema:** `npm audit` riporta 13 vulnerabilità: 1 critica, 9 alte, 2 moderate e 1 bassa. Riguardano vite 6.3.5 (accesso ai file tramite il dev server), `launch-editor` e `webpack`.
- **Soluzione:** eseguire `npm update`, che porta i pacchetti alle versioni "wanted": vite 6.4.6, tailwind 4.3.5, `@roots/vite-plugin` 1.3.1. Poi rigenerare la build: in `public/build/manifest.json` ci sono ancora voci di file rimossi come `acf-color-picker` e `hero-page`.

### M10 – ACF Pro via Composer 🔴 S
*Fonte: BED-01*
- **Problema:** ACF Pro 6.5 è presente solo su disco ed è ignorato da git. `auth.json` contiene già le credenziali per `connect.advancedcustomfields.com`, ma il repository non è dichiarato in `composer.json`. Su un clone pulito, i blocchi vanno in fatal error alle chiamate a `get_field()`.
- **Soluzione**
  ```json
  "repositories": [
    {"type":"composer","url":"https://wpackagist.org","only":["wpackagist-plugin/*","wpackagist-theme/*"]},
    {"type":"composer","url":"https://connect.advancedcustomfields.com"}
  ],
  "require": { "wpengine/advanced-custom-fields-pro": "^6.5" }
  ```
  In CI usare `COMPOSER_AUTH`. Documentare la creazione di `auth.json` nel README.

---

## Fase 1: igiene della repo e dipendenze

### M11 – `.idea` e `.gitignore` 🟠 S
*Fonti: BED-03, BED-14*
- **Problema**
  - 6 file di `.idea/` sono tracciati, tra cui `new.test.iml`, che porta il nome di un altro progetto. La regola `.idea` nel gitignore non ha effetto sui file già tracciati.
  - `.gitignore:30` è ridondante.
  - Mancano `web/app/upgrade-temp-backup/`, `.DS_Store` e `/logs`.
- **Soluzione:** `git rm -r --cached .idea`, poi un'unica regola `/.idea/` e queste aggiunte:
  ```
  web/app/upgrade/
  web/app/upgrade-temp-backup/
  .DS_Store
  /logs/*
  !/logs/.gitkeep
  ```

### M12 – `debug.log` fuori dalla webroot 🟠 S
*Fonti: BED-04, BED-10*
- **Problema**
  - Il log va nel default `web/app/debug.log`, che sta sotto la docroot e quindi è scaricabile via HTTP su uno staging esposto.
  - `WP_DEBUG` non è definito esplicitamente in `application.php`.
- **Soluzione**
  - `Config::define('WP_DEBUG_LOG', env('WP_DEBUG_LOG') ?? $root_dir . '/logs/debug.log');`
  - Aggiungere `Config::define('WP_DEBUG', false);` in `application.php:140`.
  - Facoltativo: creare `production.php` con commenti guida.

### M13 – Aggiornare le dipendenze Composer 🟠 M
*Fonte: BED-06*
- **Problema:** il lock è fermo all'agosto 2025.
  - roots/wordpress è alla 6.8.2, contro la 7.1.4 disponibile. Indietro anche pint, phpdotenv, bedrock-autoloader e disallow-indexing.
  - I vincoli sono esatti (`6.8.2`, `roots/wp-config: 1.0.0`).
  - Con `AUTOMATIC_UPDATER_DISABLED`, il core riceve patch solo tramite Composer.
- **Soluzione**
  - `composer update` per le minor.
  - `"roots/wordpress": "~7.1.0"` dopo un test.
  - `"roots/wp-config": "^1.0"`.

### M14 – Upgrade major del frontend 🟠 M
*Fonte: FE-20 (major)*
- **Soluzione**
  - Aggiornare vite a 7/8, `@roots/vite-plugin` a 2.x e `laravel-vite-plugin` a 2/3, seguendo le release di Sage upstream.
  - Impostare `engines.node` a `>=20.19`.

### M15 – Traduzioni del core e twentytwentyfive 🟢 M
*Fonti: BED-07, BED-13*
- **Problema**
  - 75 file di traduzione del core 6.8 sono tracciati in `web/app/languages/` e non si aggiornano: `DISALLOW_FILE_MODS` è attivo in produzione.
  - twentytwentyfive viene installato senza essere usato.
- **Soluzione**
  - Usare `inpsyde/wp-translation-downloader` per `it_IT` e ignorare `web/app/languages/*`.
  - Rimuovere twentytwentyfive, oppure documentarlo come tema di fallback.

### M16 – Metadati e branding 🟢 S
*Fonti: BED-12, PHP-25, FE-22*
- **Problema**
  - `composer.json` alla radice si chiama `roots/bedrock`, quello del tema `roots/sage`.
  - `style.css` riporta "Sage Starter Theme / Roots" e `LICENSE.md` dice "Roots Software LLC".
  - Il vincolo PHP è `>=8.1` alla radice e `>=8.2` nel tema.
  - Il tema ha `minimum-stability: dev`.
- **Soluzione:** rinominare i pacchetti (ad esempio `toms15/sage-starter`), aggiornare authors e support, aggiungere il proprio copyright mantenendo la licenza MIT di Roots e allineare il vincolo a PHP `>=8.2`.

---

## Fase 2: correttezza di PHP, componenti, a11y e i18n

### M17 – `excerpt_more` duplicato e helper 🟠 S
*Fonti: FE-24, PHP-04, PHP-21*
- **Problema**
  - `app/filters.php:14` e `app/helpers.php:119-123` registrano entrambi `excerpt_more` con priorità 10. Vince il secondo (`'...'`), quindi il link "Continued" non compare mai.
  - `random_string_for_id` usa `rand()`.
  - `get_next_heading_level` non valida l'input.
  - `wordpack_excerpt` reimplementa `wp_trim_words()`.
- **Soluzione**
  - Tenere un solo filtro, con un link e un `aria-label` come "Continue reading: {title}".
  - Sostituire `rand()` con `wp_unique_id('b-')`.
  - In `wordpack_excerpt`: `wp_trim_words(get_field('excerpt',$id) ?: strip_shortcodes(get_post_field('post_content',$id)), $n, '…')`.

### M18 – Rewrite degli archivi 🟠 M
*Fonte: PHP-05*
- **Problema** in `app/rewrites.php:3-17`:
  - La regex riscrive anche gli archivi mensili `/2024/05/` verso un URL per cui non esiste una regola, quindi danno 404.
  - Manca `preg_quote(home_url())`.
  - Manca una regola per `/page/N/`.
  - Il flush dei rewrite non è documentato.
- **Soluzione**
  ```php
  $base = preg_quote(home_url(), '~');
  preg_replace("~{$base}/(\d{4})/(?!\d)~", home_url('/news/archivio/$1/'), $html);
  add_rewrite_rule('^news/archivio/([0-9]{4})/page/([0-9]+)/?$', 'index.php?year=$matches[1]&paged=$matches[2]', 'top');
  add_action('after_switch_theme', 'flush_rewrite_rules');
  ```

### M19 – CPT `faq` 🟠 S
*Fonti: PHP-06, PHP-07*
- **Problema** in `app/CustomPostTypes/faqs.php`:
  - `'description' => __('', 'sage')` restituisce l'header del file `.mo`.
  - Le impostazioni si contraddicono: `public=true` ma `publicly_queryable=false`, eppure `show_in_nav_menus` e `rewrite` sono attivi.
  - Il prefisso `green_faqs` è un residuo di un altro progetto.
- **Soluzione**
  - `'description' => ''`.
  - Se le FAQ sono solo dati: `public => false`, `show_ui => true`, `show_in_rest => true`, `rewrite => false`, `show_in_nav_menus => false`.

### M20 – `wp-embed` deregistrato 🟠 S
*Fonte: PHP-10*
- **Problema:** `app/helpers.php:148` esegue `wp_deregister_script('wp-embed')` su `init`.
  - In WP 6.8 lo script viene già caricato solo quando la pagina contiene un embed, quindi rimuoverlo non fa risparmiare nulla.
  - Rompe il ridimensionamento automatico degli embed.
  - Agisce anche in admin, dove lo usa TinyMCE.
- **Soluzione:** eliminare la riga, oppure usare `remove_action('wp_head','wp_oembed_add_host_js')` solo sul frontend. La rimozione delle emoji è stata verificata ed è OK.

### M21 – Filtro `allowed_block_types_all` 🟠 S
*Fonte: PHP-18*
- **Problema:** `app/blocks.php:82-112` riparte da `[]`, scartando il valore in ingresso. I blocchi core sono permessi solo su `post` e widget, quindi il CPT `faq` resta senza paragrafo e senza `core/block`.
- **Soluzione:** usare `apply_filters('sage/core_blocks_post_types', ['post','faq'])` e partire dal valore ricevuto.

### M22 – `render_acf_block` 🟢 S
*Fonte: PHP-17*
- **Problema** in `app/blocks.php:20-45`:
  - Il ramo `slug === 'empty'` è morto.
  - `str_replace('acf/', '')` non è ancorato al prefisso.
  - Usa un path assoluto con `file_exists` invece del nome della view.
  - Mancano i tipi.
  - `"jsx": true` è dichiarato ma `$content`/InnerBlocks non viene mai stampato.
- **Soluzione**
  ```php
  function render_acf_block(array $block, string $content = '', bool $is_preview = false, int|string $post_id = 0): void {
      $slug = \Illuminate\Support\Str::after($block['name'], 'acf/');
      $name = "blocks.{$slug}.{$slug}";
      if ($is_preview) { echo view('components.acf-block-placeholder', ['block' => $block, 'title' => get_field('title')]); return; }
      if (view()->exists($name)) echo view($name, ['block' => $block, 'data' => get_block_data($block), 'content' => $content]);
  }
  ```

### M23 – Query dei post 🟠 S
*Fonte: PHP-20*
- **Problema** in `app/Controllers/posts.php`:
  - N+1 sulle thumbnail, che vengono caricate in dimensione `full`.
  - `wp_date('M d, Y')` è fisso e ignora `date_format`.
  - Mancano `no_found_rows` e `ignore_sticky_posts`.
  - Il fallback punta a `placehold.co`, un servizio esterno.
- **Soluzione**
  - Chiamare `update_post_thumbnail_cache($q)`.
  - Usare `get_the_post_thumbnail_url($id,'large')` con un fallback locale (`asset('images/fallback.webp')`).
  - Usare `get_the_date('', $id)`.

### M24 – Componenti Blade 🟠 M
*Fonti: FE-09, FE-10, PHP-13, FE-11, FE-12*
- **`x-alert`**
  - Usare `{{ $message }}` invece di `{!! !!}`.
  - Aggiungere `role="alert|status"`.
  - Correggere il contrasto: è sotto 4.5:1 sulle varianti yellow e green.
- **`x-share`**
  - Codificare gli URL con `rawurlencode()`.
  - Aggiungere `rel="noopener noreferrer"`.
  - Mettere `aria-hidden` sugli SVG.
  - Sostituire `whatsapp://` con `https://wa.me/`.
  - Cambiare la label da "Twitter X" a "X".
  - Considerare `navigator.share` come miglioramento progressivo.
- **`x-title`**
  - Accettare solo i tag h1–h6, p e span.
  - Rimuovere la prop `class`, che sostituisce il merge nativo degli attributi.
  - Impostare il default a `h2`.
- **Placeholder ACF:** unificare le due view in `<x-acf.placeholder>` con `@props` e stringhe tradotte con `__()`.

### M25 – Breadcrumb 🟠 M
*Fonti: FE-13, PHP-22*
- **Problema:** `app/breadcrumbs.php` non è mai chiamato.
  - Fa `echo` di HTML con classi Tailwind.
  - Mancano `<nav aria-label>`, `<ol>` e `aria-current`.
  - Ha rami per CPT di altri progetti (`event`, `team`, `project`).
  - `/news` è scritto a mano.
  - Non produce JSON-LD.
- **Soluzione:** un View Composer `Breadcrumbs` che restituisce `[['label','url']]` e un componente `x-breadcrumbs`.
  - Il componente usa `<nav><ol>`, il separatore `aria-hidden` e il JSON-LD `BreadcrumbList`.
  - L'URL delle news si ottiene con `get_permalink(get_option('page_for_posts'))`.
  - Le label dei CPT vengono da `get_post_type_object()->labels->name`.

### M26 – i18n completo 🟠 S
*Fonti: FE-21, PHP-23*
- **Problema**
  - Le stringhe sorgente mescolano italiano ("Modifica faq", "Categoria", "Archivio", "Solo testo") e inglese.
  - I titoli delle categorie dei blocchi e il placeholder non passano da `__()`.
  - `resources/lang/` non esiste.
  - `load_theme_textdomain` è chiamato in `helpers.php:129` e duplica quello che fa già Sage.
- **Soluzione**
  - Tutte le stringhe sorgente in inglese, con il dominio `sage`.
  - Creare `resources/lang/.gitkeep`.
  - Eseguire `npm run translate` per ottenere `it_IT.po`.

### M27 – Landmark del layout 🟢 S
*Fonte: FE-14*
- **Soluzione**
  - Skip link con `focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:px-4 focus:py-2`.
  - `<aside aria-label="{{ __('Sidebar','sage') }}">`.
  - `aria-label="{{ __('Main menu','sage') }}"` sul menu.
  - `the_custom_logo()` con fallback a `$siteName`.

---

## Fase 3: architettura

### M28 – Service Provider e classi PSR-4 🟠 L
*Fonti: PHP-14, PHP-15*
- **Problema**
  - `ThemeServiceProvider` è vuoto.
  - 9 file procedurali vengono caricati con `locate_template`.
  - Controllers e CustomPostTypes contengono funzioni globali senza prefisso (`getAllPosts`, `custom_breadcrumbs`) sotto il namespace PSR-4 `App\`.
  - Il loader di CPT, tassonomie e controller è copiato tre volte.
  - Il loader include `wp-admin/includes/file.php` a ogni richiesta del frontend, solo per usare `list_files()`.
- **Soluzione**
  - Classi `App\PostTypes\Faq` registrate dal provider, oppure `log1x/poet` con `config/poet.php`.
  - `App\Queries\Posts` oppure View Composer.
  - Un `App\Blocks\BlockRenderer`.
  - Nel frattempo, un solo loader con `glob(__DIR__.'/CustomPostTypes/*.php')`.

### M29 – Registrazione dei blocchi e asset per blocco 🟢 M
*Fonti: PHP-16, FE-06*
- **Problema**
  - `glob()` e il parsing dei `block.json` vengono rifatti a ogni `init`.
  - `resources/js/blocks/hero-page.js` non è tra gli input di Vite, il blocco corrispondente non esiste e il file contiene solo un `console.log`.
- **Soluzione**
  - Registrare i blocchi con `wp_register_block_types_from_metadata_collection()` e un manifest generato.
  - Adottare la convenzione `blocks/<slug>/{block.json, <slug>.blade.php, view.js?, style.css?}`.
  - Aggiungere i JS dei blocchi agli input di Vite tramite glob e accodarli solo quando il blocco è presente.
  - Rimuovere `hero-page.js`.

### M30 – Field group ACF 🟢 M
*Fonte: PHP-19*
- **Problema:** `acf-json/group_69c1778d2ba98.json` ha la location `block == all`. Ogni blocco futuro eredita quindi title, content, button e list_style.
- **Soluzione:** un gruppo comune solo per spaziature e colori, più gruppi o campi `clone` specifici per ogni blocco.

### M31 – Struttura dei componenti e styleguide 🟢 M
*Fonte: FE-23*
- **Soluzione**
  - Organizzare i componenti in `components/ui/` (button, title, alert, image), `components/acf/` e `components/content/` (share, breadcrumbs, carousel-navigation).
  - Creare una pagina o un template "Styleguide" visibile solo con `WP_ENV === 'development'`.

### M32 – `base` di Vite 🟠 S
*Fonte: FE-15*
- **Problema:** `vite.config.js:7` contiene `/app/themes/sage/public/build/`, che si rompe se il tema viene rinominato.
- **Soluzione:** ``base: `/app/themes/${path.basename(process.cwd())}/public/build/` ``, oppure una variabile `VITE_THEME_SLUG`.

### M33 – Font e immagini 🟠 M
*Fonte: FE-17*
- **Problema:** non ci sono `@font-face`, preload o un componente immagine responsive.
- **Soluzione**
  - `@font-face` con `font-display: swap` e font woff2 locali.
  - Preload del font principale con `Vite::asset()`.
  - Un componente `<x-image :id :size :sizes :eager>` basato su `wp_get_attachment_image` con `loading`, `fetchpriority` e `sizes`.

### M34 – `editor.js` 🟢 S
*Fonte: FE-18*
- **Problema**
  - Un `MutationObserver` con `subtree` su tutto il body chiama `bindCanvas()` a ogni mutazione.
  - I selettori dipendono dalle classi interne di ACF.
  - `core/edit-post` non funziona nel site editor.
  - I commenti sono in italiano.
- **Soluzione**
  - Limitare le chiamate tramite `requestAnimationFrame`.
  - Nascondere il pannello via CSS.
  - Usare `core/interface` come fallback.
  - Scrivere i commenti in inglese.

---

## Fase 4: qualità, CI e documentazione

### M35 – Analisi statica e test PHP 🟠 M
*Fonti: PHP-24, BED-08*
- **Soluzione**
  ```json
  "require-dev": { "larastan/larastan": "^3.0", "szepeviktor/phpstan-wordpress": "^2.0", "pestphp/pest": "^3.0", "brain/monkey": "^2.6" },
  "scripts": {
    "lint": "pint --test", "lint:fix": "pint",
    "analyse": "phpstan analyse",
    "test": ["@composer validate --strict", "@composer audit", "@lint", "@analyse", "pest"]
  }
  ```
  - `phpstan.neon` a livello 5–6 su `config/` e `app/`.
  - I primi test: `get_custom_padding`, `get_custom_margin` e `get_next_heading_level`.
  - `declare(strict_types=1)` e tipi sulle funzioni.

### M36 – Lint del frontend 🟠 M
*Fonte: FE-19*
- **Soluzione**
  - ESLint, Stylelint, Prettier con `prettier-plugin-tailwindcss` e blade-formatter.
  - Script `lint` e `format`.
  - `.nvmrc` impostato a 22.
  - Allineare l'indentazione a `.editorconfig`: oggi `button` e `share` usano 4 spazi.

### M37 – CI e Dependabot 🟠 M
*Fonte: BED-08*
- **Soluzione**
  - `.github/workflows/ci.yml` con:
    - PHP 8.3 e 8.4;
    - `composer install` con `COMPOSER_AUTH` da secret, per ACF;
    - `composer test`;
    - `npm ci && npm run lint && npm run build` nel tema.
  - `.github/dependabot.yml` per composer, npm e github-actions.

### M38 – Ambiente locale, deploy e `solo.yml` 🟠 M
*Fonte: BED-09*
- **Problema**
  - Non c'è Docker, DDEV o Lando.
  - `solo.yml` ha `processes: {}`.
  - Il deploy nel README è un "upload files" senza `composer install --no-dev -o`.
- **Soluzione**
  - Definire i processi in `solo.yml`, ad esempio `npm run dev` nel tema, e committare il file.
  - Facoltativo: `.ddev/config.yaml` (wordpress, docroot `web`, PHP 8.3).
  - Documentare il deploy: `composer install --no-dev -o`, `npm ci && npm run build`, `.env` sul server.

### M39 – README e convenzioni 🟠 S
*Fonti: BED-05, BED-11, FE-22*
- **Problema**
  - Il README dice "PHP >= 8.0" e "Node >= 16", ma i requisiti reali sono più alti.
  - Cita `npm run dev:watch` (da verificare che esista).
  - `WP_HOME_DOMAIN` è documentato ma non viene usato e non compare in `.env.example`.
  - Mancano le istruzioni per ACF/`auth.json`, `wp dotenv salts`, `wp db create` e gli ambienti.
- **Soluzione:** aggiornare i requisiti e aggiungere le sezioni:
  - "Credenziali premium";
  - "Comandi qualità";
  - "Ambienti";
  - "Convenzioni": blocchi ACF, `render_acf_block`, `get_block_data`, rinomina del tema;
  - "Nuovo blocco: checklist".

---

### M40 – Permalink di default nel setup 🟠 S
*Fonte: review di M02 (2026-11-18)*
- **Problema:** `permalink_structure` è vuoto, cioè i permalink sono impostati su "semplici". In questa modalità WordPress non applica le regole di riscrittura (`class-wp.php:163-165`) e risponde con la home:
  - un URL inesistente risponde 200 invece di 404;
  - la regola `news/archivio/YYYY` di `app/rewrites.php:11-17` non funziona.

  Il template 404 del tema è a posto: `/?p=999999` risponde 404.
- **Soluzione**
  - Impostare la struttura dei permalink nel setup del progetto: `wp rewrite structure '/%postname%/' --hard` nel README, oppure su `after_switch_theme` solo se `permalink_structure` è vuoto, seguito da `flush_rewrite_rules()`.
  - Documentarlo in M39.
  - Poi verificare il 404 e le rewrite di M18.

## Già a posto (verificato)
- I salt vengono da env.
- `DISALLOW_FILE_EDIT` e `DISALLOW_FILE_MODS` sono attivi in produzione, e in produzione gli errori non vengono mostrati.
- `DISALLOW_INDEXING` è attivo in staging e development.
- `.env`, `.env.*` e `auth.json` sono gitignorati e non tracciati.
- `roave/security-advisories` è presente e `composer audit` è pulito.
- `wp-config.php`, `index.php`, `wp-cli.yml` e `bedrock-autoloader.php` sono identici a upstream.
- La rimozione delle emoji funziona su WP 6.8.
- Nel tema non ci sono REST route o AJAX senza `permission_callback`.
