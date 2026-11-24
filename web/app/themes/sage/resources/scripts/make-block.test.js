/**
 * Test di resources/scripts/make-block.js (node --test, `npm run test:js`):
 * formato di --fields, slug e nomi dei campi, validazioni, icone già usate,
 * chiavi ACF univoche e generazione dei file in un tema temporaneo.
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterEach, beforeEach, describe, test } from 'node:test';
import { getIconSvg } from './icons.js';
import {
  answersFromFlags,
  blockStrings,
  buildFiles,
  buildModule,
  buildStyleCss,
  buildView,
  buildViewJs,
  commentText,
  formatFiles,
  createKeyFactory,
  existingKeys,
  existingSlugs,
  iconWarning,
  identifyIcon,
  main,
  parseCliArgs,
  parseFieldsSpec,
  readCategories,
  themeDir,
  toAcfJson,
  toFieldName,
  toSlug,
  untranslated,
  usedIcons,
  validateFields,
  validateIcon,
  validateSlug,
  validateText,
  writeFiles,
} from './make-block.js';

let dir;

const write = (file, content) => {
  fs.mkdirSync(path.dirname(path.join(dir, file)), { recursive: true });
  fs.writeFileSync(path.join(dir, file), content);
};

/** Tema minimo: categorie, un blocco esistente con un'icona e il suo field group. */
beforeEach(() => {
  dir = fs.mkdtempSync(path.join(os.tmpdir(), 'sage-make-block-'));

  write('app/blocks.php', fs.readFileSync(path.join(themeDir, 'app/blocks.php'), 'utf8'));
  write('.bladeformatterrc.json', fs.readFileSync(path.join(themeDir, '.bladeformatterrc.json')));
  write('.prettierrc.json', JSON.stringify({ singleQuote: true, printWidth: 100 }));
  write(
    'resources/views/blocks/esistente/block.json',
    JSON.stringify({ name: 'acf/esistente', icon: getIconSvg('star', 'fill') }),
  );
  write(
    'acf-json/group_000000000000a.json',
    toAcfJson({
      key: 'group_000000000000a',
      title: 'Blocco: Altro',
      fields: [{ key: 'field_000000000000b', name: 'x', type: 'text' }],
      location: [[{ param: 'block', operator: '==', value: 'acf/solo-gruppo' }]],
    }),
  );
});

afterEach(() => {
  fs.rmSync(dir, { recursive: true, force: true });
});

describe('slug e nomi dei campi', () => {
  test('toSlug produce kebab-case senza accenti né cifre iniziali', () => {
    assert.equal(toSlug("Galleria d'immagini"), 'galleria-d-immagini');
    assert.equal(toSlug('  Città & Più  '), 'citta-piu');
    assert.equal(toSlug('3 colonne'), 'colonne');
  });

  test('toFieldName produce snake_case', () => {
    assert.equal(toFieldName('Testo del pulsante'), 'testo_del_pulsante');
    assert.equal(toFieldName('Qualità'), 'qualita');
    assert.equal(toFieldName('2 Colonne'), 'colonne');
  });

  test('validateSlug rifiuta formati non validi e slug di blocchi o field group esistenti', () => {
    const taken = existingSlugs(dir);

    assert.deepEqual([...taken].sort(), ['esistente', 'solo-gruppo']);
    assert.equal(validateSlug('nuovo-blocco', taken), undefined);
    assert.match(validateSlug('Nuovo', taken), /non valido/);
    assert.match(validateSlug('nuovo--blocco', taken), /non valido/);
    assert.match(validateSlug('esistente', taken), /già usato/);
    assert.match(validateSlug('solo-gruppo', taken), /già usato/);
  });
});

