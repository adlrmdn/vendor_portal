<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\PreventBackHistory::class,
        ]);
        // Machine-to-machine callback (automaton/pw_service/debit_note) —
        // no browser session, so no CSRF token to send. Auth is the
        // X-RPA-Token header (RpaFinalizeController).
        $middleware->validateCsrfTokens(except: [
            'rpa/deduction/*/finalize',
        ]);
        // This app boots via this file (Laravel 11+ style), not app/Http/Kernel.php
        // — that class is unreferenced dead code, so its middlewareAliases array
        // (including 'role') was never actually registered. Route groups in
        // routes/web.php depend on 'role:<roles>' resolving, so it's aliased here.
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
