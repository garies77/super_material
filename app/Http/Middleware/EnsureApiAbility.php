<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemeriksaan izin di backend: ability token DAN peran pengguna harus sama-sama mengizinkan (PRD 8.12.3).
 */
class EnsureApiAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();
        if (! $user) {
            return ApiError::response(401);
        }

        if (! $user->tokenCan($ability)) {
            return ApiError::response(403, "Token tidak memiliki ability {$ability}.", 'insufficient_ability');
        }

        if (! $user->canUseApiAbility($ability)) {
            return ApiError::response(403, "Peran pengguna tidak mengizinkan {$ability}.", 'insufficient_role');
        }

        return $next($request);
    }
}
