<?php

namespace App\Models;

use App\Models\Concerns\TracksPublicUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    use TracksPublicUrl;

    public const PUBLIC_ROUTE = 'author.show';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'same_as' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    public function publishedArticles(): HasMany
    {
        return $this->articles()->published();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Penegakan kemurnian nama penulis (PRD 9.13.2 & SEO-02).
     * Menghapus awalan redaksional seperti "Ditulis oleh:", "Posted by:", dsb.
     */
    public function setNameAttribute(?string $value): void
    {
        $cleaned = preg_replace('/^(ditulis oleh|posted by|oleh|penulis|author)\s*[:\-]?\s*/i', '', trim((string) $value));
        $this->attributes['name'] = $cleaned;
    }

    /**
     * Nama tampilan lengkap untuk HTML publik (memuat gelar awalan & akhiran).
     */
    public function displayName(): string
    {
        $parts = [];
        if ($this->honorific_prefix) {
            $parts[] = trim($this->honorific_prefix);
        }
        $parts[] = $this->name;
        $title = implode(' ', $parts);
        if ($this->honorific_suffix) {
            $title .= ', '.trim($this->honorific_suffix);
        }

        return $title;
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        return asset('storage/'.ltrim((string) $this->avatar, '/'));
    }

    public function url(): string
    {
        return route('author.show', $this->slug);
    }
}
