# WordPress Project with Bedrock & Sage

[![CI](https://github.com/Toms15/bedwind/actions/workflows/ci.yml/badge.svg)](https://github.com/Toms15/bedwind/actions/workflows/ci.yml)

This project uses [Bedrock](https://roots.io/bedrock/) as a WordPress boilerplate and [Sage](https://roots.io/sage/) (with [Acorn](https://roots.io/acorn/)) as a starter theme, with ACF Pro for the blocks.

This README covers the **project**: requirements, local setup, credentials, environments, WordPress translations, CI and deploy. Theme development (build, lint, PHP quality, theme translations, fonts, components) is in [`web/app/themes/sage/README.md`](web/app/themes/sage/README.md), and the coding conventions with the new block checklist are in [`web/app/themes/sage/CONVENTIONS.md`](web/app/themes/sage/CONVENTIONS.md).

## Contents

- [Requirements](#requirements)
- [Local development with Herd](#local-development-with-herd)
- [Environments](#environments)
- [WordPress translations](#wordpress-translations)
- [Commands](#commands)
- [Continuous integration](#continuous-integration)
- [Deployment](#deployment)
- [Project structure](#project-structure)

## Requirements

- **PHP >= 8.2** to run the site: the root and theme `composer.json` declare PHP >= 8.2 (the root has no `config.platform`: Pint in `require-dev` needs 8.3, see below). Production installs with `--no-dev`.
- **PHP >= 8.3** for root development: the root dev tools (Pint 1.32, in `require-dev`) require it, so a full `composer install` in the root fails on 8.2. The theme alone supports PHP >= 8.2 for development too.
- **Composer 2**.
- **Node.js** `^22.22.2 || ^24.15.0 || >=26` (`engines` in the theme `package.json`, the minimum required by Stylelint 18) and npm. The theme `.nvmrc` says `22`: see [Node version](web/app/themes/sage/README.md#node-version) in the theme README.
- **WP-CLI** (`wp`), for installing WordPress, the salts and the theme translation scripts.
- An **ACF Pro** license (see [ACF Pro credentials](#2-acf-pro-credentials)).

## Local development with Herd

The local environment is [Laravel Herd](https://herd.laravel.com) (macOS/Windows): nginx + PHP, with HTTPS certificates for `*.test` sites. There is no Docker/DDEV/Lando setup. The examples use the site name `project-name`, served at `https://project-name.test`.

You need:

- **Herd**, which ships PHP and Composer. Use PHP 8.3 or newer for the site (see [Requirements](#requirements)); the site's PHP version can be pinned with `herd isolate 8.3`;
- **MySQL**: the MySQL service of Herd Pro, [DBngin](https://dbngin.com), or any local MySQL/MariaDB server;
- **Node.js** via [nvm](https://github.com/nvm-sh/nvm), WP-CLI and an ACF Pro license (see [Requirements](#requirements)).

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

ACF Pro (`wpengine/advanced-custom-fields-pro`) is installed from `https://connect.advancedcustomfields.com`, which requires a license. Before running `composer install`, create a local `auth.json` in the project root (gitignored, never commit it):

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

Where there is no `auth.json` (CI, a build server), pass the same JSON through the `COMPOSER_AUTH` environment variable, stored as a secret: see [Secrets to create on GitHub](#secrets-to-create-on-github).

### 3. Configure `.env`

```bash
cp .env.example .env
```

Set at least:

| Key | Value |
| --- | --- |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | the local database (create it first, e.g. `mysql -u root -e "CREATE DATABASE project_name"`) |
| `DB_HOST` | uncomment it only if the server is not on `localhost` (e.g. `127.0.0.1:3307` for a second DBngin instance) |
| `WP_ENV` | `development` (see [Environments](#environments)) |
| `WP_HOME` | `https://project-name.test`: it must match the Herd site, the Vite dev server reads it to pick the certificate |
| `WP_SITEURL` | leave `"${WP_HOME}/wp"` |
| salts (`AUTH_KEY` … `NONCE_SALT`) | unique random values |

Generate the salts with the [WP-CLI dotenv command](https://github.com/aaemnnosttv/wp-cli-dotenv-command) or paste them from the [Roots generator](https://roots.io/salts.html):

```bash
wp package install aaemnnosttv/wp-cli-dotenv-command
wp dotenv salts regenerate
```

The optional keys are listed in [Environment variables](#environment-variables). Personal overrides that should not touch the shared `.env` can go in `.env.local` (gitignored, loaded after `.env`).

### 4. Install dependencies

```bash
# Root: WordPress, plugins, ACF Pro, WordPress translations
composer install

# Theme: Acorn (PHP) and the Vite/Tailwind toolchain (Node)
cd web/app/themes/sage
composer install
nvm install    # reads .nvmrc and uses the latest Node 22.x (see "Node version" in the theme README)
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
```

Permalinks: when the theme is activated and no permalink structure is set, the theme sets `/%postname%/` and flushes the rewrite rules (`after_switch_theme` in `app/setup.php`; WordPress fires it on the first request after the switch, e.g. the next `wp` command or page load). An existing structure is never overwritten. Herd uses nginx, which reads no `.htaccess`: nothing to create (the `web/.htaccess` WordPress may write on Apache is gitignored).

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

Vite serves the assets with hot reload on `https://project-name.test:5173` and writes `public/hot`, which tells the theme to load the assets from the dev server; the file is removed when Vite stops (Ctrl+C), and the site falls back to the build in `public/build` (`npm run build`). Without either, the site has no CSS/JS. Details in [Build](web/app/themes/sage/README.md#build) in the theme README.

### Solo processes

With [Solo](https://soloterm.com), `solo.yml` defines the project processes (approve them once in the Solo UI: new or changed commands need to be trusted):

| Process | Command | Starts automatically |
| --- | --- | --- |
| **Vite** | `npm run dev` in the theme (restarted when `vite.config.js` or `package-lock.json` change) | yes |
| **Debug log** | `tail -n 0 -F logs/debug.log` (new lines only, waits for the file if it does not exist yet) | yes |
| **Lint frontend** | `npm run lint` in the theme | no, start it from the sidebar |
| **Build tema** | `npm run build` in the theme | no |
| **Check PHP** | `composer check` (root and theme) | no |

Solo does not run `nvm use`: its processes use the **nvm default** Node. Make sure the default satisfies `engines` (e.g. `nvm alias default 22` after `nvm install 22`), otherwise Vite and the linters run on an older version.

### Access URLs

- **Frontend**: `https://project-name.test`
- **Admin**: `https://project-name.test/wp/wp-admin`

In development PHP errors are shown on the page and written to `logs/debug.log` (outside the webroot).

### Site driver (`LocalValetDriver.php`)

Herd picks up `LocalValetDriver.php` in the project root before its own drivers, on every request (no restart needed). The file extends Herd's `BedrockValetDriver` and changes one thing: for requests routed to the front controller (`web/index.php`) it sets `$_SERVER['PHP_SELF']` to `/index.php`, as nginx (`try_files … /index.php?$args`) and Apache (WordPress `.htaccess`) do.

Without it Herd sets `PHP_SELF` to the requested URI, and WordPress (`WP::parse_request()`) cancels the 404 when the requested path equals `PHP_SELF`: a non-existent URL that matches no rewrite rule, such as `/a/b/c/`, answers 200 with the home page instead of the theme 404. Static files, `wp-admin`, `wp-login.php`, `wp-cron.php`, `xmlrpc.php` and the other real PHP scripts are not affected.

The file is outside the docroot (`web/`), so it is never served, and staging/production ignore it. It needs Herd or Valet 4+ (which ship `Valet\Drivers\Specific\BedrockValetDriver`). To go back to Herd's default behaviour, delete the file.

If Herd shows a fatal error about `BedrockValetDriver` (for example because a future version renames the class), delete `LocalValetDriver.php`. The driver also takes precedence over Herd's global custom drivers (such as `BedrockMultisiteValetDriver`): if the site becomes a multisite, remove it or adapt it.

## Environments

`WP_ENV` selects the environment. `config/application.php` holds the **production** defaults and then loads `config/environments/{WP_ENV}.php`, if it exists, to override them. An empty `WP_ENV` means `production`. `WP_ENVIRONMENT_TYPE` (what `wp_get_environment_type()` returns) defaults to `WP_ENV` when it is `production`, `staging`, `development` or `local`.

| Setting | `production` (`config/application.php`) | `staging` (`environments/staging.php`) | `development` (`environments/development.php`) |
| --- | --- | --- | --- |
| `WP_DEBUG`, `WP_DEBUG_DISPLAY`, `SCRIPT_DEBUG` | off | off | on (`WP_DEBUG`/`WP_DEBUG_DISPLAY` overridable from `.env`) |
| `WP_DEBUG_LOG` | off | off | `logs/debug.log` (`false`/`0` turns it off, a string is used as path) |
| `SAVEQUERIES`, `WP_DISABLE_FATAL_ERROR_HANDLER` | off | off | on |
| `DISALLOW_INDEXING` (search engines blocked) | no | yes | yes |
| `DISALLOW_FILE_MODS` (no plugin/theme installs and updates from the admin) | yes | yes | no |
| `DISALLOW_FILE_EDIT`, `AUTOMATIC_UPDATER_DISABLED` | yes | yes | yes |

In every environment WordPress and the plugins are updated with Composer, never from the admin; staging differs from production only in blocking search engines. There is no `local` file: use `development` locally.

### Environment variables

Read by `config/application.php` and `config/environments/` (`.env`, then `.env.local`):

| Variable | Required | Notes |
| --- | --- | --- |
| `WP_HOME`, `WP_SITEURL` | yes | site URL and WordPress URL (`"${WP_HOME}/wp"`) |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | yes, unless `DATABASE_URL` is set | |
| `DATABASE_URL` | no | DSN (`mysql://user:password@host:port/name`) instead of the `DB_*` keys |
| `DB_HOST` | no | default `localhost` |
| `DB_PREFIX` | no | table prefix, default `wp_` |
| `DB_SSL` | no | `true` enables `MYSQLI_CLIENT_SSL` |
| `WP_ENV` | no | `development`, `staging` or `production` (default) |
| `WP_ENVIRONMENT_TYPE` | no | default `WP_ENV` when it is `production`, `staging`, `development` or `local` |
| `DISABLE_WP_CRON` | no | run WP-Cron from a system cron instead of on page loads |
| `WP_POST_REVISIONS` | no | `true` (default), `false` or the number of revisions to keep |
| `WP_DEBUG`, `WP_DEBUG_DISPLAY`, `WP_DEBUG_LOG` | no | development only (production and staging force them off) |
| `AUTH_KEY` … `NONCE_SALT` | yes | eight salts, different per environment |

The theme build reads only `WP_HOME` (and `APP_URL`, if set) from the project `.env`. `APP_URL` and `VITE_THEME_SLUG` can also be passed as shell variables to `npm run dev`/`build`: see [Build](web/app/themes/sage/README.md#build).

## WordPress translations

WordPress translations (core and wordpress.org plugins/themes installed via Composer) are downloaded by [`inpsyde/wp-translation-downloader`](https://github.com/inpsyde/wp-translation-downloader) on every `composer install`/`composer update`, matching the installed versions. The files in `web/app/languages/` and `wp-translation-downloader.lock` are **not versioned**: if they are missing or stale, regenerate them with:

```bash
composer install
# or, to force the download
composer wp-translation-downloader:download
```

To add a language, add its locale to `extra.wp-translation-downloader.languages` in `composer.json` (e.g. `["it_IT", "en_GB"]`) and run `composer wp-translation-downloader:download` (`en_US` needs nothing: it is built into WordPress). Packages that ship their own translations (ACF Pro) are listed in `excludes`.

The theme strings are written in Italian and translated in the theme itself: see [Translations](web/app/themes/sage/README.md#translations) in the theme README.

## Commands

### Bedrock

```bash
# Install a plugin or theme from wpackagist
composer require wpackagist-plugin/plugin-name
composer require wpackagist-theme/theme-name

# Update WordPress and plugins (WordPress stays on ~7.1.0: a new x.y release is a manual change)
composer update
```

### Theme (from `web/app/themes/sage`)

```bash
npm run dev      # Vite dev server with hot reload
npm run build    # production build in public/build
npm run lint     # ESLint, Stylelint, blade-formatter --check-formatted, prettier --check
npm run format   # Prettier + blade-formatter (writes)
npm run translate          # rebuild sage.pot and update the .po files
npm run translate:compile  # compile .mo, .l10n.php and .json
```

The single `lint:*`, `format:*` and `translate:*` scripts are described in the theme README.

### PHP quality (from the project root)

```bash
# Pint: root (preset per) + theme (preset laravel, own pint.json)
composer lint
composer lint:fix

# PHPStan level 6 and Pest tests of the theme
composer analyse
composer test

# Everything (CI): root validate --strict, audit and Pint, then `composer check` of the theme
composer check
```

The same scripts exist in the theme (`composer lint`, `analyse`, `test`, `check` from `web/app/themes/sage`, theme only): see [PHP quality](web/app/themes/sage/README.md#php-quality).

## Continuous integration

`.github/workflows/ci.yml` runs on every push to `main` and on every pull request (a new push to a PR cancels the previous run). The workflow only has `contents: read`. Every action is pinned to a commit SHA (with the version in a comment); Dependabot updates SHA and comment together.

| Job | What it runs |
| --- | --- |
| **Tema PHP 8.2 / 8.3 / 8.4** | in `web/app/themes/sage`: `composer install` + `composer check` (validate --strict, audit, Pint, PHPStan, Pest). Public packages only, no credentials. |
| **Root Bedrock** (PHP 8.3: the root lock includes Pint 1.32, which needs PHP 8.3, so a full install with dev tools fails on 8.2) | `composer validate --strict`, `composer install` (ACF Pro via `COMPOSER_AUTH`, WordPress translations via wp-translation-downloader), `composer audit --locked`, `vendor/bin/pint --test`. |
| **Frontend** (Node from `.nvmrc`, i.e. the latest 22.x) | in `web/app/themes/sage`: `npm ci`, `npm run lint`, `npm run build`. Needs neither `vendor/` nor `.env`. |

### Secrets to create on GitHub

- **Actions** → *Settings → Secrets and variables → Actions → New repository secret*: `COMPOSER_AUTH`, whose value is the whole content of your local `auth.json` (the `http-basic` JSON for `connect.advancedcustomfields.com`, same shape as in [ACF Pro credentials](#2-acf-pro-credentials)). Never paste it anywhere else.
- **Dependabot** → *Settings → Secrets and variables → Dependabot*:
  - `COMPOSER_AUTH` again, with the same value: workflow runs triggered by Dependabot PRs only see Dependabot secrets, so without it the root job fails on those PRs;
  - `ACF_PRO_LICENSE_KEY` (ACF Pro license key) and `ACF_PRO_SITE_URL` (site URL the license is activated for), used by `.github/dependabot.yml` to reach the ACF Pro repository when updating the root `composer.lock`.

If `COMPOSER_AUTH` is missing the root job fails with an explicit error, except on pull requests from forks: GitHub does not pass secrets to them, so the job warns, skips `composer install` and Pint and still runs `validate` and `audit`.

### Dependabot

`.github/dependabot.yml` checks every Monday (Europe/Rome) Composer in `/` and in `web/app/themes/sage`, npm in `web/app/themes/sage` and the GitHub Actions used by the workflow. Minor and patch updates are grouped (dev and production separately for Composer), with at most 5 open PRs per ecosystem. For npm, Stylelint and its config are grouped including majors, since they share major and engines. WordPress core (`roots/wordpress`) only gets patch updates: moving to a new x.y release stays a manual decision. `roave/security-advisories` is ignored.

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
- The theme `composer.json` sets `config.platform.php` to `8.2.0`, so Composer does not check the PHP version of the build machine: the check only happens at runtime, in `vendor/composer/platform_check.php` (plus WordPress, which refuses to activate the theme below `Requires PHP: 8.2` in `style.css`). Make sure the server runs PHP >= 8.2.
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
- tests, stubs and tool configs: `web/app/themes/sage/tests/`, `web/app/themes/sage/stubs/` (PHPStan stubs), `phpunit.xml`, `phpstan.neon.dist`, `pint.json` (root and theme), `eslint.config.js`, `.prettierrc.json`, `.prettierignore`, `.stylelintrc.json`, `.bladeformatterrc.json`, `.bladeignore`, `.editorconfig`, `.nvmrc`, `solo.yml`, `CONVENTIONS.md`, `README.md` (root and theme).

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

Use `WP_ENV='staging'` for a staging copy: what changes is described in [Environments](#environments).

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

# Only if rewrite rules changed (new post types/taxonomies, custom rewrites, permalink structure)
wp rewrite flush
```

`wp acorn optimize` caches the configuration too: after changing `.env` run `wp acorn optimize:clear` (and `wp acorn optimize` again). On the first deploy also run `wp core install` or import the database (`wp search-replace` of the URLs) and activate the theme: the permalinks are set on activation as in [step 5](#5-install-wordpress-or-import-a-database) of the local setup. With Apache, WordPress writes the rewrite rules in `web/.htaccess` only if the file is writable; with nginx configure `try_files $uri $uri/ /index.php?$args;`.

## Project structure

```
project-name/
├── config/                 # Bedrock configuration (application.php + environments/)
├── web/                    # Docroot
│   ├── app/
│   │   ├── languages/      # WordPress translations (generated, not versioned)
│   │   ├── mu-plugins/     # Must-use plugins
│   │   ├── plugins/        # Plugins (installed by Composer, not versioned)
│   │   ├── themes/
│   │   │   └── sage/       # The theme: see its README and CONVENTIONS.md
│   │   └── uploads/        # Uploaded files (not versioned)
│   ├── wp/                 # WordPress core (installed by Composer, never edit it)
│   ├── wp-config.php       # Loads config/application.php
│   └── index.php           # Entry point
├── logs/                   # debug.log in development (outside the webroot, not versioned)
├── vendor/                 # Composer dependencies
├── .env                    # Environment configuration (never commit it)
├── auth.json               # ACF Pro credentials (never commit it)
├── composer.json           # PHP dependencies and quality scripts
├── LocalValetDriver.php    # Herd site driver, local only (see "Site driver")
├── solo.yml                # Solo processes
└── wp-cli.yml              # WP-CLI paths (web/wp, docroot web/)
```
