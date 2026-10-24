<?php

namespace App\Http\Resources\Api;

use App\Models\Category;
use Illuminate\Http\Request;

/**
 * Kategori dalam pohon (maksimal tiga level). Anak dimuat lewat relasi tree_children (PRD 8.12.5).
 *
 * @mixin Category
 */
class CategoryResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'parent' => $category->parent_id && $category->relationLoaded('parent') && $category->parent ? self::summary($category->parent) : null,
            'image_url' => $category->image ? (str_contains($category->image, '://') ? $category->image : asset('storage/'.ltrim($category->image, '/'))) : null,
            'url' => $category->url(),
            'children' => $this->when($category->relationLoaded('treeChildren'), fn () => self::collection($category->getRelation('treeChildren'))->resolve($request)),
        ];
    }

    /** @return array{slug: string, name: string} */
    public static function summary(Category $category): array
    {
        return ['slug' => $category->slug, 'name' => $category->name];
    }
}
