# WordPress Project with Bedrock & Sage

This project uses [Bedrock](https://roots.io/bedrock/) as a WordPress boilerplate and [Sage](https://roots.io/sage/) as a starter theme.

## Requirements

- PHP >= 8.0
- Composer
- Node.js >= 16.0
- npm

## Initial Setup

### 1. Clone the repository

```bash
git clone <repository-url> project-name
cd project-name
```

### 2. Install PHP dependencies

```bash
composer install
```

#### Premium credentials (ACF Pro)

ACF Pro is installed from `https://connect.advancedcustomfields.com`, which requires a license. Before running `composer install`, create a local `auth.json` (gitignored, never commit it):

```bash
# username = ACF Pro license key, password = the site URL the license is activated for
composer config --auth http-basic.connect.advancedcustomfields.com <license_key> <site_url>
```

In CI, pass the same credentials through the `COMPOSER_AUTH` environment variable (stored as a secret):

```bash
COMPOSER_AUTH='{"http-basic":{"connect.advancedcustomfields.com":{"username":"<license_key>","password":"<site_url>"}}}'
```

### 3. Configure environment

Copy the example configuration file and customize it:

```bash
cp .env.example .env
```

Edit the `.env` file with your configurations:

```env
DB_NAME='database_name'
DB_USER='db_user'
DB_PASSWORD='db_password'
DB_HOST='localhost'

WP_ENV='development'
WP_HOME='https://project-name.test'
WP_SITEURL="${WP_HOME}/wp"
WP_HOME_DOMAIN='project-name.test'

# Generate secure keys at https://roots.io/salts.html
AUTH_KEY='generate-secure-key'
SECURE_AUTH_KEY='generate-secure-key'
LOGGED_IN_KEY='generate-secure-key'
NONCE_KEY='generate-secure-key'
AUTH_SALT='generate-secure-key'
SECURE_AUTH_SALT='generate-secure-key'
LOGGED_IN_SALT='generate-secure-key'
NONCE_SALT='generate-secure-key'
```

### 4. Sage theme setup

Navigate to the theme folder and install Node.js dependencies:

```bash
cd web/app/themes/sage
composer install
npm install
```

### 5. Theme development

To start development compilation (with hot reload):

```bash
npm run dev
```

To compile assets for production:

```bash
npm run build
```

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

# Watch mode (automatically recompiles)
npm run dev:watch

# Code linting
npm run lint

# Auto-fix linting errors
npm run lint:fix
```

## Database

Make sure to create the database specified in `DB_NAME` in the `.env` file before accessing the site.

## Access URLs

- **Frontend**: `https://project-name.test`
- **Admin**: `https://project-name.test/wp/wp-admin`

## Deployment

For production deployment:

1. Change `WP_ENV` from `development` to `production` in the `.env` file
2. Update `WP_HOME` with the production URL
3. Run `npm run build` to compile optimized assets
4. Upload files to server excluding:
   - `node_modules/`
   - `.env` (create new configuration on server)
   - Development files (`.git`, `.gitignore`, etc.)

## Additional Notes

- The `.env` file contains sensitive information and **must not be committed** to the repository
- Compiled assets are in the `web/app/themes/sage/public/` folder
- Dependencies are managed through Composer (PHP) and npm (Node.js)
- WordPress core is installed in the `web/wp/` folder and should not be modified directly
