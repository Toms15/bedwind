/**
 * Icone Phosphor (@phosphor-icons/core): catalogo, validazione e manifest delle
 * icone usate dal tema, resources/icons/phosphor.php.
 *
 * Il componente <x-ui.icon> (App\Icons\Phosphor) stampa gli SVG inline lato PHP,
 * ma in produzione node_modules non c'è: le icone usate vengono copiate in un
 * manifest PHP versionato ("<peso>" => ["<nome>" => "<contenuto dell'svg>"]),
 * con solo il contenuto dell'elemento <svg> (il viewBox è sempre 0 0 256 256).
 * Nessuno sprite completo: entrano solo
 *  - i tag <x-ui.icon> con name (e weight) statici nelle view Blade
 *    (resources/views/**\/*.blade.php);
 *  - le voci di resources/icons/allowlist.json, per le icone dinamiche
 *    (:name="$icon", nomi letti da campi ACF, chiamate dirette da PHP):
 *    "nome" (peso regular) o "nome:peso".
 * Un nome o un peso inesistente fa fallire lo script, con dei suggerimenti.
 *
 * Il manifest è versionato come blocks-manifest.php: WordPress lo legge a
 * runtime senza Node. Vite lo rigenera a ogni build e, in dev, a ogni modifica
 * di una view o dell'allowlist (vite.config.js); la CI controlla che sia
 * aggiornato (--check).
 *
 * Le stesse funzioni (elenco degli slug, validazione, SVG, suggerimenti, icona
 * casuale) servono al wizard dei nuovi blocchi (M47) per l'icona del block.json.
 *
 * Uso:
 *   npm run icons        scrive il manifest
 *   npm run icons:check  exit 1 se non è aggiornato (--check), usato in CI
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { icons as catalog } from '@phosphor-icons/core';
import { toPhp } from './blocks-manifest.js';

const themeDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

export const viewsDir = path.join(themeDir, 'resources/views');
export const iconsDir = path.join(themeDir, 'resources/icons');
export const manifestPath = path.join(iconsDir, 'phosphor.php');
export const allowlistPath = path.join(iconsDir, 'allowlist.json');
export const assetsDir = path.join(themeDir, 'node_modules/@phosphor-icons/core/assets');

/** Pesi di Phosphor, nell'ordine del manifest. */
export const WEIGHTS = ['thin', 'light', 'regular', 'bold', 'fill', 'duotone'];

export const DEFAULT_WEIGHT = 'regular';

/** Slug ammessi: minuscole, cifre e trattini (niente percorsi). Uguale a App\Icons\Phosphor::NAME_PATTERN. */
export const NAME_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

/**
 * Voci del catalogo del pacchetto: nome, categorie e tag di ogni icona.
 *
 * @returns {{ name: string, categories: string[], tags: string[] }[]}
 */
export function listIcons() {
  return catalog.map(({ name, categories, tags }) => ({
    name,
    categories: [...categories],
    tags: tags.filter((tag) => !tag.startsWith('*')),
  }));
}

const names = new Set(catalog.map((entry) => entry.name));

/**
 * Slug di tutte le icone del pacchetto, in ordine alfabetico.
 *
 * @returns {string[]}
 */
export function listIconNames() {
  return [...names].sort();
}

export const isValidName = (name) => typeof name === 'string' && NAME_PATTERN.test(name);

export const isValidWeight = (weight) => WEIGHTS.includes(weight);

/**
 * Indica se l'icona esiste nel pacchetto installato con quel peso.
 *
 * @param {string} name
 * @param {string} [weight]
 * @returns {boolean}
 */
export function iconExists(name, weight = DEFAULT_WEIGHT) {
  return isValidName(name) && isValidWeight(weight) && names.has(name);
}

/**
 * Percorso del file SVG nel pacchetto (es. assets/bold/arrow-right-bold.svg).
 *
 * @param {string} name
 * @param {string} [weight]
 * @returns {string}
 */
export function iconPath(name, weight = DEFAULT_WEIGHT) {
  const suffix = weight === DEFAULT_WEIGHT ? '' : `-${weight}`;

  return path.join(assetsDir, weight, `${name}${suffix}.svg`);
}

/**
 * SVG completo dell'icona così come lo fornisce il pacchetto
 * (<svg xmlns viewBox="0 0 256 256" fill="currentColor">…</svg>), ad esempio per
 * l'icona di un block.json. Errore se l'icona non esiste.
 *
 * @param {string} name
 * @param {string} [weight]
 * @returns {string}
 */
export function getIconSvg(name, weight = DEFAULT_WEIGHT) {
  if (!iconExists(name, weight)) {
    throw new Error(unknownIconMessage(name, weight));
  }

  return fs.readFileSync(iconPath(name, weight), 'utf8').trim();
}

/**
 * Contenuto dell'elemento <svg> (path, opacity del duotone), senza il tag esterno.
 *
 * @param {string} name
 * @param {string} [weight]
 * @returns {string}
 */
