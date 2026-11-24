<?php

/**
 * Driver Valet/Herd del sito, usato solo in locale (M41).
 *
 * Herd lo carica dalla root del progetto prima dei driver inclusi e lo
 * preferisce a tutti gli altri. Sta fuori da web/, quindi non è raggiungibile
 * via HTTP, e in staging/produzione non viene mai letto.
 *
 * Problema: BasicValetDriver::beforeLoading() imposta $_SERVER['PHP_SELF']
 * all'URI richiesto. Quando la richiesta finisce sul front controller,
 * WP::parse_request() vede che il file richiesto coincide con PHP_SELF e
 * annulla il 404: un URL inesistente che non corrisponde a nessuna regola di
 * rewrite (es. /a/b/c/) risponde 200 con la home. Con nginx (try_files ...
 * /index.php?$args) o Apache (.htaccess di WordPress) PHP_SELF vale
 * /index.php e il 404 resta.
 *
 * Soluzione: dopo aver risolto il front controller, se lo script è
 * /index.php della docroot si riallinea PHP_SELF a /index.php, come in
 * produzione. I file statici non passano di qui; wp-admin, wp-login.php,
 * wp-cron.php, xmlrpc.php e gli altri script PHP reali hanno uno SCRIPT_NAME
 * diverso e restano invariati.
 *
 * Per tornare al driver Bedrock di Herd basta eliminare questo file.
 */
class LocalValetDriver extends Valet\Drivers\Specific\BedrockValetDriver
{
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        $path = parent::frontControllerPath($sitePath, $siteName, $uri);

        if ($path !== null && ($_SERVER['SCRIPT_NAME'] ?? null) === '/index.php') {
            $_SERVER['PHP_SELF'] = '/index.php';
        }

        return $path;
    }
}
