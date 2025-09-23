# Progetto WordPress con Bedrock & Sage

Questo progetto utilizza [Bedrock](https://roots.io/bedrock/) come boilerplate per WordPress e [Sage](https://roots.io/sage/) come starter theme.

## Requisiti

- PHP >= 8.0
- Composer
- Node.js >= 16.0
- npm

## Setup Iniziale

### 1. Clona il repository

```bash
git clone <url-repository> nome-progetto
cd nome-progetto
```

### 2. Installa le dipendenze PHP

```bash
composer install
```

### 3. Configura l'ambiente

Copia il file di configurazione di esempio e personalizzalo:

```bash
cp .env.example .env
```

Modifica il file `.env` con le tue configurazioni:

```env
DB_NAME='nome_database'
DB_USER='utente_db'
DB_PASSWORD='password_db'
DB_HOST='localhost'

WP_ENV='development'
WP_HOME='https://nome-progetto.test'
WP_SITEURL="${WP_HOME}/wp"
WP_HOME_DOMAIN='nome-progetto.test'

# Genera chiavi sicure su https://roots.io/salts.html
AUTH_KEY='genera-chiave-sicura'
SECURE_AUTH_KEY='genera-chiave-sicura'
LOGGED_IN_KEY='genera-chiave-sicura'
NONCE_KEY='genera-chiave-sicura'
AUTH_SALT='genera-chiave-sicura'
SECURE_AUTH_SALT='genera-chiave-sicura'
LOGGED_IN_SALT='genera-chiave-sicura'
NONCE_SALT='genera-chiave-sicura'
```

### 4. Setup del tema Sage

Naviga nella cartella del tema e installa le dipendenze Node.js:

```bash
cd web/app/themes/sage
composer install
npm install
```

### 5. Sviluppo del tema

Per avviare la compilazione in modalità sviluppo (con hot reload):

```bash
npm run dev
```

Per compilare gli asset per la produzione:

```bash
npm run build
```

## Struttura del Progetto

```
nome-progetto/
├── config/                 # File di configurazione di Bedrock
├── web/
│   ├── app/
│   │   ├── mu-plugins/     # Must-use plugins
│   │   ├── plugins/        # Plugin standard
│   │   ├── themes/         # Temi personalizzati
│   │   │   └── nome-tema/  # Tema Sage
│   │   └── uploads/        # File caricati
│   ├── wp-config.php       # WordPress config
│   └── index.php           # Entry point
├── vendor/                 # Dipendenze Composer
├── .env                    # Configurazione ambiente (non committare!)
└── composer.json           # Dipendenze PHP
```

## Comandi Utili

### Bedrock

```bash
# Installa plugin via Composer
composer require wpackagist-plugin/plugin-name

# Installa tema via Composer  
composer require wpackagist-theme/theme-name

# Aggiorna WordPress e plugin
composer update
```

### Sage

```bash
# Compila asset per sviluppo
npm run dev

# Compila asset per produzione
npm run build

# Watch mode (ricompila automaticamente)
npm run dev:watch

# Linting del codice
npm run lint

# Correggi errori di linting automaticamente
npm run lint:fix
```

## Database

Assicurati di aver creato il database specificato in `DB_NAME` nel file `.env` prima di accedere al sito.

## URL di Accesso

- **Frontend**: `https://nome-progetto.test`
- **Admin**: `https://nome-progetto.test/wp/wp-admin`

## Deployment

Per il deployment in produzione:

1. Cambia `WP_ENV` da `development` a `production` nel file `.env`
2. Aggiorna `WP_HOME` con l'URL di produzione
3. Esegui `npm run build` per compilare gli asset ottimizzati
4. Carica i file sul server escludendo:
   - `node_modules/`
   - `.env` (crea una nuova configurazione sul server)
   - File di sviluppo (`.git`, `.gitignore`, etc.)

## Note Aggiuntive

- Il file `.env` contiene informazioni sensibili e **non deve essere committato** nel repository
- Gli asset compilati sono nella cartella `web/app/themes/sage/public/`
- Le dipendenze sono gestite tramite Composer (PHP) e npm (Node.js)
- WordPress core è installato nella cartella `web/wp/` e non deve essere modificato direttamente
