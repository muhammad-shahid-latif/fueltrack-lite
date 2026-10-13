<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turbo prefetches links on hover with an "X-Sec-Purpose: prefetch" header.
 * Laravel only knows the standard "Purpose" header, so without this a hovered
 * link would become the "previous URL" that back() and failed validation return to.
 */
class RecognizeTurboPrefetch
{
    public function handle(Request $request, Closure $next): Response
    {
        if (strcasecmp($request->headers->get('X-Sec-Purpose') ?? '', 'prefetch') === 0) {
            $request->headers->set('Purpose', 'prefetch');
        }

        return $next($request);
    }
}
