<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ApiCollection;
use App\Http\Resources\Api\ApiResource;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Konvensi bersama API v1 (PRD 8.12.4): paginasi cursor, include dan sort dengan daftar putih.
 */
abstract class ApiController extends Controller
{
    protected bool $admin = false;

    protected function perPage(Request $request): int
    {
        $max = config($this->admin ? 'api.per_page.admin_max' : 'api.per_page.public_max');
        $value = $request->query('per_page', config('api.per_page.default'));

        if (! is_numeric($value) || (int) $value < 1 || (int) $value > $max) {
            throw ValidationException::withMessages(['per_page' => ["per_page harus antara 1 dan {$max}."]]);
        }

        return (int) $value;
    }

    /**
     * Relasi yang diminta lewat include=a,b; nilai di luar daftar putih ditolak 422.
     *
     * @param  array<int, string>  $allowed
     * @return array<int, string>
     */
    protected function includes(Request $request, array $allowed): array
    {
        $raw = $request->query('include');
        $requested = is_string($raw) && $raw !== '' ? array_values(array_filter(array_map('trim', explode(',', $raw)))) : [];

        $invalid = array_diff($requested, $allowed);
        if ($invalid) {
            throw ValidationException::withMessages(['include' => ['Relasi tidak dikenal: '.implode(', ', $invalid).'. Pilihan: '.implode(', ', $allowed).'.']]);
        }

        $request->attributes->set('api_includes', $requested);

        return $requested;
    }

    /** Untuk detail: seluruh relasi ditampilkan. */
    protected function includeAll(Request $request, array $relations): void
    {
        $request->attributes->set('api_includes', $relations);
    }

    /**
     * Terapkan sort=kolom atau sort=-kolom dari daftar putih; id selalu jadi penentu akhir agar cursor stabil.
     *
     * @param  array<string, string|callable>  $allowed  kunci sort → kolom atau callback(Builder, string $direction)
     */
    public function applySort(Builder $query, Request $request, array $allowed, string $default): void
    {
        $sort = $request->query('sort', $default);
        if (! is_string($sort) || $sort === '') {
            $sort = $default;
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $key = ltrim($sort, '-');

        if (! array_key_exists($key, $allowed)) {
            throw ValidationException::withMessages(['sort' => ['Urutan tidak dikenal. Pilihan: '.implode(', ', array_keys($allowed)).' (awali dengan - untuk menurun).']]);
        }

        $column = $allowed[$key];
        $column instanceof \Closure ? $column($query, $direction) : $query->orderBy($column, $direction);

        $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
    }

    /** @param class-string<ApiResource> $resource */
    protected function paginate(Builder $query, Request $request, string $resource): ApiCollection
    {
        $perPage = $this->perPage($request);
        $total = (clone $query)->reorder()->count();

        return $resource::collection($query->cursorPaginate($perPage)->withQueryString())->withTotal($total);
    }

    /** @return array<string, mixed> */
    protected function filters(Request $request): array
    {
        $filters = $request->query('filter', []);

        return is_array($filters) ? array_filter($filters, fn ($v) => is_scalar($v) && $v !== '') : [];
    }

    protected function withLastModified(Response $response, ?DateTimeInterface $date): Response
    {
        if ($date) {
            $response->setLastModified(Carbon::instance($date));
        }

        return $response;
    }
}
