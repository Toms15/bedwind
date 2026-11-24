<?php

use App\Providers\ThemeServiceProvider;
use Roots\Acorn\Application;

/*
|--------------------------------------------------------------------------
| Registrazione dell'autoloader
|--------------------------------------------------------------------------
|
| Composer fornisce un class loader generato automaticamente per il tema.
| Basta includerlo qui, così nessuna classe va caricata a mano in seguito.
|
*/

if (! file_exists($composer = __DIR__.'/vendor/autoload.php')) {
    wp_die(__('Autoloader non trovato. Esegui <code>composer install</code>.', 'sage'));
}

require $composer;

/*
|--------------------------------------------------------------------------
| Registrazione del bootloader
|--------------------------------------------------------------------------
|
| Per prima cosa si programma l'avvio di un nuovo container applicativo
| Acorn quando WordPress ha finito di caricare il tema. L'applicazione fa
| da "collante" fra i componenti di Laravel ed è il container IoC che
| collega fra loro tutte le parti del sistema.
|
*/

Application::configure()
    ->withProviders([
        ThemeServiceProvider::class,
    ])
    ->boot();

/*
|--------------------------------------------------------------------------
| Registrazione dei file del tema Sage
|--------------------------------------------------------------------------
|
| Sage include file del tema divisi per categoria, con funzionalità comuni e
| configurazione da avviare insieme al tema. Aggiungi (o rimuovi) file
| dall'array qui sotto per cambiare cosa viene registrato insieme a Sage.
|
*/

collect(
    [
        'setup',
        'filters',
        'blocks',
        'helpers',
        'svg',
        'rewrites',
    ]
)
    ->each(function ($file) {
        if (! locate_template($file = "app/{$file}.php", true, true)) {
            wp_die(
                /* translators: %s viene sostituito con il percorso relativo del file */
                sprintf(__('Impossibile trovare <code>%s</code> da includere.', 'sage'), $file)
            );
        }
    });
