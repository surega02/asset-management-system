<?php

use App\Http\Middleware\ProtectIdentitySession;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) env('AMS_TRUSTED_PROXIES', '')))));
        if ($proxies !== []) {
            $middleware->trustProxies(at: $proxies, headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        }
        $middleware->web(append: [ProtectIdentitySession::class, SetLocale::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ProtectIdentitySession::class);
        $middleware->redirectUsersTo('/workspace');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['login', 'code', 'recovery_code', 'reset_code']);
    })
    ->create();
