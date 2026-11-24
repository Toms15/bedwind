<?php

/**
 * Override della configurazione per WP_ENV === 'development'
 */

use Roots\WPConfig\Config;

use function Env\env;

Config::define('SAVEQUERIES', true);
Config::define('WP_DEBUG', env('WP_DEBUG') ?? true);
Config::define('WP_DEBUG_DISPLAY', env('WP_DEBUG_DISPLAY') ?? true);
// debug.log resta fuori dalla webroot ($root_dir arriva da config/application.php):
// WP_DEBUG_LOG true/1 o non impostata = logs/debug.log; false/0 = log disattivato; una stringa = quel percorso.
// Con WP_DEBUG attivo è wp_debug_mode() a impostare log_errors ed error_log da questa costante.
Config::define('WP_DEBUG_LOG', (require dirname(__DIR__) . '/debug-log.php')($root_dir));
Config::define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
Config::define('SCRIPT_DEBUG', true);
Config::define('DISALLOW_INDEXING', true);

ini_set('display_errors', Config::get('WP_DEBUG_DISPLAY') ? '1' : '0');

// Attiva aggiornamenti e installazione di plugin e temi dall'admin
Config::define('DISALLOW_FILE_MODS', false);
