# bedwind

[![CI](https://github.com/Toms15/bedwind/actions/workflows/ci.yml/badge.svg)](https://github.com/Toms15/bedwind/actions/workflows/ci.yml)

Base di partenza per siti WordPress: [Bedrock](https://roots.io/bedrock/) come struttura del progetto e il tema starter [Sage](https://roots.io/sage/) 11 (con [Acorn](https://roots.io/acorn/) 5 e Blade), con Tailwind CSS 4, Vite 8 e blocchi ACF Pro.

- **Blocchi ACF**: un render callback unico (`App\Blocks\BlockRenderer`), registrazione da manifest, asset JS/CSS caricati solo dove il blocco compare, field group in `acf-json/`.
- **Lingua**: le stringhe del tema (PHP, Blade, `block.json`, field group ACF) sono scritte in italiano con il text domain `sage`; `en_US` è una traduzione.
- **Qualità**: PHPStan (livello 6), Pest e Pint per il PHP; ESLint, Stylelint, Prettier e blade-formatter per il frontend; GitHub Actions su ogni push e pull request, Dependabot settimanale.
- **Sviluppo locale** con [Laravel Herd](https://herd.laravel.com), styleguide dei componenti su `/styleguide/` in development.

Questo README riguarda il **progetto**: requisiti, setup locale, credenziali, ambienti, traduzioni di WordPress, CI e deploy. Lo sviluppo del tema (build, lint, qualità PHP, traduzioni del tema, font, componenti) è descritto in [`web/app/themes/sage/README.md`](web/app/themes/sage/README.md); le convenzioni di codice e la checklist per un nuovo blocco in [`web/app/themes/sage/CONVENTIONS.md`](web/app/themes/sage/CONVENTIONS.md).

## Indice

- [Requisiti](#requisiti)
- [Sviluppo locale con Herd](#sviluppo-locale-con-herd)
- [Ambienti](#ambienti)
- [Traduzioni di WordPress](#traduzioni-di-wordpress)
- [Comandi](#comandi)
- [Integrazione continua (CI)](#integrazione-continua-ci)
- [Deploy](#deploy)
- [Struttura del progetto](#struttura-del-progetto)
- [Licenza](#licenza)

## Requisiti

- **PHP >= 8.2** per far girare il sito: i `composer.json` della root e del tema dichiarano PHP >= 8.2 (la root non ha `config.platform`: Pint in `require-dev` richiede 8.3, vedi sotto). In produzione si installa con `--no-dev`.
- **PHP >= 8.3** per lo sviluppo nella root: gli strumenti di sviluppo della root (Pint 1.32, in `require-dev`) lo richiedono, quindi un `composer install` completo nella root fallisce su 8.2. Il tema da solo supporta PHP >= 8.2 anche in sviluppo.
- **Composer 2**.
- **Node.js** `^22.22.2 || ^24.15.0 || >=26` (`engines` nel `package.json` del tema, il minimo richiesto da Stylelint 18) e npm. Il `.nvmrc` del tema indica `22`: vedi [Versione di Node](web/app/themes/sage/README.md#versione-di-node) nel README del tema.
- **WP-CLI** (`wp`), per installare WordPress, generare i salt ed eseguire gli script di traduzione del tema.
- Una licenza **ACF Pro** (vedi [Credenziali ACF Pro](#2-credenziali-acf-pro)).

## Sviluppo locale con Herd

L'ambiente locale è [Laravel Herd](https://herd.laravel.com) (macOS/Windows): nginx + PHP, con certificati HTTPS per i siti `*.test`. Non c'è un setup Docker/DDEV/Lando. Gli esempi usano il nome di sito `project-name`, servito su `https://project-name.test`.

Servono:

- **Herd**, che include PHP e Composer. Per il sito usa PHP 8.3 o successivo (vedi [Requisiti](#requisiti)); la versione di PHP del sito si fissa con `herd isolate 8.3`;
- **MySQL**: il servizio MySQL di Herd Pro, [DBngin](https://dbngin.com) o un qualunque server MySQL/MariaDB locale;
- **Node.js** tramite [nvm](https://github.com/nvm-sh/nvm), WP-CLI e una licenza ACF Pro (vedi [Requisiti](#requisiti)).

### 1. Clonare e collegare il sito

```bash
git clone <repository-url> project-name
cd project-name

# Serve la cartella come https://project-name.test (Herd riconosce Bedrock e usa web/ come docroot)
herd link project-name
herd secure project-name
```

Se la cartella si trova in un percorso "parked" da Herd (es. `~/Herd`), `herd link` non serve: il sito è già servito come `<cartella>.test`. `herd secure` è comunque necessario: il dev server di Vite riusa il certificato Herd del sito per servire gli asset in HTTPS.

### 2. Credenziali ACF Pro

ACF Pro (`wpengine/advanced-custom-fields-pro`) si installa da `https://connect.advancedcustomfields.com`, che richiede una licenza. Prima di `composer install` crea un `auth.json` locale nella root del progetto (è in `.gitignore`, non committarlo mai):

```bash
# username = chiave di licenza ACF Pro, password = URL del sito per cui la licenza è attivata
composer config --auth http-basic.connect.advancedcustomfields.com <license_key> <site_url>
```

Il file risultante ha questa forma:

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

Dove non c'è un `auth.json` (CI, server di build), passa lo stesso JSON nella variabile d'ambiente `COMPOSER_AUTH`, salvata come secret: vedi [Secret da creare su GitHub](#secret-da-creare-su-github).

### 3. Configurare `.env`

```bash
cp .env.example .env
```

Imposta almeno:

| Chiave                              | Valore                                                                                                                    |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | il database locale (crealo prima, es. `mysql -u root -e "CREATE DATABASE project_name"`)                                  |
| `DB_HOST`                           | da decommentare solo se il server non è su `localhost` (es. `127.0.0.1:3307` per una seconda istanza DBngin)              |
| `WP_ENV`                            | `development` (vedi [Ambienti](#ambienti))                                                                                |
| `WP_HOME`                           | `https://project-name.test`: deve corrispondere al sito Herd, il dev server di Vite lo legge per scegliere il certificato |
| `WP_SITEURL`                        | lascia `"${WP_HOME}/wp"`                                                                                                  |
| salt (`AUTH_KEY` … `NONCE_SALT`)    | valori casuali unici                                                                                                      |

Genera i salt con il [comando dotenv di WP-CLI](https://github.com/aaemnnosttv/wp-cli-dotenv-command) oppure copiali dal [generatore di Roots](https://roots.io/salts.html):

```bash
wp package install aaemnnosttv/wp-cli-dotenv-command
wp dotenv salts regenerate
```

Le chiavi opzionali sono elencate in [Variabili d'ambiente](#variabili-dambiente). Gli override personali che non devono toccare il `.env` condiviso vanno in `.env.local` (in `.gitignore`, caricato dopo `.env`).

### 4. Installare le dipendenze

```bash
# Root: WordPress, plugin, ACF Pro, traduzioni di WordPress
composer install

# Tema: Acorn (PHP) e la toolchain Vite/Tailwind (Node)
cd web/app/themes/sage
composer install
nvm install    # legge .nvmrc e usa l'ultima Node 22.x (vedi "Versione di Node" nel README del tema)
npm ci
cd -
```

### 5. Installare WordPress (o importare un database)

Installazione da zero:

```bash
wp core install --url=https://project-name.test --title="Project name" \
  --admin_user=admin --admin_email=admin@example.test --prompt=admin_password
wp theme activate sage
wp site switch-language it_IT
```

Permalink: quando il tema viene attivato e non c'è una struttura dei permalink, il tema imposta `/%postname%/` e rigenera le regole di rewrite (`after_switch_theme` in `app/setup.php`; WordPress lo esegue alla prima richiesta dopo il cambio di tema, es. il comando `wp` successivo o il caricamento di una pagina). Una struttura già impostata non viene mai sovrascritta. Herd usa nginx, che non legge `.htaccess`: non c'è niente da creare (il `web/.htaccess` che WordPress può scrivere su Apache è in `.gitignore`).

Oppure importa un dump esistente e adatta gli URL:

```bash
wp db import dump.sql
wp search-replace 'https://www.example.com' 'https://project-name.test' --all-tables --precise
wp rewrite flush
```

I field group ACF vengono caricati da `web/app/themes/sage/acf-json/`: non c'è niente da importare.

### 6. Avviare il dev server del tema

```bash
cd web/app/themes/sage
npm run dev
```

Vite serve gli asset con hot reload su `https://project-name.test:5173` e scrive `public/hot`, che indica al tema di caricare gli asset dal dev server; il file viene rimosso quando Vite si ferma (Ctrl+C) e il sito torna alla build in `public/build` (`npm run build`). Senza nessuno dei due il sito non ha CSS/JS. Dettagli in [Build](web/app/themes/sage/README.md#build) nel README del tema.

### Processi Solo

Con [Solo](https://soloterm.com), `solo.yml` definisce i processi del progetto (vanno approvati una volta nella UI di Solo: i comandi nuovi o modificati devono essere "trusted"):

| Processo          | Comando                                                                                   | Avvio automatico           |
| ----------------- | ----------------------------------------------------------------------------------------- | -------------------------- |
| **Vite**          | `npm run dev` nel tema (riavviato quando cambiano `vite.config.js` o `package-lock.json`) | sì                         |
| **Debug log**     | `tail -n 0 -F logs/debug.log` (solo le righe nuove, aspetta il file se non esiste ancora) | sì                         |
| **Lint frontend** | `npm run lint` nel tema                                                                   | no, si avvia dalla sidebar |
| **Build tema**    | `npm run build` nel tema                                                                  | no                         |
| **Check PHP**     | `composer check` (root e tema)                                                            | no                         |

Solo non esegue `nvm use`: i suoi processi usano la Node **di default di nvm**. Assicurati che il default soddisfi `engines` (es. `nvm alias default 22` dopo `nvm install 22`), altrimenti Vite e i linter girano su una versione più vecchia.

### URL di accesso

- **Frontend**: `https://project-name.test`
- **Admin**: `https://project-name.test/wp/wp-admin`
- **Styleguide** (solo in development): `https://project-name.test/styleguide/`

In development gli errori PHP vengono mostrati nella pagina e scritti in `logs/debug.log` (fuori dalla webroot).

### Driver del sito (`LocalValetDriver.php`)

Herd carica `LocalValetDriver.php` dalla root del progetto prima dei propri driver, a ogni richiesta (non serve riavviare). Il file estende il `BedrockValetDriver` di Herd e cambia una sola cosa: per le richieste instradate al front controller (`web/index.php`) imposta `$_SERVER['PHP_SELF']` a `/index.php`, come fanno nginx (`try_files … /index.php?$args`) e Apache (`.htaccess` di WordPress).

Senza il driver Herd imposta `PHP_SELF` all'URI richiesto, e WordPress (`WP::parse_request()`) annulla il 404 quando il percorso richiesto coincide con `PHP_SELF`: un URL inesistente che non corrisponde a nessuna regola di rewrite, come `/a/b/c/`, risponde 200 con la home invece del 404 del tema. I file statici, `wp-admin`, `wp-login.php`, `wp-cron.php`, `xmlrpc.php` e gli altri script PHP reali non sono toccati.

Il file sta fuori dalla docroot (`web/`), quindi non viene mai servito, e staging e produzione lo ignorano. Richiede Herd o Valet 4+ (che includono `Valet\Drivers\Specific\BedrockValetDriver`). Per tornare al comportamento predefinito di Herd basta eliminare il file.

Se Herd mostra un errore fatale su `BedrockValetDriver` (per esempio perché una versione futura rinomina la classe), elimina `LocalValetDriver.php`. Il driver ha anche la precedenza sui driver personalizzati globali di Herd (come `BedrockMultisiteValetDriver`): se il sito diventa un multisite, rimuovilo o adattalo.

## Ambienti

`WP_ENV` sceglie l'ambiente. `config/application.php` contiene i valori di **produzione** e poi carica `config/environments/{WP_ENV}.php`, se esiste, per sovrascriverli. Un `WP_ENV` vuoto vale `production`. `WP_ENVIRONMENT_TYPE` (il valore restituito da `wp_get_environment_type()`) vale di default `WP_ENV` quando questo è `production`, `staging`, `development` o `local`.

| Impostazione                                                                          | `production` (`environments/production.php`)                                             | `staging` (`environments/staging.php`)          | `development` (`environments/development.php`)                               |
| ------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- | ----------------------------------------------- | ---------------------------------------------------------------------------- |
| `WP_DEBUG`, `WP_DEBUG_DISPLAY`, `SCRIPT_DEBUG`                                        | off                                                                                      | off                                             | on (`WP_DEBUG`/`WP_DEBUG_DISPLAY` sovrascrivibili da `.env`)                 |
| `WP_DEBUG_LOG`                                                                        | `logs/debug.log`, solo errori e warning, mai a schermo (stessa semantica di development) | off                                             | `logs/debug.log` (`false`/`0` lo disattiva, altrimenti un percorso assoluto) |
| `SAVEQUERIES`, `WP_DISABLE_FATAL_ERROR_HANDLER`                                       | off                                                                                      | off                                             | on                                                                           |
| `DISALLOW_INDEXING` (motori di ricerca bloccati)                                      | no (`false` esplicito)                                                                   | sì                                              | sì                                                                           |
| `DISALLOW_FILE_MODS` (niente installazioni e aggiornamenti di plugin/temi dall'admin) | sì                                                                                       | sì                                              | no                                                                           |
| `DISALLOW_FILE_EDIT`, `AUTOMATIC_UPDATER_DISABLED`                                    | sì                                                                                       | sì                                              | sì                                                                           |
| `WP_POST_REVISIONS`                                                                   | 10 (sovrascrivibile da `.env`)                                                           | illimitate (sovrascrivibile da `.env`)          | illimitate (sovrascrivibile da `.env`)                                       |
| `FORCE_SSL_ADMIN`                                                                     | sì (`FORCE_SSL_ADMIN=false` nel `.env` lo disattiva)                                     | default di WordPress (sì se il sito è in HTTPS) | default di WordPress (sì se il sito è in HTTPS)                              |
| `DISABLE_WP_CRON`                                                                     | da `.env`, default no                                                                    | da `.env`, default no                           | da `.env`, default no                                                        |

`config/application.php` resta la base con i valori di produzione (vale anche per lo staging); `environments/production.php` li ripete in modo esplicito e aggiunge ciò che vale solo in produzione: log su file, 10 revisioni, `FORCE_SSL_ADMIN`. `Config::define()` negli ambienti sovrascrive la base perché viene eseguito prima di `Config::apply()`. In ogni ambiente WordPress e i plugin si aggiornano con Composer, mai dall'admin. Lo staging differisce dalla produzione per il blocco dei motori di ricerca, il log spento, le revisioni illimitate e `FORCE_SSL_ADMIN` lasciato al default di WordPress. Non c'è un file `local`: in locale usa `development`.

Il percorso del log è calcolato da `config/debug-log.php`, condiviso da development e production. In produzione WordPress non configura il log da solo (con `WP_DEBUG` off `wp_debug_mode()` non tocca `log_errors`/`error_log`), quindi lo fa `production.php`; il livello resta quello che `wp_debug_mode()` imposta con `WP_DEBUG` off: errori e warning, senza notice e deprecation. Con `WP_DEBUG_LOG=false` WordPress non scrive un suo log e valgono le impostazioni di `php.ini` del server.

### Variabili d'ambiente

Lette da `config/application.php` e `config/environments/` (`.env`, poi `.env.local`):

| Variabile                           | Obbligatoria                 | Note                                                                                                                                  |
| ----------------------------------- | ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `WP_HOME`, `WP_SITEURL`             | sì                           | URL del sito e URL di WordPress (`"${WP_HOME}/wp"`)                                                                                   |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | sì, salvo con `DATABASE_URL` |                                                                                                                                       |
| `DATABASE_URL`                      | no                           | DSN (`mysql://user:password@host:port/name`) al posto delle chiavi `DB_*`                                                             |
| `DB_HOST`                           | no                           | default `localhost`                                                                                                                   |
| `DB_PREFIX`                         | no                           | prefisso delle tabelle, default `wp_`                                                                                                 |
| `DB_SSL`                            | no                           | `true` attiva `MYSQLI_CLIENT_SSL`                                                                                                     |
| `WP_ENV`                            | no                           | `development`, `staging` o `production` (default)                                                                                     |
| `WP_ENVIRONMENT_TYPE`               | no                           | default `WP_ENV` quando è `production`, `staging`, `development` o `local`                                                            |
| `DISABLE_WP_CRON`                   | no                           | esegue WP-Cron da un cron di sistema invece che al caricamento delle pagine                                                           |
| `WP_POST_REVISIONS`                 | no                           | `true`, `false` o il numero di revisioni da conservare (default 10 in production, `true` altrove)                                     |
| `FORCE_SSL_ADMIN`                   | no                           | solo in production, default `true`: admin e login in HTTPS                                                                            |
| `WP_DEBUG`, `WP_DEBUG_DISPLAY`      | no                           | solo in development (produzione e staging li forzano a off)                                                                           |
| `WP_DEBUG_LOG`                      | no                           | development e production: `true`/`1`/assente = `logs/debug.log`, `false`/`0` = off, altrimenti un percorso assoluto; in staging è off |
| `AUTH_KEY` … `NONCE_SALT`           | sì                           | otto salt, diversi per ogni ambiente                                                                                                  |

La build del tema legge dal `.env` del progetto solo `WP_HOME` (e `APP_URL`, se impostata). `APP_URL` e `VITE_THEME_SLUG` si possono passare anche come variabili di shell a `npm run dev`/`build`: vedi [Build](web/app/themes/sage/README.md#build).

## Traduzioni di WordPress

Le traduzioni di WordPress (core e plugin/temi di wordpress.org installati con Composer) vengono scaricate da [`inpsyde/wp-translation-downloader`](https://github.com/inpsyde/wp-translation-downloader) a ogni `composer install`/`composer update`, in base alle versioni installate. I file in `web/app/languages/` e `wp-translation-downloader.lock` **non sono versionati**: se mancano o non sono aggiornati, rigenerali con:

```bash
composer install
# oppure, per forzare il download
composer wp-translation-downloader:download
```

Per aggiungere una lingua, aggiungi il suo locale a `extra.wp-translation-downloader.languages` in `composer.json` (es. `["it_IT", "en_GB"]`) ed esegui `composer wp-translation-downloader:download` (`en_US` non richiede nulla: è incluso in WordPress). I pacchetti che includono le proprie traduzioni (ACF Pro) sono elencati in `excludes`.

Le stringhe del tema sono scritte in italiano e tradotte nel tema stesso: vedi [Traduzioni](web/app/themes/sage/README.md#traduzioni) nel README del tema.

## Comandi

### Bedrock

```bash
# Installa un plugin o un tema da wpackagist
composer require wpackagist-plugin/plugin-name
composer require wpackagist-theme/theme-name

# Aggiorna WordPress e i plugin (WordPress resta su ~7.1.0: una nuova release x.y è una modifica manuale)
composer update
```

### Tema (da `web/app/themes/sage`)

```bash
npm run dev      # dev server Vite con hot reload
npm run build    # build di produzione in public/build
npm run lint     # ESLint, Stylelint, blade-formatter --check-formatted, prettier --check
npm run format   # Prettier + blade-formatter (scrive i file)
npm run blocks:manifest    # rigenera il manifest dei blocchi dai block.json
npm run icons              # rigenera il manifest delle icone Phosphor usate
npm run translate          # rigenera sage.pot e aggiorna i file .po
npm run translate:compile  # compila .mo, .l10n.php e .json
```

I singoli script `lint:*`, `format:*`, `blocks:*`, `icons:*`, `test:js` e `translate:*` sono descritti nel README del tema.

### Qualità PHP (dalla root del progetto)

```bash
# Pint: root (preset per) + tema (preset laravel, con il suo pint.json)
composer lint
composer lint:fix

# PHPStan livello 6 e test Pest del tema
composer analyse
composer test

# Tutto (CI): validate --strict, audit e Pint della root, poi `composer check` del tema
composer check
```

Gli stessi script esistono nel tema (`composer lint`, `analyse`, `test`, `check` da `web/app/themes/sage`, solo per il tema): vedi [Qualità PHP](web/app/themes/sage/README.md#qualità-php).

## Integrazione continua (CI)

`.github/workflows/ci.yml` gira a ogni push su `main` e su ogni pull request (una nuova push su una PR annulla il run precedente). Il workflow ha solo il permesso `contents: read`. Ogni action è fissata allo SHA di un commit (con la versione in un commento); Dependabot aggiorna SHA e commento insieme.

| Job                                                                                                                                                              | Cosa esegue                                                                                                                                                                                         |
| ---------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Tema PHP 8.2 / 8.3 / 8.4**                                                                                                                                     | in `web/app/themes/sage`: `composer install` + `composer check` (validate --strict, audit, Pint, PHPStan, Pest). Solo pacchetti pubblici, nessuna credenziale.                                      |
| **Root Bedrock** (PHP 8.3: il lock della root include Pint 1.32, che richiede PHP 8.3, quindi un install completo con gli strumenti di sviluppo fallisce su 8.2) | `composer validate --strict`, `composer install` (ACF Pro tramite `COMPOSER_AUTH`, traduzioni di WordPress tramite wp-translation-downloader), `composer audit --locked`, `vendor/bin/pint --test`. |
| **Frontend** (Node da `.nvmrc`, cioè l'ultima 22.x)                                                                                                              | in `web/app/themes/sage`: `npm ci`, `npm run lint`, `npm run blocks:manifest:check`, `npm run icons:check`, `npm run test:js`, `npm run build`. Non servono né `vendor/` né `.env`.                 |

### Secret da creare su GitHub

- **Actions** → _Settings → Secrets and variables → Actions → New repository secret_: `COMPOSER_AUTH`, con il contenuto completo del tuo `auth.json` locale (il JSON `http-basic` per `connect.advancedcustomfields.com`, nella stessa forma di [Credenziali ACF Pro](#2-credenziali-acf-pro)). Non incollarlo da nessun'altra parte.
- **Dependabot** → _Settings → Secrets and variables → Dependabot_:
  - di nuovo `COMPOSER_AUTH`, con lo stesso valore: i run del workflow avviati dalle PR di Dependabot vedono solo i secret di Dependabot, quindi senza di esso il job della root fallisce su quelle PR;
  - `ACF_PRO_LICENSE_KEY` (chiave di licenza ACF Pro) e `ACF_PRO_SITE_URL` (URL del sito per cui la licenza è attivata), usati da `.github/dependabot.yml` per raggiungere il repository di ACF Pro quando aggiorna il `composer.lock` della root.

Se `COMPOSER_AUTH` manca, il job della root fallisce con un errore esplicito, tranne che nelle pull request da fork: GitHub non passa loro i secret, quindi il job mostra un avviso, salta `composer install` e Pint ed esegue comunque `validate` e `audit`.

### Dependabot

`.github/dependabot.yml` controlla ogni lunedì (Europe/Rome) Composer in `/` e in `web/app/themes/sage`, npm in `web/app/themes/sage` e le GitHub Actions usate dal workflow. Gli aggiornamenti minor e patch sono raggruppati (per Composer, dipendenze di sviluppo e di produzione separate), con al massimo 5 PR aperte per ecosistema. Per npm, Stylelint e la sua config sono raggruppati major comprese, perché condividono major ed `engines`. Il core di WordPress (`roots/wordpress`) riceve solo aggiornamenti patch: il passaggio a una nuova release x.y resta una decisione manuale. `roave/security-advisories` è ignorato.

## Deploy

Non c'è uno script di deploy: i passi seguenti valgono per qualunque destinazione (build sul server, artefatto di CI caricato via rsync/SFTP, ecc.). Il server richiede PHP >= 8.2, MySQL, un web server con la docroot su **`web/`** (non sulla root del progetto) e HTTPS.

### 1. Build

Dalla root del progetto (sul server o in CI, con le credenziali ACF Pro disponibili, vedi sotto):

```bash
composer install --no-dev --optimize-autoloader --no-interaction

cd web/app/themes/sage
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

- `--no-dev` esclude Pint, PHPStan, Pest e gli altri strumenti di sviluppo (in produzione basta quindi PHP 8.2).
- `--optimize-autoloader` (`-o`, già il default in entrambi i `composer.json`) genera una classmap. Anche `--classmap-authoritative` funziona con Acorn (le classi del tema in `app/` e quelle dei vendor sono tutte nella classmap), ma in quel caso ogni nuova classe richiede un nuovo `composer install`/`dump-autoload`: usalo solo se la build gira sempre a ogni deploy.
- Il `composer.json` del tema imposta `config.platform.php` a `8.2.0`, quindi Composer non controlla la versione di PHP della macchina di build: il controllo avviene solo a runtime, in `vendor/composer/platform_check.php` (oltre che in WordPress, che rifiuta di attivare il tema sotto `Requires PHP: 8.2` di `style.css`). Assicurati che il server abbia PHP >= 8.2.
- Il `composer install` della root scarica anche le traduzioni di WordPress in `web/app/languages/` e installa ACF Pro, quindi richiede le credenziali ACF dove gira: un `auth.json` sul server (fuori dalla webroot, mai nel repository) oppure `COMPOSER_AUTH` come secret di CI quando la build si fa in CI.
- `npm run build` scrive `web/app/themes/sage/public/build/` (asset con hash + `manifest.json`). Node non serve sul server se la build si fa altrove.

### 2. Cosa caricare

Carica l'intero progetto (root compresa, non solo `web/`): `config/`, `vendor/`, `web/` (con `web/wp`, `web/app/plugins`, `web/app/languages` generati da Composer), `composer.json`/`composer.lock`, `wp-cli.yml`, il tema con `vendor/` e `public/build/`.

**Non** caricare:

- `.git/`, `.github/`, `.idea/`, `MIGLIORIE.md`, `LocalValetDriver.php` e gli altri file di sviluppo;
- `web/app/themes/sage/node_modules/` (mai usato a runtime: le icone Phosphor usate sono già in `resources/icons/phosphor.php`). Mantieni `resources/`: le view Blade, i blocchi, `lang/` e i file di font e immagini controllati dal tema vengono letti a runtime;
- `web/app/themes/sage/public/hot` (se esiste, il tema prova a caricare gli asset dal dev server di Vite);
- `.env`, `.env.local` e `auth.json` locali;
- `logs/*.log`, `web/app/uploads/` (sincronizzata a parte, mai sovrascritta da un deploy), `web/app/cache/`;
- test, stub e configurazioni degli strumenti: `web/app/themes/sage/tests/`, `web/app/themes/sage/stubs/` (stub di PHPStan), `phpunit.xml`, `phpstan.neon.dist`, `pint.json` (root e tema), `eslint.config.js`, `.prettierrc.json`, `.prettierignore`, `.stylelintrc.json`, `.bladeformatterrc.json`, `.bladeignore`, `.editorconfig`, `.nvmrc`, `solo.yml`, `CONVENTIONS.md`, `README.md` (root e tema).

`wp-translation-downloader.lock` serve solo insieme a `web/app/languages/`: carica entrambi o nessuno dei due, altrimenti un successivo `composer install` sul server considera le traduzioni già installate e non le scarica.

### 3. `.env` sul server

Crealo a mano sul server (non copiare quello locale):

```env
DB_NAME='...'
DB_USER='...'
DB_PASSWORD='...'
# DB_HOST='...'

WP_ENV='production'
WP_HOME='https://www.example.com'
WP_SITEURL="${WP_HOME}/wp"

# salt nuovi, diversi da quelli di sviluppo
AUTH_KEY='...'
# ... SECURE_AUTH_KEY, LOGGED_IN_KEY, NONCE_KEY, AUTH_SALT, SECURE_AUTH_SALT, LOGGED_IN_SALT, NONCE_SALT
```

Per una copia di staging usa `WP_ENV='staging'`: le differenze sono descritte in [Ambienti](#ambienti).

Se HTTPS è terminato da un proxy o da un load balancer, il proxy deve impostare `X-Forwarded-Proto: https` (`config/application.php` lo traduce in `$_SERVER['HTTPS']`), sovrascrivendo l'header ricevuto dal client invece di inoltrarlo: senza, `FORCE_SSL_ADMIN` manda l'admin in redirect loop. Su un server senza HTTPS imposta `FORCE_SSL_ADMIN=false`, solo in via temporanea.

#### PHP in produzione

Imposta `display_errors=Off` e `display_startup_errors=Off` nel `php.ini` o nel pool di PHP-FPM di produzione (es. `php_admin_flag[display_errors] = off`). Gli errori del bootstrap (autoload di Composer, `.env` incompleto) arrivano prima che `config/application.php` applichi la configurazione di Bedrock: con `display_errors` attivo nel server verrebbero mostrati a schermo, con i percorsi dei file.

#### Cron di sistema (facoltativo)

Di default WP-Cron parte al caricamento delle pagine. Per eseguirlo a orari regolari anche senza traffico, imposta `DISABLE_WP_CRON=true` nel `.env` e aggiungi al crontab dell'utente PHP:

```cron
*/5 * * * * cd /percorso/del/progetto && wp cron event run --due-now > /dev/null 2>&1
```

### 4. Permessi

L'utente PHP deve poter scrivere in:

- `web/app/uploads/` (media);
- `web/app/cache/` (Acorn: view Blade compilate e cache di bootstrap in `web/app/cache/acorn/`);
- `logs/` (in produzione il log degli errori è attivo di default; con `WP_DEBUG_LOG=false` o un percorso diverso non serve).

Tutto il resto può essere in sola lettura per l'utente PHP.

Se `logs/` non è scrivibile il sito funziona lo stesso, ma PHP scrive gli errori nel log di PHP-FPM o del web server. `logs/debug.log` non viene ruotato da WordPress e cresce senza limiti: ruotalo con logrotate, usando `copytruncate` perché PHP tiene il file aperto. Per esempio in `/etc/logrotate.d/sage`:

```text
/percorso/del/progetto/logs/debug.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    copytruncate
}
```

### 5. Dopo ogni deploy

Dalla root del progetto sul server:

```bash
# Svuota le cache di Acorn della release precedente, poi ricostruiscile
wp acorn optimize:clear
wp acorn optimize        # config, eventi, route e view Blade compilate

# Solo se sono cambiate le regole di rewrite (nuovi post type/tassonomie, rewrite personalizzate, struttura dei permalink)
wp rewrite flush
```

`wp acorn optimize` mette in cache anche la configurazione: dopo una modifica al `.env` esegui `wp acorn optimize:clear` (e di nuovo `wp acorn optimize`). Al primo deploy esegui anche `wp core install` o importa il database (`wp search-replace` degli URL) e attiva il tema: i permalink vengono impostati all'attivazione come al [passo 5](#5-installare-wordpress-o-importare-un-database) del setup locale. Con Apache, WordPress scrive le regole di rewrite in `web/.htaccess` solo se il file è scrivibile; con nginx configura `try_files $uri $uri/ /index.php?$args;`.

## Struttura del progetto

```
project-name/
├── .github/                # Workflow CI e configurazione di Dependabot
├── config/                 # Configurazione Bedrock (application.php + environments/)
├── web/                    # Docroot
│   ├── app/
│   │   ├── languages/      # Traduzioni di WordPress (generate, non versionate)
│   │   ├── mu-plugins/     # Plugin must-use
│   │   ├── plugins/        # Plugin (installati da Composer, non versionati)
│   │   ├── themes/
│   │   │   └── sage/       # Il tema: vedi il suo README e CONVENTIONS.md
│   │   └── uploads/        # File caricati (non versionati)
│   ├── wp/                 # Core di WordPress (installato da Composer, non modificarlo mai)
│   ├── wp-config.php       # Carica config/application.php
│   └── index.php           # Entry point
├── logs/                   # debug.log in development (fuori dalla webroot, non versionato)
├── vendor/                 # Dipendenze Composer
├── .env                    # Configurazione dell'ambiente (non committarlo mai)
├── auth.json               # Credenziali ACF Pro (non committarlo mai)
├── composer.json           # Dipendenze PHP e script di qualità
├── LocalValetDriver.php    # Driver del sito per Herd, solo in locale (vedi "Driver del sito")
├── solo.yml                # Processi Solo
└── wp-cli.yml              # Percorsi di WP-CLI (web/wp, docroot web/)
```

## Licenza

MIT (vedi [LICENSE.md](LICENSE.md)). Bedrock e Sage sono progetti di [Roots](https://roots.io/), anch'essi con licenza MIT.
