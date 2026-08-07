<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
try {
    /** @var Application $app */
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $app->handleRequest(Request::capture());
} catch (Throwable $exception) {
    error_log(sprintf(
        'Loterias Online request failure: %s at %s:%d',
        get_class($exception),
        basename($exception->getFile()),
        $exception->getLine(),
    ));

    throw $exception;
}
