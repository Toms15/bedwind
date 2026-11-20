<p align="center">
  <a href="https://roots.io/sage/"><img alt="Sage" src="https://cdn.roots.io/app/uploads/logo-sage.svg" height="100"></a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/roots/sage"><img alt="Packagist Installs" src="https://img.shields.io/packagist/dt/roots/sage?label=projects%20created&colorB=2b3072&colorA=525ddc&style=flat-square"></a>
  <a href="https://github.com/roots/sage/actions/workflows/main.yml"><img alt="Build Status" src="https://img.shields.io/github/actions/workflow/status/roots/sage/main.yml?branch=main&logo=github&label=CI&style=flat-square"></a>
  <a href="https://bsky.app/profile/roots.dev"><img alt="Follow roots.dev on Bluesky" src="https://img.shields.io/badge/follow-@roots.dev-0085ff?logo=bluesky&style=flat-square"></a>
</p>

# Sage

**Advanced hybrid WordPress starter theme with Laravel Blade and Tailwind CSS**

- 🔧 Clean, efficient theme templating with Laravel Blade
- ⚡️ Modern front-end development workflow powered by Vite
- 🎨 Out of the box support for Tailwind CSS
- 🚀 Harness the power of Laravel with [Acorn integration](https://github.com/roots/acorn)
- 📦 Block editor support built-in

Sage brings proper PHP templating and modern JavaScript tooling to WordPress themes. Write organized, component-based code using Laravel Blade, enjoy instant builds and CSS hot-reloading with Vite, and leverage Laravel's robust feature set through Acorn.

[Read the docs to get started](https://roots.io/sage/docs/installation/)

## Lint e formattazione

Node 22 (`.nvmrc` in questa cartella, dove si eseguono gli script npm: `nvm use`). `engines`: `^22.13.0 || >=24`, cioè l'intersezione di ESLint 10 (`^20.19.0 || ^22.13.0 || >=24`), Vite 8 (`^20.19.0 || >=22.12.0`) ed `editorconfig` 4 (`>=22`, vedi override sotto).

| Script                 | Cosa fa                                                                                     |
| ---------------------- | ------------------------------------------------------------------------------------------- |
| `npm run lint`         | Tutti i controlli sotto, in sequenza (da usare in CI)                                       |
| `npm run lint:js`      | ESLint 10, flat config in `eslint.config.js`                                                |
| `npm run lint:css`     | Stylelint con `stylelint-config-standard` e le at-rule di Tailwind v4 (`.stylelintrc.json`) |
| `npm run lint:blade`   | `blade-formatter --check-formatted` sulle viste                                             |
| `npm run format:check` | `prettier --check .`                                                                        |
| `npm run format`       | Scrive: Prettier (JS, CSS, JSON, Markdown) + blade-formatter                                |
| `npm run format:blade` | Solo blade-formatter                                                                        |

- Prettier usa `prettier-plugin-tailwindcss` con `tailwindStylesheet: ./resources/css/app.css`: ordina le classi in JS e negli `@apply`.
- Nelle Blade l'ordinamento delle classi è **disattivato**: blade-formatter usa internamente Tailwind 3 e con v4 darebbe un ordine sbagliato.
- blade-formatter riformatterebbe il PHP di `@props`/`@php` a 4 spazi (non configurabile): i preamboli PHP dei componenti sono racchiusi tra `{{-- blade-formatter-disable --}}` e `{{-- blade-formatter-enable --}}`.
- Lo stesso vale per gli array multilinea di `@class(...)`: blade-formatter li reindenta con il proprio stile. Si racchiude il solo `@class` tra i due commenti, dentro il tag (vedi `components/share.blade.php` e `modules/only-text.blade.php`), con l'array a 2 spazi.
- Lo stile del PHP inline nelle Blade (`!$x`) differisce da quello di Pint in `app/` (`! $x`): blade-formatter non è configurabile su questo.
- `overrides` in `package.json` (`blade-formatter` → `glob ^14.0.6`, `editorconfig ^4.0.1`): portano `npm audit` a 0 vulnerabilità (ReDoS di `minimatch` e `glob` nelle dipendenze di blade-formatter/js-beautify). Rimuoverli quando blade-formatter aggiornerà le proprie dipendenze; `editorconfig` 4 richiede Node >= 22.
- Esclusi dalla formattazione: `acf-json/` (lo riscrive ACF), `resources/lang/`, lock file, `composer.json`, `pint.json`, `public/`, `vendor/`. Il PHP di `app/` resta a Pint (`vendor/bin/pint`).

## Breadcrumb

Componente pronto, non incluso in nessun template: aggiungerlo dove serve.

```blade
<x-breadcrumbs />
<x-breadcrumbs :jsonld="false" class="mb-6" />
<x-breadcrumbs :items="[['label' => 'Home', 'url' => home_url('/')], ['label' => 'Contatti', 'url' => null]]" />
```

- Voci calcolate da `App\Navigation\Breadcrumbs::items()` sulla query principale: pagine con genitori, articoli (pagina degli articoli → prima categoria con i genitori → titolo), CPT con archivio, tassonomie, archivi per data (anno su `news/archivio/YYYY/`), autore, ricerca, 404 e paginazione. In home page non stampa nulla.
- Markup: `<nav aria-label="Percorso di navigazione">` con `<ol>`, separatori `aria-hidden` e `aria-current="page"` sull'ultima voce.
- JSON-LD `BreadcrumbList` (prop `jsonld`, default `true`): disattivarlo se un plugin SEO lo genera già.

Le voci (`label` in testo semplice, `url` o `null`; l'ultima è la pagina corrente) si modificano con il filtro `sage/breadcrumbs`:

```php
add_filter('sage/breadcrumbs', function (array $items): array {
    // Es.: togliere la voce "Home".
    return array_slice($items, 1);
});
```

## Sponsors

Sage is an open source project and completely free to use. If you've benefited from our projects and would like to support our future endeavors, [please consider sponsoring us](https://github.com/sponsors/roots).

<div align="center">
<a href="https://carrot.com/"><img src="https://cdn.roots.io/app/uploads/carrot.svg" alt="Carrot" width="120" height="90"></a> <a href="https://wordpress.com/"><img src="https://cdn.roots.io/app/uploads/wordpress.svg" alt="WordPress.com" width="120" height="90"></a> <a href="https://worksitesafety.ca/careers/"><img src="https://cdn.roots.io/app/uploads/worksite-safety.svg" alt="Worksite Safety" width="120" height="90"></a> <a href="https://40q.agency/"><img src="https://cdn.roots.io/app/uploads/40q.svg" alt="40Q" width="120" height="90"></a> <a href="https://www.itineris.co.uk/"><img src="https://cdn.roots.io/app/uploads/itineris.svg" alt="Itineris" width="120" height="90"></a> <a href="https://bonsai.so/"><img src="https://cdn.roots.io/app/uploads/bonsai.svg" alt="Bonsai" width="120" height="90"></a>
</div>
