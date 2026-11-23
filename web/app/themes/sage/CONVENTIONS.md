# Conventions

Conventions decided for this theme. Tooling (build, lint, PHP quality, translation scripts) is described in the [README](README.md); the project setup in the [project README](../../../../README.md).

- [ACF blocks](#acf-blocks)
- [Block assets](#block-assets)
- [New block checklist](#new-block-checklist)
- [Internationalization](#internationalization)
- [PHP](#php)
- [Security and escaping](#security-and-escaping)
- [Components](#components)
- [Styleguide](#styleguide)
- [Renaming the theme](#renaming-the-theme)

## ACF blocks

Every block is an ACF block (`acf/<slug>`) split in three files, plus two optional assets. Reference implementation: the `only-text` block.

| File                                             | Role                                                                                    |
| ------------------------------------------------ | --------------------------------------------------------------------------------------- |
| `resources/views/blocks/<slug>/block.json`       | Block metadata, registered by `app/blocks.php` through the block manifest               |
| `resources/views/blocks/<slug>/<slug>.blade.php` | Block view: prepares the data, no markup                                                |
| `resources/views/modules/<slug>.blade.php`       | Module: the markup, receives ready-to-print data                                        |
| `resources/views/blocks/<slug>/view.js`          | Optional: frontend script module, loaded only where the block is rendered               |
| `resources/views/blocks/<slug>/style.css`        | Optional: frontend stylesheet, loaded only where the block is rendered                  |
| `resources/views/blocks/blocks-manifest.php`     | Generated from every `block.json` (`npm run blocks:manifest`): versioned, never edit it |

**Block manifest**: `app/blocks.php` registers the blocks with `wp_register_block_types_from_metadata_collection()` (WordPress 6.8+), reading the metadata of every block from `blocks-manifest.php` instead of globbing the folders and decoding each `block.json` on every request (without the function or the manifest it falls back to one `register_block_type()` per folder). The registration still goes through `register_block_type_from_metadata()` and its filters, so ACF and the translations (`textdomain`) work as with `block.json`. Only the blocks listed in the manifest are registered and with the metadata it contains: Vite rewrites it at every build and, with `npm run dev`, whenever a `block.json` is added, changed or removed; otherwise run `npm run blocks:manifest`. Commit it with the `block.json` changes: CI fails if it is stale (`npm run blocks:manifest:check`).

**`block.json`**

- `"name": "acf/<slug>"`, `"textdomain": "sage"`, `title`, `description` and `keywords` in Italian;
- `"supports": { "anchor": true, "align": false }`:
  - `align: false`: the theme blocks do not use the block alignment (the section is already `w-full` with its `container`), so the alignment toolbar is hidden (ACF registers `align: true` by default);
  - the theme blocks do not use InnerBlocks, so no `supports.jsx` in `block.json`: ACF still registers it as `true` by default, but with `blockVersion` 3 the editor ignores it, so do not declare it (not even as `false`). The render callback does not pass the InnerBlocks `$content` to the views; the ACF `content` field is unrelated and read with `get_field()`;
- `"acf": { "blockVersion": 3, "renderCallback": "App\\Blocks\\BlockRenderer::render" }`: one render callback for every block. In the editor it shows the `<x-acf.placeholder>` (block name, description, title field) instead of a preview; on the frontend it renders the view `blocks.<slug>.<slug>` (`blocks/<slug>/<slug>.blade.php`) with `$block` (the file name must match the slug, the `acf/` prefix of `name` is stripped), nothing if the view is missing;
- `category`: one of the theme categories registered in `app/blocks.php` (`carousels`, `dynamic-content`, `static-content`, `commons`, `heroes`, `dividers`), shown first in the inserter.

ACF blocks are allowed in every editor; core blocks only in the post types of `sage/core_blocks_post_types` (`post`, `faq`) and in the widget/site editors; other blocks (e.g. third-party) only if listed by `sage/extra_blocks` (`app/blocks.php`).

**Block view** (`blocks/<slug>/<slug>.blade.php`): a single `@php` block, wrapped in `{{-- blade-formatter-disable --}}`/`{{-- blade-formatter-enable --}}`, that

- calls `\App\Blocks\BlockRenderer::data($block)`, which returns the common data grouped as `settings` (`id`, `background_color`, `text_color`), `contents` (`title`, `title_formatting`, `title_tag`, `next_heading`, `content`, `buttons`) and `spacings` (`padding`, `margin` as Tailwind classes);
- reads and validates the block-specific fields (`get_field()`), sanitizes URLs and colors (see [Security and escaping](#security-and-escaping)) and drops invalid items;
- is followed by `@include('modules.<slug>', [...])`, passing only what the module prints.

**Module** (`modules/<slug>.blade.php`): a `{{-- --}}` header with the `@var` of every variable, then:

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

- `id`: `settings.id` is the block **anchor** when set, otherwise the ACF block id (`block_…`), so every section has an id;
- the outer `<section>` always has `w-full overflow-x-clip` plus the padding/margin classes of the block; the content sits in `<div class="container mx-auto px-6 3xl:px-0">`;
- classes are composed with `@class(array_filter([...]))` (empty presets are dropped); a multi-line `@class` is wrapped in the blade-formatter markers, inside the tag, with the array at 2 spaces;
- headings: the block title uses `<x-ui.title :tag="$contents['title_tag']">` (ACF `title_formatting`, h1–h6/p, default `h2`); child headings (subtitles, card titles) use `$contents['next_heading']`, from `\App\get_next_heading_level($title_tag)` (one level down, `p` after `h6`). Pass `next_heading` further down with `\App\get_next_heading_level($contents['next_heading'])` for nested levels.

**Field groups** live in `acf-json/` (ACF local JSON, saved there by `acf/settings/save_json` in `app/setup.php`). Labels, instructions and choices are in **Italian** (translated at runtime, see [Internationalization](#internationalization)). Two kinds of groups:

| Group                          | File                       | Location                                     | Fields                                                                                                                          |
| ------------------------------ | -------------------------- | -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| _Impostazioni blocco_ (shared) | `group_69c1778d2ba98.json` | _Block is equal to All_                      | accordion _Spaziature e colori_, `padding`, `padding_dimension`, `margin`, `margin_dimension`, `background_color`, `text_color` |
| _Blocco: …_ (one per block)    | `group_<id>.json`          | _Block is equal to_ the block (`acf/<slug>`) | the block fields, e.g. _Blocco: Solo testo_ (`group_6b0457c69122b.json`): `title`, `title_formatting`, `content`, `buttons`     |

- the shared group has `menu_order` 100, the block groups 0: in the block form the block fields come first, the shared settings last, in a closed accordion;
- only spacings and colors are shared: a new block gets nothing else. `BlockRenderer::data()` still reads `title`, `title_formatting`, `content` and `buttons` by name (`null`/`[]` when the block has no such field): to use them in a new block, create fields **with the same names** in its own group (new keys; a field key belongs to one group), or a seamless _Clone_ of the _Solo testo_ fields;
- **never change the key or the name of an existing field**, not even when moving it to another group: block data in `post_content` stores every value by name with a `_<name>` → field key reference, and `get_field()` and the block form resolve the field by that key (`acf_get_field()` finds it in any group). A new key or name orphans the saved values. Move fields between groups by editing the JSON (or with _Move_ in the field group screen), keeping key and name, and update `modified`;
- the groups are only in `acf-json/` (not in the database): _Sync available_ in _ACF → Field Groups_ lists them because there is no database copy; syncing imports them with the same keys (no duplicates), but it is not needed.

## Block assets

Most blocks need no assets of their own: Tailwind classes in the module and `resources/js/app.js` cover them. When a block does need its own JS or CSS, put it in the block folder:

- `view.js`: enqueued as a script module (`wp_enqueue_script_module()`, `type="module"` in the footer, handle `sage-block-<slug>`). It runs once per page even with several instances of the block: select them all (`document.querySelectorAll('.cards')`). Do not import CSS from it (in build Vite would emit a separate file that is not enqueued): use `style.css`;
- `style.css`: enqueued as a stylesheet (`wp_enqueue_style()`, handle `sage-block-<slug>`); WordPress 6.9+ moves the styles enqueued while rendering into the `<head>`. Tailwind utilities are already in `app.css`: use it for what they cannot express. To use the theme tokens, start it with `@reference "../../../css/app.css";`.

Both are Vite entries (`vite.config.js` globs `resources/views/blocks/*/{view.js,style.css}` when it starts: restart `npm run dev` after adding the first asset of a block, then run `npm run build`) and are enqueued by `App\Blocks\BlockAssets` from `BlockRenderer::render()`, only when the block view is rendered on the frontend: pages without the block do not load them, and in the editor (placeholder) nothing is enqueued. With the dev server they come from Vite (a change reloads the page); an asset missing from the build manifest is skipped.

## New block checklist

Example slug: `cards`. Copy from `only-text` where possible.

1. **Metadata**: create `resources/views/blocks/cards/block.json` with `"name": "acf/cards"`, Italian `title`/`description`/`keywords`, a theme `category`, `"textdomain": "sage"`, `"apiVersion": 3`, `supports` with `"anchor": true` and `"align": false` only (no `supports.jsx`: the theme blocks do not use InnerBlocks and `blockVersion` 3 ignores it), `acf.blockVersion: 3` and `acf.renderCallback: "App\\Blocks\\BlockRenderer::render"`. No PHP registration needed: `app/blocks.php` registers the blocks of the manifest, which `npm run dev`/`npm run build` (or `npm run blocks:manifest`) regenerate from the `block.json` files. Commit `resources/views/blocks/blocks-manifest.php` too.
2. **Fields**: locally, in _ACF → Field Groups_ create a group named after the block (e.g. _Blocco: Card_) with Italian labels, location _Block is equal to_ the new block (`acf/cards`) and order 0. Spacings and colors come from the shared _Impostazioni blocco_ group: do not add them. For a title, text or buttons use the names `title`, `title_formatting`, `content`, `buttons` (read by `BlockRenderer::data()`). Saving writes `acf-json/group_*.json`: commit it. Never change the key or name of fields already used by saved blocks (see [ACF blocks](#acf-blocks)).
3. **Block view**: create `resources/views/blocks/cards/cards.blade.php`: `BlockRenderer::data($block)`, read and validate the block fields, sanitize URLs (`sanitize_url()`) and colors (`sanitize_hex_color()`), then `@include('modules.cards', [...])`.
4. **Assets (optional)**: only if the block needs its own JS or CSS, add `resources/views/blocks/cards/view.js` and/or `style.css` (see [Block assets](#block-assets)), then restart `npm run dev`.
5. **Module**: create `resources/views/modules/cards.blade.php` with the `@var` header, the `<section id … class="w-full overflow-x-clip …">` and `<div class="container mx-auto px-6 3xl:px-0">` markup, `<x-ui.title :tag>` for the title and `next_heading` for the child headings. Use the [components](#components) for buttons, images and titles.
6. **Strings**: every visible string in Italian with the `sage` text domain (`{{ __('Leggi di più', 'sage') }}`).
7. **Logic and tests**: move non-trivial data preparation (queries, mapping) to a class in `app/` (e.g. `App\Queries\…` or a static method in `App\Blocks\`), with a Pest test in `tests/Unit/` that mocks `get_field()` with Brain Monkey (see `tests/Unit/BlockRendererTest.php`). Run `composer test` and `composer analyse`.
8. **Translations**: `npm run translate` (block.json, Blade and the new ACF group end up in `sage.pot` and in the `.po` files), translate the new entries in `resources/lang/en_US.po`, then `npm run translate:compile`.
9. **Lint**: `npm run format` (or `npm run format:blade`), then `npm run lint` and `composer lint`.
10. **Check**: `npm run build` (or `npm run dev`), insert the block in a page: placeholder in the editor, markup, anchor, spacings and heading levels on the frontend; with the site in English (`wp site switch-language en_US`) the labels are translated.

## Internationalization

- Source language: **Italian**. Every string of the theme (PHP, Blade, `block.json`, `theme.json`, `style.css`, ACF field groups) is written in Italian with the `sage` text domain; English is a translation (`resources/lang/en_US.po`).
- Use `__()`, `_x()` (with context for short or ambiguous strings), `_n()` and a `/* translators: */` comment for placeholders. In Blade: `{{ __('…', 'sage') }}`.
- ACF field groups stay in Italian in `acf-json/`: ACF translates them at runtime through `acf/settings/l10n_textdomain` (`sage`, turned off while editing field groups).
- After adding or changing strings: `npm run translate`, update `en_US.po`, `npm run translate:compile`. Commit `.pot`, `.po`, `.mo` and `.l10n.php` (`acf-json.php` is generated and gitignored). Details in [Translations](README.md#translations).

## PHP

- Namespace `App`, PSR-4 autoload from `app/` (`composer.json`). Every file starts with `declare(strict_types=1);`.
- Classes, one per file:
  - `App\PostTypes\*`: custom post types, a static `register()` calling `register_post_type()`; listed in `$postTypes` of `App\Providers\ThemeServiceProvider`, which hooks them on `init` (taxonomies: `$taxonomies`, same contract, registered first);
  - `App\Queries\*`: static query methods returning plain arrays for the views (`Posts::latest()`, `Posts::paginated()`, `Pages::children()`);
  - `App\Blocks\*`: block rendering and shared block data (`BlockRenderer`), per-block assets (`BlockAssets`);
  - `App\Navigation\*`: navigation helpers (`Breadcrumbs`);
  - `App\View\Composers\*`: Acorn view composers;
  - `App\Styleguide`: the development-only `/styleguide/` route (see [Styleguide](#styleguide)).
- Hooks and functions without a class are in the files loaded by `functions.php` (`setup`, `filters`, `blocks`, `helpers`, `svg`, `rewrites`). Helper functions live in `app/helpers.php`, namespace `App`, and are called in Blade with the fully qualified name: `\App\get_next_heading_level()`, `\App\get_trimmed_excerpt()`, `\App\random_string_for_id()`, `\App\get_fallback_image_url()`.
- Theme filters are prefixed `sage/` (list in the [README](README.md#theme-filters)).
- Types: native types where WordPress calls with known types, PHPDoc array shapes elsewhere. PHPStan level 6 must pass (`composer analyse`); unit tests with Pest and Brain Monkey (`composer test`). Pint (preset `laravel`) formats `app/` (`composer lint:fix`).

## Security and escaping

- Blade `{{ }}` escapes with `htmlspecialchars()`: inside it use `sanitize_url()` for URLs, **not** `esc_url()` (which would encode `&` as `&#038;`, escaped a second time by Blade). `esc_url()`/`esc_attr()` are for PHP that prints directly (`printf` in `app/`).
- Colors from ACF or props go through `sanitize_hex_color()`; invalid values become `''`/`null` and nothing is printed.
- Link targets are whitelisted (`_self`/`_blank`); `_blank` adds `rel="noopener noreferrer"` (`<x-ui.button>`).
- `{!! !!}` only for trusted HTML: the ACF wysiwyg content, already filtered by WordPress, or markup built by the theme.
- Heading and title tags are whitelisted (`<x-ui.title>`, `BlockRenderer::data()`), never printed from raw input.
- SVG uploads are sanitized with `enshrined/svg-sanitize` and limited to `sage/svg_upload_capability` (`app/svg.php`).

## Components

Anonymous Blade components in `resources/views/components/`, grouped by folder (the folder is the prefix of the tag: `components/ui/button.blade.php` → `<x-ui.button>`); the full prop documentation is in the comment at the top of each file. Extra attributes (`class`, `id`, `data-*`) are merged into the root element.

| Folder     | Contents                                                     |
| ---------- | ------------------------------------------------------------ |
| `ui/`      | Generic building blocks, with no knowledge of the content    |
| `content/` | Pieces tied to the current content, page or post (or a list) |
| `acf/`     | ACF block helpers used by `BlockRenderer`                    |

| Component                         | Use                                                                              | Main props                                                                                                                 |
| --------------------------------- | -------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `<x-ui.button>`                   | Link or disabled button; renders nothing if `link` is invalid and not `disabled` | `link`, `target` (`_self`/`_blank`), `type` (`primary`, `secondary`, `white`, `black`), `label`, `disabled`, `customColor` |
| `<x-ui.title>`                    | Heading with a whitelisted tag                                                   | `tag` (h1–h6, `p`, `span`; default `h2`), `textColor`                                                                      |
| `<x-ui.alert>`                    | Notice box, nothing without content                                              | `type` (`success`, `caution`, `warning`, default info), `message` (or the slot), `role` (`status`/`alert`)                 |
| `<x-ui.image>`                    | Attachment image (`wp_get_attachment_image()`)                                   | `id`, `size` (default `large`), `sizes`, `eager`, `alt`, `fallback`, `img-id`                                              |
| `<x-content.share>`               | Social share links + Web Share API button                                        | `title`, `link` (default: current post), `facebook`, `twitter` (X), `linkedin`, `whatsapp`                                 |
| `<x-content.breadcrumbs>`         | Breadcrumbs with JSON-LD                                                         | `items` (default `Breadcrumbs::items()`), `jsonld` (default `true`)                                                        |
| `<x-content.carousel-navigation>` | Previous/next buttons for a carousel                                             | `id` (prefix of the `-prev`/`-next` classes)                                                                               |
| `<x-acf.placeholder>`             | Editor placeholder of ACF blocks (used by `BlockRenderer`)                       | `block`, `title`, `message`                                                                                                |

A new component goes in the folder that matches its role (no components in the root of `components/`), is documented in its header comment and in this table, and gets an example in the [styleguide](#styleguide).

## Styleguide

`/styleguide/` shows the design tokens (colors, font families and sizes, read from `public/build/assets/theme.json`, generated by the build from the `@theme` of `resources/css/theme.css`), the h1–h6 headings, links and focus, and every component with its main variants on static sample data. View: `resources/views/styleguide.blade.php`; logic: `App\Styleguide` (`app/Styleguide.php`), hooked by `ThemeServiceProvider::boot()`.

- **Development only**: the rewrite rule, the `sage_styleguide` query var and the template are registered only with `WP_ENV === 'development'` (no filter to turn it on elsewhere). In every other environment `/styleguide/` is a regular 404 and `?sage_styleguide=1` is ignored.
- **No database page**: a rewrite rule (`^styleguide/?$`) renders the view with the theme layout; the main query runs no posts query, the page is `noindex, nofollow` (`wp_robots`), titled _Styleguide_ and has the `styleguide` body class. Nothing on the site links to it.
- **Rewrite rules**: they are stored in the database, so they are not flushed on every request. On `init` the theme compares the stored rules with the environment and flushes once when they disagree: the first request in development adds the rule, the first request (or WP-CLI command) in another environment removes a rule left there, e.g. by a database copied from development. The option `sage_styleguide_rewrite` remembers the last flush, so a rule removed by something else is not flushed on every request. With plain permalinks there are no stored rules: use `/?sage_styleguide=1`. If the page is a 404 in development, run `wp rewrite flush`.
- **Own stylesheet**: the utilities used only by the view are compiled in `resources/css/styleguide.css` (a Vite entry with `tailwindcss/utilities` and `@source` on the view only), enqueued by `Styleguide::enqueueAssets()` only on the styleguide. `app.css` and `editor.css` exclude the view (`@source not '../views/styleguide.blade.php'`), so its classes never reach the production stylesheets. The components shown keep using the classes compiled in `app.css`. Both also exclude `resources/lang/`: translations are text, and an English word that is also a utility name would add it to the stylesheets.
- **Headings**: the sample headings (h1–h6 and `<x-ui.title>`) have `role="presentation"`, so the heading structure of the page is one h1 and one h2 per section.
- **Contrast**: every palette color shows its WCAG 2.2 contrast with white and black and the level met (AA/AAA, normal and large text), from `Styleguide::contrasts()` (hex and the oklch of `theme.json`, converted to sRGB). The page runs no content query: `<x-ui.image>` shows the theme fallback.
- When adding or changing a component or a token, update the styleguide: it is the quick visual check of the theme (`npm run dev`, then `https://<site>/styleguide/`).

## Renaming the theme

The folder name is the theme slug for WordPress. To rename `sage` to `<new>`:

1. Rename `web/app/themes/sage` to `web/app/themes/<new>`. The Vite asset base is derived from the folder name (`vite.config.js`): nothing to change. `VITE_THEME_SLUG` is only for a theme installed as a **symlink** with a different target folder name, and is passed as a shell variable (`VITE_THEME_SLUG=<new> npm run build`), not in `.env`.
2. Update the paths outside the theme: `--working-dir=web/app/themes/sage` in the root `composer.json` scripts, `working_dir` and `restart_when_changed` in `solo.yml`, `.github/workflows/ci.yml` (working directory, `node-version-file`), `.github/dependabot.yml` (directories), `WP_DEFAULT_THEME` in `config/application.php` and the paths in both READMEs (`grep -rn "themes/sage" --exclude-dir=vendor --exclude-dir=node_modules .`).
3. Update the theme identity: `Theme Name` in `style.css`, `name` in `composer.json` (`roots/sage`) and `package.json` (`sage`).
4. Text domain (optional, it can stay `sage`): replace it in every `__()`/`_x()` call, `"textdomain"` in `block.json`, `Text Domain` in `style.css`, `load_theme_textdomain()` and the `acf/settings/l10n_textdomain` filter in `app/setup.php`, then rebuild the catalogue (`npm run translate`, `npm run translate:compile`). The `sage/` filter prefixes and `sage.pot` are names, not the text domain: they can stay.
5. `composer dump-autoload`, `npm run build`, `wp theme activate <new>`, `wp acorn optimize:clear`.
