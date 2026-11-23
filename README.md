# WordPress Project with Bedrock & Sage

[![CI](https://github.com/Toms15/bedwind/actions/workflows/ci.yml/badge.svg)](https://github.com/Toms15/bedwind/actions/workflows/ci.yml)

This project uses [Bedrock](https://roots.io/bedrock/) as a WordPress boilerplate and [Sage](https://roots.io/sage/) as a starter theme.

## Requirements

- PHP >= 8.2 to run the site (the non-dev packages of the root and of the theme; production installs with `--no-dev`)
- PHP >= 8.3 for root development: the root dev tools (Pint 1.32, in `require-dev`) require it. The theme alone supports PHP >= 8.2 for development too
- Composer
- Node.js 22 (see `web/app/themes/sage/.nvmrc`; `engines`: `^22.22.2 || ^24.15.0 || >=26`, the minimum required by Stylelint 18)
- npm

## Local development with Herd

The local environment is [Laravel Herd](https://herd.laravel.com) (macOS/Windows): nginx + PHP, with HTTPS certificates for `*.test` sites. There is no Docker/DDEV/Lando setup. The examples use the site name `project-name`, served at `https://project-name.test`.

### Requirements

- **Herd**, which ships PHP and Composer (use PHP 8.3 or newer for the site: see Requirements above). The site's PHP version can be pinned with `herd isolate 8.3`
- **MySQL**: the MySQL service of Herd Pro, [DBngin](https://dbngin.com), or any local MySQL/MariaDB server
- **Node.js** via [nvm](https://github.com/nvm-sh/nvm): `nvm use` in the theme reads `web/app/themes/sage/.nvmrc`
- **WP-CLI** (`wp`), for installing WordPress, the salts and the theme translation scripts
- An **ACF Pro** license (see step 2)

### 1. Clone and link the site

```bash
git clone <repository-url> project-name
cd project-name

# Serve the folder as https://project-name.test (Herd detects Bedrock and uses web/ as docroot)
herd link project-name
herd secure project-name
```

If the folder is inside a path parked by Herd (e.g. `~/Herd`), `herd link` is not needed: the site is already served as `<folder>.test`. `herd secure` is required anyway: the Vite dev server reuses the Herd certificate of the site to serve the assets over HTTPS.

### 2. ACF Pro credentials

ACF Pro is installed from `https://connect.advancedcustomfields.com`, which requires a license. Before running `composer install`, create a local `auth.json` in the project root (gitignored, never commit it):

```bash
# username = ACF Pro license key, password = the site URL the license is activated for
composer config --auth http-basic.connect.advancedcustomfields.com <license_key> <site_url>
```

The resulting file has this shape:

```json
{
    "http-basic": {
        "connect.advancedcustomfields.com": {
            "username": "<license_key>",
            "password": "<site_url>"
        }
    }
}
```

In CI, pass the same JSON through the `COMPOSER_AUTH` environment variable (stored as a secret, see "Continuous integration").

### 3. Configure `.env`

```bash
cp .env.example .env
```

Set at least:

| Key | Value |
| --- | --- |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | the local database (create it first, e.g. `mysql -u root -e "CREATE DATABASE project_name"`) |
| `DB_HOST` | uncomment it only if the server is not on `localhost` (e.g. `127.0.0.1:3307` for a second DBngin instance) |
| `WP_ENV` | `development` (loads `config/environments/development.php`: debug on, `logs/debug.log`, indexing disabled, plugin installs from the admin allowed) |
| `WP_HOME` | `https://project-name.test`: it must match the Herd site, the Vite dev server reads it to pick the certificate |
| `WP_SITEURL` | leave `"${WP_HOME}/wp"` |
| salts (`AUTH_KEY` … `NONCE_SALT`) | unique random values |

Generate the salts with the [WP-CLI dotenv command](https://github.com/aaemnnosttv/wp-cli-dotenv-command) or paste them from the [Roots generator](https://roots.io/salts.html):

```bash
wp package install aaemnnosttv/wp-cli-dotenv-command
wp dotenv salts regenerate
```

The optional keys (`WP_DEBUG`, `WP_DEBUG_DISPLAY`, `WP_DEBUG_LOG`, `DB_PREFIX`, `DATABASE_URL`, …) are documented in `.env.example`. Personal overrides that should not touch the shared `.env` can go in `.env.local` (gitignored).

### 4. Install dependencies

```bash
# Root: WordPress, plugins, ACF Pro, WordPress translations
composer install

# Theme: Acorn (PHP) and the Vite/Tailwind toolchain (Node)
cd web/app/themes/sage
composer install
nvm use
npm ci
cd -
```

### 5. Install WordPress (or import a database)

Fresh install:

```bash
wp core install --url=https://project-name.test --title="Project name" \
  --admin_user=admin --admin_email=admin@example.test --prompt=admin_password
wp theme activate sage
wp site switch-language it_IT
wp rewrite structure '/%postname%/'
```

Or import an existing dump and adapt the URLs:

```bash
wp db import dump.sql
wp search-replace 'https://www.example.com' 'https://project-name.test' --all-tables --precise
wp rewrite flush
```

ACF field groups are loaded from `web/app/themes/sage/acf-json/`: nothing to import.

### 6. Start the theme dev server

```bash
cd web/app/themes/sage
npm run dev
```

Vite serves the assets with hot reload on `https://project-name.test:5173` and writes `public/hot`, which tells the theme to load the assets from the dev server; the file is removed when Vite stops (Ctrl+C), and the site falls back to the build in `public/build` (`npm run build`). Without either, the site has no CSS/JS.

With [Solo](https://soloterm.com), `solo.yml` defines the project processes (approve them once in the Solo UI, new or changed commands need to be trusted):

| Process | Command | Starts automatically |
| --- | --- | --- |
| **Vite** | `npm run dev` in the theme (restarted when `vite.config.js` or `package-lock.json` change) | yes |
| **Debug log** | `tail -F logs/debug.log` (new lines only) | yes |
| **Lint frontend** | `npm run lint` in the theme | no, start it from the sidebar |
| **Build tema** | `npm run build` in the theme | no |
| **Check PHP** | `composer check` (root and theme) | no |

### Access URLs

- **Frontend**: `https://project-name.test`
- **Admin**: `https://project-name.test/wp/wp-admin`

In development PHP errors are shown on the page and written to `logs/debug.log` (outside the webroot).

## Translations

WordPress translations (core and wordpress.org plugins/themes installed via Composer) are downloaded by [`inpsyde/wp-translation-downloader`](https://github.com/inpsyde/wp-translation-downloader) on every `composer install`/`composer update`, matching the installed versions. The files in `web/app/languages/` and `wp-translation-downloader.lock` are **not versioned**: if they are missing or stale, regenerate them with:

```bash
composer install
# or, to force the download
composer wp-translation-downloader:download
```

To add a language, add its locale to `extra.wp-translation-downloader.languages` in `composer.json` (e.g. `["it_IT", "en_GB"]`) and run `composer wp-translation-downloader:download`. Packages that ship their own translations (e.g. ACF Pro) are listed in `excludes`.

### Theme translations

The source language of the theme is **Italian**: every string in `app/`, `resources/views/` (Blade, `block.json`), the ACF field groups in `acf-json/` and the `style.css` header is written in Italian and uses the `sage` text domain (`__('Continua a leggere', 'sage')`, `{{ __('Vai al contenuto', 'sage') }}`). With the site in Italian no translation file is loaded; `resources/lang/en_US.po` provides the English translation, so the project can be switched to English by changing the site language (`wp site switch-language en_US`, or *Settings → General → Site Language*).

Files in `web/app/themes/sage/resources/lang/` (all versioned, the theme is deployed as is):

- `sage.pot`: template with every string of the theme;
- `{locale}.po`: translation, edited by hand or with Poedit;
- `{locale}.mo` and `{locale}.l10n.php`: compiled files loaded by WordPress (since 6.5 the `.l10n.php` file is preferred).

The path is registered with `load_theme_textdomain()` in `app/setup.php` (also declared as `Domain Path` in `style.css`). ACF labels are translated at runtime through `acf/settings/l10n_textdomain` (disabled while editing field groups, so acf-json always stays in Italian); `wp i18n make-pot` does not read acf-json, so `npm run translate:acf` extracts those strings into `resources/lang/acf-json.php` (generated, not versioned) before the `.pot` is built.

From `web/app/themes/sage` (requires WP-CLI):

```bash
# After adding or changing strings: rebuild sage.pot and merge it into every .po
npm run translate

# Translate the new/empty entries in the .po files, then compile .mo and .l10n.php
npm run translate:compile
```

To add a language (e.g. `de_DE`): copy `sage.pot` to `resources/lang/de_DE.po`, set the `Language` and `Plural-Forms` headers, translate it and run `npm run translate:compile`. For WordPress core and plugin strings add the locale to `extra.wp-translation-downloader.languages` in `composer.json` as described above (`en_US` needs nothing: it is built into WordPress).

## Fonts and images

The theme ships **no webfont**: text uses the system stack in `--font-body` (`resources/css/theme.css`).

To add a webfont (paths from `web/app/themes/sage`):

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

WordPress adds `srcset`, `sizes`, `width`/`height`, `decoding="async"` and picks `loading`/`fetchpriority` itself (prefixing `sizes` with `auto,` on lazy images); `eager` forces `loading="eager" fetchpriority="high"` for a single above-the-fold image. With an empty or non-image ID the local `fallback.svg` is printed (alt `""`, lazy) unless `:fallback="false"`.

## Project Structure

```
project-name/
├── config/                 # Bedrock configuration files
├── web/
│   ├── app/
│   │   ├── languages/      # Translations (generated, not versioned)
│   │   ├── mu-plugins/     # Must-use plugins
│   │   ├── plugins/        # Standard plugins
│   │   ├── themes/         # Custom themes
│   │   │   └── sage/       # Sage theme
│   │   └── uploads/        # Uploaded files
│   ├── wp-config.php       # WordPress config
│   └── index.php           # Entry point
├── logs/                   # debug.log in development (outside the webroot, not versioned)
├── vendor/                 # Composer dependencies
├── .env                    # Environment configuration (do not commit!)
└── composer.json           # PHP dependencies
```

## Useful Commands

### Bedrock

```bash
# Install plugin via Composer
composer require wpackagist-plugin/plugin-name

# Install theme via Composer  
composer require wpackagist-theme/theme-name

# Update WordPress and plugins
composer update
```

### Sage

```bash
# Compile assets for development
npm run dev

# Compile assets for production
npm run build

# Lint: ESLint (JS), Stylelint (CSS), blade-formatter --check-formatted (Blade), prettier --check
npm run lint

# Format: Prettier (JS/CSS/JSON/Markdown) + blade-formatter (Blade)
npm run format
```

See the theme README for the single `lint:*` / `format:*` scripts and the formatter settings.

### PHP quality (from the project root)

```bash
# Pint: root (preset per) + theme (preset laravel, own pint.json)
composer lint
composer lint:fix

# PHPStan level 6 and Pest tests of the theme
composer analyse
composer test

# Everything (CI): validate --strict, audit, lint, analyse, test
composer check
```

See "Qualità PHP" in the theme README for the PHPStan setup and how to write a test.

## Continuous integration

`.github/workflows/ci.yml` runs on every push to `main` and on every pull request (a new push to a PR cancels the previous run). The workflow only has `contents: read`. Every action is pinned to a commit SHA (with the version in a comment); Dependabot updates SHA and comment together.

| Job | What it runs |
| --- | --- |
| **Tema PHP 8.2 / 8.3 / 8.4** | in `web/app/themes/sage`: `composer install` + `composer check` (validate --strict, audit, Pint, PHPStan, Pest). Public packages only, no credentials. |
| **Root Bedrock** (PHP 8.3: the root lock includes Pint 1.32, which needs PHP 8.3, so a full install with dev tools fails on 8.2) | `composer validate --strict`, `composer install` (ACF Pro via `COMPOSER_AUTH`, WordPress translations via wp-translation-downloader), `composer audit --locked`, `vendor/bin/pint --test`. |
| **Frontend** (Node from `.nvmrc`) | in `web/app/themes/sage`: `npm ci`, `npm run lint`, `npm run build`. Needs neither `vendor/` nor `.env`. |

### Secrets to create on GitHub

- **Actions** → *Settings → Secrets and variables → Actions → New repository secret*: `COMPOSER_AUTH`, whose value is the whole content of your local `auth.json` (the `http-basic` JSON for `connect.advancedcustomfields.com`, same shape as the example above). Never paste it anywhere else.
- **Dependabot** → *Settings → Secrets and variables → Dependabot*:
  - `COMPOSER_AUTH` again, with the same value: workflow runs triggered by Dependabot PRs only see Dependabot secrets, so without it the root job fails on those PRs;
  - `ACF_PRO_LICENSE_KEY` (ACF Pro license key) and `ACF_PRO_SITE_URL` (site URL the license is activated for), used by `.github/dependabot.yml` to reach the ACF Pro repository when updating the root `composer.lock`.

If `COMPOSER_AUTH` is missing the root job fails with an explicit error, except on pull requests from forks: GitHub does not pass secrets to them, so the job warns, skips `composer install` and Pint and still runs `validate` and `audit`.

### Dependabot

`.github/dependabot.yml` checks every Monday (Europe/Rome) Composer in `/` and in `web/app/themes/sage`, npm in `web/app/themes/sage` and the GitHub Actions used by the workflow. Minor and patch updates are grouped (dev and production separately for Composer), with at most 5 open PRs per ecosystem. WordPress core (`roots/wordpress`) only gets patch updates: moving to a new x.y release stays a manual decision. `roave/security-advisories` is ignored.

## Deployment

There is no deploy script: the steps below work for any target (a build on the server, a CI artifact uploaded via rsync/SFTP, etc.). The server needs PHP >= 8.2, MySQL, a web server with the docroot on **`web/`** (not the project root) and HTTPS.

### 1. Build

From the project root (on the server or in CI, with the ACF Pro credentials available, see below):

```bash
composer install --no-dev --optimize-autoloader --no-interaction

cd web/app/themes/sage
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

- `--no-dev` drops Pint, PHPStan, Pest and the other dev tools (production then only needs PHP 8.2).
- `--optimize-autoloader` (`-o`, already the default in both `composer.json`) generates a classmap. `--classmap-authoritative` also works with Acorn (the theme classes in `app/` and the vendor classes are all in the classmap), but then every new class requires a new `composer install`/`dump-autoload`: use it only if the build always runs on deploy.
- The root `composer install` also downloads the WordPress translations into `web/app/languages/` and installs ACF Pro, so it needs the ACF credentials where it runs: an `auth.json` on the server (outside the webroot, never in the repository) or `COMPOSER_AUTH` as a CI secret when the build is done in CI.
- `npm run build` writes `web/app/themes/sage/public/build/` (hashed assets + `manifest.json`). Node is not needed on the server if the build is done elsewhere.

### 2. What to upload

Upload the whole project (root included, not only `web/`): `config/`, `vendor/`, `web/` (with `web/wp`, `web/app/plugins`, `web/app/languages` generated by Composer), `composer.json`/`composer.lock`, `wp-cli.yml`, the theme with `vendor/` and `public/build/`.

Do **not** upload:

- `.git/`, `.github/`, `.idea/`, `MIGLIORIE.md` and other development files;
- `web/app/themes/sage/node_modules/` (never used at runtime). Keep `resources/`: Blade views, blocks, `lang/` and the font/image files checked by the theme are read at runtime;
- `web/app/themes/sage/public/hot` (if it exists the theme tries to load the assets from the Vite dev server);
- the local `.env`, `.env.local`, `auth.json`;
- `logs/*.log`, `web/app/uploads/` (synced separately, never overwritten by a deploy), `web/app/cache/`;
- tests and tool configs: `web/app/themes/sage/tests/`, `phpunit.xml`, `phpstan.neon.dist`, `pint.json` (root and theme), `eslint.config.js`, `.prettierrc.json`, `.prettierignore`, `.stylelintrc.json`, `.bladeformatterrc.json`, `.bladeignore`, `solo.yml`.

`wp-translation-downloader.lock` is only useful together with `web/app/languages/`: upload both or neither, otherwise a later `composer install` on the server considers the translations installed and does not download them.

### 3. `.env` on the server

Create it by hand on the server (never copy the local one):

```env
DB_NAME='...'
DB_USER='...'
DB_PASSWORD='...'
# DB_HOST='...'

WP_ENV='production'
WP_HOME='https://www.example.com'
WP_SITEURL="${WP_HOME}/wp"

# new salts, different from development
AUTH_KEY='...'
# ... SECURE_AUTH_KEY, LOGGED_IN_KEY, NONCE_KEY, AUTH_SALT, SECURE_AUTH_SALT, LOGGED_IN_SALT, NONCE_SALT
```

With `WP_ENV='production'` `config/application.php` applies the production defaults: debug, display and `debug.log` off, `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS` on (plugins and WordPress are updated with Composer, not from the admin), automatic updates disabled. Use `WP_ENV='staging'` for a staging copy (same settings, search engines blocked).

### 4. Permissions

The PHP user must be able to write:

- `web/app/uploads/` (media);
- `web/app/cache/` (Acorn: compiled Blade views and bootstrap cache in `web/app/cache/acorn/`);
- `logs/`, only if `WP_DEBUG_LOG` is enabled on the server.

Everything else can be read-only for the PHP user.

### 5. After each deploy

From the project root on the server:

```bash
# Clear the Acorn caches of the previous release, then rebuild them
wp acorn optimize:clear
wp acorn optimize        # config, events, routes and compiled Blade views

# Only if rewrite rules changed (new post types/taxonomies, permalink structure)
wp rewrite flush
```

`wp acorn optimize` caches the configuration too: after changing `.env` run `wp acorn optimize:clear` (and `wp acorn optimize` again). On the first deploy also run `wp core install` or import the database (`wp search-replace` of the URLs), activate the theme and set the permalinks as in the local setup.

## Additional Notes

- The `.env` file contains sensitive information and **must not be committed** to the repository
- Compiled assets are in the `web/app/themes/sage/public/` folder
- Dependencies are managed through Composer (PHP) and npm (Node.js)
- WordPress core is installed in the `web/wp/` folder and should not be modified directly
