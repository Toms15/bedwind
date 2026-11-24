/**
 * Genera il manifest dei metadati dei blocchi: resources/views/blocks/blocks-manifest.php.
 *
 * Il manifest è un file PHP che restituisce un array "<cartella del blocco> =>
 * <metadati del block.json>", il formato che WordPress legge con
 * wp_register_block_types_from_metadata_collection() (WP 6.8+): i blocchi
 * vengono registrati senza glob sulle cartelle né decodifica di ogni block.json a
 * ogni richiesta (vedi app/blocks.php). È ciò che produce `build-blocks-manifest`
 * di @wordpress/scripts, senza la dipendenza.
 *
 * Sono elencate solo le cartelle con un block.json, in ordine di nome. I valori
 * sono il JSON decodificato così com'è (gli oggetti diventano array associativi,
 * come json_decode(..., true)); l'output segue il preset laravel di Pint.
 *
 * Il manifest è versionato: WordPress ne ha bisogno a runtime, anche dove Node
 * non è disponibile. Vite lo rigenera a ogni build e, in dev, ogni volta che un
 * block.json cambia (vite.config.js); la CI controlla che sia aggiornato (--check).
 *
 * Uso:
 *   npm run blocks:manifest        scrive il manifest
 *   npm run blocks:manifest:check  exit 1 se non è aggiornato (--check), usato in CI
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const themeDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

export const blocksDir = path.join(themeDir, 'resources/views/blocks');
export const manifestPath = path.join(blocksDir, 'blocks-manifest.php');

const indent = (level) => '    '.repeat(level);

const phpString = (value) => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;

/**
 * Letterale PHP di un valore JSON decodificato.
 *
 * @param {unknown} value
 * @param {number} level
 * @returns {string}
 */
function toPhp(value, level) {
  if (value === null) {
    return 'null';
  }

  if (typeof value === 'boolean') {
    return value ? 'true' : 'false';
  }

  if (typeof value === 'number') {
    return Number.isInteger(value) ? String(value) : JSON.stringify(value);
  }

  if (typeof value === 'string') {
    return phpString(value);
  }

  const entries = Array.isArray(value)
    ? value.map((item) => [null, item])
    : Object.entries(/** @type {object} */ (value));

  if (entries.length === 0) {
    return '[]';
  }

  const lines = entries.map(([key, item]) => {
    const prefix = key === null ? '' : `${phpString(key)} => `;

    return `${indent(level + 1)}${prefix}${toPhp(item, level + 1)},`;
  });

  return `[\n${lines.join('\n')}\n${indent(level)}]`;
}

/**
 * Codice sorgente del manifest per i block.json attuali.
 *
 * @returns {string}
 */
export function buildBlocksManifest() {
  const metadata = {};

  const folders = fs
    .readdirSync(blocksDir, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name)
    .sort();

  for (const folder of folders) {
    const file = path.join(blocksDir, folder, 'block.json');

    if (!fs.existsSync(file)) {
      continue;
    }

    try {
      metadata[folder] = JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch (error) {
      throw new Error(`${path.relative(themeDir, file)}: ${error.message}`, { cause: error });
    }
  }

  return [
    '<?php',
    '',
    '// Generato da resources/scripts/blocks-manifest.js a partire dai block.json:',
    '// non modificarlo, esegui `npm run blocks:manifest` (Vite lo fa in build e in dev).',
    '',
    `return ${toPhp(metadata, 0)};`,
    '',
  ].join('\n');
}

/**
 * Scrive il manifest se il suo contenuto è cambiato.
 *
 * @returns {boolean} Se il file è stato scritto.
 */
export function writeBlocksManifest() {
  const source = buildBlocksManifest();
  const current = fs.existsSync(manifestPath) ? fs.readFileSync(manifestPath, 'utf8') : null;

  if (source === current) {
    return false;
  }

  fs.writeFileSync(manifestPath, source);

  return true;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const relative = path.relative(themeDir, manifestPath);

  if (process.argv.includes('--check')) {
    const current = fs.existsSync(manifestPath) ? fs.readFileSync(manifestPath, 'utf8') : null;

    if (current !== buildBlocksManifest()) {
      console.error(
        `${relative} non è aggiornato: esegui \`npm run blocks:manifest\` e fanne il commit.`,
      );
      process.exit(1);
    }

    console.log(`${relative} è aggiornato.`);
  } else {
    console.log(writeBlocksManifest() ? `${relative} scritto.` : `${relative} invariato.`);
  }
}
