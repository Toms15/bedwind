<?php

use App\Icons\Phosphor;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

/**
 * Manifest e pacchetto finti in una cartella temporanea: il manifest contiene
 * "house" (regular e fill), il pacchetto anche "heart" (solo regular e bold).
 *
 * @return array{manifest: string, package: string}
 */
function fake_icon_sources(): array
{
    $dir = sys_get_temp_dir().'/sage-phosphor-'.bin2hex(random_bytes(4));

    mkdir($dir.'/assets/regular', 0777, true);
    mkdir($dir.'/assets/bold', 0777, true);

    file_put_contents($dir.'/phosphor.php', "<?php\n\nreturn ".var_export([
        'regular' => ['house' => '<path d="M1"/>'],
        'fill' => ['house' => '<path d="M2"/>'],
    ], true).";\n");

    $svg = fn (string $d): string => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="'.$d.'"/></svg>';

    file_put_contents($dir.'/assets/regular/house.svg', $svg('M1'));
    file_put_contents($dir.'/assets/regular/heart.svg', $svg('M3'));
    file_put_contents($dir.'/assets/bold/heart-bold.svg', $svg('M4'));
    // Fuori da assets/: non deve mai essere letto.
    file_put_contents($dir.'/secret.svg', $svg('SEGRETO'));

    return ['manifest' => $dir.'/phosphor.php', 'package' => $dir.'/assets', 'dir' => $dir];
}

beforeEach(function () {
    $this->sources = fake_icon_sources();

    Functions\stubEscapeFunctions();
    Functions\stubTranslationFunctions();
});

afterEach(function () {
    Phosphor::configure();

    $dir = $this->sources['dir'];
    exec('rm -rf '.escapeshellarg($dir));
});

/**
 * Configura Phosphor sulle sorgenti finte, in un dato ambiente.
 */
function use_icons(array $sources, string $env = 'production', bool $package = true): void
{
    Phosphor::configure($sources['manifest'], $package ? $sources['package'] : $sources['package'].'-assente', $env);
}

describe('validazione', function () {
    test('accetta solo slug con minuscole, cifre e trattini', function (string $name, bool $valid) {
        expect(Phosphor::isValidName($name))->toBe($valid);
    })->with([
        ['arrow-right', true],
        ['number-circle-1', true],
        ['', false],
        ['Arrow', false],
        ['arrow--right', false],
        ['-arrow', false],
        ['../secret', false],
        ['regular/house', false],
        ["house\0", false],
    ]);

    test('accetta i sei pesi di Phosphor', function () {
        foreach (['thin', 'light', 'regular', 'bold', 'fill', 'duotone'] as $weight) {
            expect(Phosphor::isValidWeight($weight))->toBeTrue();
        }

        expect(Phosphor::isValidWeight('heavy'))->toBeFalse()
            ->and(Phosphor::isValidWeight('Regular'))->toBeFalse();
    });

    test('un nome con un percorso non legge file fuori dagli asset', function () {
        use_icons($this->sources, 'development');

        expect(Phosphor::inner('../secret', 'regular'))->toBeNull()
            ->and(Phosphor::exists('../secret'))->toBeFalse();
    });
});

