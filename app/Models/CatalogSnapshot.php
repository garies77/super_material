<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Potret harian kesehatan katalog, dibuat App\Support\Reports\CatalogHealth::snapshot().
 *
 * @property array{products: int, articles: int, complete: int, issues: array<string, int>, product_seo: array{avg: float, buckets: array<string, int>}, article_seo: array{avg: float, buckets: array<string, int>}} $metrics
 */
class CatalogSnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'metrics' => 'array'];
    }
}
