<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jumlah pencarian per hari per kata kunci; results = jumlah hasil pada pencarian terakhir hari itu. */
class SearchStat extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'searches' => 'integer', 'results' => 'integer'];
    }
}