describe('svg()', function () {
    test('è decorativa di default: aria-hidden, focusable false, 1em e currentColor', function () {
        use_icons($this->sources);

        expect(Phosphor::svg('house'))->toBe(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="1em" height="1em" fill="currentColor" data-icon aria-hidden="true" focusable="false"><path d="M1"/></svg>'
        );
    });

    test('con label diventa role="img" con aria-label e title escapati', function () {
        use_icons($this->sources);

        $svg = Phosphor::svg('house', 'regular', [], 'Casa "mia" <b>');

        expect($svg)
            ->toContain('role="img"')
            ->toContain('aria-label="Casa &quot;mia&quot; &lt;b&gt;"')
            ->toContain('<title>Casa &quot;mia&quot; &lt;b&gt;</title>')
            ->not->toContain('aria-hidden');
    });

    test('una label vuota o di soli spazi lascia l\'icona decorativa', function () {
        use_icons($this->sources);

        expect(Phosphor::svg('house', 'regular', [], '   '))->toContain('aria-hidden="true"')->not->toContain('<title>');
    });

    test('unisce gli attributi extra e ignora quelli gestiti dal componente', function () {
        use_icons($this->sources);

        $svg = Phosphor::svg('house', 'regular', [
            'class' => 'size-6 text-primary-500',
            'data-x' => '"><script>',
            'hidden' => true,
            'id' => null,
            'aria-hidden' => 'false',
            'viewBox' => '0 0 1 1',
            'fill' => 'red',
            'width' => '24',
            'data-icon' => 'altro',
            'on"click' => 'x',
        ]);

        expect($svg)
            ->toContain('class="size-6 text-primary-500"')
            ->toContain('data-x="&quot;&gt;&lt;script&gt;"')
            ->toContain(' hidden ')
            ->toContain('width="24"')
            ->toContain('aria-hidden="true"')
            ->toContain('viewBox="0 0 256 256"')
            ->toContain('fill="currentColor"')
            ->not->toContain('id=')
            ->not->toContain('red')
            ->not->toContain('aria-hidden="false"')
            ->not->toContain('altro')
            ->not->toContain('on"click');
    });

    test('usa il peso richiesto', function () {
        use_icons($this->sources);

        expect(Phosphor::svg('house', 'fill'))->toContain('<path d="M2"/>');
    });
});

describe('sorgenti: manifest e pacchetto', function () {
    test('fuori da development usa solo il manifest', function () {
        use_icons($this->sources, 'production');

        expect(Phosphor::available('house'))->toBeTrue()
            ->and(Phosphor::available('heart'))->toBeFalse()
            ->and(Phosphor::svg('heart'))->toBe('');
    });

    test('in development ripiega sul pacchetto per le icone assenti dal manifest', function () {
        use_icons($this->sources, 'development');

        expect(Phosphor::inner('heart', 'bold'))->toBe('<path d="M4"/>')
            ->and(Phosphor::inManifest('heart', 'bold'))->toBeFalse()
            ->and(Phosphor::inManifest('house', 'regular'))->toBeTrue();
    });

    test('senza pacchetto né manifest non stampa nulla', function () {
        Phosphor::configure('/inesistente/phosphor.php', '/inesistente/assets', 'development');

        expect(Phosphor::svg('house'))->toBe('')
            ->and(Phosphor::names())->toBe([]);
    });

    test('source() restituisce l\'SVG del pacchetto, senza attributi del componente', function () {
        use_icons($this->sources);

        expect(Phosphor::source('house'))
            ->toBe('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor"><path d="M1"/></svg>')
            ->and(Phosphor::source('inesistente'))->toBeNull();
    });
});

