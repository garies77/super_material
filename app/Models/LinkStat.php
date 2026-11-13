<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Agregat harian link-in-bio. link_item_id = 0 adalah tayangan halaman; selain itu klik pada tombol tersebut. */
class LinkStat extends Model
{
    public const PAGE_VIEW = 0;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'count' => 'integer', 'link_item_id' => 'integer'];
    }

    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }
}
