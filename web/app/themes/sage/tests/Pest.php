<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Ogni test gira in Tests\TestCase, che prepara e smonta Brain Monkey:
| le funzioni di WordPress sono mock, non servono né database né WordPress.
|
*/

uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| File del tema
|--------------------------------------------------------------------------
|
| Le classi in app/ sono in autoload (PSR-4 App\). Le funzioni helper no:
| le carica functions.php con locate_template(), quindi si includono qui.
| Le classi di WordPress usate dal codice testato hanno uno stub in tests/Stubs.
|
*/

require_once __DIR__.'/Stubs/wordpress.php';
require_once dirname(__DIR__).'/app/helpers.php';
