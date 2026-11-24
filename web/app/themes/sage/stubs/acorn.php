<?php

/**
 * Stub PHPStan per gli helper Laravel che Acorn carica all'avvio.
 *
 * Acorn include vendor/roots/acorn/src/Illuminate/Foundation/helpers.php
 * da Application::__construct(), non tramite l'autoload di Composer, quindi
 * PHPStan non li vede. Non si può analizzare l'intero file: dichiara anche
 * la __() di Laravel, che oscurerebbe quella di WordPress.
 *
 * Solo dichiarazioni, mai caricate a runtime.
 */

/**
 * Restituisce il percorso della cartella public.
 */
function public_path(string $path = ''): string {}