export function getIconInner(name, weight = DEFAULT_WEIGHT) {
  const match = getIconSvg(name, weight).match(/^<svg\b[^>]*>([\s\S]*)<\/svg>$/);

  if (!match) {
    throw new Error(`SVG inatteso per ${name} (${weight}).`);
  }

  return match[1];
}

/**
 * Distanza di Levenshtein, per i suggerimenti.
 */
function distance(a, b) {
  const row = Array.from({ length: b.length + 1 }, (_, index) => index);

  for (let i = 1; i <= a.length; i++) {
    let previous = row[0];
    row[0] = i;

    for (let j = 1; j <= b.length; j++) {
      const current = row[j];
      row[j] = Math.min(row[j] + 1, row[j - 1] + 1, previous + (a[i - 1] === b[j - 1] ? 0 : 1));
      previous = current;
    }
  }

  return row[b.length];
}

/**
 * Slug simili a quello dato: nomi vicini (refusi), nomi che lo contengono e
 * icone con un tag uguale. Per i messaggi d'errore e per il wizard di M47.
 *
 * @param {string} query
 * @param {number} [limit]
 * @returns {string[]}
 */
export function suggestIcons(query, limit = 5) {
  const needle = String(query).toLowerCase().trim();

  if (!needle) {
    return [];
  }

  return listIcons()
    .map(({ name, tags }) => {
      let score = distance(needle, name);

      if (name.includes(needle)) {
        score = Math.min(score, 1 + (name.length - needle.length) / 100);
      }

      if (tags.includes(needle)) {
        score = Math.min(score, 2);
      }

      return { name, score };
    })
    .filter(({ score }) => score <= Math.max(2, Math.floor(needle.length / 3)))
    .sort((a, b) => a.score - b.score || a.name.localeCompare(b.name))
    .slice(0, limit)
    .map(({ name }) => name);
}

/**
 * Slug di un'icona a caso del pacchetto (tutte esistono in tutti i pesi), come
 * App\Icons\Phosphor::random(). Null se le esclusioni le coprono tutte.
 *
 * @param {{ exclude?: Iterable<string>, random?: () => number }} [options]
 *   exclude: slug da escludere (es. icone già usate da altri blocchi);
 *   random: generatore in [0, 1), sostituibile nei test.
 * @returns {string|null}
 */
export function randomIcon({ exclude = [], random = Math.random } = {}) {
  const excluded = new Set(exclude);
  const all = listIconNames().filter((name) => !excluded.has(name));

  return all.length ? all[Math.floor(random() * all.length)] : null;
}

function unknownIconMessage(name, weight) {
  if (!isValidWeight(weight)) {
    return `Peso "${weight}" non valido: usa ${WEIGHTS.join(', ')}.`;
  }

  const suggestions = suggestIcons(name);
  const hint = suggestions.length ? ` Forse: ${suggestions.join(', ')}.` : '';

  return `Icona Phosphor "${name}" inesistente.${hint}`;
}

/**
 * Attributi di un tag, letti in sequenza: chiave => valore (senza virgolette;
 * true per un attributo senza valore). Le chiavi restano come scritte (:name,
 * data-name), quindi un name="…" dentro il valore di un altro attributo non
 * viene scambiato per l'attributo name.
 *
 * @param {string} attributes Testo fra il nome del tag e la ">".
 * @returns {Map<string, string|true>}
 */
