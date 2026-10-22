<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'reference_price' => 'integer',
            'discount_starts_at' => 'datetime',
            'discount_ends_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getSlugAttribute(?string $val): string
    {
        return $val ?: Str::slug($this->name);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }

        return asset('storage/'.ltrim((string) $this->image_path, '/'));
    }

    public function effectivePrice(): ?int
    {
        return $this->price ?? $this->reference_price;
    }

    public function effectiveCompareAtPrice(): ?int
    {
        $price = $this->effectivePrice();
        if (! $this->compare_at_price || ! $price || $this->compare_at_price <= $price) {
            return null;
        }

        $now = now();
        if ($this->discount_starts_at && $now->lt($this->discount_starts_at)) {
            return null;
        }
        if ($this->discount_ends_at && $now->gt($this->discount_ends_at)) {
            return null;
        }

        return (int) $this->compare_at_price;
    }

    public function discountPercentage(): ?int
    {
        $compare = $this->effectiveCompareAtPrice();
        $price = $this->effectivePrice();
        if (! $compare || ! $price || $compare <= $price) {
            return null;
        }

        return (int) round((($compare - $price) / $compare) * 100);
    }

    public function schemaPriceValidUntil(): string
    {
        if ($this->discount_ends_at && $this->discount_ends_at->isFuture()) {
            return $this->discount_ends_at->setTimezone('Asia/Jakarta')->toIso8601String();
        }

        if ($this->product && $this->product->price_valid_until && $this->product->price_valid_until->isFuture()) {
            return $this->product->price_valid_until->setTimezone('Asia/Jakarta')->toIso8601String();
        }

        return now()->addDays(30)->endOfDay()->setTimezone('Asia/Jakarta')->toIso8601String();
    }
}
