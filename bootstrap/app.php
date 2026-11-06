<?php

use App\Http\Middleware\SetPreviewLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetPreviewLocale::class]);
    })
    ->withExceptions()
    ->create();
