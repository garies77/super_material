<?php

namespace App\Http\Resources\Api;

use App\Models\Brand;
use Illuminate\Http\Request;

/** @mixin Brand */
class BrandResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        /** @var Brand $brand */
        $brand = $this->resource;

        return [
            'id' => $brand->id,
            'slug' => $brand->slug,
            'name' => $brand->name,
            'logo_url' => self::logoUrl($brand),
            'product_count' => (int) ($brand->products_count ?? 0),
        ];
    }

    /** @return array{slug: string, name: string} */
    public static function summary(Brand $brand): array
    {
        return ['slug' => $brand->slug, 'name' => $brand->name];
    }

    private static function logoUrl(Brand $brand): ?string
    {
        if (! $brand->logo) {
            return null;
        }

        return str_contains($brand->logo, '://') ? $brand->logo : asset('storage/'.ltrim($brand->logo, '/'));
    }
}
