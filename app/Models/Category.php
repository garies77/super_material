<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasSeo;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['noindex' => 'boolean', 'is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_category');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Kedalaman 1 = akar. */
    public function depth(): int
    {
        $d = 1;
        $node = $this;
        while ($node->parent_id && $d < 10) {
            $node = $node->parent;
            $d++;
        }

        return $d;
    }

    /** @return array<int, self> daftar dari akar ke kategori ini */
    public function breadcrumbs(): array
    {
        $trail = [];
        $node = $this;
        while ($node) {
            array_unshift($trail, $node);
            $node = $node->parent;
        }

        return $trail;
    }

    /** ID kategori ini beserta seluruh turunannya. */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $queue = [$this->id];
        while ($queue) {
            $children = self::whereIn('parent_id', $queue)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $queue = $children;
        }

        return $ids;
    }

    public function url(): string
    {
        return route('category.show', $this->slug);
    }

    public function seoName(): string
    {
        return $this->name;
    }

    public function seoFallbackDescription(): ?string
    {
        return $this->intro ?: "Daftar {$this->name} lengkap dengan harga acuan dan tautan beli di marketplace.";
    }
}
