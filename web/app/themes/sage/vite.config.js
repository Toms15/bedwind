import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, loadEnv } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { wordpressPlugin, wordpressThemeJson } from '@roots/vite-plugin';
import { blocksDir, writeBlocksManifest } from './resources/scripts/blocks-manifest.js';
import { allowlistPath, viewsDir, writeIconsManifest } from './resources/scripts/icons.js';

const themeDir = path.dirname(fileURLToPath(import.meta.url));

// Root di Bedrock (web/app/themes/<theme> -> ../../../..), dove sta il .env del progetto.
const bedrockRoot = path.resolve(themeDir, '../../../..');

// Lo slug del tema si ricava dalla cartella di questa config, così un tema
// rinominato continua a funzionare; VITE_THEME_SLUG lo sovrascrive.
const themeSlug = process.env.VITE_THEME_SLUG || path.basename(themeDir);

// Cartelle dei certificati Herd/Valet controllate da laravel-vite-plugin.
const tlsConfigPaths = [
  path.resolve(os.homedir(), 'Library', 'Application Support', 'Herd', 'config', 'valet'),
  path.resolve(os.homedir(), '.config', 'herd', 'config', 'valet'),
  path.resolve(os.homedir(), '.config', 'valet'),
  path.resolve(os.homedir(), '.valet'),
];

// Il rilevamento TLS di default di laravel-vite-plugin cerca "<cartella cwd>.<tld>",
// cioè il nome della cartella del tema, quindi un tema rinominato tornerebbe
// in silenzio a http. Si usa invece l'host del sito, ma solo se esiste un
// certificato Herd/Valet per quell'host: con un valore stringa il plugin
// lancia un errore se il certificato manca.
function resolveDetectTls(appUrl) {
  let host;
  try {
    host = new URL(appUrl).hostname;
  } catch {
    return null;
  }

  if (!host || host === 'localhost' || host === '127.0.0.1') {
    return null;
  }

  // Come il plugin, cerca solo nella prima cartella di config Herd/Valet esistente.
  const configPath = tlsConfigPaths.find((dir) => fs.existsSync(dir));

  const hasCertificate =
    Boolean(configPath) &&
    fs.existsSync(path.join(configPath, 'Certificates', `${host}.crt`)) &&
    fs.existsSync(path.join(configPath, 'Certificates', `${host}.key`));

  // null mantiene il rilevamento di default del plugin.
  return hasCertificate ? host : null;
}

// Asset opzionali per blocco (resources/views/blocks/<slug>/view.js e
// style.css): una entry ciascuno, caricata solo nelle pagine che renderizzano
// il blocco (App\Blocks\BlockAssets). Il glob gira al caricamento della config,
// quindi un asset aggiunto dopo l'avvio diventa entry solo riavviando
// `npm run dev`. In sviluppo il dev server lo serve comunque, anche senza
// riavvio; l'entry serve alla build, che la scrive nel manifest.
const blockAssets = fs
  .globSync(['resources/views/blocks/*/view.js', 'resources/views/blocks/*/style.css'], {
    cwd: themeDir,
  })
  .map((file) => file.split(path.sep).join('/'))
  .sort();

// Tiene resources/views/blocks/blocks-manifest.php allineato ai block.json:
// scritto all'avvio della build e, in dev, ogni volta che un block.json viene
// aggiunto, modificato o rimosso (poi l'opzione refresh ricarica la pagina).
function blocksManifest() {
  const isBlockJson = (file) =>
    path.basename(file) === 'block.json' && path.dirname(path.dirname(file)) === blocksDir;

  const regenerate = (file) => {
    if (!isBlockJson(path.resolve(file))) {
      return;
    }

    try {
      writeBlocksManifest();
    } catch (error) {
      console.error(`[blocks-manifest] ${error.message}`);
    }
  };

  return {
    name: 'sage-blocks-manifest',
    buildStart() {
      writeBlocksManifest();
    },
    configureServer(server) {
      server.watcher.add(path.join(blocksDir, '*/block.json'));
      server.watcher.on('add', regenerate);
      server.watcher.on('change', regenerate);
      server.watcher.on('unlink', regenerate);
    },
  };
}

// Tiene resources/icons/phosphor.php allineato alle icone usate: scritto
// all'avvio della build (un'icona inesistente la fa fallire) e, in dev, ogni volta
// che cambia una view Blade o resources/icons/allowlist.json. In dev un errore
// (es. un nome scritto a metà) viene solo stampato, senza fermare il server.
function iconsManifest() {
  const isSource = (file) =>
    file === allowlistPath || (file.startsWith(viewsDir + path.sep) && file.endsWith('.blade.php'));

  const regenerate = (file) => {
    if (!isSource(path.resolve(file))) {
      return;
    }

    try {
      writeIconsManifest();
    } catch (error) {
      console.error(`[icons] ${error.message}`);
    }
  };

  return {
    name: 'sage-icons-manifest',
    buildStart() {
      writeIconsManifest();
    },
    configureServer(server) {
      server.watcher.add([path.join(viewsDir, '**/*.blade.php'), allowlistPath]);
      server.watcher.on('add', regenerate);
      server.watcher.on('change', regenerate);
      server.watcher.on('unlink', regenerate);
    },
  };
}

export default defineConfig(({ mode }) => {
  // laravel-vite-plugin legge APP_URL (anche da process.env) per la allowlist
  // CORS del dev server, l'host TLS con VITE_DEV_SERVER_KEY/CERT, la sua pagina
  // di fallback e il banner in console. Di default vale il WP_HOME di Bedrock;
  // si caricano solo queste chiavi, così nessun altro valore del .env arriva alla build.
  const env = loadEnv(mode, bedrockRoot, ['WP_HOME', 'APP_URL']);
  const appUrl = process.env.APP_URL || env.APP_URL || env.WP_HOME || 'http://localhost';
  process.env.APP_URL = appUrl;

  return {
    base: `/app/themes/${themeSlug}/public/build/`,
    plugins: [
      blocksManifest(),
      iconsManifest(),
      tailwindcss(),
      laravel({
        input: [
          'resources/css/app.css',
          'resources/css/editor.css',
          'resources/css/styleguide.css',
          'resources/js/app.js',
          'resources/js/editor.js',
          ...blockAssets,
        ],
        refresh: true,
        detectTls: resolveDetectTls(appUrl),
        // laravel-vite-plugin 3 / Vite 8: gli asset statici vanno qui, non in import.meta.glob.
        assets: ['resources/images/**', 'resources/fonts/**'],
      }),

      wordpressPlugin(),

      // Genera il file theme.json nella directory public/build/assets
      // a partire dalla config di Tailwind e dal theme.json nella cartella base del tema
      wordpressThemeJson({
        disableTailwindColors: false,
        disableTailwindFonts: false,
        disableTailwindFontSizes: false,
      }),
    ],
    resolve: {
      alias: {
        '@scripts': '/resources/js',
        '@styles': '/resources/css',
        '@fonts': '/resources/fonts',
        '@images': '/resources/images',
      },
    },
  };
});
