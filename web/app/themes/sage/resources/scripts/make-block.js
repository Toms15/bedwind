/**
 * Wizard per un nuovo blocco ACF del tema: `npm run make:block`.
 *
 * Genera, seguendo CONVENTIONS.md (blocchi, checklist del nuovo blocco):
 *  - resources/views/blocks/<slug>/block.json (apiVersion 3, icona Phosphor fill);
 *  - resources/views/blocks/<slug>/<slug>.blade.php (view: prepara i dati);
 *  - resources/views/modules/<slug>.blade.php (modulo: il markup);
 *  - acf-json/group_<id>.json (field group del blocco, location acf/<slug>);
 *  - view.js e/o style.css facoltativi nella cartella del blocco;
 * poi aggiorna il catalogo delle traduzioni (npm run translate +
 * translate:compile) e rigenera il manifest dei blocchi e quello delle icone.
 *
 * Le domande compaiono solo per i valori non passati con i flag; senza un
 * terminale interattivo (TTY) i valori obbligatori mancanti sono un errore.
 * Non sovrascrive mai nulla: uno slug già usato da un blocco o da un field
 * group viene rifiutato.
 *
 * Uso:
 *   npm run make:block
 *   npm run make:block -- --title "Card" --description "Elenco di card" \
 *     --category static-content --icon cards \
 *     --fields "Titolo=title:text!,Card=cards:repeater[Titolo:text!,Immagine:image,Link:link]" \
 *     --yes
 *
 * Flag: --title, --slug, --description, --category, --icon ("" = casuale),
 * --keywords (separate da virgola), --fields (formato in parseFieldsSpec()),
 * --view-js/--no-view-js, --style/--no-style, --yes (salta la conferma),
 * --dry-run (mostra i file senza scriverli), --no-translate (salta gli script
 * di traduzione), --help.
 */

import { spawnSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';
import { writeBlocksManifest } from './blocks-manifest.js';
import {
  assetsDir,
  getIconSvg,
  iconExists,
  listIconNames,
  randomIcon,
  suggestIcons,
  writeIconsManifest,
} from './icons.js';

export const themeDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

/** Peso delle icone dei blocchi: fisso, uguale per tutti i blocchi. */
export const ICON_WEIGHT = 'fill';

/** Tipi di campo del wizard: tipo ACF => etichetta nel wizard. */
export const FIELD_TYPES = {
  text: 'Testo',
  textarea: 'Area di testo',
  wysiwyg: 'Editor WYSIWYG',
  image: 'Immagine',
  link: 'Link',
  true_false: 'Vero/falso',
  select: 'Select',
  repeater: 'Repeater',
};

/** Tipi ammessi come sotto-campi di un repeater (niente select né repeater annidati). */
export const SUB_FIELD_TYPES = ['text', 'textarea', 'wysiwyg', 'image', 'link', 'true_false'];

/** Alias accettati in --fields, oltre ai tipi ACF. */
const TYPE_ALIASES = {
  testo: 'text',
  editor: 'wysiwyg',
  immagine: 'image',
  bool: 'true_false',
  boolean: 'true_false',
  'true-false': 'true_false',
  truefalse: 'true_false',
};

/**
 * Nomi di campo vietati al primo livello:
 *  - i campi del gruppo condiviso "Impostazioni blocco" (block == all);
 *  - title_formatting e buttons, letti da BlockRenderer::data() con una forma
 *    precisa (title_formatting viene aggiunto dal wizard insieme a "title");
 *  - le variabili usate dalla view e dal modulo generati (ogni campo diventa
 *    una variabile $<name>) e quelle riservate di Blade.
 */
export const RESERVED_NAMES = new Set([
  'padding',
  'padding_dimension',
  'margin',
  'margin_dimension',
  'background_color',
  'text_color',
  'title_formatting',
  'buttons',
  'block',
  'data',
  'settings',
  'contents',
  'spacings',
  'section_style',
  'to_link',
  'rows',
  'row',
  'item',
  'attributes',
  'slot',
  'loop',
  'errors',
  'app',
  'component',
  'this',
]);

export const SLUG_PATTERN = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/;
export const FIELD_NAME_PATTERN = /^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/;

const stripAccents = (text) => String(text).normalize('NFD').replace(/[̀-ͯ]/g, '');

/** Caratteri di controllo (C0, DEL, C1) e separatori di riga Unicode. */
// eslint-disable-next-line no-control-regex
const CONTROL_CHARS = /[\u0000-\u001f\u007f-\u009f\u2028\u2029]/;

/**
 * Valida un testo libero (titolo, etichetta, …): niente caratteri di controllo.
 *
 * @param {string} value
 * @param {string} what Nome del valore nel messaggio (es. "Il titolo").
 * @returns {string|undefined}
 */
export function validateText(value, what) {
  return CONTROL_CHARS.test(String(value ?? ''))
    ? `${what} contiene caratteri di controllo (a capo, tabulazioni, …): usa testo su una riga.`
    : undefined;
}

/** Sequenze che chiudono o aprono commenti/blocchi PHP, Blade, JS e CSS. */
const COMMENT_BREAKERS = /\*\/|\/\*|\?>|<\?|\{\{|\}\}|\{!!|!!\}|@/g;

/**
 * Testo sicuro dentro un commento generato (PHP, Blade, JS, CSS): una riga,
 * senza le sequenze che chiudono il commento o aprono codice
 * (`*\/`, `/*`, `?>`, `<?`, `{{`, `}}`, `{!!`, `!!}`, `@` → `(at)`).
 * `--}}` e `{{--` sono coperti da `}}` e `{{`. Ripete la sostituzione finché
 * il testo non cambia, così non restano sequenze formate dai pezzi rimasti.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function commentText(value) {
  let text = String(value ?? '')
    .replace(new RegExp(CONTROL_CHARS.source, 'g'), ' ')
    .replace(/\s+/g, ' ')
    .trim();

  for (let previous; previous !== text;) {
    previous = text;
    text = text.replace(COMMENT_BREAKERS, (match) =>
      match === '@' ? '(at)' : `${match[0]} ${match.slice(1)}`,
    );
  }

  return text;
}

/**
 * Slug kebab-case da un titolo ("Galleria d'immagini" → "galleria-d-immagini").
 *
 * @param {string} text
 * @returns {string}
 */
export function toSlug(text) {
  return stripAccents(text)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/^[0-9-]+/, '');
}

/**
 * Nome di campo snake_case da un'etichetta ("Testo del pulsante" → "testo_del_pulsante").
 *
 * @param {string} text
 * @returns {string}
 */
export function toFieldName(text) {
  return stripAccents(text)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .replace(/^[0-9_]+/, '');
}

const capitalize = (text) => {
  const value = String(text).trim();

  return value.charAt(0).toUpperCase() + value.slice(1);
};

/**
 * Divide una stringa sulle virgole fuori da parentesi tonde e quadre.
 *
 * @param {string} text
 * @returns {string[]}
 */
function splitTopLevel(text) {
  const parts = [];
  let depth = 0;
  let current = '';

  for (const char of text) {
    if (char === '[' || char === '(') {
      depth++;
    } else if (char === ']' || char === ')') {
      depth--;
    }

    if (char === ',' && depth === 0) {
      parts.push(current);
      current = '';
    } else {
      current += char;
    }
  }

  parts.push(current);

  return parts.map((part) => part.trim()).filter(Boolean);
}

/**
 * Scelte di un select: "Chiaro|Scuro" o "chiaro=Chiaro|scuro=Scuro".
 *
 * @param {string} text
 * @returns {{ value: string, label: string }[]}
 */
export function parseChoices(text) {
  return String(text)
    .split(/[|,]/)
    .map((choice) => choice.trim())
    .filter(Boolean)
    .map((choice) => {
      const index = choice.indexOf('=');

      if (index === -1) {
        return { value: toFieldName(choice), label: capitalize(choice) };
      }

      return {
        value: choice.slice(0, index).trim(),
        label: capitalize(choice.slice(index + 1)),
      };
    });
}

/**
 * Normalizza il tipo di un campo (alias compresi); null se sconosciuto.
 *
 * @param {string} type
 * @returns {string|null}
 */
export function normalizeType(type) {
  const value = String(type).trim().toLowerCase();

  if (Object.hasOwn(FIELD_TYPES, value)) {
    return value;
  }

  return TYPE_ALIASES[value] ?? null;
}

