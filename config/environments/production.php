<?php

/**
 * Override della configurazione per WP_ENV === 'production' (anche il default
 * quando WP_ENV è vuota).
 *
 * config/application.php contiene già i valori di produzione: qui sono ripetuti
 * in modo esplicito, così la produzione non dipende da come cambierà la base
 * (che vale anche per lo staging), e si aggiunge ciò che vale solo qui.
 * Config::define() prima di Config::apply() sovrascrive il valore della base
 * senza errori: l'eccezione scatta solo se la costante PHP è già definita.
 */

use Roots\WPConfig\Config;

use function Env\env;

/**
 * Debug: niente output a schermo, niente asset non minificati
 */
Config::define('WP_DEBUG', false);
Config::define('WP_DEBUG_DISPLAY', false);
Config::define('SCRIPT_DEBUG', false);
ini_set('display_errors', '0');

/**
 * Log degli errori su file, fuori dalla webroot ($root_dir arriva da config/application.php).
 *
 * Con WP_DEBUG false wp_debug_mode() non tocca log_errors né error_log, quindi il
 * log va configurato qui. WP_DEBUG_LOG ha la stessa semantica di development
 * (vedi config/debug-log.php): non impostata = logs/debug.log, false/0 = nessun
 * log da parte di WordPress (restano le impostazioni di php.ini del server),
 * una stringa = quel percorso.
 *
 * Il livello non si imposta qui: con WP_DEBUG false wp_debug_mode() chiama comunque
 * error_reporting() con errori, warning ed E_USER_ERROR/E_USER_WARNING, escludendo
 * notice, deprecation ed E_STRICT. È il livello giusto per la produzione: nel log
 * finisce ciò che indica un malfunzionamento, non il rumore dei plugin.
 */
$debug_log = (require dirname(__DIR__) . '/debug-log.php')($root_dir);
Config::define('WP_DEBUG_LOG', $debug_log);

if (is_string($debug_log) && $debug_log !== '') {
    ini_set('log_errors', '1');
    ini_set('error_log', $debug_log);
}

/**
 * Sicurezza: niente editor di file né installazioni/aggiornamenti dall'admin
 * (WordPress e i plugin si aggiornano con Composer), niente aggiornamenti automatici
 */
Config::define('DISALLOW_FILE_EDIT', true);
Config::define('DISALLOW_FILE_MODS', true);
Config::define('AUTOMATIC_UPDATER_DISABLED', true);

/**
 * Indicizzazione consentita: il mu-plugin bedrock-disallow-indexing blocca i
 * motori di ricerca solo con DISALLOW_INDEXING === true
 */
Config::define('DISALLOW_INDEXING', false);

/**
 * Admin e login solo in HTTPS. Dietro un proxy o un load balancer che termina SSL
 * serve l'header X-Forwarded-Proto: https (gestito in config/application.php),
 * altrimenti l'admin va in redirect loop. FORCE_SSL_ADMIN=false nel .env lo disattiva.
 */
Config::define('FORCE_SSL_ADMIN', env('FORCE_SSL_ADMIN') ?? true);

/**
 * Revisioni dei post: 10 di default (true = illimitate, false/0 = nessuna)
 */
Config::define('WP_POST_REVISIONS', env('WP_POST_REVISIONS') ?? 10);

/**
 * WP-Cron resta come in config/application.php: DISABLE_WP_CRON=true nel .env lo
 * disattiva al caricamento delle pagine, e va eseguito da un cron di sistema
 * (vedi la sezione Deploy del README).
 */
