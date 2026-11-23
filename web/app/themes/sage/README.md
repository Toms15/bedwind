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

## This theme

This README covers **theme development**. The project setup (Herd, ACF Pro credentials, `.env`, environments, CI, deploy) is in the [project README](../../../../README.md); the coding conventions (blocks, i18n, PHP, escaping, components, renaming the theme) and the **new block checklist** are in [CONVENTIONS.md](CONVENTIONS.md).

All commands below run from this folder (`web/app/themes/sage`) unless stated otherwise.

- [Node version](#node-version)
- [Build](#build)
- [Lint and formatting](#lint-and-formatting)
- [PHP quality](#php-quality)
- [Translations](#translations)
- [Fonts and images](#fonts-and-images)
- [Breadcrumbs](#breadcrumbs)
- [Theme filters](#theme-filters)

## Node version

`engines` in `package.json` is `^22.22.2 || ^24.15.0 || >=26`, the intersection of Stylelint 18 and `stylelint-config-standard` 41 (`^22.22.2 || ^24.15.0 || >=26.0.0`), ESLint 10 (`^20.19.0 || ^22.13.0 || >=24`), Vite 8 (`^20.19.0 || >=22.12.0`) and `editorconfig` 4 (`>=22`, see the overrides in [Lint and formatting](#lint-and-formatting)). The tightest constraint is Stylelint's: Node 22 needs at least 22.22.2.

`.nvmrc` says `22` (nvm does not accept ranges, and a pinned `22.22.2` would force everyone onto that exact patch). In CI `actions/setup-node` resolves it to the latest 22.x. Locally:

- `nvm install` reads `.nvmrc`, installs the latest 22.x if it is missing and uses it: prefer it to `nvm use`;
- `nvm use` picks the newest 22.x **already installed**, which may be older than 22.22.2 (`npm ci` then warns with `EBADENGINE` and Stylelint can fail): check with `node -v`;
- [Solo](https://soloterm.com) processes do not run `nvm use`, they use the nvm default: `nvm alias default 22`.

## Build

| Script                          | What it does                                                                                     |
| ------------------------------- | ------------------------------------------------------------------------------------------------ |
| `npm run dev`                   | Vite dev server with hot reload (HTTPS on port 5173 with the Herd certificate)                   |
| `npm run build`                 | Production build in `public/build/` (hashed assets, `manifest.json`, theme.json)                 |
| `npm run blocks:manifest`       | Regenerates `resources/views/blocks/blocks-manifest.php` from the `block.json` files (versioned) |
| `npm run blocks:manifest:check` | Exits with an error if the manifest is stale, without writing it (used in CI)                    |

- `npm run dev` writes `public/hot` and removes it when Vite stops: while the file exists the theme loads the assets from the dev server, otherwise from `public/build/`. Without either the site has no CSS/JS.
- Entry points (`vite.config.js`): `resources/css/app.css`, `resources/js/app.js` (frontend, `@vite` in `layouts/app.blade.php`), `resources/css/editor.css`, `resources/js/editor.js` (block editor, `app/setup.php`), plus the optional `resources/views/blocks/*/view.js` and `style.css` of each block (globbed when Vite starts, enqueued only on the pages that render the block: see [CONVENTIONS](CONVENTIONS.md#block-assets)). Aliases: `@scripts`, `@styles`, `@fonts`, `@images`.
- Block manifest: `app/blocks.php` registers the blocks from `resources/views/blocks/blocks-manifest.php` (`wp_register_block_types_from_metadata_collection()`, WordPress 6.8+). Vite rewrites it at every build and, in dev, whenever a `block.json` changes; `npm run blocks:manifest` does the same without Vite. It is versioned (WordPress reads it at runtime, also where Node is not available) and CI fails if it is stale: commit it with the `block.json` changes.
- `public/build/assets/theme.json` is generated by `@roots/vite-plugin` from `theme.json` and the Tailwind tokens in `resources/css/theme.css` (colors, fonts, font sizes).
- The dev server URL comes from `WP_HOME` in the project `.env` (only `WP_HOME` and `APP_URL` are read from it): it sets the CORS allowlist and the TLS host. The certificate is the Herd/Valet one of that host, if it exists; otherwise the dev server falls back to Vite's default detection.
- Shell variables (not read from `.env`): `APP_URL` overrides `WP_HOME` for the dev server; `VITE_THEME_SLUG` overrides the theme folder name used in the asset base URL (`/app/themes/<slug>/public/build/`). It is only needed when the theme is a **symlink** whose target folder has a different name from the one WordPress uses, e.g. `VITE_THEME_SLUG=sage npm run build`. A renamed theme folder works without it.

## Lint and formatting

| Script                 | What it does                                                                                                                              |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `npm run lint`         | Every check below, in sequence (used in CI)                                                                                               |
| `npm run lint:js`      | ESLint 10, flat config in `eslint.config.js`                                                                                              |
| `npm run lint:css`     | Stylelint (`resources/css` and the block `style.css`) with `stylelint-config-standard` and the Tailwind v4 at-rules (`.stylelintrc.json`) |
| `npm run lint:blade`   | `blade-formatter --check-formatted` on the views                                                                                          |
| `npm run format:check` | `prettier --check .`                                                                                                                      |
| `npm run format`       | Writes: Prettier (JS, CSS, JSON, Markdown) + blade-formatter                                                                              |
| `npm run format:blade` | blade-formatter only                                                                                                                      |

- Prettier uses `prettier-plugin-tailwindcss` with `tailwindStylesheet: ./resources/css/app.css`: it sorts the classes in JS and in `@apply`.
- In Blade class sorting is **off**: blade-formatter uses Tailwind 3 internally and with v4 it would produce a wrong order.
- blade-formatter would reformat the PHP of `@props`/`@php` with 4 spaces (not configurable): the PHP preambles of the components are wrapped in `{{-- blade-formatter-disable --}}` and `{{-- blade-formatter-enable --}}`.
- The same applies to multi-line `@class(...)` arrays, which blade-formatter re-indents with its own style: only the `@class` is wrapped in the two comments, inside the tag (see `components/share.blade.php` and `modules/only-text.blade.php`), with the array at 2 spaces.
- The style of inline PHP in Blade (`!$x`) differs from Pint's in `app/` (`! $x`): blade-formatter cannot be configured for this.
- `overrides` in `package.json` (`blade-formatter` → `glob ^14.0.6`, `editorconfig ^4.0.1`) bring `npm audit` to 0 vulnerabilities (ReDoS in `minimatch` and `glob` among the blade-formatter/js-beautify dependencies). Remove them when blade-formatter updates its own dependencies; `editorconfig` 4 requires Node >= 22.
- Excluded from Prettier (`.prettierignore`): `acf-json/` (rewritten by ACF), `resources/lang/`, lock files, `composer.json`, `pint.json`, `public/`, `vendor/`, `node_modules/` and `*.blade.php` (blade-formatter). The PHP of `app/` is left to Pint (`composer lint`, see below). Markdown is formatted with `proseWrap: preserve`.

## PHP quality

The PHP tools are dev dependencies of this `composer.json` (the `App\` autoload is here). The scripts run from this folder or from the project root, which delegates them with `composer --working-dir=web/app/themes/sage`.

| Script              | What it does                                                                                                     |
| ------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `composer lint`     | `pint --test`, preset `laravel` (`pint.json`). From the root it also checks the root (preset `per`)              |
| `composer lint:fix` | Pint, writing                                                                                                    |
| `composer analyse`  | PHPStan 2, level 6, on `app/` and `functions.php` (`phpstan.neon.dist`)                                          |
| `composer test`     | Pest 3 + Brain Monkey: unit tests in `tests/Unit`, without WordPress or a database                               |
| `composer check`    | `validate --strict`, `audit`, `lint`, `analyse`, `test` in sequence (used in CI). From the root: root checks too |

Dependencies:

- `config.platform.php` is `8.2.0`, the minimum declared in `require.php`: `composer update` resolves versions as if it ran on PHP 8.2, even with a newer local PHP. Without it the lock would pick packages that require PHP 8.4 (e.g. `symfony/clock`, `symfony/string`, `symfony/translation` 8.x) and the theme would not start on 8.2/8.3. Raise it together with `require.php`; with it Pest and Symfony can move to the next major.
- Because of `config.platform.php`, `composer install`/`update` **do not check the real PHP version** of the machine (Composer believes it is 8.2.0): installing on PHP 8.1 succeeds. The check only happens at runtime, in `vendor/composer/platform_check.php`, which stops the site on PHP < 8.2.

PHPStan:

- `szepeviktor/phpstan-wordpress` (WordPress stubs and the types of `apply_filters()` & co.) and `php-stubs/acf-pro-stubs` (`get_field()`, ...). No Larastan: Acorn is not a full Laravel application and the theme does not use Eloquent; the Illuminate facades are already typed.
- `stubs/acorn.php` declares the Laravel helpers that Acorn loads at boot and PHPStan cannot see (`public_path()`). It is only read by PHPStan: not needed in production.
- For a higher level: `vendor/bin/phpstan analyse --level=7`. No baseline: errors are fixed, and the rare exceptions have a `@phpstan-ignore <identifier>` with the reason next to it.
- The files in `app/` declare `declare(strict_types=1)`: it only applies to the calls made from those files, not to the callbacks called by WordPress.

Writing a test:

- One `tests/Unit/*Test.php` file; `tests/Pest.php` uses `Tests\TestCase` (`Monkey\setUp()`/`Monkey\tearDown()`) and loads `app/helpers.php`. The classes in `app/` come from the autoload.
- WordPress and ACF functions are mocked with Brain Monkey: `Functions\when('get_field')->justReturn('...')`, `->alias(fn ...)`, or `Functions\expect('get_field')->once()->with('excerpt', 42)->andReturn('...')` to verify the call.
- Test the public behaviour: the block spacing presets go through `BlockRenderer::data()` (see `tests/Unit/BlockRendererTest.php`), without making private methods public. Blade views are not rendered in the unit tests: keep the logic worth testing in `app/`.

```php
use App\Blocks\BlockRenderer;
use Brain\Monkey\Functions;

it('maps the padding preset to Tailwind classes', function () {
    $fields = ['padding' => 'pt', 'padding_dimension' => 'm', 'margin' => 'no'];
    Functions\when('get_field')->alias(fn (string $name) => $fields[$name] ?? null);
    Functions\when('sanitize_hex_color')->justReturn(null);

    expect(BlockRenderer::data([])['spacings']['padding'])->toBe('pt-5 lg:pt-8 xl:pt-10');
});
```

## Translations

The source language of the theme is **Italian**: every string in `app/`, `resources/views/` (Blade, `block.json`), the ACF field groups in `acf-json/`, `theme.json` and the `style.css` header is written in Italian and uses the `sage` text domain (`__('Continua a leggere', 'sage')`, `{{ __('Vai al contenuto', 'sage') }}`). With the site in Italian no translation file is loaded; `resources/lang/en_US.po` provides the English translation, so the project can be switched to English by changing the site language (`wp site switch-language en_US`, or _Settings → General → Site Language_).

Files in `resources/lang/` (versioned, the theme is deployed as is):

- `sage.pot`: template with every string of the theme;
- `{locale}.po`: translation, edited by hand or with Poedit;
- `{locale}.mo` and `{locale}.l10n.php`: compiled files loaded by WordPress (since 6.5 the `.l10n.php` file is preferred);
- `acf-json.php`: generated by `npm run translate:acf`, not versioned (see below).

The path is registered with `load_theme_textdomain()` in `app/setup.php` (also declared as `Domain Path` in `style.css`). ACF labels are translated at runtime through `acf/settings/l10n_textdomain` (disabled while editing field groups, so acf-json always stays in Italian); `wp i18n make-pot` does not read acf-json, so `npm run translate:acf` extracts those strings into `resources/lang/acf-json.php` before the `.pot` is built.

Scripts (they require WP-CLI):

| Script                      | What it does                                                                                                    |
| --------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `npm run translate`         | `translate:pot` + `translate:update`: run it after adding or changing strings                                   |
| `npm run translate:acf`     | Extracts the ACF strings into `resources/lang/acf-json.php` (`resources/scripts/acf-strings.js`)                |
| `npm run translate:pot`     | `translate:acf`, then `wp i18n make-pot` on `app`, `resources/views`, `acf-json.php`, `theme.json`, `style.css` |
| `npm run translate:update`  | `wp i18n update-po`: merges `sage.pot` into every `.po`                                                         |
| `npm run translate:compile` | `translate:mo` + `translate:php` + `translate:js`: run it after translating the `.po` files                     |
| `npm run translate:mo`      | `wp i18n make-mo`                                                                                               |
| `npm run translate:php`     | `wp i18n make-php` (`.l10n.php`)                                                                                |
| `npm run translate:js`      | `wp i18n make-json` (JSON for JS strings, if any)                                                               |

To add a language (e.g. `de_DE`): copy `sage.pot` to `resources/lang/de_DE.po`, set the `Language` and `Plural-Forms` headers, translate it and run `npm run translate:compile`. For WordPress core and plugin strings see [WordPress translations](../../../../README.md#wordpress-translations) in the project README.

## Fonts and images

The theme ships **no webfont**: text uses the system stack in `--font-body` (`resources/css/theme.css`).

To add a webfont:

1. Copy the `.woff2` files into `resources/fonts/`.
2. Uncomment and adapt the `@font-face` example in `resources/css/fonts.css` (imported by `app.css` and `editor.css`): `font-display: swap`, a `font-weight` range for variable fonts, optional `unicode-range`. Keep the relative `url("../fonts/...")` path.
3. Put the family first in `--font-body`, keeping the system stack as fallback: `--font-body: "Inter", ui-sans-serif, system-ui, sans-serif;`.
4. Optionally preload the font used above the fold (1–2 files at most):

   ```php
   add_filter('sage/preload_fonts', fn () => ['resources/fonts/inter-var.woff2']);
   ```

   `app/setup.php` prints `<link rel="preload" as="font" type="font/woff2" crossorigin>` in `wp_head` for every listed file that exists in the theme (missing files are skipped, in build and with the dev server). The URL comes from `Vite::asset()` and matches the one in the compiled `@font-face`, so the font is downloaded once. Files under Vite's `assetsInlineLimit` (4 KB) would be inlined in the CSS as data URIs: real fonts are always larger.

For attachment images use the `<x-image>` component (`resources/views/components/image.blade.php`), a wrapper around `wp_get_attachment_image()`:

```blade
<x-image :id="$post['image_id']" />                                  {{-- large, lazy handled by WP --}}
<x-image :id="$hero_id" size="full" sizes="100vw" eager class="w-full" /> {{-- LCP image: eager + fetchpriority high --}}
<x-image :id="$id" alt="" :fallback="false" />                        {{-- decorative, nothing printed without image --}}
```

WordPress adds `srcset`, `sizes`, `width`/`height`, `decoding="async"` and picks `loading`/`fetchpriority` itself (prefixing `sizes` with `auto,` on lazy images); `eager` forces `loading="eager" fetchpriority="high"` for a single above-the-fold image. With an empty or non-image ID the local `resources/images/fallback.svg` is printed (alt `""`, lazy) unless `:fallback="false"`.

SVG uploads are allowed only to users with `edit_others_posts` (filter `sage/svg_upload_capability`) and are sanitized with `enshrined/svg-sanitize` before WordPress stores them (`app/svg.php`).

## Breadcrumbs

Ready-made component, not included in any template: add it where needed.

```blade
<x-breadcrumbs />
<x-breadcrumbs :jsonld="false" class="mb-6" />
<x-breadcrumbs :items="[['label' => 'Home', 'url' => home_url('/')], ['label' => 'Contatti', 'url' => null]]" />
```

- Items computed by `App\Navigation\Breadcrumbs::items()` from the main query: pages with parents, posts (posts page → first category with its parents → title), CPTs with an archive, taxonomies, date archives (year on `news/archivio/YYYY/`), author, search, 404 and pagination. Nothing is printed on the front page.
- Markup: `<nav aria-label="Percorso di navigazione">` with `<ol>`, `aria-hidden` separators and `aria-current="page"` on the last item.
- JSON-LD `BreadcrumbList` (prop `jsonld`, default `true`): turn it off if an SEO plugin already outputs one.

The items (`label` as plain text, `url` or `null`; the last one is the current page) can be changed with the `sage/breadcrumbs` filter:

```php
add_filter('sage/breadcrumbs', function (array $items): array {
    // E.g. remove the "Home" item.
    return array_slice($items, 1);
});
```

## Theme filters

| Filter                        | Default               | Where                                                                                  |
| ----------------------------- | --------------------- | -------------------------------------------------------------------------------------- |
| `sage/breadcrumbs`            | computed items        | `App\Navigation\Breadcrumbs::items()`                                                  |
| `sage/core_blocks_post_types` | `['post', 'faq']`     | `app/blocks.php`: post types where core blocks are allowed (elsewhere: ACF only)       |
| `sage/extra_blocks`           | `[]`                  | `app/blocks.php`: other blocks (e.g. third-party `my-plugin/form`) allowed everywhere  |
| `sage/preload_fonts`          | `[]`                  | `app/setup.php`: fonts to preload                                                      |
| `sage/svg_upload_capability`  | `'edit_others_posts'` | `app/svg.php`: capability required to upload SVGs                                      |
| `sage/yearly_archive_base`    | `'news/archivio'`     | `app/rewrites.php`: base of the yearly archives (`wp rewrite flush` after changing it) |

## Sponsors

Sage is an open source project and completely free to use. If you've benefited from our projects and would like to support our future endeavors, [please consider sponsoring us](https://github.com/sponsors/roots).

<div align="center">
<a href="https://carrot.com/"><img src="https://cdn.roots.io/app/uploads/carrot.svg" alt="Carrot" width="120" height="90"></a> <a href="https://wordpress.com/"><img src="https://cdn.roots.io/app/uploads/wordpress.svg" alt="WordPress.com" width="120" height="90"></a> <a href="https://worksitesafety.ca/careers/"><img src="https://cdn.roots.io/app/uploads/worksite-safety.svg" alt="Worksite Safety" width="120" height="90"></a> <a href="https://40q.agency/"><img src="https://cdn.roots.io/app/uploads/40q.svg" alt="40Q" width="120" height="90"></a> <a href="https://www.itineris.co.uk/"><img src="https://cdn.roots.io/app/uploads/itineris.svg" alt="Itineris" width="120" height="90"></a> <a href="https://bonsai.so/"><img src="https://cdn.roots.io/app/uploads/bonsai.svg" alt="Bonsai" width="120" height="90"></a>
</div>
