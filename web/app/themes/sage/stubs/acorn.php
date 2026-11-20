<?php

/**
 * PHPStan stubs for the Laravel helpers that Acorn loads at boot.
 *
 * Acorn requires vendor/roots/acorn/src/Illuminate/Foundation/helpers.php
 * from Application::__construct(), not through Composer autoload, so PHPStan
 * does not see them. The whole file cannot be scanned: it also declares
 * Laravel's __(), which would shadow the WordPress one.
 *
 * Only declarations, never loaded at runtime.
 */

/**
 * Get the path to the public folder.
 */
function public_path(string $path = ''): string {}
