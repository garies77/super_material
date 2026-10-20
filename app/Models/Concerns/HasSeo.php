<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyediakan metadata SEO dengan fallback template bila kolom meta kosong.
 */
trait HasSeo
{
    abstract public function seoName(): string;

    abstract public function seoFallbackDescription(): ?string;

    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->seoName().' | '.config('app.name');
    }

    public function seoDescription(): string
    {
        $text = $this->meta_description ?: ($this->seoFallbackDescription() ?: '');

        return Str::limit(trim(strip_tags($text)), 158);
    }

    public function seoImage(): ?string
    {
        $path = $this->og_image ?: ($this->image ?? null);

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