/**
 * Legge il formato compatto di --fields.
 *
 * Campi separati da virgole; ogni campo è
 *   <Etichetta>[=<name>]:<tipo>[!][(<scelte>)|[<sotto-campi>]]
 * - l'etichetta è in italiano; il name, se omesso, è l'etichetta in snake_case;
 * - `!` (subito dopo il tipo o dopo le parentesi) rende il campo obbligatorio;
 * - select: scelte fra tonde separate da `|`, come "Etichetta" o "valore=Etichetta";
 * - repeater: sotto-campi fra quadre, con lo stesso formato e i tipi di SUB_FIELD_TYPES.
 *
 * Esempio: "Titolo=title:text!,Immagine:image,cta:link,Stile:select(Chiaro|Scuro),
 * Voci:repeater[Testo:text!,Icona:image]".
 *
 * Restituisce i campi senza validarli (vedi validateFields()).
 *
 * @param {string} spec
 * @returns {Field[]}
 */
export function parseFieldsSpec(spec) {
  return splitTopLevel(String(spec ?? '')).map(parseFieldToken);
}

/**
 * @typedef {object} Field
 * @property {string} label
 * @property {string} name
 * @property {string} type
 * @property {boolean} required
 * @property {string} instructions
 * @property {{ value: string, label: string }[]} [choices]
 * @property {Field[]} [subFields]
 */

function parseFieldToken(token) {
  const match = token.match(
    /^(?<label>[^:=[\]()]+?)\s*(?:=\s*(?<name>[^:[\]()]+?))?\s*:\s*(?<type>[a-z_-]+)\s*(?<required>!)?\s*(?:\((?<choices>[^)]*)\)|\[(?<subs>.*)\])?\s*(?<requiredAfter>!)?$/i,
  );

  if (!match) {
    throw new Error(
      `Campo "${token}" non valido: usa <Etichetta>[=<name>]:<tipo>[!], es. "Titolo=title:text!".`,
    );
  }

  const { label, name, type, required, choices, subs, requiredAfter } = match.groups;
  const normalized = normalizeType(type);

  if (!normalized) {
    throw new Error(
      `Campo "${token}": tipo "${type}" sconosciuto (tipi: ${Object.keys(FIELD_TYPES).join(', ')}).`,
    );
  }

  /** @type {Field} */
  const field = {
    label: capitalize(label),
    name: name ? name.trim() : toFieldName(label),
    type: normalized,
    required: Boolean(required || requiredAfter),
    instructions: '',
  };

  if (normalized === 'select') {
    field.choices = parseChoices(choices ?? '');
  } else if (choices !== undefined) {
    throw new Error(`Campo "${token}": le scelte fra parentesi valgono solo per select.`);
  }

  if (normalized === 'repeater') {
    field.subFields = parseFieldsSpec(subs ?? '');
  } else if (subs !== undefined) {
    throw new Error(`Campo "${token}": i sotto-campi fra quadre valgono solo per repeater.`);
  }

  return field;
}

/**
 * Valida un nome di campo; restituisce il messaggio d'errore o undefined.
 *
 * @param {string} name
 * @param {{ taken?: Iterable<string>, topLevel?: boolean }} [options]
 * @returns {string|undefined}
 */
export function validateFieldName(name, { taken = [], topLevel = true } = {}) {
  if (!FIELD_NAME_PATTERN.test(name ?? '')) {
    return `Nome "${name}" non valido: snake_case, minuscole, cifre e "_", inizia con una lettera.`;
  }

  if (topLevel && RESERVED_NAMES.has(name)) {
    return `Nome "${name}" riservato (gruppo condiviso, BlockRenderer::data() o variabili della view).`;
  }

  if ([...taken].includes(name)) {
    return `Nome "${name}" già usato da un altro campo.`;
  }

  return undefined;
}

/**
 * Valida i campi (nomi univoci per livello, tipi, scelte, sotto-campi).
 *
 * @param {Field[]} fields
 * @returns {string[]} Errori (vuoto se tutto è valido).
 */
export function validateFields(fields, { topLevel = true } = {}) {
  const errors = [];
  const names = [];

  for (const field of fields) {
    const where = `Campo "${field.label}"`;

    if (!field.label?.trim()) {
      errors.push(`Campo "${field.name}": etichetta mancante.`);
    }

    for (const [value, what] of [
      [field.label, "l'etichetta"],
      [field.instructions, 'le istruzioni'],
      ...(field.choices ?? []).map((choice) => [choice.label, `la scelta "${choice.value}"`]),
    ]) {
      const textError = validateText(value, what);

      if (textError) {
        errors.push(`Campo "${commentText(field.label)}": ${textError}`);
      }
    }

    const nameError = validateFieldName(field.name, { taken: names, topLevel });

    if (nameError) {
      errors.push(`${where}: ${nameError}`);
    }

    names.push(field.name);

    if (!topLevel && !SUB_FIELD_TYPES.includes(field.type)) {
      errors.push(
        `${where}: tipo "${field.type}" non ammesso in un repeater (tipi: ${SUB_FIELD_TYPES.join(', ')}).`,
      );
    } else if (!Object.hasOwn(FIELD_TYPES, field.type)) {
      errors.push(`${where}: tipo "${field.type}" sconosciuto.`);
    }

    if (field.name === 'title' && field.type !== 'text' && topLevel) {
      errors.push(`${where}: "title" è il titolo del blocco e deve essere di tipo text.`);
    }

    if (field.type === 'select') {
      const values = (field.choices ?? []).map((choice) => choice.value);

      if (values.length === 0) {
        errors.push(`${where}: un select ha bisogno di almeno una scelta.`);
      }

      if (values.some((value) => !/^[a-z0-9_-]+$/.test(value))) {
        errors.push(`${where}: valori delle scelte non validi (minuscole, cifre, "_", "-").`);
      }

      if (new Set(values).size !== values.length) {
        errors.push(`${where}: valori delle scelte duplicati.`);
      }
    }

    if (field.type === 'repeater') {
      if (!field.subFields?.length) {
        errors.push(`${where}: un repeater ha bisogno di almeno un sotto-campo.`);
      } else {
        errors.push(...validateFields(field.subFields, { topLevel: false }));
      }
    }
  }

  return errors;
}

/**
 * Categorie registrate dal tema in app/blocks.php (filtro block_categories_all).
 *
 * @param {string} [dir] Cartella del tema.
 * @returns {{ slug: string, title: string }[]}
 */
