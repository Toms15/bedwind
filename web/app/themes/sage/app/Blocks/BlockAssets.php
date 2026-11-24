<?php

declare(strict_types=1);

namespace App\Blocks;

use Illuminate\Support\Facades\Vite;

/**
 * Asset frontend opzionali per blocco.
 *
 * La cartella di un blocco può contenere, accanto a block.json e <slug>.blade.php:
 * - view.js: caricato come script module (type="module", nel footer);
 * - style.css: caricato come foglio di stile (WordPress 6.9+ sposta nel <head>
 *   gli stili caricati durante il render anche nei temi classici).
 *
 * Entrambi sono entry Vite (vite.config.js li raccoglie con un glob) e li
 * carica BlockRenderer::render() solo quando il blocco è renderizzato nel
 * frontend, quindi le pagine senza il blocco non li caricano. Nell'editor i
 * blocchi renderizzano un placeholder, quindi lì non si carica nulla.
 *
 * Con il dev server, Vite::asset() restituisce l'URL del dev server (HMR tramite
 * il client Vite caricato con app.js); in build, il file con hash del manifest.
 * Un asset assente dal manifest di Vite (aggiunto senza rifare la build) viene
 * saltato, così non rompe mai la pagina.
 */
class BlockAssets
{
    /**
     * Tipo di asset => nome del file nella cartella del blocco.
     */
    public const FILES = [
        'style' => 'style.css',
        'script' => 'view.js',
    ];

    /**
     * Gli asset esistenti di un blocco, come percorsi Vite relativi al tema.
     *
     * @return array{style?: string, script?: string}
     */
    public static function for(string $slug): array
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
            return [];
        }

        $assets = [];

        foreach (self::FILES as $type => $file) {
            $path = "resources/views/blocks/{$slug}/{$file}";

            if (is_file(get_theme_file_path($path))) {
                $assets[$type] = $path;
            }
        }

        return $assets;
    }

    /**
     * Handle degli asset del blocco (handle del foglio di stile e id dello script module).
     */
    public static function handle(string $slug): string
    {
        return "sage-block-{$slug}";
    }

    /**
     * Carica gli asset di un blocco. Si può chiamare una volta per ogni blocco
     * renderizzato: WordPress carica ogni handle una volta sola.
     */
    public static function enqueue(string $slug): void
    {
        $handle = self::handle($slug);

        foreach (self::for($slug) as $type => $path) {
            try {
                $url = Vite::asset($path);
            } catch (\Throwable) {
                continue;
            }

            // Versione null: i file di build hanno l'hash nel nome, il dev server non ne ha bisogno.
            if ($type === 'style') {
                wp_enqueue_style($handle, $url, [], null);
            } else {
                wp_enqueue_script_module($handle, $url, [], null);
            }
        }
    }
}
