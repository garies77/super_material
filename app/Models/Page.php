<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function url(): string
    {
        return route('page.show', $this->slug);
    }
}
