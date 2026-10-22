<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use App\Models\Concerns\TracksPublicUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasSeo, TracksPublicUrl;

    public const PUBLIC_ROUTE = 'page.show';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'noindex' => 'boolean',
        ];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function url(): string
    {
        return route('page.show', $this->slug);
    }

    public function seoName(): string
    {
        return $this->title;
    }

    public function seoFallbackDescription(): ?string
    {
        return Str::limit(strip_tags((string) $this->content), 158);
    }
}
