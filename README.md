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

## Project Structure

```
project-name/
├── config/                 # Bedrock configuration files
├── web/
│   ├── app/
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
