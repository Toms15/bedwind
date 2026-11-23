import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, loadEnv } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { wordpressPlugin, wordpressThemeJson } from '@roots/vite-plugin';
import { blocksDir, writeBlocksManifest } from './resources/scripts/blocks-manifest.js';

const themeDir = path.dirname(fileURLToPath(import.meta.url));

// Bedrock root (web/app/themes/<theme> -> ../../../..), where the project .env lives.
const bedrockRoot = path.resolve(themeDir, '../../../..');

// Derive the theme slug from this config's folder so a renamed theme keeps
// working; set VITE_THEME_SLUG to override it.
const themeSlug = process.env.VITE_THEME_SLUG || path.basename(themeDir);

// Herd/Valet certificate folders checked by laravel-vite-plugin.
const tlsConfigPaths = [
  path.resolve(os.homedir(), 'Library', 'Application Support', 'Herd', 'config', 'valet'),
  path.resolve(os.homedir(), '.config', 'herd', 'config', 'valet'),
  path.resolve(os.homedir(), '.config', 'valet'),
  path.resolve(os.homedir(), '.valet'),
];

// laravel-vite-plugin's default TLS detection looks for "<cwd folder>.<tld>",
// i.e. the theme folder name, so a renamed theme would silently fall back to
// http. Use the site host instead, but only when a Herd/Valet certificate
// exists for it: a string value makes the plugin throw if it is missing.
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

  // Like the plugin, only look in the first existing Herd/Valet config folder.
  const configPath = tlsConfigPaths.find((dir) => fs.existsSync(dir));

  const hasCertificate =
    Boolean(configPath) &&
    fs.existsSync(path.join(configPath, 'Certificates', `${host}.crt`)) &&
    fs.existsSync(path.join(configPath, 'Certificates', `${host}.key`));

  // null keeps the plugin's default detection.
  return hasCertificate ? host : null;
}

// Optional per-block assets (resources/views/blocks/<slug>/view.js and
// style.css): one entry each, enqueued only on the pages that render the block
// (App\Blocks\BlockAssets). Globbed when the config loads: restart `npm run
// dev` after adding one (the dev server serves it anyway, the build needs it).
const blockAssets = fs
  .globSync(['resources/views/blocks/*/view.js', 'resources/views/blocks/*/style.css'], {
    cwd: themeDir,
  })
  .map((file) => file.split(path.sep).join('/'))
  .sort();

// Keep resources/views/blocks/blocks-manifest.php in sync with the block.json
// files: written at build start and, in dev, whenever a block.json is added,
// changed or removed (the refresh option then reloads the page).
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

export default defineConfig(({ mode }) => {
  // laravel-vite-plugin reads APP_URL (process.env included) for the dev-server
  // CORS allowlist, the TLS host with VITE_DEV_SERVER_KEY/CERT, its fallback
  // page and the console banner. Default it to Bedrock's WP_HOME; only these
  // keys are loaded, so no other .env value reaches the build.
  const env = loadEnv(mode, bedrockRoot, ['WP_HOME', 'APP_URL']);
  const appUrl = process.env.APP_URL || env.APP_URL || env.WP_HOME || 'http://localhost';
  process.env.APP_URL = appUrl;

  return {
    base: `/app/themes/${themeSlug}/public/build/`,
    plugins: [
      blocksManifest(),
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
        // laravel-vite-plugin 3 / Vite 8: static assets go here, not in import.meta.glob.
        assets: ['resources/images/**', 'resources/fonts/**'],
      }),

      wordpressPlugin(),

      // Generate the theme.json file in the public/build/assets directory
      // based on the Tailwind config and the theme.json file from base theme folder
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
