<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jumlah tayangan halaman produk/artikel per hari (viewable_type: product|article). */
class PageViewStat extends Model
{
    public const PRODUCT = 'product';

    public const ARTICLE = 'article';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'views' => 'integer'];
    }
}
