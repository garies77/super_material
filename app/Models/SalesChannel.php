<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesChannel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['allowed_hosts' => 'array', 'is_active' => 'boolean'];
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProductLink::class);
    }

    public function isWhatsapp(): bool
    {
        return $this->type === 'whatsapp';
    }
}
