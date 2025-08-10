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
        /**
         * Register route middleware aliases here
         * so you can use them like: middleware(['role:admin'])
         */
        $middleware->alias([
            'auth.jwt' => \App\Http\Middleware\JWTMiddleware::class,
            'auth.user' => \App\Http\Middleware\AuthMiddleware::class,
            'auth.admin' =>  \App\Http\Middleware\AdminMiddleware::class,
            'auth.agent' =>  \App\Http\Middleware\AgentMiddleware::class,
            'auth.merchant' =>  \App\Http\Middleware\MerchantMiddleware::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
