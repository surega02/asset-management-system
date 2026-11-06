<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetPreviewLocale
{
    public const SUPPORTED = ['id', 'en', 'zh-CN'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('preview_locale', 'id');
        App::setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'id');

        return $next($request);
    }
}
