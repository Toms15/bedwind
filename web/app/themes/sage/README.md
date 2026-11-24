# Sage Starter

Tema starter WordPress basato su [Sage](https://roots.io/sage/) 11 di [Roots](https://roots.io/) ([documentazione di Sage](https://roots.io/sage/docs/installation/)): template Blade, [Acorn](https://github.com/roots/acorn) 5 per i servizi di Laravel, Vite 8 e Tailwind CSS 4 per gli asset, blocchi ACF Pro per l'editor.

## Questo tema

Questo README riguarda lo **sviluppo del tema**. Il setup del progetto (Herd, credenziali ACF Pro, `.env`, ambienti, CI, deploy) è nel [README del progetto](../../../../README.md); le convenzioni di codice (blocchi, i18n, PHP, escaping, componenti, styleguide, rinomina del tema) e la **checklist per un nuovo blocco** (che parte da `npm run make:block`) sono in [CONVENTIONS.md](CONVENTIONS.md).

Tutti i comandi seguenti si eseguono da questa cartella (`web/app/themes/sage`), salvo dove indicato.

- [Versione di Node](#versione-di-node)
- [Build](#build)
- [Lint e formattazione](#lint-e-formattazione)
- [Qualità PHP](#qualità-php)
- [Traduzioni](#traduzioni)
- [Font e immagini](#font-e-immagini)
- [Header e logo](#header-e-logo)
- [Breadcrumb](#breadcrumb)
- [Styleguide](#styleguide)
- [Filtri del tema](#filtri-del-tema)
- [Crediti](#crediti)

## Versione di Node

`engines` in `package.json` è `^22.22.2 || ^24.15.0 || >=26`, l'intersezione fra Stylelint 18 e `stylelint-config-standard` 41 (`^22.22.2 || ^24.15.0 || >=26.0.0`), ESLint 10 (`^20.19.0 || ^22.13.0 || >=24`), Vite 8 (`^20.19.0 || >=22.12.0`) ed `editorconfig` 4 (`>=22`, vedi gli override in [Lint e formattazione](#lint-e-formattazione)). Il vincolo più stretto è quello di Stylelint: con Node 22 serve almeno la 22.22.2.

`.nvmrc` indica `22` (nvm non accetta intervalli, e un `22.22.2` fisso costringerebbe tutti a quella patch esatta). In CI `actions/setup-node` lo risolve nell'ultima 22.x. In locale:

- `nvm install` legge `.nvmrc`, installa l'ultima 22.x se manca e la usa: preferiscilo a `nvm use`;
- `nvm use` sceglie la 22.x più recente **già installata**, che può essere precedente alla 22.22.2 (`npm ci` mostra allora l'avviso `EBADENGINE` e Stylelint può fallire): controlla con `node -v`;
- i processi di [Solo](https://soloterm.com) non eseguono `nvm use` e usano il default di nvm: `nvm alias default 22`.

## Build

| Script                          | Cosa fa                                                                                                                             |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `npm run dev`                   | Dev server Vite con hot reload (HTTPS sulla porta 5173 con il certificato di Herd)                                                  |
| `npm run build`                 | Build di produzione in `public/build/` (asset con hash, `manifest.json`, theme.json)                                                |
| `npm run blocks:manifest`       | Rigenera `resources/views/blocks/blocks-manifest.php` dai file `block.json` (versionato)                                            |
| `npm run blocks:manifest:check` | Termina con un errore se il manifest non è aggiornato, senza scriverlo (usato in CI)                                                |
| `npm run icons`                 | Rigenera `resources/icons/phosphor.php` con le icone Phosphor usate (versionato)                                                    |
| `npm run icons:check`           | Termina con un errore se il manifest delle icone non è aggiornato (usato in CI)                                                     |
| `npm run test:js`               | Test degli script Node in `resources/scripts/` (`node --test`, usato in CI)                                                         |
| `npm run make:block`            | Wizard per un nuovo blocco ACF: file, field group, traduzioni e manifest ([CONVENTIONS](CONVENTIONS.md#nuovo-blocco-con-il-wizard)) |

- `npm run dev` scrive `public/hot` e lo rimuove quando Vite si ferma: finché il file esiste il tema carica gli asset dal dev server, altrimenti da `public/build/`. Senza nessuno dei due il sito non ha CSS/JS.
- Entry point (`vite.config.js`): `resources/css/app.css`, `resources/js/app.js` (frontend, `@vite` in `layouts/app.blade.php`), `resources/css/editor.css`, `resources/js/editor.js` (editor a blocchi, `app/setup.php`), `resources/css/styleguide.css` (solo su `/styleguide/` in development), più gli eventuali `resources/views/blocks/*/view.js` e `style.css` di ogni blocco (trovati con un glob all'avvio di Vite, caricati solo nelle pagine che mostrano il blocco: vedi [CONVENTIONS](CONVENTIONS.md#asset-dei-blocchi)). Alias: `@scripts`, `@styles`, `@fonts`, `@images`.
- Manifest dei blocchi: `app/blocks.php` registra i blocchi da `resources/views/blocks/blocks-manifest.php` (`wp_register_block_types_from_metadata_collection()`, WordPress 6.8+). Vite lo riscrive a ogni build e, in dev, a ogni modifica di un `block.json`; `npm run blocks:manifest` fa lo stesso senza Vite. È versionato (WordPress lo legge a runtime, anche dove Node non c'è) e la CI fallisce se non è aggiornato: committalo insieme alle modifiche ai `block.json`.
- Manifest delle icone: `<x-ui.icon>` stampa le icone [Phosphor](https://phosphoricons.com) (pacchetto `@phosphor-icons/core`, dipendenza di sviluppo) leggendole da `resources/icons/phosphor.php`, che contiene solo le icone usate: i tag `<x-ui.icon>` con `name` statico nelle view e le voci di `resources/icons/allowlist.json` (icone dinamiche). Lo genera `resources/scripts/icons.js`; Vite lo riscrive a ogni build e, in dev, a ogni modifica di una view o dell'allowlist. Un nome o un peso inesistente fa fallire build e `npm run icons`, con file, riga e dei suggerimenti. È versionato per lo stesso motivo del manifest dei blocchi (`node_modules` non viene deployato) e la CI fallisce se non è aggiornato: committalo insieme alle view. Uso del componente in [CONVENTIONS](CONVENTIONS.md#icone).
- `public/build/assets/theme.json` è generato da `@roots/vite-plugin` a partire da `theme.json` e dai token Tailwind di `resources/css/theme.css` (colori, font, dimensioni del testo).
- L'URL del dev server viene da `WP_HOME` nel `.env` del progetto (da lì si leggono solo `WP_HOME` e `APP_URL`): determina l'allowlist CORS e l'host TLS. Il certificato è quello di Herd/Valet per quell'host, se esiste; altrimenti il dev server torna al rilevamento predefinito di Vite.
- Variabili di shell (non lette da `.env`): `APP_URL` sostituisce `WP_HOME` per il dev server; `VITE_THEME_SLUG` sostituisce il nome della cartella del tema usato nel base URL degli asset (`/app/themes/<slug>/public/build/`). Serve solo quando il tema è un **symlink** la cui cartella di destinazione ha un nome diverso da quello usato da WordPress, es. `VITE_THEME_SLUG=sage npm run build`. Una cartella del tema rinominata funziona senza.

## Lint e formattazione

| Script                 | Cosa fa                                                                                                                                     |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `npm run lint`         | Tutti i controlli seguenti, in sequenza (usato in CI)                                                                                       |
| `npm run lint:js`      | ESLint 10, flat config in `eslint.config.js`                                                                                                |
| `npm run lint:css`     | Stylelint (`resources/css` e gli `style.css` dei blocchi) con `stylelint-config-standard` e le at-rule di Tailwind v4 (`.stylelintrc.json`) |
| `npm run lint:blade`   | `blade-formatter --check-formatted` sulle view                                                                                              |
| `npm run format:check` | `prettier --check .`                                                                                                                        |
| `npm run format`       | Scrive i file: Prettier (JS, CSS, JSON, Markdown) + blade-formatter                                                                         |
| `npm run format:blade` | Solo blade-formatter                                                                                                                        |

- Prettier usa `prettier-plugin-tailwindcss` con `tailwindStylesheet: ./resources/css/app.css`: ordina le classi nel JS e in `@apply`.
- In Blade l'ordinamento delle classi è **disattivato**: blade-formatter usa internamente Tailwind 3 e con la v4 produrrebbe un ordine sbagliato.
- blade-formatter riformatterebbe il PHP di `@props`/`@php` con 4 spazi (non configurabile): i preamboli PHP dei componenti sono racchiusi fra `{{-- blade-formatter-disable --}}` e `{{-- blade-formatter-enable --}}`.
- Lo stesso vale per gli array `@class(...)` su più righe, che blade-formatter re-indenta con il suo stile: solo il `@class` è racchiuso fra i due commenti, dentro il tag (vedi `components/content/share.blade.php` e `modules/only-text.blade.php`), con l'array a 2 spazi.
- Lo stile del PHP inline in Blade (`!$x`) è diverso da quello di Pint in `app/` (`! $x`): blade-formatter non si può configurare per questo.
- Gli `overrides` in `package.json` (`blade-formatter` → `glob ^14.0.6`, `editorconfig ^4.0.1`) portano `npm audit` a 0 vulnerabilità (ReDoS in `minimatch` e `glob` fra le dipendenze di blade-formatter/js-beautify). Vanno rimossi quando blade-formatter aggiornerà le proprie dipendenze; `editorconfig` 4 richiede Node >= 22.
- Esclusi da Prettier (`.prettierignore`): `acf-json/` (riscritto da ACF), `resources/lang/`, i file di lock, `composer.json`, `pint.json`, `public/`, `vendor/`, `node_modules/` e `*.blade.php` (blade-formatter). Il PHP di `app/` è affidato a Pint (`composer lint`, vedi sotto). Il Markdown è formattato con `proseWrap: preserve`.

## Qualità PHP

Gli strumenti PHP sono dipendenze di sviluppo di questo `composer.json` (l'autoload `App\` è qui). Gli script si eseguono da questa cartella o dalla root del progetto, che li delega con `composer --working-dir=web/app/themes/sage`.

| Script              | Cosa fa                                                                                                                     |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `composer lint`     | `pint --test`, preset `laravel` (`pint.json`). Dalla root controlla anche la root (preset `per`)                            |
| `composer lint:fix` | Pint, scrivendo i file                                                                                                      |
| `composer analyse`  | PHPStan 2, livello 6, su `app/` e `functions.php` (`phpstan.neon.dist`)                                                     |
| `composer test`     | Pest 3 + Brain Monkey: test unitari in `tests/Unit`, senza WordPress né database                                            |
| `composer check`    | `validate --strict`, `audit`, `lint`, `analyse`, `test` in sequenza (usato in CI). Dalla root: anche i controlli della root |

Dipendenze:

- `config.platform.php` è `8.2.0`, il minimo dichiarato in `require.php`: `composer update` risolve le versioni come se girasse su PHP 8.2, anche con un PHP locale più recente. Senza, il lock sceglierebbe pacchetti che richiedono PHP 8.4 (es. `symfony/clock`, `symfony/string`, `symfony/translation` 8.x) e il tema non partirebbe su 8.2/8.3. Va alzato insieme a `require.php`; a quel punto Pest e Symfony possono passare alla major successiva.
- Per via di `config.platform.php`, `composer install`/`update` **non controllano la versione reale di PHP** della macchina (Composer crede che sia 8.2.0): l'installazione su PHP 8.1 riesce. Il controllo avviene solo a runtime, in `vendor/composer/platform_check.php`, che ferma il sito su PHP < 8.2.

PHPStan:

- `szepeviktor/phpstan-wordpress` (stub di WordPress e tipi di `apply_filters()` & co.) e `php-stubs/acf-pro-stubs` (`get_field()`, ...). Niente Larastan: Acorn non è un'applicazione Laravel completa e il tema non usa Eloquent; le facade di Illuminate sono già tipizzate.
- `stubs/acorn.php` dichiara gli helper di Laravel che Acorn carica all'avvio e che PHPStan non vede (`public_path()`). È letto solo da PHPStan: non serve in produzione.
- Per un livello più alto: `vendor/bin/phpstan analyse --level=7`. Niente baseline: gli errori si correggono, e le rare eccezioni hanno un `@phpstan-ignore <identifier>` con il motivo accanto.
- I file in `app/` dichiarano `declare(strict_types=1)`: vale solo per le chiamate fatte da quei file, non per le callback chiamate da WordPress.

Scrivere un test:

- Un file `tests/Unit/*Test.php`; `tests/Pest.php` usa `Tests\TestCase` (`Monkey\setUp()`/`Monkey\tearDown()`) e carica `app/helpers.php`. Le classi di `app/` arrivano dall'autoload.
- Le funzioni di WordPress e ACF si simulano con Brain Monkey: `Functions\when('get_field')->justReturn('...')`, `->alias(fn ...)`, oppure `Functions\expect('get_field')->once()->with('excerpt', 42)->andReturn('...')` per verificare la chiamata.
- Si testa il comportamento pubblico: i preset di spaziatura dei blocchi passano da `BlockRenderer::data()` (vedi `tests/Unit/BlockRendererTest.php`), senza rendere pubblici i metodi privati. Le view Blade non vengono renderizzate nei test unitari: la logica da testare va tenuta in `app/`.

```php
use App\Blocks\BlockRenderer;
use Brain\Monkey\Functions;

test('converte il preset di padding nelle classi Tailwind', function () {
    $fields = ['padding' => 'pt', 'padding_dimension' => 'm', 'margin' => 'no'];
    Functions\when('get_field')->alias(fn (string $name) => $fields[$name] ?? null);
    Functions\when('sanitize_hex_color')->justReturn(null);

    expect(BlockRenderer::data([])['spacings']['padding'])->toBe('pt-5 lg:pt-8 xl:pt-10');
});
```

## Traduzioni

La lingua sorgente del tema è l'**italiano**: ogni stringa in `app/`, `functions.php`, `resources/views/` (Blade, `block.json`), i field group ACF in `acf-json/`, `theme.json` e l'header di `style.css` è scritta in italiano e usa il text domain `sage` (`__('Continua a leggere', 'sage')`, `{{ __('Vai al contenuto', 'sage') }}`). Con il sito in italiano non viene caricato nessun file di traduzione; `resources/lang/en_US.po` fornisce la traduzione inglese, così il progetto passa all'inglese cambiando la lingua del sito (`wp site switch-language en_US`, oppure _Impostazioni → Generali → Lingua del sito_).

File in `resources/lang/` (versionati, il tema si pubblica così com'è):

- `sage.pot`: template con tutte le stringhe del tema;
- `{locale}.po`: traduzione, da modificare a mano o con Poedit;
- `{locale}.mo` e `{locale}.l10n.php`: file compilati caricati da WordPress (dalla 6.5 è preferito il `.l10n.php`);
- `acf-json.php`: generato da `npm run translate:acf`, non versionato (vedi sotto).

Il percorso è registrato con `load_theme_textdomain()` in `app/setup.php` (e dichiarato come `Domain Path` in `style.css`). Le etichette ACF sono tradotte a runtime tramite `acf/settings/l10n_textdomain` (disattivato mentre si modificano i field group, così acf-json resta sempre in italiano); `wp i18n make-pot` non legge acf-json, quindi `npm run translate:acf` estrae quelle stringhe in `resources/lang/acf-json.php` prima di generare il `.pot`.

Script (richiedono WP-CLI):

| Script                      | Cosa fa                                                                                                                         |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `npm run translate`         | `translate:pot` + `translate:update`: da eseguire dopo aver aggiunto o modificato stringhe                                      |
| `npm run translate:acf`     | Estrae le stringhe ACF in `resources/lang/acf-json.php` (`resources/scripts/acf-strings.js`)                                    |
| `npm run translate:pot`     | `translate:acf`, poi `wp i18n make-pot` su `functions.php`, `app`, `resources/views`, `acf-json.php`, `theme.json`, `style.css` |
| `npm run translate:update`  | `wp i18n update-po`: unisce `sage.pot` in ogni `.po`                                                                            |
| `npm run translate:compile` | `translate:mo` + `translate:php` + `translate:js`: da eseguire dopo aver tradotto i `.po`                                       |
| `npm run translate:mo`      | `wp i18n make-mo`                                                                                                               |
| `npm run translate:php`     | `wp i18n make-php` (`.l10n.php`)                                                                                                |
| `npm run translate:js`      | `wp i18n make-json` (JSON per le stringhe JS, se ce ne sono)                                                                    |

Per aggiungere una lingua (es. `de_DE`): copia `sage.pot` in `resources/lang/de_DE.po`, imposta gli header `Language` e `Plural-Forms`, traducilo ed esegui `npm run translate:compile`. Per le stringhe del core e dei plugin vedi [Traduzioni di WordPress](../../../../README.md#traduzioni-di-wordpress) nel README del progetto.

## Font e immagini

Il tema non include **nessun webfont**: il testo usa lo stack di sistema in `--font-body` (`resources/css/theme.css`).

Per aggiungere un webfont:

1. Copia i file `.woff2` in `resources/fonts/`.
2. Decommenta e adatta l'esempio di `@font-face` in `resources/css/fonts.css` (importato da `app.css` e `editor.css`): `font-display: swap`, un intervallo di `font-weight` per i font variabili, `unicode-range` facoltativo. Mantieni il percorso relativo `url("../fonts/...")`.
3. Metti la famiglia in testa a `--font-body`, lasciando lo stack di sistema come fallback: `--font-body: "Inter", ui-sans-serif, system-ui, sans-serif;`.
4. Facoltativo: precarica il font usato above the fold (al massimo 1–2 file):

   ```php
   add_filter('sage/preload_fonts', fn () => ['resources/fonts/inter-var.woff2']);
   ```

   `app/setup.php` stampa in `wp_head` un `<link rel="preload" as="font" type="font/woff2" crossorigin>` per ogni file elencato che esiste nel tema (i file mancanti vengono saltati, sia in build sia con il dev server). L'URL viene da `Vite::asset()` e coincide con quello del `@font-face` compilato, quindi il font viene scaricato una volta sola. I file sotto l'`assetsInlineLimit` di Vite (4 KB) verrebbero inclusi nel CSS come data URI: i font reali sono sempre più grandi.

Per le immagini allegate usa il componente `<x-ui.image>` (`resources/views/components/ui/image.blade.php`), un wrapper di `wp_get_attachment_image()`:

```blade
<x-ui.image :id="$post['image_id']" />                                  {{-- large, lazy gestito da WP --}}
<x-ui.image :id="$hero_id" size="full" sizes="100vw" eager class="w-full" /> {{-- immagine LCP: eager + fetchpriority high --}}
<x-ui.image :id="$id" alt="" :fallback="false" />                        {{-- decorativa, nulla senza immagine --}}
```

WordPress aggiunge `srcset`, `sizes`, `width`/`height`, `decoding="async"` e sceglie da sé `loading`/`fetchpriority` (anteponendo `auto,` a `sizes` nelle immagini lazy); `eager` forza `loading="eager" fetchpriority="high"` per una singola immagine above the fold. Con un ID vuoto o che non è un'immagine viene stampato il `resources/images/fallback.svg` locale (alt `""`, lazy), a meno di `:fallback="false"`.

L'upload di SVG è consentito solo agli utenti con `edit_others_posts` (filtro `sage/svg_upload_capability`) e i file vengono sanificati con `enshrined/svg-sanitize` prima che WordPress li salvi (`app/svg.php`).

## Header e logo

- Il layout (`layouts/app.blade.php`) ha uno skip link _Vai al contenuto_ verso `<main id="main" tabindex="-1">`, che riceve il focus, e la sidebar, se presente, in un `<aside>` etichettato; l'header mette il menu `primary_navigation` in un `<nav>` con `aria-label`.
- Il tema attiva `custom-logo` (_Aspetto → Personalizza → Identità del sito_; dimensioni consigliate 240×80, flessibili). L'header (`sections/header.blade.php`) stampa il logo limitato a 80px di altezza; senza logo mostra il nome del sito come link alla home, con `aria-current="page"` sulla front page.
- `get_custom_logo_image_attributes` (`app/filters.php`) calcola `sizes` sulla larghezza reale del logo nell'header e imposta `fetchpriority="auto"`, così il logo non sottrae priorità all'immagine LCP.

## Breadcrumb

Componente pronto, non incluso in nessun template: aggiungilo dove serve.

```blade
<x-content.breadcrumbs />
<x-content.breadcrumbs :jsonld="false" class="mb-6" />
<x-content.breadcrumbs :items="[['label' => 'Home', 'url' => home_url('/')], ['label' => 'Contatti', 'url' => null]]" />
```

- Voci calcolate da `App\Navigation\Breadcrumbs::items()` a partire dalla query principale: pagine con genitori, articoli (pagina degli articoli → prima categoria con i suoi genitori → titolo), CPT con archivio, tassonomie, archivi per data (anno su `news/archivio/YYYY/`), autore, ricerca, 404 e paginazione. Sulla front page non viene stampato nulla.
- Markup: `<nav aria-label="Percorso di navigazione">` con `<ol>`, separatori `aria-hidden` e `aria-current="page"` sull'ultima voce.
- JSON-LD `BreadcrumbList` (prop `jsonld`, default `true`): disattivalo se un plugin SEO ne produce già uno.

Le voci (`label` come testo semplice, `url` oppure `null`; l'ultima è la pagina corrente) si possono modificare con il filtro `sage/breadcrumbs`:

```php
add_filter('sage/breadcrumbs', function (array $items): array {
    // Es. rimuove la voce "Home".
    return array_slice($items, 1);
});
```

## Styleguide

Con `WP_ENV=development` il tema serve una styleguide su `/styleguide/` (es. `https://project-name.test/styleguide/`): design token dalla build (prima `npm run build` o `npm run dev`), titoli, link e focus, e tutti i componenti (`<x-ui.*>`, `<x-content.*>`, `<x-acf.placeholder>`) con le loro varianti. È `noindex`, non è collegata dal sito e non è registrata negli altri ambienti (404). La regola di rewrite viene aggiunta o rimossa automaticamente alla prima richiesta dopo un cambio di ambiente; se in development `/styleguide/` dà 404, esegui `wp rewrite flush`. Dettagli in [CONVENTIONS.md](CONVENTIONS.md#styleguide).

## Filtri del tema

| Filtro                        | Default               | Dove                                                                                      |
| ----------------------------- | --------------------- | ----------------------------------------------------------------------------------------- |
| `sage/breadcrumbs`            | voci calcolate        | `App\Navigation\Breadcrumbs::items()`                                                     |
| `sage/core_blocks_post_types` | `['post', 'faq']`     | `app/blocks.php`: post type in cui i blocchi core sono consentiti (altrove: solo ACF)     |
| `sage/extra_blocks`           | `[]`                  | `app/blocks.php`: altri blocchi (es. di terze parti, `my-plugin/form`) consentiti ovunque |
| `sage/preload_fonts`          | `[]`                  | `app/setup.php`: font da precaricare                                                      |
| `sage/svg_upload_capability`  | `'edit_others_posts'` | `app/svg.php`: capability richiesta per caricare SVG                                      |
| `sage/yearly_archive_base`    | `'news/archivio'`     | `app/rewrites.php`: base degli archivi annuali (dopo averlo cambiato, `wp rewrite flush`) |

## Crediti

Basato su [Sage](https://github.com/roots/sage) di [Roots](https://roots.io/), rilasciato con licenza MIT (vedi [LICENSE.md](LICENSE.md)). Se Sage ti è utile, valuta di [sostenere Roots](https://github.com/sponsors/roots).
