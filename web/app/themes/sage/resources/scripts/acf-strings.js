/**
 * Estrae le stringhe traducibili dei field group ACF (acf-json/*.json)
 * in un file PHP che `wp i18n make-pot` può analizzare.
 *
 * make-pot legge PHP, JS, Blade, block.json e theme.json, non i file local
 * JSON di ACF. ACF traduce queste stringhe a runtime con
 * __($string, 'sage') (acf/settings/l10n_textdomain in app/setup.php), quindi
 * basta elencarle nel .pot: il file generato non viene mai caricato da
 * WordPress.
 *
 * Si estraggono solo le impostazioni che ACF passa davvero da acf_translate()
 * (ACF 6.x): titolo del field group; label e istruzioni dei campi;
 * scelte (select, checkbox, radio, button_group); message, ui_on_text e
 * ui_off_text (true_false, message); button_label e label dei layout
 * (repeater, flexible_content). Placeholder, prepend e append ACF non li
 * traduce.
 *
 * Uso: node resources/scripts/acf-strings.js [output]
 * Output di default: resources/lang/acf-json.php (ignorato da git).
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const themeDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const sourceDir = path.join(themeDir, 'acf-json');
const output = path.resolve(themeDir, process.argv[2] ?? 'resources/lang/acf-json.php');

/** @type {Map<string, Set<string>>} stringa → descrizioni (per i traduttori). */
const strings = new Map();

const add = (value, description) => {
  if (typeof value !== 'string' || value.trim() === '') {
    return;
  }

  if (!strings.has(value)) {
    strings.set(value, new Set());
  }

  strings.get(value).add(description);
};

const walkFields = (fields, group, parents = []) => {
  for (const field of fields ?? []) {
    const name = [...parents, field.name || field.key].join(' › ');
    const where = `ACF "${group}", ${name}`;

    add(field.label, `${where} (etichetta)`);
    add(field.instructions, `${where} (istruzioni)`);
    add(field.button_label, `${where} (etichetta del pulsante)`);
    add(field.message, `${where} (messaggio)`);
    add(field.ui_on_text, `${where} (testo dello stato attivo)`);
    add(field.ui_off_text, `${where} (testo dello stato disattivo)`);

    if (field.choices && typeof field.choices === 'object') {
      for (const [key, label] of Object.entries(field.choices)) {
        add(label, `${where} (scelta "${key}")`);
      }
    }

    walkFields(field.sub_fields, group, [...parents, field.name]);

    for (const layout of Object.values(field.layouts ?? {})) {
      add(layout.label, `${where} (layout "${layout.name}")`);
      walkFields(layout.sub_fields, group, [...parents, field.name, layout.name]);
    }
  }
};

const files = fs.existsSync(sourceDir)
  ? fs
      .readdirSync(sourceDir)
      .filter((file) => file.endsWith('.json'))
      .sort()
  : [];

for (const file of files) {
  const group = JSON.parse(fs.readFileSync(path.join(sourceDir, file), 'utf8'));

  // Solo i field group: post type, tassonomie e options page hanno altre chiavi.
  if (!Array.isArray(group.fields)) {
    continue;
  }

  add(group.title, `ACF "${group.title}" (titolo del field group, ${file})`);
  walkFields(group.fields, group.title);
}

const php = (value) => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
const comment = (value) => value.replace(/\*\//g, '* /');

const lines = [
  '<?php',
  '',
  '/**',
  ' * Stringhe traducibili dei field group ACF (acf-json/*.json).',
  ' *',
  ' * Generato da resources/scripts/acf-strings.js per `wp i18n make-pot`:',
  ' * non modificarlo, non caricarlo. Per aggiornarlo esegui `npm run translate:pot`.',
  ' */',
  '',
  'return;',
  '',
];

for (const [value, descriptions] of strings) {
  lines.push(`/* translators: ${comment([...descriptions].join('; '))} */`);
  lines.push(`__(${php(value)}, 'sage');`);
}

fs.mkdirSync(path.dirname(output), { recursive: true });
fs.writeFileSync(output, `${lines.join('\n')}\n`);

console.log(
  `acf-strings: ${strings.size} stringhe da ${files.length} file → ${path.relative(themeDir, output)}`,
);
