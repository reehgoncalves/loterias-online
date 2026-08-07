<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

$apiPrefix = getenv('VERCEL') ? '' : 'api';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up', apiPrefix: $apiPrefix)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: ['api/*', 'stripe/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->expectsJson()) {
                if ($e instanceof ValidationException) return new \Illuminate\Http\JsonResponse(['message' => 'Os dados enviados são inválidos.', 'errors' => $e->errors()], 422);
                $status = method_exists($e, 'getStatusCode') && is_int($e->getStatusCode()) ? $e->getStatusCode() : 500;
                return new \Illuminate\Http\JsonResponse(['message' => $e->getMessage() ?: 'Erro interno.'], $status);
            }
        });
    })->create();