describe('formato di --fields', () => {
  test('etichetta, name, tipo, obbligatorio, scelte e sotto-campi', () => {
    const fields = parseFieldsSpec(
      'Titolo=title:text!, immagine:image, cta:link, Mostra bordo:bool, ' +
        'Stile:select(Chiaro|scuro=Scuro scuro)!, Voci:repeater[Testo:text!,Icona:immagine]',
    );

    assert.deepEqual(
      fields.map(({ label, name, type, required }) => [label, name, type, required]),
      [
        ['Titolo', 'title', 'text', true],
        ['Immagine', 'immagine', 'image', false],
        ['Cta', 'cta', 'link', false],
        ['Mostra bordo', 'mostra_bordo', 'true_false', false],
        ['Stile', 'stile', 'select', true],
        ['Voci', 'voci', 'repeater', false],
      ],
    );
    assert.deepEqual(fields[4].choices, [
      { value: 'chiaro', label: 'Chiaro' },
      { value: 'scuro', label: 'Scuro scuro' },
    ]);
    assert.deepEqual(
      fields[5].subFields.map(({ name, type, required }) => [name, type, required]),
      [
        ['testo', 'text', true],
        ['icona', 'image', false],
      ],
    );
  });

  test('stringa vuota = nessun campo', () => {
    assert.deepEqual(parseFieldsSpec(''), []);
    assert.deepEqual(parseFieldsSpec(undefined), []);
  });

  test('errori di sintassi e tipi sconosciuti', () => {
    assert.throws(() => parseFieldsSpec('titolo'), /non valido/);
    assert.throws(() => parseFieldsSpec('titolo:numero'), /tipo "numero" sconosciuto/);
    assert.throws(() => parseFieldsSpec('titolo:text(a|b)'), /solo per select/);
    assert.throws(() => parseFieldsSpec('titolo:text[a:text]'), /solo per repeater/);
  });
});

describe('validazione dei campi', () => {
  test('nomi duplicati, riservati e non validi', () => {
    const errors = validateFields(
      parseFieldsSpec('a:text,a:image,padding:text,Margin=margin:text,Data=data:text,X=1x:text'),
    );

    assert.equal(errors.length, 5);
    assert.match(errors.join('\n'), /"a" già usato/);
    assert.match(errors.join('\n'), /"padding" riservato/);
    assert.match(errors.join('\n'), /"margin" riservato/);
    assert.match(errors.join('\n'), /"data" riservato/);
    assert.match(errors.join('\n'), /"1x" non valido/);
  });

  test('title deve essere text, select con scelte, repeater con sotto-campi semplici', () => {
    const errors = validateFields(
      parseFieldsSpec(
        'T=title:image,s:select,r:repeater,q:repeater[x:select(a|b),y:repeater[z:text]]',
      ),
    ).join('\n');

    assert.match(errors, /"title" è il titolo del blocco/);
    assert.match(errors, /almeno una scelta/);
    assert.match(errors, /almeno un sotto-campo/);
    assert.match(errors, /tipo "select" non ammesso in un repeater/);
    assert.match(errors, /tipo "repeater" non ammesso in un repeater/);
  });

  test('i nomi riservati valgono solo al primo livello', () => {
    assert.deepEqual(validateFields(parseFieldsSpec('r:repeater[Data=data:text,a:text]')), []);
  });
});

