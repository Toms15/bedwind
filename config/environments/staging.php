<?php

/**
 * Override della configurazione per WP_ENV === 'staging'
 */

use Roots\WPConfig\Config;

/**
 * Lo staging va tenuto il più vicino possibile alla produzione. Se serve, i
 * valori della configurazione di produzione si possono comunque sovrascrivere
 * con `Config::define`.
 *
 * Esempio: `Config::define('WP_DEBUG', true);`
 * Esempio: `Config::define('DISALLOW_FILE_MODS', false);`
 */

Config::define('DISALLOW_INDEXING', true);
