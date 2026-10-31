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
            // Simpan array biasa, bukan model: Laravel menolak unserialize objek dari cache (cache.serializable_classes)
            $map = Cache::remember('redirects.map', 600, fn () => Redirect::query()->get(['from_path', 'to_path', 'code'])
                ->mapWithKeys(fn (Redirect $redirect) => [$redirect->from_path => ['to_path' => $redirect->to_path, 'code' => (int) $redirect->code]])
                ->all());
            $path = '/'.ltrim($request->path(), '/');

            if ($hit = $map[$path] ?? null) {
                $to = $hit['to_path'];
                if (str_starts_with($to, '/')) {
                    $to = url($to);
                }

                return redirect()->to($to, $hit['code']);
            }
        }

        return $next($request);
    }
}
