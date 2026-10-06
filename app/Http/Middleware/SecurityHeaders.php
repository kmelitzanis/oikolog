<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections, sent by the app itself so they hold whether
 * it runs behind the bundled nginx, another proxy or `artisan serve`.
 *
 * The content policy is deliberately narrow: it stops framing, foreign form
 * targets, <base> hijacking and plugins without touching scripts or styles,
 * which Alpine and the inline theme bootstrap still need.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'SAMEORIGIN',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            // The barcode scanner needs the camera; nothing needs the rest.
            'Permissions-Policy'      => 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()',
            'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'",
        ];

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