describe('icone', () => {
  test('identifyIcon riconosce un SVG fill anche scritto in modo diverso', () => {
    const svg = getIconSvg('article', 'fill');
    const reformatted = svg
      .replace(/^<svg[^>]*>/, "<svg viewBox='0 0 256 256'>")
      .replace(/"/g, "'");

    assert.equal(identifyIcon(svg), 'article');
    assert.equal(identifyIcon(reformatted), 'article');
    assert.equal(identifyIcon(getIconSvg('article', 'regular')), null);
    assert.equal(identifyIcon('<svg><path d="M0"/></svg>'), null);
  });

  test('usedIcons elenca le icone dei block.json esistenti', () => {
    assert.deepEqual([...usedIcons(dir)], ['star']);
  });

  test("l'icona casuale esclude quelle già usate", () => {
    // Il generatore restituisce sempre 0: senza esclusioni sarebbe la prima icona in ordine alfabetico.
    const { answers } = answersFromFlags(
      { title: 'Prova', description: 'd', category: 'commons' },
      { dir, random: () => 0 },
    );

    assert.notEqual(answers.icon, 'star');
    assert.equal(validateIcon(answers.icon), undefined);
  });

  test('validateIcon accetta vuoto (casuale) e suggerisce per i refusi', () => {
    assert.equal(validateIcon(''), undefined);
    assert.equal(validateIcon('house'), undefined);
    assert.match(validateIcon('hosue'), /inesistente\. Forse: .*house/);
  });
});

describe('categorie e flag', () => {
  test('le categorie arrivano da app/blocks.php', () => {
    const slugs = readCategories(dir).map(({ slug }) => slug);

    assert.ok(slugs.includes('static-content'));
    assert.ok(slugs.includes('heroes'));
    assert.equal(readCategories(dir).find(({ slug }) => slug === 'commons').title, 'Generici');
  });

  test('parseCliArgs: booleani negabili e flag sconosciuti', () => {
    assert.deepEqual(
      { ...parseCliArgs(['--title', 'A', '--no-view-js', '--style', '-y']) },
      { title: 'A', 'view-js': false, style: true, yes: true, translate: true },
    );
    assert.equal(parseCliArgs(['--no-translate']).translate, false);
    assert.throws(() => parseCliArgs(['--colore', 'rosso']));
  });

  test('senza TTY: default per i facoltativi, errore per i valori non validi', () => {
    const { answers, missing } = answersFromFlags(
      { title: 'card in evidenza', description: 'Card', category: 'commons', icon: 'cards' },
      { dir },
    );

    assert.deepEqual(missing, []);
    assert.equal(answers.title, 'Card in evidenza');
    assert.equal(answers.slug, 'card-in-evidenza');
    assert.deepEqual(answers.keywords, ['card', 'evidenza']);
    assert.deepEqual(answers.fields, []);
    assert.equal(answers.viewJs, false);

    assert.throws(
      () => answersFromFlags({ title: 'Esistente', description: 'd', category: 'nope' }, { dir }),
      /già usato[\s\S]*Categoria "nope" inesistente/,
    );
  });

  test('senza TTY e con valori mancanti: errore chiaro, nessuna domanda', async () => {
    await assert.rejects(
      main(['--title', 'Prova'], { dir, interactive: false, log: () => {} }),
      /Valori mancanti: --description, --category/,
    );
  });

  test('senza TTY e senza --yes: nessuna scrittura', async () => {
    await assert.rejects(
      main(['--title', 'Prova', '--description', 'd', '--category', 'commons', '--icon', 'cube'], {
        dir,
        interactive: false,
        log: () => {},
      }),
      /--yes/,
    );
    assert.equal(fs.existsSync(path.join(dir, 'resources/views/blocks/prova')), false);
  });
});

describe('chiavi ACF', () => {
  test('formato di uniqid e univocità anche con collisioni', () => {
    const values = ['aaaaa', 'aaaaa', 'bbbbb', 'aaaaa', 'ccccc'];
    const nextKey = createKeyFactory(['field_00000001bbbbb'], {
      now: () => 1000,
      random: () => values.shift(),
    });

    assert.equal(nextKey('group'), 'group_00000001aaaaa');
    assert.equal(nextKey('field'), 'field_00000001aaaaa');
    // "bbbbb" è già preso, "aaaaa" anche: si passa a "ccccc".
    assert.equal(nextKey('field'), 'field_00000001ccccc');
  });

  test('existingKeys legge gruppi e campi di acf-json', () => {
    assert.deepEqual([...existingKeys(dir)].sort(), ['field_000000000000b', 'group_000000000000a']);
  });
});

describe('generazione dei file', () => {
  const answers = () => ({
    title: 'Test wizard',
    slug: 'test-wizard',
    description: 'Blocco di prova',
    category: 'static-content',
    icon: 'cube',
    keywords: ['prova'],
    fields: parseFieldsSpec(
      'Titolo=title:text!,Immagine:image,Link=cta:link,Mostra bordo=bordo:true_false,' +
        'Stile:select(Chiaro|Scuro),Voci:repeater[Testo:text!,Descrizione:textarea,Link:link]',
    ),
    viewJs: true,
    style: true,
  });

  test('block.json, view, modulo, field group e asset', () => {
    const files = buildFiles(answers(), { dir, now: () => 1795529332000 });
    const byPath = Object.fromEntries(files.map((file) => [file.path, file.content]));
    const groupPath = files.find((file) => file.path.startsWith('acf-json/')).path;

    assert.deepEqual(
      files.map((file) => file.path),
      [
        'resources/views/blocks/test-wizard/block.json',
        'resources/views/blocks/test-wizard/test-wizard.blade.php',
        'resources/views/modules/test-wizard.blade.php',
        groupPath,
        'resources/views/blocks/test-wizard/view.js',
        'resources/views/blocks/test-wizard/style.css',
      ],
    );

    const block = JSON.parse(byPath['resources/views/blocks/test-wizard/block.json']);

    assert.equal(block.apiVersion, 3);
    assert.equal(block.name, 'acf/test-wizard');
    assert.equal(block.textdomain, 'sage');
    assert.equal(block.category, 'static-content');
    assert.deepEqual(block.supports, { anchor: true, align: false });
    assert.deepEqual(block.acf, {
      blockVersion: 3,
      renderCallback: 'App\\Blocks\\BlockRenderer::render',
    });
    assert.equal(block.icon, getIconSvg('cube', 'fill'));

    const group = JSON.parse(byPath[groupPath]);

    assert.match(group.key, /^group_[0-9a-f]{13}$/);
    assert.equal(groupPath, `acf-json/${group.key}.json`);
    assert.equal(group.title, 'Blocco: Test wizard');
    assert.equal(group.modified, 1795529332);
    assert.deepEqual(group.location, [
      [{ param: 'block', operator: '==', value: 'acf/test-wizard' }],
    ]);
    assert.match(byPath[groupPath], /"acf\\\/test-wizard"/);
    assert.deepEqual(
      group.fields.map(({ name, type, required }) => [name, type, required]),
      [
        ['title', 'text', 1],
        ['title_formatting', 'button_group', 0],
        ['immagine', 'image', 0],
        ['cta', 'link', 0],
        ['bordo', 'true_false', 0],
        ['stile', 'select', 0],
        ['voci', 'repeater', 0],
      ],
    );
    assert.equal(group.fields[2].return_format, 'id');
    assert.deepEqual(group.fields[5].choices, { chiaro: 'Chiaro', scuro: 'Scuro' });

    const repeater = group.fields[6];

    assert.deepEqual(
      repeater.sub_fields.map(({ name, parent_repeater }) => [name, parent_repeater]),
      [
        ['testo', repeater.key],
        ['descrizione', repeater.key],
        ['link', repeater.key],
      ],
    );

    // Nessun campo condiviso duplicato.
    assert.ok(
      !group.fields.some(({ name }) => ['padding', 'margin', 'background_color'].includes(name)),
    );

    const view = byPath['resources/views/blocks/test-wizard/test-wizard.blade.php'];

    assert.match(view, /\\App\\Blocks\\BlockRenderer::data\(\$block\)/);
    assert.match(view, /\$cta = \$to_link\(get_field\('cta'\)\);/);
    assert.match(
      view,
      /'testo' => is_string\(\$row\['testo'\] \?\? null\) \? trim\(\$row\['testo'\]\) : ''/,
    );
    assert.match(
      view,
      /\$stile = in_array\(\$stile, \['chiaro', 'scuro'\], true\) \? \$stile : 'chiaro';/,
    );
    assert.match(view, /@include\('modules\.test-wizard', \[/);

    const module = byPath['resources/views/modules/test-wizard.blade.php'];

    assert.match(
      module,
      /<section\n {2}id="\{\{ \$settings\['id'\] \}\}"\n {2}data-block="test-wizard"/,
    );
    assert.match(module, /'w-full overflow-x-clip',/);
    assert.match(module, /<div class="container mx-auto px-6 3xl:px-0">/);
    assert.match(
      module,
      /<x-ui\.title :tag="\$contents\['title_tag'\]" class="mb-6">\{\{ \$title \}\}/,
    );
    assert.match(module, /<x-ui\.title :tag="\$contents\['next_heading'\]"/);
    assert.match(module, /<x-ui\.image :id="\$immagine"/);
    assert.match(module, /<x-ui\.button :link="\$cta\['url'\]"/);
    assert.match(module, /Varianti: \$bordo \(Mostra bordo\), \$stile \(Stile\)/);
  });

  test('chiavi univoche nel gruppo e rispetto a quelle esistenti', () => {
    const files = buildFiles(answers(), { dir });
    const group = JSON.parse(files.find((file) => file.path.startsWith('acf-json/')).content);
    const keys = [group.key];

    const walk = (fields) =>
      fields.forEach((field) => {
        keys.push(field.key);
        walk(field.sub_fields ?? []);
      });

    walk(group.fields);

    assert.equal(keys.length, 11);
    assert.equal(new Set(keys).size, keys.length);
    assert.ok(keys.slice(1).every((key) => /^field_[0-9a-f]{13}$/.test(key)));
    assert.ok(!keys.some((key) => existingKeys(dir).has(key)));
  });

  test('senza asset né campi: solo i tre file e il field group vuoto', () => {
    const files = buildFiles({ ...answers(), fields: [], viewJs: false, style: false }, { dir });

    assert.equal(files.length, 4);
    assert.doesNotMatch(files[2].content, /data-block/);
    assert.doesNotMatch(files[1].content, /\$to_link/);
  });

  test('stringhe da tradurre del blocco', () => {
    const strings = blockStrings(answers());

    for (const value of [
      'Test wizard',
      'Blocco di prova',
      'prova',
      'Blocco: Test wizard',
      'Stile',
      'Scuro',
      'Aggiungi voce',
      'Formattazione',
    ]) {
      assert.ok(strings.includes(value), value);
    }
  });

  test('--dry-run non scrive nulla, --yes scrive senza sovrascrivere', async () => {
    const argv = [
      '--title',
      'Test wizard',
      '--description',
      'Prova',
      '--category',
      'commons',
      '--icon',
      'cube',
      '--fields',
      'Titolo=title:text!,Voci:repeater[Testo:text]',
      '--no-translate',
    ];
    const options = { dir, interactive: false, log: () => {} };

    const dry = await main([...argv, '--dry-run'], options);

    assert.equal(dry.written, false);
    assert.equal(fs.existsSync(path.join(dir, 'resources/views/blocks/test-wizard')), false);
    assert.equal(fs.readdirSync(path.join(dir, 'acf-json')).length, 1);

    const real = await main([...argv, '--yes'], options);

    assert.equal(real.written, true);

    for (const file of real.files) {
      assert.equal(fs.readFileSync(path.join(dir, file.path), 'utf8'), file.content);
    }

    // Lo slug ora esiste (cartella e field group): un secondo giro viene rifiutato.
    await assert.rejects(main([...argv, '--yes'], options), /già usato/);
  });
});

describe('traduzioni', () => {
  test('untranslated elenca i msgid con msgstr vuoto', () => {
    write(
      'en_US.po',
      [
        'msgid ""',
        'msgstr ""',
        '"Language: en_US\\n"',
        '',
        '#: a.php:1',
        'msgid "Tradotto"',
        'msgstr "Translated"',
        '',
        '#: b.php:1',
        'msgctxt "block title"',
        'msgid "Nuovo"',
        'msgstr ""',
        '',
        'msgid ""',
        '"Su due "',
        '"righe"',
        'msgstr ""',
        '',
      ].join('\n'),
    );

    assert.deepEqual([...untranslated(path.join(dir, 'en_US.po'))].sort(), [
      'Nuovo',
      'Su due righe',
    ]);
  });
});

describe('testi nei commenti generati', () => {
  const MALICIOUS_TITLE =
    'Inj */ echo "PWN"; /* ?> <?php echo 1; @endphp {{ 7*7 }} --}} {!! x !!} @php echo 2; @endphp';
  const MALICIOUS_FIELDS =
    'Ti */ ?> --}}tolo=title:text!,Flag --}} x {{ y }}=flag:true_false,Img */ @php=img:image,' +
    'Voci ?> x <? {!!=voci:repeater[Testo:text,Ok {{ --}}=ok:true_false]';
  const BREAKERS = ['*/', '/*', '?>', '<?', '{{', '}}', '{!!', '!!}', '@php', '@endphp'];

  const blockWith = (title, spec) => ({
    title,
    slug: 'prova',
    description: 'd',
    category: 'commons',
    icon: 'cube',
    keywords: [],
    fields: parseFieldsSpec(spec),
    viewJs: true,
    style: true,
  });

  test('commentText: una riga, senza sequenze che chiudono commenti o aprono codice', () => {
    assert.equal(commentText('Card in evidenza'), 'Card in evidenza');
    assert.equal(commentText('a\n\tb'), 'a b');
    assert.equal(commentText('a */ b'), 'a * / b');
    assert.equal(commentText('x@y'), 'x(at)y');
    assert.equal(commentText('{{{ }}}'), '{ { { } } }');

    const text = commentText(MALICIOUS_TITLE);

    for (const sequence of [...BREAKERS, '--}}', '{{--', '@']) {
      assert.ok(!text.includes(sequence), `${sequence} in ${text}`);
    }
  });

  test('view, modulo, view.js e style.css non contengono le sequenze delle etichette', () => {
    const block = blockWith(MALICIOUS_TITLE, MALICIOUS_FIELDS);
    const outputs = {
      view: buildView(block),
      module: buildModule({ ...block, viewJs: true }),
      viewJs: buildViewJs(block),
      style: buildStyleCss(block),
    };
    const clean = blockWith(
      'Prova',
      'Titolo=title:text!,Flag=flag:true_false,Img=img:image,' +
        'Voci=voci:repeater[Testo:text,Ok=ok:true_false]',
    );
    const reference = {
      view: buildView(clean),
      module: buildModule({ ...clean, viewJs: true }),
      viewJs: buildViewJs(clean),
      style: buildStyleCss(clean),
    };

    // Le sequenze pericolose compaiono solo dove le mette il template, mai di più.
    const count = (text, sequence) => text.split(sequence).length - 1;

    for (const [name, output] of Object.entries(outputs)) {
      for (const sequence of BREAKERS) {
        assert.equal(
          count(output, sequence),
          count(reference[name], sequence),
          `${name}: ${sequence}`,
        );
      }
    }
  });

  test('Prettier e blade-formatter non vanno in crash con etichette malevole', async () => {
    const files = await formatFiles(
      buildFiles(blockWith(MALICIOUS_TITLE, MALICIOUS_FIELDS), { dir }),
      dir,
    );

    assert.equal(files.length, 6);
  });

  test('un errore di formattazione dà un messaggio chiaro su una riga', async () => {
    await assert.rejects(
      formatFiles([{ path: 'x/view.js', content: 'const = ;', format: 'prettier' }], dir),
      (error) => {
        assert.match(
          error.message,
          /^Formattazione di x\/view\.js non riuscita \(.+\)\. Nessun file scritto/,
        );
        assert.ok(!error.message.includes('\n'));

        return true;
      },
    );
  });

  test('caratteri di controllo rifiutati in titolo, descrizione ed etichette', () => {
    assert.equal(validateText('Card', 'Il titolo'), undefined);
    assert.match(validateText('Ca\nrd', 'Il titolo'), /caratteri di controllo/);
    assert.throws(
      () =>
        answersFromFlags({ title: 'A\u0000b', description: 'd\te', category: 'commons' }, { dir }),
      /--title contiene caratteri di controllo[\s\S]*--description contiene/,
    );
    assert.match(
      validateFields(parseFieldsSpec('Eti\u001bchetta=x:text')).join('\n'),
      /etichetta contiene caratteri di controllo/,
    );
  });
});

describe('titoli, slug, icone e scrittura', () => {
  test('voci del repeater: next_heading con il campo title, title_tag senza', () => {
    const block = (spec) => ({ slug: 'prova', title: 'Prova', fields: parseFieldsSpec(spec) });
    const withTitle = buildModule(block('Titolo=title:text,Voci:repeater[Testo:text]'));
    const withoutTitle = buildModule(block('Voci:repeater[Testo:text]'));

    assert.match(withTitle, /<x-ui.title :tag="\$contents\['next_heading'\]" class="mb-2">/);
    assert.match(withoutTitle, /<x-ui.title :tag="\$contents\['title_tag'\]" class="mb-2">/);
    assert.ok(!withoutTitle.includes(`:tag="$contents['next_heading']"`));
  });

  test('slug non ricavabile dal titolo: suggerisce --slug', () => {
    assert.throws(
      () => answersFromFlags({ title: '€€', description: 'd', category: 'commons' }, { dir }),
      /Impossibile ricavare uno slug dal titolo "€€".*passa --slug/,
    );
    assert.equal(
      answersFromFlags(
        { title: '€€', slug: 'euro', description: 'd', category: 'commons' },
        { dir },
      ).answers.slug,
      'euro',
    );
  });

  test('icona già usata: avviso con il blocco che la usa', () => {
    assert.match(iconWarning('star', dir), /"star" è già usata da acf\/esistente/);
    assert.equal(iconWarning('cube', dir), undefined);

    const { warnings } = answersFromFlags(
      { title: 'Prova', description: 'd', category: 'commons', icon: 'star' },
      { dir },
    );

    assert.equal(warnings.length, 1);
  });

  test('scrittura atomica: se un file fallisce, quelli già creati vengono rimossi', () => {
    write('resources/views/modules/prova.blade.php', 'esistente');

    assert.throws(
      () =>
        writeFiles(
          [
            { path: 'resources/views/blocks/prova/block.json', content: '{}' },
            { path: 'acf-json/group_1.json', content: '{}' },
            { path: 'resources/views/modules/prova.blade.php', content: 'nuovo' },
          ],
          dir,
        ),
      /Scrittura dei file non riuscita .*rimossi i file già creati/,
    );

    assert.equal(fs.existsSync(path.join(dir, 'resources/views/blocks/prova')), false);
    assert.equal(fs.existsSync(path.join(dir, 'acf-json/group_1.json')), false);
    assert.ok(fs.existsSync(path.join(dir, 'acf-json/group_000000000000a.json')));
    assert.equal(
      fs.readFileSync(path.join(dir, 'resources/views/modules/prova.blade.php'), 'utf8'),
      'esistente',
    );
  });
});
