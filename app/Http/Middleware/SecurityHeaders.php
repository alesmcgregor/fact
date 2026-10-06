<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $localDevelopment = app()->environment('local');
        $scriptPolicy = $localDevelopment ? "'self' 'unsafe-inline' http://localhost:5173 ws://localhost:5173" : "'self'";
        $stylePolicy = $localDevelopment ? "'self' 'unsafe-inline' http://localhost:5173" : "'self'";

        $connectPolicy = $localDevelopment ? "'self' http://localhost:5173 ws://localhost:5173" : "'self'";

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; img-src 'self' data:; font-src 'self'; script-src {$scriptPolicy}; style-src {$stylePolicy}; connect-src {$connectPolicy}",
        );
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
