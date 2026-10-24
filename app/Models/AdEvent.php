<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Impresi, klik, atau penutupan pop-up kampanye. Tidak menyimpan IP mentah (PRD 8.8). */
class AdEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['impression', 'click', 'close'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_bot' => 'boolean', 'created_at' => 'datetime'];
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
