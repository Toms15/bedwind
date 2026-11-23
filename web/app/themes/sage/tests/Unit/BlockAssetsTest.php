<?php

use App\Blocks\BlockAssets;
use Brain\Monkey\Functions;
use Illuminate\Support\Facades\Vite;

/**
 * A temporary theme with the given block files (paths relative to
 * resources/views/blocks), resolved by get_theme_file_path().
 *
 * @param  list<string>  $files
 */
function fake_theme(array $files): string
{
    $theme = sys_get_temp_dir().'/sage-block-assets-'.bin2hex(random_bytes(4));
    $GLOBALS['sage_fake_themes'][] = $theme;

    foreach ($files as $file) {
        $path = "{$theme}/resources/views/blocks/{$file}";
        is_dir(dirname($path)) || mkdir(dirname($path), 0777, true);
        file_put_contents($path, '');
    }

    Functions\when('get_theme_file_path')->alias(fn (string $path) => "{$theme}/{$path}");

    return $theme;
}

/**
 * Swap the Vite facade: asset() returns "build/<path>", or throws for the
 * paths missing from the manifest.
 *
 * @param  list<string>  $missing
 */
function fake_vite(array $missing = []): void
{
    Vite::swap(new class($missing)
    {
        /** @param  list<string>  $missing */
        public function __construct(private array $missing) {}

        public function asset(string $path): string
        {
            if (in_array($path, $this->missing, true)) {
                throw new RuntimeException("Unable to locate file in Vite manifest: {$path}.");
            }

            return "build/{$path}";
        }
    });
}

afterEach(function () {
    Vite::clearResolvedInstances();

    foreach ($GLOBALS['sage_fake_themes'] ?? [] as $theme) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($theme, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($theme);
    }

    $GLOBALS['sage_fake_themes'] = [];
});

describe('for', function () {
    it('lists only the assets that exist in the block folder', function () {
        fake_theme(['cards/view.js', 'cards/style.css', 'hero/style.css', 'only-text/block.json']);

        expect(BlockAssets::for('cards'))->toBe([
            'style' => 'resources/views/blocks/cards/style.css',
            'script' => 'resources/views/blocks/cards/view.js',
        ])
            ->and(BlockAssets::for('hero'))->toBe(['style' => 'resources/views/blocks/hero/style.css'])
            ->and(BlockAssets::for('only-text'))->toBe([]);
    });

    it('rejects slugs that are not a block folder name', function (string $slug) {
        fake_theme(['cards/view.js']);

        expect(BlockAssets::for($slug))->toBe([]);
    })->with(['', '../cards', 'cards/../cards', 'Cards', '-cards']);
});

describe('enqueue', function () {
    it('enqueues the stylesheet and the script module with the Vite URLs', function () {
        fake_theme(['cards/view.js', 'cards/style.css']);
        fake_vite();

        Functions\expect('wp_enqueue_style')
            ->once()
            ->with('sage-block-cards', 'build/resources/views/blocks/cards/style.css', [], null);
        Functions\expect('wp_enqueue_script_module')
            ->once()
            ->with('sage-block-cards', 'build/resources/views/blocks/cards/view.js', [], null);

        BlockAssets::enqueue('cards');
    });

    it('enqueues nothing for a block without assets', function () {
        fake_theme(['only-text/block.json']);
        fake_vite();

        Functions\expect('wp_enqueue_style')->never();
        Functions\expect('wp_enqueue_script_module')->never();

        BlockAssets::enqueue('only-text');
    });

    it('skips the assets missing from the Vite manifest', function () {
        fake_theme(['cards/view.js', 'cards/style.css']);
        fake_vite(['resources/views/blocks/cards/view.js']);

        Functions\expect('wp_enqueue_style')->once();
        Functions\expect('wp_enqueue_script_module')->never();

        BlockAssets::enqueue('cards');
    });
});
