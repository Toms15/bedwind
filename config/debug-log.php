<?php

/**
 * Percorso del debug.log, condiviso da config/environments/development.php e
 * production.php. Sta in config/, fuori dalla webroot, e non dichiara simboli
 * globali: restituisce una closure da chiamare con la root del progetto.
 *
 * Semantica di WP_DEBUG_LOG nel .env:
 * - non impostata, true o 1: {root}/logs/debug.log (fuori dalla webroot);
 * - false o 0: log disattivato (restituisce il valore così com'è);
 * - qualunque altra stringa: usata come percorso del file. Deve essere un percorso
 *   assoluto: uno relativo viene risolto rispetto alla directory corrente del processo
 *   (web/ in PHP-FPM) e può finire nella webroot.
 *
 * Uso: `$debug_log = (require __DIR__ . '/../debug-log.php')($root_dir);`
 */

use function Env\env;

return static function (string $root_dir): mixed {
    $debug_log = env('WP_DEBUG_LOG');

    // Stesso controllo di verità di WordPress (wp-includes/load.php): true, 'true' e '1' attivano il log di default
    $enabled = $debug_log === null || in_array(strtolower((string) $debug_log), ['true', '1'], true);

    return $enabled ? $root_dir . '/logs/debug.log' : $debug_log;
};
