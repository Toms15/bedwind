<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every test runs in Tests\TestCase, which sets up and tears down Brain
| Monkey: WordPress functions are mocked, no database or WordPress needed.
|
*/

uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Theme files
|--------------------------------------------------------------------------
|
| Classes in app/ are autoloaded (PSR-4 App\). The helper functions are not:
| functions.php loads them with locate_template(), so require them here.
|
*/

require_once dirname(__DIR__).'/app/helpers.php';
