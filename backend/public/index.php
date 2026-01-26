<?php

/**
 * TWUNGURANE - Point d'entrée public
 * 
 * Toutes les requêtes HTTP passent par ce fichier
 */

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Mode maintenance
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Autoloader Composer
require __DIR__.'/../vendor/autoload.php';

// Bootstrap et gestion de la requête
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
