<?php

/**
 * Configuration overrides for WP_ENV === 'development'
 */

use Roots\WPConfig\Config;

use function Env\env;

Config::define('SAVEQUERIES', true);
Config::define('WP_DEBUG', env('WP_DEBUG') ?? true);
Config::define('WP_DEBUG_DISPLAY', env('WP_DEBUG_DISPLAY') ?? true);
// Keep debug.log outside the webroot ($root_dir comes from config/application.php):
// WP_DEBUG_LOG true/1 or unset = logs/debug.log; false/0 = logging off; a string = that path
$debug_log = env('WP_DEBUG_LOG');
// Same truthy check as WordPress (wp-includes/load.php): true, 'true' and '1' enable the default log
$debug_log_enabled = $debug_log === null || in_array(strtolower((string) $debug_log), ['true', '1'], true);
Config::define('WP_DEBUG_LOG', $debug_log_enabled ? $root_dir . '/logs/debug.log' : $debug_log);
Config::define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
Config::define('SCRIPT_DEBUG', true);
Config::define('DISALLOW_INDEXING', true);

ini_set('display_errors', Config::get('WP_DEBUG_DISPLAY') ? '1' : '0');

// Enable plugin and theme updates and installation from the admin
Config::define('DISALLOW_FILE_MODS', false);
