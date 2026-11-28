<?php

namespace App\Support\Catalog;

use App\Filament\Support\SlugInput;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Aturan validasi kategori dan merek untuk REST API dan MCP, setara dengan formulir Filament:
 * slug mengikuti ProductRules::SLUG_PATTERN, kategori maksimal tiga level dan tidak boleh berinduk ke turunannya.
 */
class TaxonomyRules
{
    public const MAX_CATEGORY_DEPTH = 3;

    /** @return array<string, mixed> */
    public static function category(?Category $category = null): array
    {
        $creating = $category === null;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:'.ProductRules::SLUG_PATTERN, Rule::unique('categories', 'slug')->ignore($category?->id)],
            'parent' => ['sometimes', 'nullable', 'string', Rule::exists('categories', 'slug')],
            'intro' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'bottom_content' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]+$/'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:70'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'canonical_url' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'noindex' => ['sometimes', 'boolean'],
        ];
    }

    /** Induk tidak boleh kategori itu sendiri atau turunannya, dan kategori baru tidak boleh lebih dari tiga level. */
    public static function categoryAfter(Validator $validator, ?Category $category = null): void
    {
        $parentSlug = $validator->getData()['parent'] ?? null;
        if (! $parentSlug || $validator->errors()->has('parent')) {
            return;
        }

        $parent = Category::firstWhere('slug', $parentSlug);
        if ($category && in_array($parent->id, $category->descendantIds(), true)) {
            $validator->errors()->add('parent', 'Kategori tidak boleh berinduk ke dirinya sendiri atau turunannya.');
        } elseif ($parent->depth() >= self::MAX_CATEGORY_DEPTH) {
            $validator->errors()->add('parent', 'Kategori maksimal '.self::MAX_CATEGORY_DEPTH.' level; pilih induk yang lebih tinggi.');
        }
    }

    /** @return array<string, mixed> */
    public static function brand(?Brand $brand = null): array
    {
        return [
            'name' => [$brand === null ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:'.ProductRules::SLUG_PATTERN, Rule::unique('brands', 'slug')->ignore($brand?->id)],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['slug.regex' => SlugInput::MESSAGE];
    }
}
