/**
 * Test delle sorgenti di Tailwind della styleguide (node --test, `npm run test:js`).
 *
 * resources/css/styleguide.css compila nel layer styleguide le utility di tutta
 * la pagina, quindi deve leggere le stesse sorgenti di app.css: rilevamento
 * automatico (nessun source() sull'import) e stesse righe @source, a parte
 * l'esclusione della view, che sta solo in app.css ed editor.css. Se divergono,
 * le classi base di una sorgente mancante tornano a vincere sulle varianti della
 * styleguide, senza nessun errore in build.
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, test } from 'node:test';

const cssDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../css');

/**
 * Toglie i commenti CSS, lasciando intatte le stringhe: '../../*.md' contiene "/*".
 *
 * @param {string} css
 * @returns {string}
 */
const stripComments = (css) =>
  css.replace(/('[^'\n]*'|"[^"\n]*")|\/\*[\s\S]*?\*\//g, (match, string) => string ?? '');

/** Esclusione della view della styleguide: in app.css ed editor.css, non in styleguide.css. */
export const VIEW_EXCLUSION = "@source not '../views/styleguide.blade.php';";

/**
 * Righe @source di un foglio, senza commenti, con spazi compressi e virgolette singole.
 *
 * @param {string} css
 * @returns {string[]}
 */
export function sourceLines(css) {
  return stripComments(css)
    .split('\n')
    .map((line) => line.trim().replace(/\s+/g, ' ').replace(/"/g, "'"))
    .filter((line) => line.startsWith('@source '));
}

/**
 * Indica se un @import di Tailwind limita il rilevamento automatico con source(...).
 *
 * @param {string} css
 * @returns {string[]} Gli @import con source(...).
 */
export function importsWithSource(css) {
  return stripComments(css)
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => /^@import\s+['"]tailwindcss[^'"]*['"].*\bsource\(/.test(line));
}

/**
 * Differenze fra le sorgenti di app.css, editor.css e styleguide.css, come
 * messaggi pronti per l'errore del test. Vuoto se allineate.
 *
 * @param {{ app: string, editor: string, styleguide: string }} sheets Contenuto dei tre fogli.
 * @returns {string[]}
 */
export function sourceProblems({ app, editor, styleguide }) {
  const problems = [];
  const appLines = sourceLines(app);
  const editorLines = sourceLines(editor);
  const styleguideLines = sourceLines(styleguide);
  const expected = appLines.filter((line) => line !== VIEW_EXCLUSION);

  if (!appLines.includes(VIEW_EXCLUSION)) {
    problems.push(
      `app.css: manca "${VIEW_EXCLUSION}". Rimettila, altrimenti le classi della styleguide finiscono in produzione.`,
    );
  }

  if (!editorLines.includes(VIEW_EXCLUSION)) {
    problems.push(
      `editor.css: manca "${VIEW_EXCLUSION}". Rimettila, altrimenti le classi della styleguide finiscono nell'editor.`,
    );
  }

  if (styleguideLines.includes(VIEW_EXCLUSION)) {
    problems.push(
      `styleguide.css: togli "${VIEW_EXCLUSION}". Il foglio deve compilare anche le classi della view.`,
    );
  }

  for (const line of expected) {
    if (!styleguideLines.includes(line)) {
      problems.push(
        `styleguide.css: manca "${line}", presente in app.css. Aggiungila a styleguide.css (o toglila da app.css).`,
      );
    }
  }

  for (const line of styleguideLines) {
    if (line !== VIEW_EXCLUSION && !expected.includes(line)) {
      problems.push(
        `styleguide.css: "${line}" non è in app.css. Aggiungila anche ad app.css (ed editor.css) o toglila da styleguide.css.`,
      );
    }
  }

  for (const [name, css] of [
    ['app.css', app],
    ['styleguide.css', styleguide],
  ]) {
    for (const line of importsWithSource(css)) {
      problems.push(
        `${name}: "${line}" limita il rilevamento automatico con source(...). app.css e styleguide.css devono usare lo stesso rilevamento: togli source(...) o allinea l'altro foglio.`,
      );
    }
  }

  return problems;
}

const read = (file) => fs.readFileSync(path.join(cssDir, file), 'utf8');

describe('sorgenti della styleguide', () => {
  test('styleguide.css legge le stesse sorgenti di app.css, view compresa', () => {
    const problems = sourceProblems({
      app: read('app.css'),
      editor: read('editor.css'),
      styleguide: read('styleguide.css'),
    });

    assert.deepEqual(
      problems,
      [],
      `Sorgenti di Tailwind non allineate (vedi il docblock di resources/css/styleguide.css):\n- ${problems.join('\n- ')}`,
    );
  });

  describe('rileva le divergenze', () => {
    const app = `@import 'tailwindcss' theme(static);\n@source '../views/';\n/* l'esclusione */\n@source not '../lang/';\n@source not '../../*.md';\n${VIEW_EXCLUSION}\n`;
    const editor = `@import 'tailwindcss';\n@source '../views/';\n${VIEW_EXCLUSION}\n`;
    const styleguide = `@import 'tailwindcss/utilities' layer(styleguide);\n@source "../views/";\n@source  not '../lang/';\n/* testo, non classi */\n@source not '../../*.md';\n`;

    test('nessun problema con sorgenti allineate (commenti, spazi, virgolette e /* nelle stringhe)', () => {
      assert.deepEqual(sourceProblems({ app, editor, styleguide }), []);
    });

    test('una @source di app.css mancante in styleguide.css', () => {
      const problems = sourceProblems({
        app: app + "@source '../../vendor/pacchetto/';\n",
        editor,
        styleguide,
      });

      assert.equal(problems.length, 1);
      assert.match(
        problems[0],
        /styleguide\.css: manca "@source '\.\.\/\.\.\/vendor\/pacchetto\/';"/,
      );
    });

    test('una @source solo in styleguide.css', () => {
      const problems = sourceProblems({
        app,
        editor,
        styleguide: styleguide + "@source not '../../tests/';\n",
      });

      assert.equal(problems.length, 1);
      assert.match(problems[0], /"@source not '\.\.\/\.\.\/tests\/';" non è in app\.css/);
    });

    test("l'esclusione della view in styleguide.css", () => {
      const problems = sourceProblems({ app, editor, styleguide: styleguide + VIEW_EXCLUSION });

      assert.equal(problems.length, 1);
      assert.match(problems[0], /styleguide\.css: togli/);
    });

    test("l'esclusione della view mancante in app.css o editor.css", () => {
      const problems = sourceProblems({
        app: app.replace(VIEW_EXCLUSION, ''),
        editor: editor.replace(VIEW_EXCLUSION, ''),
        styleguide,
      });

      assert.equal(problems.length, 2);
      assert.match(problems[0], /^app\.css: manca/);
      assert.match(problems[1], /^editor\.css: manca/);
    });

    test('source(...) su un import di Tailwind', () => {
      const problems = sourceProblems({
        app,
        editor,
        styleguide: styleguide.replace('layer(styleguide);', 'layer(styleguide) source(none);'),
      });

      assert.equal(problems.length, 1);
      assert.match(problems[0], /limita il rilevamento automatico/);
    });
  });
});