export function readCategories(dir = themeDir) {
  const source = fs.readFileSync(path.join(dir, 'app/blocks.php'), 'utf8');
  const start = source.indexOf('block_categories_all');
  const section = start === -1 ? '' : source.slice(start, source.indexOf('});', start));

  return [
    ...section.matchAll(/'slug'\s*=>\s*'([^']+)',\s*'title'\s*=>\s*__\(\s*'((?:[^'\\]|\\.)*)'/g),
  ].map(([, slug, title]) => ({ slug, title: title.replace(/\\'/g, "'") }));
}

const readJson = (file) => JSON.parse(fs.readFileSync(file, 'utf8'));

const listGroups = (dir) => {
  const acfDir = path.join(dir, 'acf-json');

  return fs.existsSync(acfDir)
    ? fs
        .readdirSync(acfDir)
        .filter((file) => file.endsWith('.json'))
        .sort()
        .map((file) => ({ file, group: readJson(path.join(acfDir, file)) }))
    : [];
};

/**
 * Slug già in uso: cartelle dei blocchi e blocchi acf/<slug> nelle location dei field group.
 *
 * @param {string} [dir] Cartella del tema.
 * @returns {Set<string>}
 */
export function existingSlugs(dir = themeDir) {
  const slugs = new Set();
  const blocksDir = path.join(dir, 'resources/views/blocks');

  if (fs.existsSync(blocksDir)) {
    for (const entry of fs.readdirSync(blocksDir, { withFileTypes: true })) {
      if (entry.isDirectory()) {
        slugs.add(entry.name);
      }
    }
  }

  for (const { group } of listGroups(dir)) {
    for (const rule of (group.location ?? []).flat()) {
      if (rule?.param === 'block' && String(rule.value).startsWith('acf/')) {
        slugs.add(String(rule.value).slice(4));
      }
    }
  }

  return slugs;
}

/**
 * Valida uno slug di blocco; restituisce il messaggio d'errore o undefined.
 *
 * @param {string} slug
 * @param {Iterable<string>} [taken]
 * @returns {string|undefined}
 */
export function validateSlug(slug, taken = existingSlugs()) {
  if (!SLUG_PATTERN.test(slug ?? '')) {
    return `Slug "${slug}" non valido: kebab-case, minuscole, cifre e "-", inizia con una lettera.`;
  }

  if (new Set(taken).has(slug)) {
    return `Lo slug "${slug}" è già usato da un blocco o da un field group: scegline un altro.`;
  }

  return undefined;
}

/** Chiavi ACF (group_/field_) già presenti in acf-json. */
export function existingKeys(dir = themeDir) {
  const keys = new Set();

  const walk = (value) => {
    if (Array.isArray(value)) {
      value.forEach(walk);
    } else if (value && typeof value === 'object') {
      if (typeof value.key === 'string') {
        keys.add(value.key);
      }

      Object.values(value).forEach(walk);
    }
  };

  listGroups(dir).forEach(({ group }) => walk(group));

  return keys;
}

/**
 * Generatore di chiavi ACF nel formato di uniqid() di PHP (13 cifre esadecimali:
 * 8 dei secondi, 5 della parte variabile), univoche rispetto a `taken` e fra loro.
 *
 * @param {Iterable<string>} [taken]
 * @param {{ now?: () => number, random?: () => string }} [options]
 * @returns {(prefix: 'group' | 'field') => string}
 */
export function createKeyFactory(taken = [], { now = Date.now, random } = {}) {
  const used = new Set(taken);
  const variable = random ?? (() => crypto.randomBytes(3).toString('hex').slice(0, 5));

  return (prefix) => {
    for (;;) {
      const seconds = Math.floor(now() / 1000)
        .toString(16)
        .padStart(8, '0');
      const key = `${prefix}_${seconds}${variable()}`;

      if (!used.has(key)) {
        used.add(key);

        return key;
      }
    }
  };
}

/** Contenuto dell'<svg> normalizzato, per confrontare icone scritte in modi diversi. */
const normalizeSvg = (svg) =>
  String(svg)
    .replace(/^[\s\S]*?<svg\b[^>]*>/, '')
    .replace(/<\/svg>\s*$/, '')
    .replace(/'/g, '"')
    .replace(/\s+/g, ' ')
    .replace(/\s*\/>/g, '/>')
    .trim();

let fillIndex;

/**
 * Slug Phosphor di un SVG con il peso fill (null se non corrisponde a nessuna icona).
 *
 * L'icona di un block.json è l'SVG inline (l'unico formato di "icon" che ACF
 * accetta oltre ai dashicons) e WordPress non ammette chiavi personalizzate
 * utili lì: lo slug si ricava quindi confrontando l'SVG con quelli del
 * pacchetto, senza metadati in più nel block.json né nel manifest.
 *
 * @param {string} svg
 * @returns {string|null}
 */
export function identifyIcon(svg) {
  if (!fillIndex) {
    fillIndex = new Map();

    for (const name of listIconNames()) {
      const file = path.join(assetsDir, ICON_WEIGHT, `${name}-${ICON_WEIGHT}.svg`);

      if (fs.existsSync(file)) {
        fillIndex.set(normalizeSvg(fs.readFileSync(file, 'utf8')), name);
      }
    }
  }

  return fillIndex.get(normalizeSvg(svg)) ?? null;
}

/**
 * Icone Phosphor già usate dai block.json del tema, con i blocchi che le usano.
 *
 * @param {string} [dir] Cartella del tema.
 * @returns {Map<string, string[]>} Slug dell'icona => nomi dei blocchi (acf/<slug>).
 */
export function iconUsers(dir = themeDir) {
  const users = new Map();
  const blocksDir = path.join(dir, 'resources/views/blocks');

  if (!fs.existsSync(blocksDir)) {
    return users;
  }

  for (const entry of fs.readdirSync(blocksDir, { withFileTypes: true })) {
    const file = path.join(blocksDir, entry.name, 'block.json');

    if (entry.isDirectory() && fs.existsSync(file)) {
      const json = readJson(file);
      const name = typeof json.icon === 'string' ? identifyIcon(json.icon) : null;

      if (name) {
        users.set(name, [...(users.get(name) ?? []), json.name ?? `acf/${entry.name}`]);
      }
    }
  }

  return users;
}

/**
 * Icone Phosphor già usate dai block.json del tema.
 *
 * @param {string} [dir] Cartella del tema.
 * @returns {Set<string>}
 */
export const usedIcons = (dir = themeDir) => new Set(iconUsers(dir).keys());

/**
 * Avviso se l'icona è già usata da un altro blocco (undefined altrimenti).
 *
 * @param {string} name
 * @param {string} [dir] Cartella del tema.
 * @returns {string|undefined}
 */
export function iconWarning(name, dir = themeDir) {
  const blocks = name ? iconUsers(dir).get(name) : undefined;

  return blocks?.length
    ? `L'icona "${name}" è già usata da ${blocks.join(', ')}: va bene, ma i blocchi saranno meno riconoscibili nell'inserter.`
    : undefined;
}

/**
 * Valida uno slug di icona Phosphor (peso fill); vuoto = icona casuale.
 *
 * @param {string} name
 * @returns {string|undefined}
 */
export function validateIcon(name) {
  if (!name) {
    return undefined;
  }

  if (iconExists(name, ICON_WEIGHT)) {
    return undefined;
  }

  const suggestions = suggestIcons(name);

  return `Icona Phosphor "${name}" inesistente.${suggestions.length ? ` Forse: ${suggestions.join(', ')}.` : ''}`;
}

/* -------------------------------------------------------------------------- */
/* Generazione dei file                                                       */
/* -------------------------------------------------------------------------- */

/** Campi del field group comuni a ogni tipo, come li salva ACF 6.8. */
const baseField = (key, field) => ({
  key,
  label: field.label,
  name: field.name,
  'aria-label': '',
  type: field.type,
  instructions: field.instructions ?? '',
  required: field.required ? 1 : 0,
  conditional_logic: 0,
  wrapper: { width: '', class: '', id: '' },
});

/** Impostazioni specifiche per tipo (default di ACF 6.8, return format usati dalla view). */
function typeSettings(field, key, nextKey) {
  switch (field.type) {
    case 'text':
      return {
        default_value: '',
        maxlength: '',
        allow_in_bindings: 0,
        placeholder: '',
        prepend: '',
        append: '',
      };
    case 'textarea':
      return {
        default_value: '',
        maxlength: '',
        allow_in_bindings: 0,
        rows: '',
        placeholder: '',
        new_lines: '',
      };
    case 'wysiwyg':
      return {
        default_value: '',
        allow_in_bindings: 0,
        tabs: 'all',
        toolbar: 'full',
        media_upload: 0,
        delay: 0,
      };
    case 'image':
      return {
        return_format: 'id',
        library: 'all',
        min_width: '',
        min_height: '',
        min_size: '',
        max_width: '',
        max_height: '',
        max_size: '',
        mime_types: '',
        allow_in_bindings: 0,
        preview_size: 'medium',
      };
    case 'link':
      return { return_format: 'array', allow_in_bindings: 0 };
    case 'true_false':
      return {
        message: '',
        default_value: 0,
        allow_in_bindings: 0,
        ui: 1,
        ui_on_text: '',
        ui_off_text: '',
      };
    case 'select':
      return {
        choices: Object.fromEntries(field.choices.map(({ value, label }) => [value, label])),
        default_value: field.choices[0]?.value ?? '',
        return_format: 'value',
        multiple: 0,
        allow_null: 0,
        allow_in_bindings: 0,
        ui: 0,
        ajax: 0,
        placeholder: '',
        create_options: 0,
        save_options: 0,
      };
    case 'repeater': {
      const wide = field.subFields.some((sub) => ['textarea', 'wysiwyg'].includes(sub.type));

      return {
        layout: wide || field.subFields.length > 3 ? 'block' : 'table',
        pagination: 0,
        min: 0,
        max: 0,
        collapsed: '',
        button_label: 'Aggiungi voce',
        rows_per_page: 20,
        sub_fields: field.subFields.map((sub) => ({
          ...acfField(sub, nextKey('field'), nextKey),
          parent_repeater: key,
        })),
      };
    }
    default:
      throw new Error(`Tipo "${field.type}" non gestito.`);
  }
}

function acfField(field, key, nextKey) {
  return { ...baseField(key, field), ...typeSettings(field, key, nextKey) };
}

/** Il campo "Formattazione" del titolo, come in "Blocco: Solo testo" (chiave nuova). */
const titleFormattingField = (key) => ({
  key,
  label: 'Formattazione',
  name: 'title_formatting',
  'aria-label': '',
  type: 'button_group',
  instructions: 'Nella pagina deve essere presente solamente un H1 per fini SEO.',
  required: 0,
  conditional_logic: 0,
  wrapper: { width: '', class: '', id: '' },
  choices: { h1: 'H1', h2: 'H2', h3: 'H3', h4: 'H4', h5: 'H5', h6: 'H6', p: 'Testo' },
  default_value: 'h2',
  return_format: 'value',
  allow_null: 0,
  allow_in_bindings: 0,
  layout: 'horizontal',
});

/**
 * Field group del blocco (formato del local JSON di ACF).
 *
 * Un campo "title" (text) riceve subito dopo il campo "title_formatting", letto
 * da BlockRenderer::data() per il tag del titolo. Spaziature e colori arrivano
 * dal gruppo condiviso "Impostazioni blocco" e non vengono duplicati.
 *
 * @param {{ slug: string, title: string, fields: Field[] }} block
 * @param {(prefix: string) => string} nextKey
 * @param {number} modified Timestamp Unix.
 */
export function buildFieldGroup({ slug, title, fields }, nextKey, modified) {
  const groupKey = nextKey('group');
  const acfFields = [];

  for (const field of fields) {
    acfFields.push(acfField(field, nextKey('field'), nextKey));

    if (field.name === 'title') {
      acfFields.push(titleFormattingField(nextKey('field')));
    }
  }

  return {
    key: groupKey,
    title: `Blocco: ${title}`,
    fields: acfFields,
    location: [[{ param: 'block', operator: '==', value: `acf/${slug}` }]],
    menu_order: 0,
    position: 'normal',
    style: 'default',
    label_placement: 'top',
    instruction_placement: 'label',
    hide_on_screen: '',
    active: true,
    description: `Campi del blocco ${title} (acf/${slug}).`,
    show_in_rest: 0,
    display_title: '',
    allow_ai_access: false,
    ai_description: '',
    modified,
  };
}

/** JSON come lo scrive ACF (acf_json_encode: 4 spazi, "/" escapato, unicode in chiaro). */
export const toAcfJson = (value) => `${JSON.stringify(value, null, 4).replace(/\//g, '\\/')}\n`;

/**
 * Metadati del block.json.
 *
 * @param {{ slug: string, title: string, description: string, category: string, icon: string, keywords: string[] }} block
 */
export function buildBlockJson({ slug, title, description, category, icon, keywords }) {
  return {
    $schema: 'https://schemas.wp.org/trunk/block.json',
    apiVersion: 3,
    name: `acf/${slug}`,
    title,
    description,
    category,
    textdomain: 'sage',
    icon: getIconSvg(icon, ICON_WEIGHT),
    keywords,
    supports: { anchor: true, align: false },
    acf: { blockVersion: 3, renderCallback: 'App\\Blocks\\BlockRenderer::render' },
  };
}

const phpString = (value) => `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;

/**
 * Espressione PHP che normalizza il valore di un campo (senza effetti collaterali).
 *
 * `value` è l'accesso al valore (`$titolo`, `$row['testo']`), `check` la sua
 * versione sicura per una chiave che può mancare (`$row['testo'] ?? null`).
 */
function readExpression(field, value, check = value) {
  switch (field.type) {
    case 'text':
    case 'textarea':
      return `is_string(${check}) ? trim(${value}) : ''`;
    case 'wysiwyg':
      return `is_string(${check}) ? ${value} : ''`;
    case 'image':
      return `is_numeric(${check}) ? (int) ${value} : 0`;
    case 'link':
      return `$to_link(${check})`;
    case 'true_false':
      return `! empty(${value})`;
    case 'select': {
      const values = field.choices.map(({ value: choice }) => phpString(choice)).join(', ');

      return `in_array(${check}, [${values}], true) ? ${value} : ${phpString(field.choices[0].value)}`;
    }
    default:
      throw new Error(`Tipo "${field.type}" non gestito.`);
  }
}

/** Tipo PHPDoc della variabile di un campo nel modulo. */
function phpDocType(field) {
  switch (field.type) {
    case 'image':
      return 'int';
    case 'link':
      return 'array{url: string, target: string, label: string}|null';
    case 'true_false':
      return 'bool';
    case 'select':
      return field.choices.map(({ value }) => phpString(value)).join('|');
    case 'repeater':
      return `list<array{${field.subFields.map((sub) => `${sub.name}: ${phpDocType(sub)}`).join(', ')}}>`;
    default:
      return 'string';
  }
}

const fieldNote = (field) => {
  const label = commentText(field.label);

  switch (field.type) {
    case 'image':
      return `ID dell'immagine "${label}" (0 se assente)`;
    case 'link':
      return `Link "${label}" (null se l'URL non è valido)`;
    case 'repeater':
      return `Righe di "${label}" (le righe vuote sono scartate)`;
    case 'wysiwyg':
      return `HTML di "${label}" (filtrato da WordPress)`;
    default:
      return `Campo "${label}"`;
  }
};

const usesType = (fields, type) =>
  fields.some(
    (field) => field.type === type || (field.subFields ?? []).some((sub) => sub.type === type),
  );

/**
 * View del blocco (blocks/<slug>/<slug>.blade.php): prepara i dati, nessun markup.
 *
 * @param {{ slug: string, title: string, fields: Field[] }} block
 */
export function buildView({ slug, title: rawTitle, fields }) {
  const php = [];
  const title = commentText(rawTitle);

  php.push(
    '  /**',
    `   * Block Name: ${title}`,
    '   *',
    `   * Prepara i dati del blocco ${title}; il markup sta in modules/${slug}.`,
    '   */',
    '',
    '  $data = \\App\\Blocks\\BlockRenderer::data($block);',
    "  $settings = $data['settings'];",
    "  $contents = $data['contents'];",
    "  $spacings = $data['spacings'];",
    '',
    "  $section_style = implode(' ', array_filter([",
    "    $settings['background_color'] ? 'background-color: '.$settings['background_color'].';' : null,",
    "    $settings['text_color'] ? 'color: '.$settings['text_color'].';' : null,",
    '  ]));',
  );

  if (usesType(fields, 'link')) {
    php.push(
      '',
      "  // Link ACF (url, title, target) → dati per <x-ui.button>; null se l'URL non è valido.",
      '  $to_link = static function (mixed $link): ?array {',
      "    $url = is_array($link) && ! empty($link['url']) ? sanitize_url($link['url']) : '';",
      '',
      '    if (! $url) {',
      '      return null;',
      '    }',
      '',
      '    return [',
      "      'url' => $url,",
      "      'target' => in_array($link['target'] ?? '', ['_self', '_blank'], true) ? $link['target'] : '_self',",
      "      'label' => ! empty($link['title']) ? $link['title'] : $link['url'],",
      '    ];',
      '  };',
    );
  }

  for (const field of fields) {
    const variable = `$${field.name}`;

    php.push('');

    if (field.type === 'repeater') {
      php.push(
        `  // ${commentText(field.label)}: righe valide, quelle senza contenuto vengono scartate.`,
        `  ${variable} = [];`,
        `  $rows = get_field(${phpString(field.name)});`,
        '',
        '  foreach (is_array($rows) ? $rows : [] as $row) {',
        '    if (! is_array($row)) {',
        '      continue;',
        '    }',
        '',
        '    $item = [',
        ...field.subFields.map(
          (sub) =>
            `      ${phpString(sub.name)} => ${readExpression(sub, `$row[${phpString(sub.name)}]`, `$row[${phpString(sub.name)}] ?? null`)},`,
        ),
        '    ];',
        '',
        '    if (array_filter($item, fn ($value) => ! is_bool($value) && $value)) {',
        `      ${variable}[] = $item;`,
        '    }',
        '  }',
      );
    } else if (field.type === 'link') {
      php.push(`  ${variable} = $to_link(get_field(${phpString(field.name)}));`);
    } else if (field.type === 'true_false') {
      php.push(`  ${variable} = (bool) get_field(${phpString(field.name)});`);
    } else {
      php.push(
        `  ${variable} = get_field(${phpString(field.name)});`,
        `  ${variable} = ${readExpression(field, variable)};`,
      );
    }
  }

  const include = [
    "  'settings' => $settings,",
    "  'contents' => $contents,",
    "  'spacings' => $spacings,",
    "  'section_style' => $section_style,",
    ...fields.map((field) => `  ${phpString(field.name)} => $${field.name},`),
  ];

  return [
    '{{-- blade-formatter-disable --}}',
    '@php',
    ...php,
    '@endphp',
    '{{-- blade-formatter-enable --}}',
    '',
    `@include('modules.${slug}', [`,
    ...include,
    '])',
    '',
  ].join('\n');
}

/** Markup di un campo nel modulo; `value` è l'espressione Blade del valore. */
function fieldMarkup(field, value, { heading, indent }) {
  const pad = ' '.repeat(indent);

  switch (field.type) {
    case 'text':
      return heading
        ? [
            `${pad}@if (${value})`,
            `${pad}  <x-ui.title :tag="${heading.tag}" class="${heading.class}">{{ ${value} }}</x-ui.title>`,
            `${pad}@endif`,
          ]
        : [`${pad}@if (${value})`, `${pad}  <p class="mb-4">{{ ${value} }}</p>`, `${pad}@endif`];
    case 'textarea':
      return [
        `${pad}@if (${value})`,
        `${pad}  <p class="mb-4">{!! nl2br(e(${value})) !!}</p>`,
        `${pad}@endif`,
      ];
    case 'wysiwyg':
      return [
        `${pad}@if (${value})`,
        `${pad}  <div class="mb-4">`,
        `${pad}    {!! ${value} !!}`,
        `${pad}  </div>`,
        `${pad}@endif`,
      ];
    case 'image':
      return [
        `${pad}@if (${value})`,
        `${pad}  <x-ui.image :id="${value}" class="mb-6 h-auto w-full" />`,
        `${pad}@endif`,
      ];
    case 'link':
      return [
        `${pad}@if (${value})`,
        `${pad}  <div class="mt-6 flex flex-wrap gap-4">`,
        `${pad}    <x-ui.button :link="${value}['url']" :target="${value}['target']" :label="${value}['label']">`,
        `${pad}      {{ ${value}['label'] }}`,
        `${pad}    </x-ui.button>`,
        `${pad}  </div>`,
        `${pad}@endif`,
      ];
    default:
      return [];
  }
}

/**
 * Modulo (modules/<slug>.blade.php): il markup, con i dati preparati dalla view.
 *
 * Il campo "title" diventa il titolo del blocco (<x-ui.title :tag="title_tag">);
 * nel repeater il primo sotto-campo text è il titolo della voce (next_heading,
 * oppure title_tag se il blocco non ha un campo "title").
 * true/false e select non hanno markup: sono elencati in un commento, da usare
 * per le varianti. Con view.js la <section> ha data-block="<slug>", usato dallo script.
 *
 * @param {{ slug: string, title: string, fields: Field[] }} block
 */
export function buildModule({ slug, title: rawTitle, fields, viewJs = false }) {
  const title = commentText(rawTitle);
  const vars = [
    '  @var array $settings Impostazioni del blocco (id, colori).',
    '  @var array $contents Dati comuni del blocco (title_tag, next_heading).',
    '  @var array $spacings Classi di padding e margin.',
    "  @var string $section_style Stile inline della section ('' se assente).",
    ...fields.map((field) => `  @var ${phpDocType(field)} $${field.name} ${fieldNote(field)}.`),
  ];

  const body = [];
  const variants = fields.filter((field) => ['true_false', 'select'].includes(field.type));

  if (variants.length) {
    body.push(
      `    {{-- Varianti: ${variants.map((field) => `$${field.name} (${commentText(field.label)})`).join(', ')}. --}}`,
    );
  }

  // Senza un titolo del blocco le voci sono i titoli di primo livello della
  // sezione: usano title_tag, non next_heading, per non saltare un livello.
  const itemTag = fields.some((field) => field.name === 'title')
    ? "$contents['next_heading']"
    : "$contents['title_tag']";

  for (const field of fields) {
    const value = `$${field.name}`;
    let lines;

    if (field.type === 'repeater') {
      const titleSub = field.subFields.find((sub) => sub.type === 'text');
      const items = field.subFields.flatMap((sub) =>
        fieldMarkup(sub, `$item['${sub.name}']`, {
          indent: 10,
          heading: sub === titleSub ? { tag: itemTag, class: 'mb-2' } : undefined,
        }),
      );
      const subVariants = field.subFields.filter((sub) => sub.type === 'true_false');

      lines = [
        `    @if (${value})`,
        '      <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">',
        `        @foreach (${value} as $item)`,
        '          <li>',
        ...(subVariants.length
          ? [
              `            {{-- Varianti: ${subVariants.map((sub) => `$item['${sub.name}'] (${commentText(sub.label)})`).join(', ')}. --}}`,
            ]
          : []),
        ...items.map((line) => `  ${line}`),
        '          </li>',
        '        @endforeach',
        '      </ul>',
        '    @endif',
      ];
    } else {
      lines = fieldMarkup(field, value, {
        indent: 4,
        heading:
          field.name === 'title' ? { tag: "$contents['title_tag']", class: 'mb-6' } : undefined,
      });
    }

    if (lines.length) {
      if (body.length) {
        body.push('');
      }

      body.push(...lines);
    }
  }

  return [
    '{{--',
    `  Modulo: ${title}`,
    '',
    `  Markup del blocco ${title}. I dati sono preparati in blocks/${slug}.`,
    '',
    ...vars,
    '--}}',
    '',
    '<section',
    `  id="{{ $settings['id'] }}"`,
    ...(viewJs ? [`  data-block="${slug}"`] : []),
    '  {{-- blade-formatter-disable --}}',
    '  @class(array_filter([',
    "    'w-full overflow-x-clip',",
    "    $spacings['padding'],",
    "    $spacings['margin'],",
    '  ]))',
    '  {{-- blade-formatter-enable --}}',
    '  @if ($section_style) style="{{ $section_style }}" @endif>',
    '  <div class="container mx-auto px-6 3xl:px-0">',
    ...body,
    '  </div>',
    '</section>',
    '',
  ].join('\n');
}

export const buildViewJs = ({ slug, title }) => `/**
 * Script del blocco ${commentText(title)} (acf/${slug}): script module caricato solo nelle
 * pagine che mostrano il blocco (App\\Blocks\\BlockAssets), una volta per pagina
 * anche con più istanze. Non importare CSS da qui: usa style.css.
 */

for (const section of document.querySelectorAll('[data-block="${slug}"]')) {
  // Comportamento di ogni istanza: section è la <section data-block="${slug}"> del modulo.
  void section;
}
`;

export const buildStyleCss = ({ slug, title }) => `/*
 * Stili del blocco ${commentText(title)} (acf/${slug}): caricati solo nelle pagine che
 * mostrano il blocco (App\\Blocks\\BlockAssets). Le utility Tailwind sono già in
 * app.css: usa questo file per ciò che non riescono a esprimere.
 */

@reference "../../../css/app.css";
`;

/**
 * Tutti i file del nuovo blocco (percorsi relativi alla cartella del tema).
 *
 * @param {BlockAnswers} block
 * @param {{ dir?: string, keys?: Iterable<string>, now?: () => number }} [options]
 * @returns {{ path: string, content: string, format?: 'prettier' | 'blade' }[]}
 */
export function buildFiles(block, { dir = themeDir, keys, now = Date.now } = {}) {
  const nextKey = createKeyFactory(keys ?? existingKeys(dir), { now });
  const group = buildFieldGroup(block, nextKey, Math.floor(now() / 1000));
  const blockDir = `resources/views/blocks/${block.slug}`;

  const files = [
    {
      path: `${blockDir}/block.json`,
      content: `${JSON.stringify(buildBlockJson(block), null, 2)}\n`,
      format: 'prettier',
    },
    { path: `${blockDir}/${block.slug}.blade.php`, content: buildView(block), format: 'blade' },
    {
      path: `resources/views/modules/${block.slug}.blade.php`,
      content: buildModule(block),
      format: 'blade',
    },
    { path: `acf-json/${group.key}.json`, content: toAcfJson(group) },
  ];

  if (block.viewJs) {
    files.push({ path: `${blockDir}/view.js`, content: buildViewJs(block), format: 'prettier' });
  }

  if (block.style) {
    files.push({
      path: `${blockDir}/style.css`,
      content: buildStyleCss(block),
      format: 'prettier',
    });
  }

  return files;
}

/**
 * @typedef {object} BlockAnswers
 * @property {string} title
 * @property {string} slug
 * @property {string} description
 * @property {string} category
 * @property {string} icon
 * @property {string[]} keywords
 * @property {Field[]} fields
 * @property {boolean} viewJs
 * @property {boolean} style
 */

/**
 * Formatta i file con Prettier e blade-formatter e la configurazione del tema,
 * così l'output (anche con --dry-run) passa `npm run lint`.
 *
 * @param {{ path: string, content: string, format?: string }[]} files
 * @param {string} [dir]
 */
export async function formatFiles(files, dir = themeDir) {
  const prettier = await import('prettier');
  const { BladeFormatter } = await import('blade-formatter');
  const bladeOptions = JSON.parse(
    fs.readFileSync(path.join(dir, '.bladeformatterrc.json'), 'utf8'),
  );

  const format = async (file) => {
    if (file.format === 'prettier') {
      const filepath = path.join(dir, file.path);
      const options = (await prettier.resolveConfig(filepath)) ?? {};

      return { ...file, content: await prettier.format(file.content, { ...options, filepath }) };
    }

    if (file.format === 'blade') {
      const formatter = new BladeFormatter({ ...bladeOptions, endWithNewline: true });

      return { ...file, content: await formatter.format(file.content) };
    }

    return file;
  };

  return Promise.all(
    files.map(async (file) => {
      try {
        return await format(file);
      } catch (error) {
        // Solo la prima riga, senza colori né code frame: il messaggio resta leggibile.
        const reason = String(error?.message ?? error)
          // eslint-disable-next-line no-control-regex
          .replace(/\u001b\[[0-9;]*m/g, '')
          .split('\n')[0];

        throw new Error(
          `Formattazione di ${file.path} non riuscita (${reason}). Nessun file scritto: controlla titolo ed etichette.`,
          { cause: error },
        );
      }
    }),
  );
}

/**
 * Scrive i file senza sovrascrivere nulla (flag "wx"). Se una scrittura
 * fallisce rimuove i file e le cartelle già creati, poi lancia un errore:
 * nessun blocco a metà.
 *
 * @param {{ path: string, content: string }[]} files
 * @param {string} [dir] Cartella del tema.
 */
export function writeFiles(files, dir = themeDir) {
  const created = [];

  try {
    for (const file of files) {
      const target = path.join(dir, file.path);
      const newDir = fs.mkdirSync(path.dirname(target), { recursive: true });

      if (newDir) {
        created.push({ dir: newDir });
      }

      fs.writeFileSync(target, file.content, { flag: 'wx' });
      created.push({ file: target });
    }
  } catch (error) {
    for (const item of created.reverse()) {
      fs.rmSync(item.file ?? item.dir, { recursive: Boolean(item.dir), force: true });
    }

    throw new Error(
      `Scrittura dei file non riuscita (${error.message}): rimossi i file già creati, nessun blocco a metà.`,
      { cause: error },
    );
  }
}

/* -------------------------------------------------------------------------- */
/* Domande e flag                                                             */
/* -------------------------------------------------------------------------- */

export const CLI_OPTIONS = {
  title: { type: 'string' },
  slug: { type: 'string' },
  description: { type: 'string' },
  category: { type: 'string' },
  icon: { type: 'string' },
  keywords: { type: 'string' },
  fields: { type: 'string' },
  'view-js': { type: 'boolean' },
  style: { type: 'boolean' },
  yes: { type: 'boolean', short: 'y' },
  'dry-run': { type: 'boolean' },
  translate: { type: 'boolean', default: true },
  help: { type: 'boolean', short: 'h' },
};

export const HELP = `Crea un nuovo blocco ACF del tema (CONVENTIONS.md, "Checklist per un nuovo blocco").

Uso: npm run make:block [-- <flag>]

  --title <testo>        Titolo in italiano (es. "Card in evidenza")
  --slug <slug>          Slug kebab-case (default: dal titolo); non deve esistere
  --description <testo>  Descrizione in italiano
  --category <slug>      Categoria del tema (app/blocks.php)
  --icon <slug>          Icona Phosphor, peso fill ("" = casuale fra quelle non usate)
  --keywords <a,b>       Parole chiave in italiano (default: dal titolo)
  --fields <spec>        Campi ACF, es. "Titolo=title:text!,Immagine:image,cta:link,
                         Stile:select(Chiaro|Scuro),Voci:repeater[Testo:text!,Icona:image]"
                         (<Etichetta>[=<name>]:<tipo>[!]; "" = nessun campo)
  --view-js, --no-view-js  Crea (o no) view.js
  --style, --no-style      Crea (o no) style.css
  -y, --yes              Salta la conferma finale
  --dry-run              Mostra i file senza scriverli
  --no-translate         Non esegue npm run translate / translate:compile
  -h, --help             Questo aiuto

Tipi: ${Object.keys(FIELD_TYPES).join(', ')} (sotto-campi del repeater: ${SUB_FIELD_TYPES.join(', ')}).
Senza un terminale interattivo servono almeno --title, --description e --category.
Un valore che inizia con "-" va scritto con "=": --title="-…".`;

/**
 * Legge i flag (node:util parseArgs).
 *
 * @param {string[]} argv
 */
export function parseCliArgs(argv) {
  const { values } = parseArgs({
    args: argv,
    options: CLI_OPTIONS,
    allowNegative: true,
    strict: true,
  });

  return values;
}

/**
 * Risolve le risposte dai flag, senza domande. Restituisce le risposte note,
 * l'elenco dei valori obbligatori mancanti e gli avvisi (es. icona già usata);
 * lancia un errore per i valori non validi.
 *
 * @param {Record<string, unknown>} flags
 * @param {{ dir?: string, interactive?: boolean, random?: () => number }} [options]
 */
export function answersFromFlags(flags, { dir = themeDir, interactive = false, random } = {}) {
  const answers = {};
  const missing = [];
  const errors = [];
  const warnings = [];
  const categories = readCategories(dir);

  if (flags.title !== undefined) {
    answers.title = capitalize(flags.title);

    if (!answers.title) {
      errors.push('--title non può essere vuoto.');
    }

    const textError = validateText(answers.title, '--title');

    if (textError) {
      errors.push(textError);
    }
  } else {
    missing.push('--title');
  }

  if (flags.slug !== undefined || (answers.title && !interactive)) {
    answers.slug = flags.slug ?? toSlug(answers.title);
    const error =
      flags.slug === undefined && !answers.slug
        ? `Impossibile ricavare uno slug dal titolo "${answers.title}" (servono lettere a-z o cifre): passa --slug, es. --slug mio-blocco.`
        : validateSlug(answers.slug, existingSlugs(dir));

    if (error) {
      errors.push(error);
    }
  }

  if (flags.description !== undefined) {
    answers.description = capitalize(flags.description);

    const textError = validateText(answers.description, '--description');

    if (textError) {
      errors.push(textError);
    }
  } else {
    missing.push('--description');
  }

  if (flags.category !== undefined) {
    if (!categories.some((category) => category.slug === flags.category)) {
      errors.push(
        `Categoria "${flags.category}" inesistente: ${categories.map((category) => category.slug).join(', ')}.`,
      );
    }

    answers.category = flags.category;
  } else {
    missing.push('--category');
  }

  if (flags.icon !== undefined || !interactive) {
    const error = validateIcon(flags.icon ?? '');

    if (error) {
      errors.push(error);
    } else if (flags.icon) {
      const warning = iconWarning(flags.icon, dir);

      if (warning) {
        warnings.push(warning);
      }
    }

    answers.icon = flags.icon || randomIcon({ exclude: usedIcons(dir), random });
  }

  if (flags.keywords !== undefined || (answers.title && !interactive)) {
    answers.keywords =
      flags.keywords !== undefined ? parseKeywords(flags.keywords) : defaultKeywords(answers.title);
  }

  if (flags.fields !== undefined || !interactive) {
    try {
      answers.fields = parseFieldsSpec(flags.fields ?? '');
      errors.push(...validateFields(answers.fields));
    } catch (error) {
      errors.push(error.message);
    }
  }

  if (flags['view-js'] !== undefined || !interactive) {
    answers.viewJs = Boolean(flags['view-js']);
  }

  if (flags.style !== undefined || !interactive) {
    answers.style = Boolean(flags.style);
  }

  if (errors.length) {
    throw new Error(errors.join('\n'));
  }

  return { answers, missing, warnings };
}

export const parseKeywords = (text) => [
  ...new Set(
    String(text)
      .split(',')
      .map((word) => word.trim().toLowerCase())
      .filter(Boolean),
  ),
];

export const defaultKeywords = (title) => [
  ...new Set(
    stripAccents(String(title ?? ''))
      .toLowerCase()
      .split(/[^a-z0-9]+/)
      .filter((word) => word.length > 2),
  ),
];

/** Interrompe il wizard se l'utente annulla (Ctrl+C) una domanda. */
async function ask(prompts, promise) {
  const value = await promise;

  if (prompts.isCancel(value)) {
    prompts.cancel('Operazione annullata: nessun file scritto.');
    process.exit(1);
  }

  return value;
}

/** Domande per un campo (o un sotto-campo del repeater); null = fine elenco. */
async function askField(prompts, { taken, topLevel }) {
  const types = topLevel ? Object.keys(FIELD_TYPES) : SUB_FIELD_TYPES;
  const type = await ask(
    prompts,
    prompts.select({
      message: topLevel ? 'Aggiungi un campo ACF' : 'Aggiungi un sotto-campo',
      options: [
        { value: '', label: topLevel ? 'Nessun altro campo' : 'Nessun altro sotto-campo' },
        ...types.map((value) => ({ value, label: FIELD_TYPES[value], hint: value })),
      ],
    }),
  );

  if (!type) {
    return null;
  }

  const label = capitalize(
    await ask(
      prompts,
      prompts.text({
        message: 'Etichetta (in italiano)',
        validate: (value) =>
          value?.trim() ? validateText(value, "L'etichetta") : "L'etichetta è obbligatoria.",
      }),
    ),
  );

  const suggested = toFieldName(label);
  const name = await ask(
    prompts,
    prompts.text({
      message: 'Nome del campo (snake_case)',
      placeholder: suggested,
      defaultValue: suggested,
      initialValue: suggested,
      validate: (value) => {
        const error = validateFieldName(value || suggested, { taken, topLevel });

        if (error) {
          return error;
        }

        return topLevel && (value || suggested) === 'title' && type !== 'text'
          ? '"title" è il titolo del blocco e deve essere di tipo Testo.'
          : undefined;
      },
    }),
  );

  /** @type {Field} */
  const field = {
    label,
    name: name || suggested,
    type,
    required: await ask(
      prompts,
      prompts.confirm({ message: 'Obbligatorio?', initialValue: false }),
    ),
    instructions:
      (
        await ask(
          prompts,
          prompts.text({ message: 'Istruzioni (facoltative)', placeholder: 'Invio per saltare' }),
        )
      )?.trim() ?? '',
  };

  if (type === 'select') {
    const choices = await ask(
      prompts,
      prompts.text({
        message: 'Scelte separate da "|" (es. "Chiaro|Scuro" o "chiaro=Chiaro|scuro=Scuro")',
        validate: (value) => {
          const errors = validateFields([{ ...field, choices: parseChoices(value ?? '') }], {
            topLevel,
          }).filter((error) => !error.includes('Nome'));

          return errors[0];
        },
      }),
    );

    field.choices = parseChoices(choices);
  }

  if (type === 'repeater') {
    field.subFields = [];
    prompts.log.step(`Sotto-campi di "${label}"`);

    for (;;) {
      const sub = await askField(prompts, {
        taken: field.subFields.map((item) => item.name),
        topLevel: false,
      });

      if (!sub) {
        if (field.subFields.length) {
          break;
        }

        prompts.log.warn('Un repeater ha bisogno di almeno un sotto-campo.');
        continue;
      }

      field.subFields.push(sub);
    }
  }

  return field;
}

/**
 * Chiede i valori mancanti (solo in un terminale interattivo).
 *
 * @param {Partial<BlockAnswers>} answers
 * @param {{ dir?: string }} [options]
 * @returns {Promise<BlockAnswers>}
 */
async function askMissing(prompts, answers, { dir = themeDir } = {}) {
  const slugs = existingSlugs(dir);

  answers.title ??= capitalize(
    await ask(
      prompts,
      prompts.text({
        message: 'Titolo del blocco (in italiano)',
        placeholder: 'es. Card in evidenza',
        validate: (value) =>
          value?.trim() ? validateText(value, 'Il titolo') : 'Il titolo è obbligatorio.',
      }),
    ),
  );

  if (answers.slug === undefined) {
    const suggested = toSlug(answers.title);
    const value = await ask(
      prompts,
      prompts.text({
        message: 'Slug (kebab-case)',
        placeholder: suggested,
        defaultValue: suggested,
        initialValue: suggested,
        validate: (input) =>
          input || suggested
            ? validateSlug(input || suggested, slugs)
            : 'Lo slug non si ricava dal titolo (servono lettere a-z o cifre): scrivilo tu.',
      }),
    );

    answers.slug = value || suggested;
  }

  answers.description ??= capitalize(
    await ask(
      prompts,
      prompts.text({
        message: 'Descrizione (in italiano)',
        validate: (value) =>
          value?.trim() ? validateText(value, 'La descrizione') : 'La descrizione è obbligatoria.',
      }),
    ),
  );

  answers.category ??= await ask(
    prompts,
    prompts.select({
      message: 'Categoria',
      options: readCategories(dir).map(({ slug, title }) => ({
        value: slug,
        label: title,
        hint: slug,
      })),
      initialValue: 'static-content',
    }),
  );

  if (answers.icon === undefined) {
    const value = await ask(
      prompts,
      prompts.text({
        message: 'Icona Phosphor, peso fill (https://phosphoricons.com)',
        placeholder: 'Invio per una icona casuale non ancora usata',
        validate: (input) => validateIcon(input?.trim() ?? ''),
      }),
    );

    answers.icon = value?.trim() || randomIcon({ exclude: usedIcons(dir) });

    if (!value?.trim()) {
      prompts.log.info(`Icona casuale: ${answers.icon}`);
    } else {
      const warning = iconWarning(answers.icon, dir);

      if (warning) {
        prompts.log.warn(warning);
      }
    }
  }

  if (answers.keywords === undefined) {
    const suggested = defaultKeywords(answers.title).join(', ');
    const value = await ask(
      prompts,
      prompts.text({
        message: 'Parole chiave (in italiano, separate da virgola)',
        placeholder: suggested,
        defaultValue: suggested,
        initialValue: suggested,
      }),
    );

    answers.keywords = parseKeywords(value ?? suggested);
  }

  if (answers.fields === undefined) {
    answers.fields = [];
    prompts.log.step(
      'Campi ACF del blocco (spaziature e colori arrivano già dal gruppo condiviso). ' +
        'Un campo chiamato "title" diventa il titolo del blocco.',
    );

    for (;;) {
      const field = await askField(prompts, {
        taken: answers.fields.map((item) => item.name),
        topLevel: true,
      });

      if (!field) {
        break;
      }

      answers.fields.push(field);
    }
  }

  answers.viewJs ??= await ask(
    prompts,
    prompts.confirm({
      message: 'Serve un view.js (JS solo per questo blocco)?',
      initialValue: false,
    }),
  );

  answers.style ??= await ask(
    prompts,
    prompts.confirm({
      message: 'Serve uno style.css (CSS solo per questo blocco)?',
      initialValue: false,
    }),
  );

  return /** @type {BlockAnswers} */ (answers);
}

const describeField = (field) =>
  `${field.label} (${field.name}: ${field.type}${field.required ? ', obbligatorio' : ''}${
    field.choices ? `, scelte ${field.choices.map((choice) => choice.value).join('|')}` : ''
  })${field.subFields ? ` [${field.subFields.map(describeField).join('; ')}]` : ''}`;

/** Riepilogo delle risposte. */
export function summary(answers, files) {
  return [
    `Titolo:        ${answers.title}`,
    `Slug:          acf/${answers.slug}`,
    `Descrizione:   ${answers.description}`,
    `Categoria:     ${answers.category}`,
    `Icona:         ${answers.icon} (${ICON_WEIGHT})`,
    `Parole chiave: ${answers.keywords.join(', ') || '—'}`,
    `Campi:         ${answers.fields.length ? '' : '—'}`,
    ...answers.fields.map((field) => `  - ${describeField(field)}`),
    `Asset:         ${[answers.viewJs && 'view.js', answers.style && 'style.css'].filter(Boolean).join(', ') || '—'}`,
    '',
    'File:',
    ...files.map((file) => `  + ${file.path}`),
  ].join('\n');
}

/** Stringhe traducibili del blocco (block.json e field group). */
export function blockStrings(answers) {
  const strings = new Set([answers.title, answers.description, ...answers.keywords]);
  strings.add(`Blocco: ${answers.title}`);

  const walk = (fields) => {
    for (const field of fields) {
      strings.add(field.label);

      if (field.instructions) {
        strings.add(field.instructions);
      }

      field.choices?.forEach((choice) => strings.add(choice.label));

      if (field.type === 'repeater') {
        strings.add('Aggiungi voce');
        walk(field.subFields);
      }

      if (field.name === 'title') {
        [
          'Formattazione',
          'Nella pagina deve essere presente solamente un H1 per fini SEO.',
          'Testo',
        ].forEach((value) => strings.add(value));
      }
    }
  };

  walk(answers.fields);

  return [...strings].filter(Boolean);
}

/**
 * msgid senza traduzione in un file .po (solo voci a una riga di msgstr).
 *
 * @param {string} file
 * @returns {Set<string>}
 */
export function untranslated(file) {
  const result = new Set();

  if (!fs.existsSync(file)) {
    return result;
  }

  const unquote = (line) => JSON.parse(line.trim().replace(/^(msgid|msgstr)\s+/, ''));

  for (const entry of fs.readFileSync(file, 'utf8').split(/\n\s*\n/)) {
    const lines = entry
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line && !line.startsWith('#'));
    const idStart = lines.findIndex((line) => line.startsWith('msgid '));
    const strStart = lines.findIndex((line) => line.startsWith('msgstr '));

    if (
      idStart === -1 ||
      strStart === -1 ||
      lines.some((line) => line.startsWith('msgid_plural'))
    ) {
      continue;
    }

    const msgid = lines.slice(idStart, strStart).map(unquote).join('');
    const msgstr = lines.slice(strStart).map(unquote).join('');

    if (msgid && !msgstr) {
      result.add(msgid);
    }
  }

  return result;
}

function run(command, args, dir) {
  const result = spawnSync(command, args, { cwd: dir, encoding: 'utf8' });

  if (result.status !== 0) {
    throw new Error(
      `${command} ${args.join(' ')} non riuscito:\n${result.stderr || result.stdout || result.error?.message}`,
    );
  }

  return result.stdout;
}

/**
 * Esegue il wizard.
 *
 * @param {string[]} argv Flag (senza "node make-block.js").
 * @param {{ dir?: string, interactive?: boolean, log?: (text: string) => void }} [options]
 * @returns {Promise<{ answers: BlockAnswers, files: { path: string, content: string }[], written: boolean }>}
 */
export async function main(argv, { dir = themeDir, interactive, log = console.log } = {}) {
  const flags = parseCliArgs(argv);

  if (flags.help) {
    log(HELP);

    return { answers: null, files: [], written: false };
  }

  const tty = interactive ?? Boolean(process.stdin.isTTY && process.stdout.isTTY);
  const { answers, missing, warnings } = answersFromFlags(flags, { dir, interactive: tty });

  if (!tty && missing.length) {
    throw new Error(
      `Valori mancanti: ${missing.join(', ')}. Senza un terminale interattivo passali come flag (npm run make:block -- --help).`,
    );
  }

  const prompts = tty ? await import('@clack/prompts') : null;

  if (prompts) {
    prompts.intro(`Nuovo blocco ACF${flags['dry-run'] ? ' (dry run)' : ''}`);
  }

  for (const warning of warnings) {
    if (prompts) {
      prompts.log.warn(warning);
    } else {
      log(`Attenzione: ${warning}`);
    }
  }

  if (prompts) {
    await askMissing(prompts, answers, { dir });

    const errors = validateFields(answers.fields);

    if (errors.length) {
      throw new Error(errors.join('\n'));
    }
  }

  const files = await formatFiles(buildFiles(answers, { dir }), dir);

  if (prompts) {
    prompts.note(summary(answers, files), 'Riepilogo');
  } else {
    log(summary(answers, files));
  }

  if (flags['dry-run']) {
    for (const file of files) {
      log(`\n----- ${file.path} -----\n${file.content}`);
    }

    log('\nDry run: nessun file scritto.');

    return { answers, files, written: false };
  }

  if (!flags.yes) {
    if (!prompts) {
      throw new Error(
        'Senza un terminale interattivo aggiungi --yes per confermare (o --dry-run).',
      );
    }

    if (
      !(await ask(prompts, prompts.confirm({ message: 'Creo il blocco?', initialValue: true })))
    ) {
      prompts.cancel('Nessun file scritto.');

      return { answers, files, written: false };
    }
  }

  // Ricontrolla subito prima di scrivere: nessuna sovrascrittura.
  const slugError = validateSlug(answers.slug, existingSlugs(dir));
  const existing = files.filter((file) => fs.existsSync(path.join(dir, file.path)));

  if (slugError || existing.length) {
    throw new Error(
      slugError ?? `File già esistenti: ${existing.map((file) => file.path).join(', ')}.`,
    );
  }

  writeFiles(files, dir);

  const step = (text) => (prompts ? prompts.log.success(text) : log(text));

  step(`Creati ${files.length} file.`);

  if (dir === themeDir) {
    step(
      writeBlocksManifest()
        ? 'Manifest dei blocchi aggiornato.'
        : 'Manifest dei blocchi invariato.',
    );
    step(
      writeIconsManifest() ? 'Manifest delle icone aggiornato.' : 'Manifest delle icone invariato.',
    );
  }

  let pending = blockStrings(answers);

  if (flags.translate) {
    run('npm', ['run', '--silent', 'translate'], dir);
    run('npm', ['run', '--silent', 'translate:compile'], dir);
    step('Traduzioni aggiornate (npm run translate, translate:compile).');

    const missingEn = untranslated(path.join(dir, 'resources/lang/en_US.po'));
    pending = pending.filter((value) => missingEn.has(value));
  }

  const next = [
    pending.length
      ? `Traduci in resources/lang/en_US.po (msgstr vuoto) e poi esegui npm run translate:compile:\n${pending.map((value) => `     - ${value}`).join('\n')}`
      : 'Le stringhe del blocco hanno già una traduzione in en_US.po.',
    ...(flags.translate ? [] : ['Esegui npm run translate e npm run translate:compile.']),
    `Controlla il markup in resources/views/modules/${answers.slug}.blade.php (ordine dei campi, classi, varianti).`,
    ...(answers.viewJs || answers.style
      ? ['Riavvia npm run dev: i nuovi asset sono entry di Vite.']
      : []),
    'Il field group esiste solo in acf-json (come gli altri): in ACF → Gruppi di campi appare come "Sincronizzazione disponibile"; sincronizzarlo non è necessario.',
    'Logica non banale? Spostala in app/ con un test Pest (CONVENTIONS, checklist punto 7).',
    'La styleguide mostra i componenti: aggiornala solo se hai creato un nuovo componente.',
    'npm run lint, composer lint, poi inserisci il blocco in una pagina e verifica editor e frontend.',
    'Committa anche blocks-manifest.php, acf-json e i file in resources/lang.',
  ];

  const text = next.map((line, index) => `${index + 1}. ${line}`).join('\n');

  if (prompts) {
    prompts.note(text, 'Prossimi passi');
    prompts.outro(`Blocco acf/${answers.slug} creato.`);
  } else {
    log(`\nProssimi passi:\n${text}`);
  }

  return { answers, files, written: true };
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  main(process.argv.slice(2)).catch((error) => {
    console.error(error.message);
    process.exit(1);
  });
}
