<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', 'fa');

        if (! in_array($locale, ['fa', 'en'], true)) {
            $locale = 'fa';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
