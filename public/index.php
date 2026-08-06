<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Dual-path bootstrap (local vs Hostinger)
|--------------------------------------------------------------------------
|
| 1. Hostinger FTP jail: public_html/index.php + public_html/laravel_app/
| 2. Ideal sibling:      public_html/index.php + ../laravel_app/
| 3. Local:              public/index.php → app root/
|
| public_path() is always this directory so asset URLs and storage:link target
| the web root.
|
*/

$laravelRoot = is_dir(__DIR__.'/laravel_app')
    ? __DIR__.'/laravel_app'
    : (is_dir(__DIR__.'/../laravel_app')
        ? __DIR__.'/../laravel_app'
        : __DIR__.'/..');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $laravelRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $laravelRoot.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';

$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
