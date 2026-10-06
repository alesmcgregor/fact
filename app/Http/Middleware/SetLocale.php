<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', $request->cookie('fact_locale', config('app.locale')));

        if (! array_key_exists((string) $locale, config('fact.locales'))) {
            $locale = config('app.locale');
        }

        App::setLocale((string) $locale);

        return $next($request);
    }
}