export function parseAttributes(attributes) {
  const parsed = new Map();

  // Una stringa tra virgolette senza chiave (es. dentro @class([...])) viene consumata e scartata.
  for (const [, key, raw] of attributes.matchAll(
    /"[^"]*"|'[^']*'|([^\s"'>/=]+)(?:\s*=\s*("[^"]*"|'[^']*'|[^\s"'>]+))?/g,
  )) {
    if (key === undefined || parsed.has(key)) {
      continue;
    }

    parsed.set(key, raw === undefined ? true : raw.replace(/^(["'])([\s\S]*)\1$/, '$2'));
  }

  return parsed;
}

/** Valore con un echo Blade ({{ … }}, {!! … !!}): dinamico, va nell'allowlist. */
const isBladeEcho = (value) => typeof value === 'string' && /\{\{|\{!!/.test(value);

/**
 * Icone usate con name statico dai tag <x-ui.icon> nelle view Blade.
 *
 * Un tag con name o weight dinamici viene ignorato: quelle icone vanno
 * nell'allowlist. Sono dinamici sia gli attributi con i due punti
 * (:name="$icon") sia i valori con un echo Blade (name="{{ $icon }}").
 *
 * I commenti Blade ({{-- … --}}) e HTML (<!-- … -->) vengono tolti prima della
 * scansione. Non i blocchi @php: un <x-ui.icon> scritto in una stringa o in un
 * commento PHP viene contato (al massimo qualche byte in più nel manifest).
 *
 * @param {string} [dir] Cartella delle view.
 * @returns {{ name: string, weight: string, source: string }[]}
 */
export function scanViews(dir = viewsDir) {
  const usages = [];

  const files = fs
    .globSync('**/*.blade.php', { cwd: dir })
    .map((file) => file.split(path.sep).join('/'))
    .sort();

  for (const file of files) {
    // Commenti sostituiti da spazi con gli stessi a capo, così i numeri di riga restano giusti.
    const source = fs
      .readFileSync(path.join(dir, file), 'utf8')
      .replace(/\{\{--[\s\S]*?--\}\}|<!--[\s\S]*?-->/g, (comment) =>
        comment.replace(/[^\n]/g, ' '),
      );

    for (const match of source.matchAll(/<x-ui\.icon(?=[\s/>])((?:[^>"']|"[^"]*"|'[^']*')*)>/g)) {
      const attributes = parseAttributes(match[1]);

      if (attributes.has(':name') || attributes.has(':weight')) {
        continue;
      }

      const name = attributes.get('name');
      const weight = attributes.get('weight') ?? DEFAULT_WEIGHT;

      if (typeof name !== 'string' || isBladeEcho(name) || isBladeEcho(weight)) {
        continue;
      }

      const line = source.slice(0, match.index).split('\n').length;

      usages.push({
        name,
        weight: String(weight),
        source: `${path.relative(themeDir, path.join(dir, file))}:${line}`,
      });
    }
  }

  return usages;
}

/**
 * Voci dell'allowlist: un array JSON di "nome" o "nome:peso".
 *
 * @param {string} [file]
 * @returns {{ name: string, weight: string, source: string }[]}
 */
export function readAllowlist(file = allowlistPath) {
  if (!fs.existsSync(file)) {
    return [];
  }

  const source = path.relative(themeDir, file);
  let entries;

  try {
    entries = JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch (error) {
    throw new Error(`${source}: ${error.message}`, { cause: error });
  }

  if (!Array.isArray(entries) || entries.some((entry) => typeof entry !== 'string')) {
    throw new Error(`${source}: deve essere un array di stringhe "nome" o "nome:peso".`);
  }

  return entries.map((entry) => {
    const [name, weight = DEFAULT_WEIGHT] = entry.trim().split(':');

    return { name, weight, source };
  });
}

/**
 * Icone da includere nel manifest, raggruppate per peso. Errore se una non esiste.
 *
 * @param {{ views?: string, allowlist?: string }} [options]
 * @returns {Record<string, string[]>}
 */
export function collectIcons({ views = viewsDir, allowlist = allowlistPath } = {}) {
  const usages = [...scanViews(views), ...readAllowlist(allowlist)];
  const errors = usages
    .filter(({ name, weight }) => !iconExists(name, weight))
    .map(({ name, weight, source }) => `${source}: ${unknownIconMessage(name, weight)}`);

  if (errors.length) {
    throw new Error([...new Set(errors)].join('\n'));
  }

  const byWeight = {};

  for (const weight of WEIGHTS) {
    const list = [
      ...new Set(usages.filter((usage) => usage.weight === weight).map((usage) => usage.name)),
    ];

    if (list.length) {
      byWeight[weight] = list.sort();
    }
  }

  return byWeight;
}

/**
 * Codice sorgente del manifest per le icone usate oggi.
 *
 * @param {{ views?: string, allowlist?: string }} [options]
 * @returns {string}
 */
export function buildIconsManifest(options) {
  const icons = {};

  for (const [weight, list] of Object.entries(collectIcons(options))) {
    icons[weight] = Object.fromEntries(list.map((name) => [name, getIconInner(name, weight)]));
  }

  return [
    '<?php',
    '',
    '// Generato da resources/scripts/icons.js (icone Phosphor usate nelle view e in',
    '// resources/icons/allowlist.json): non modificarlo, esegui `npm run icons`',
    '// (Vite lo fa in build e in dev).',
    '',
    `return ${toPhp(icons, 0)};`,
    '',
  ].join('\n');
}

/**
 * Scrive il manifest se il suo contenuto è cambiato.
 *
 * @param {{ views?: string, allowlist?: string, output?: string }} [options]
 * @returns {boolean} Se il file è stato scritto.
 */
export function writeIconsManifest({ output = manifestPath, ...options } = {}) {
  const source = buildIconsManifest(options);
  const current = fs.existsSync(output) ? fs.readFileSync(output, 'utf8') : null;

  if (source === current) {
    return false;
  }

  fs.mkdirSync(path.dirname(output), { recursive: true });
  fs.writeFileSync(output, source);

  return true;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const relative = path.relative(themeDir, manifestPath);

  try {
    if (process.argv.includes('--check')) {
      const current = fs.existsSync(manifestPath) ? fs.readFileSync(manifestPath, 'utf8') : null;

      if (current !== buildIconsManifest()) {
        console.error(`${relative} non è aggiornato: esegui \`npm run icons\` e fanne il commit.`);
        process.exit(1);
      }

      console.log(`${relative} è aggiornato.`);
    } else {
      console.log(writeIconsManifest() ? `${relative} scritto.` : `${relative} invariato.`);
    }
  } catch (error) {
    console.error(error.message);
    process.exit(1);
  }
}
