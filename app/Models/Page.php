<?php

namespace App\Models;

use App\Models\Concerns\GuardsCustomCode;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\TracksPublicUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Page extends Model
{
    use GuardsCustomCode, HasSeo, TracksPublicUrl;

    public const PUBLIC_ROUTE = 'page.show';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'noindex' => 'boolean',
            'blocks' => 'array',
            'draft_blocks' => 'array',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function url(): string
    {
        return route('page.show', $this->slug);
    }

    /** Ada perubahan di editor yang belum dipublikasikan ke halaman publik. */
    public function hasUnpublishedChanges(): bool
    {
        return $this->draft_blocks !== null && $this->draft_blocks != $this->blocks;
    }

    /** Terapkan salinan kerja ke halaman publik; HTML hasil render disimpan untuk meta dan pencarian. */
    public function publish(): void
    {
        $this->blocks = $this->draft_blocks ?? $this->blocks ?? [];
        $this->content = static::renderBlocks($this->blocks);
        $this->is_published = true;
        $this->save();
    }

    public static function renderBlocks(?array $blocks): string
    {
        return view('partials.page-blocks', ['blocks' => $blocks ?? []])->render();
    }

    public function seoName(): string
    {
        return $this->title;
    }

    public function seoFallbackDescription(): ?string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->content))), 158);
    }
}
