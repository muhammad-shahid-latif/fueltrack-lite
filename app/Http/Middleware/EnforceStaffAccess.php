<?php

namespace App\Http\Middleware;

use App\Support\StaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the manager from modules the owner switched off in Setup → Manager access.
 */
class EnforceStaffAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route()?->getName();

        if (StaffAccess::allowsRoute($request->user(), $route)) {
            return $next($request);
        }

        $message = 'The owner has turned off '.StaffAccess::labelFor($route).' for your account.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('dashboard')->with('warning', $message);
    }
}
