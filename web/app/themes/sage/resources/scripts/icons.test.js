/**
 * Test di resources/scripts/icons.js (node --test, `npm run test:js`): scansione
 * delle view, allowlist, validazione contro il pacchetto e manifest generato.
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterEach, beforeEach, describe, test } from 'node:test';
import {
  buildIconsManifest,
  collectIcons,
  getIconInner,
  getIconSvg,
  iconExists,
  listIconNames,
  listIcons,
  parseAttributes,
  randomIcon,
  scanViews,
  suggestIcons,
  writeIconsManifest,
} from './icons.js';

let dir;

const write = (file, content) => {
  fs.mkdirSync(path.dirname(path.join(dir, file)), { recursive: true });
  fs.writeFileSync(path.join(dir, file), content);
};

const options = () => ({
  views: path.join(dir, 'views'),
  allowlist: path.join(dir, 'allowlist.json'),
});

beforeEach(() => {
  dir = fs.mkdtempSync(path.join(os.tmpdir(), 'sage-icons-'));
});

afterEach(() => {
  fs.rmSync(dir, { recursive: true, force: true });
});

describe('scansione delle view', () => {
  test('trova i tag con name statico, con il peso indicato o regular', () => {
    write(
      'views/a.blade.php',
      `<x-ui.icon name="house" />
       <x-ui.icon class="size-6" weight="bold"
         name='heart' />
       <x-ui.icon name="arrow-right"></x-ui.icon>`,
    );

    assert.deepEqual(
      scanViews(path.join(dir, 'views')).map(({ name, weight }) => `${name}:${weight}`),
      ['house:regular', 'heart:bold', 'arrow-right:regular'],
    );
  });

  test('ignora tag dinamici, commenti Blade, attributi simili e altri componenti', () => {
    write(
      'views/nested/b.blade.php',
      `{{-- <x-ui.icon name="acorn" /> --}}
       <x-ui.icon :name="$icon" />
       <x-ui.icon name="house" :weight="$weight" />
       <x-ui.icon data-name="star" />
       <x-ui.icon-button name="star" />
       <x-ui.icon :label="$a > $b ? 'x' : 'y'" name="gear" />`,
    );

    assert.deepEqual(
      scanViews(path.join(dir, 'views')).map(({ name }) => name),
      ['gear'],
    );
  });

  test('tratta come dinamici i valori con un echo Blade ({{ }} e {!! !!})', () => {
    write(
      'views/c.blade.php',
      `<x-ui.icon name="{{ $icon }}" />
       <x-ui.icon name="{!! $icon !!}" />
       <x-ui.icon name="house" weight="{{ $weight }}" />
       <x-ui.icon name="icon-{{ $n }}" />
       <x-ui.icon name="gear" />`,
    );

    assert.deepEqual(
      scanViews(path.join(dir, 'views')).map(({ name }) => name),
      ['gear'],
    );
  });

  test('legge name e weight come attributi, non dentro il valore di un altro attributo', () => {
    write(
      'views/d.blade.php',
      `<x-ui.icon class="a name='bug'" name="gear" />
       <x-ui.icon data-x='weight="fill"' name="house" weight="bold" />
       <x-ui.icon @class(['size-6', 'name="bug"']) name="star" />`,
    );

    assert.deepEqual(
      scanViews(path.join(dir, 'views')).map(({ name, weight }) => `${name}:${weight}`),
      ['gear:regular', 'house:bold', 'star:regular'],
    );
    assert.deepEqual(
      [...parseAttributes(` class="a name='x'" hidden :label='$l' name=gear`)],
      [
        ['class', "a name='x'"],
        ['hidden', true],
        [':label', '$l'],
        ['name', 'gear'],
      ],
    );
  });

  test('ignora i commenti HTML e indica il file e la riga di ogni tag', () => {
    write(
      'views/e.blade.php',
      `<!-- <x-ui.icon name="acorn" />
       <x-ui.icon name="nome-sbagliato" /> -->
       {{-- <x-ui.icon name="anchor" />
       --}}
       <x-ui.icon name="gear" />`,
    );

    const usages = scanViews(path.join(dir, 'views'));

    assert.deepEqual(
      usages.map(({ name }) => name),
      ['gear'],
    );
    assert.match(usages[0].source, /views\/e\.blade\.php:5$/);
  });
});

describe('manifest', () => {
  test('unisce view e allowlist, senza duplicati e in ordine', () => {
    write('views/a.blade.php', '<x-ui.icon name="house" /><x-ui.icon name="acorn" />');
    write('allowlist.json', '["house", "heart:fill", "acorn"]');

    assert.deepEqual(collectIcons(options()), {
      regular: ['acorn', 'house'],
      fill: ['heart'],
    });
  });

  test('un nome o un peso inesistenti fanno fallire, con suggerimenti', () => {
    write('views/a.blade.php', '<x-ui.icon name="arow-right" />');
    assert.throws(
      () => collectIcons(options()),
      /views\/a\.blade\.php:1: .*"arow-right".*Forse: arrow-right/,
    );

    write('views/a.blade.php', '<p>\n</p>\n<x-ui.icon name="arow-right" />');
    assert.throws(() => collectIcons(options()), /views\/a\.blade\.php:3: /);

    write('views/a.blade.php', '<x-ui.icon name="house" weight="heavy" />');
    assert.throws(() => collectIcons(options()), /Peso "heavy" non valido/);
  });

  test("un'allowlist non valida fa fallire", () => {
    write('allowlist.json', '{"house": true}');
    assert.throws(() => collectIcons(options()), /array di stringhe/);
  });

  test('scrive un file PHP con il solo contenuto degli SVG, e non lo riscrive se invariato', () => {
    write('views/a.blade.php', '<x-ui.icon name="arrow-right" weight="duotone" />');
    const output = path.join(dir, 'out/phosphor.php');

    assert.equal(writeIconsManifest({ ...options(), output }), true);
    assert.equal(writeIconsManifest({ ...options(), output }), false);

    const source = fs.readFileSync(output, 'utf8');
    assert.equal(source, buildIconsManifest(options()));
    assert.match(source, /^<\?php\n/);
    assert.match(
      source,
      /'duotone' => \[\n {8}'arrow-right' => '<path d="[^']+" opacity="0\.2"\/><path/,
    );
    assert.doesNotMatch(source, /<svg/);
  });

  test('senza icone il manifest è un array vuoto', () => {
    write('views/a.blade.php', '<p>niente</p>');

    assert.match(buildIconsManifest(options()), /return \[\];\n$/);
  });
});

describe('catalogo (per M47)', () => {
  test('elenca le icone con categorie e tag, senza i tag interni', () => {
    const house = listIcons().find((icon) => icon.name === 'house');

    assert.ok(listIconNames().length > 1000);
    assert.ok(house.categories.length > 0);
    assert.ok(house.tags.every((tag) => !tag.startsWith('*')));
  });

  test('valida nome e peso', () => {
    assert.equal(iconExists('house', 'thin'), true);
    assert.equal(iconExists('house', 'heavy'), false);
    assert.equal(iconExists('../house'), false);
    assert.equal(iconExists('non-esiste'), false);
  });

  test("restituisce l'SVG del pacchetto e il suo contenuto", () => {
    assert.match(
      getIconSvg('house', 'fill'),
      /^<svg [^>]*viewBox="0 0 256 256"[^>]*fill="currentColor">.*<\/svg>$/,
    );
    assert.match(getIconInner('house'), /^<path d="/);
    assert.throws(() => getIconSvg('non-esiste'), /inesistente/);
  });

  test('suggerisce slug simili', () => {
    assert.equal(suggestIcons('hous')[0], 'house');
    assert.deepEqual(suggestIcons(''), []);
  });

  test("sceglie un'icona a caso, escludendo quelle indicate", () => {
    const [first, second] = listIconNames();

    assert.equal(randomIcon({ random: () => 0 }), first);
    assert.equal(randomIcon({ exclude: [first], random: () => 0 }), second);
    assert.ok(iconExists(randomIcon()));
    assert.equal(randomIcon({ exclude: listIconNames() }), null);
  });
});
