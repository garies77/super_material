<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Dokumentasi endpoint admin hanya untuk Admin yang sedang login di panel (PRD 8.12.9). */
class EnsureAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }

        abort_unless($user->is_active && $user->isAdmin(), 403);

        return $next($request);
    }
}
