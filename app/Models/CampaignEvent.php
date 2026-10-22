<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Impresi, klik CTA, atau penutupan modal kampanye. Tidak menyimpan IP mentah (PRD 8.9). */
class CampaignEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['impression', 'click', 'close'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_bot' => 'boolean', 'created_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
