<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $locale = $request->user()?->locale ?? $session->get('locale', $session->get('preview_locale', config('app.locale')));
        App::setLocale(is_string($locale) && isset(Locale::OPTIONS[$locale]) ? $locale : 'id');

        return $next($request);
    }
}
