<?php

namespace App\Http\Middleware;

use App\Support\Brand;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the dealer's fuel network theme for this request.
 */
class ResolveBrand
{
    public function handle(Request $request, Closure $next): Response
    {
        Brand::setCurrent(Brand::resolve($request));

        return $next($request);
    }
}
