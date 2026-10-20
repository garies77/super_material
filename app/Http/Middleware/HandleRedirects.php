<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/** Redirect 301/302 yang dikelola dari admin (termasuk otomatis saat slug berubah). */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->is('admin*', 'livewire*', 'go/*')) {
            $map = Cache::remember('redirects.map', 600, fn () => Redirect::query()->get(['from_path', 'to_path', 'code'])->keyBy('from_path')->all());
            $path = '/'.ltrim($request->path(), '/');

            if ($hit = $map[$path] ?? null) {
                $to = $hit->to_path;
                if (str_starts_with($to, '/')) {
                    $to = url($to);
                }

                return redirect()->to($to, (int) $hit->code);
            }
        }

        return $next($request);
    }
}