describe('render()', function () {
    beforeEach(function () {
        // error_log() scrive in un file temporaneo, non sullo stderr dei test.
        $this->log = tempnam(sys_get_temp_dir(), 'sage-phosphor-log');
        $this->previousLog = ini_set('error_log', $this->log);
    });

    afterEach(function () {
        ini_set('error_log', (string) $this->previousLog);
        @unlink($this->log);
    });

    test('in produzione un\'icona non valida non stampa nulla e non segnala errori', function (string $name, string $weight) {
        use_icons($this->sources, 'production');
        Actions\expectDone('qm/warning')->never();

        expect(Phosphor::render($name, $weight))->toBe('')
            ->and(file_get_contents($this->log))->toBe('');
    })->with([
        ['inesistente', 'regular'],
        ['house', 'heavy'],
        ['../secret', 'regular'],
        ['heart', 'regular'],
    ]);

    test('in development un\'icona inesistente va nel log, in Query Monitor e in un commento HTML', function () {
        use_icons($this->sources, 'development');
        Actions\expectDone('qm/warning')
            ->once()
            ->with(Mockery::pattern('/^x-ui\.icon: .*"inesistente" \(regular\) inesistente/'));

        expect(Phosphor::render('inesistente'))->toBe('<!-- x-ui.icon: Icona Phosphor &quot;inesistente&quot; (regular) inesistente. -->')
            ->and(file_get_contents($this->log))->toContain('x-ui.icon: Icona Phosphor "inesistente" (regular) inesistente.');
    });

    test('in development un peso non valido elenca i pesi ammessi', function () {
        use_icons($this->sources, 'development');
        Actions\expectDone('qm/warning')->once()->with(Mockery::pattern('/thin, light, regular, bold, fill, duotone/'));

        expect(Phosphor::render('house', 'heavy'))->toStartWith('<!-- x-ui.icon:');
    });

    test('in development stampa un\'icona assente dal manifest, con una segnalazione', function () {
        use_icons($this->sources, 'development');
        Actions\expectDone('qm/warning')->once()->with(Mockery::pattern('/allowlist\.json/'));

        expect(Phosphor::render('heart'))
            ->toStartWith('<svg ')
            ->toContain('<path d="M3"/>')
            ->toContain('<!-- x-ui.icon:')
            ->toContain('name statico in x-ui.icon oppure');
    });

    test('in development non lancia anche con un gestore degli errori che trasforma i warning in eccezioni (come Acorn)', function () {
        use_icons($this->sources, 'development');
        set_error_handler(function (int $level, string $message, string $file = '', int $line = 0): bool {
            throw new ErrorException($message, 0, $level, $file, $line);
        });

        try {
            expect(Phosphor::render('inesistente'))->toStartWith('<!-- x-ui.icon:')
                ->and(Phosphor::render('house', 'heavy'))->toStartWith('<!-- x-ui.icon:')
                ->and(Phosphor::render('heart'))->toStartWith('<svg ');
        } finally {
            restore_error_handler();
        }
    });

    test('un\'icona del manifest non dà segnalazioni in nessun ambiente', function (string $env) {
        use_icons($this->sources, $env);
        Actions\expectDone('qm/warning')->never();

        expect(Phosphor::render('house', 'fill', ['class' => 'size-6']))->toContain('class="size-6"')->not->toContain('<!--')
            ->and(file_get_contents($this->log))->toBe('');
    })->with(['development', 'production', 'staging']);

    test('il commento HTML non si può chiudere dal nome', function () {
        use_icons($this->sources, 'development');

        expect(Phosphor::render('a--b'))->not->toContain('a--b');
    });
});

describe('catalogo (per M47)', function () {
    test('exists() controlla il pacchetto installato, nome e peso', function () {
        use_icons($this->sources, 'production');

        expect(Phosphor::exists('heart', 'bold'))->toBeTrue()
            ->and(Phosphor::exists('heart', 'fill'))->toBeFalse()
            ->and(Phosphor::exists('heart', 'heavy'))->toBeFalse()
            ->and(Phosphor::exists('inesistente'))->toBeFalse();
    });

    test('senza pacchetto exists() e names() usano il manifest', function () {
        use_icons($this->sources, 'production', package: false);

        expect(Phosphor::exists('house', 'fill'))->toBeTrue()
            ->and(Phosphor::exists('heart'))->toBeFalse()
            ->and(Phosphor::names())->toBe(['house']);
    });

    test('senza pacchetto names() conserva gli slug solo numerici del manifest', function () {
        file_put_contents($this->sources['manifest'], "<?php\n\nreturn ".var_export([
            'regular' => ['404' => '<path d="M5"/>', 'house' => '<path d="M1"/>'],
            'bold' => ['2' => '<path d="M6"/>'],
        ], true).";\n");
        use_icons($this->sources, 'production', package: false);

        expect(Phosphor::names())->toBe(['2', '404', 'house']);
    });

    test('names() elenca gli slug del pacchetto in ordine alfabetico', function () {
        use_icons($this->sources);

        expect(Phosphor::names())->toBe(['heart', 'house']);
    });

    test('random() sceglie uno slug del set, escludendo quelli indicati', function () {
        use_icons($this->sources);

        expect(Phosphor::random())->toBeIn(['heart', 'house'])
            ->and(Phosphor::random(['house']))->toBe('heart')
            ->and(Phosphor::random(['heart', 'house']))->toBeNull();
    });
});
