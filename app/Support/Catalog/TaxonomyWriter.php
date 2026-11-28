<?php

namespace App\Support\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Tag;
use App\Support\Api\HtmlSanitizer;
use App\Support\SlugRedirects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penulisan kategori, merek, dan penggabungan tag lewat REST API admin dan MCP. Redirect 301 saat slug
 * kategori berubah berjalan lewat event model (TracksPublicUrl), sama dengan panel admin.
 */
class TaxonomyWriter
{
    private const CATEGORY_FIELDS = [
        'name', 'slug', 'intro', 'is_active', 'sort_order', 'whatsapp_number',
        'meta_title', 'meta_description', 'canonical_url', 'noindex', 'image',
    ];

    /**
     * Membuat (bila $category belum tersimpan) atau mengubah kategori.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: mixed, to: mixed}> ringkasan perubahan untuk audit log
     */
    public static function saveCategory(Category $category, array $data): array
    {
        foreach (self::CATEGORY_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $category->{$field} = $data[$field];
            }
        }
        if (array_key_exists('bottom_content', $data)) {
            $category->bottom_content = $data['bottom_content'] === null ? null : HtmlSanitizer::clean($data['bottom_content']);
        }
        if (array_key_exists('parent', $data)) {
            $category->parent_id = $data['parent'] ? Category::where('slug', $data['parent'])->value('id') : null;
        }
        if (blank($category->slug)) {
            $category->slug = self::uniqueSlug($category, (string) $category->name);
        }

        $changes = ProductWriter::diff($category);
        $category->save();

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function saveBrand(Brand $brand, array $data): array
    {
        foreach (['name', 'slug', 'logo'] as $field) {
            if (array_key_exists($field, $data)) {
                $brand->{$field} = $data[$field];
            }
        }
        if (blank($brand->slug)) {
            $brand->slug = self::uniqueSlug($brand, (string) $brand->name);
        }

        $changes = ProductWriter::diff($brand);
        $brand->save();

        return $changes;
    }

    /**
     * Pindahkan semua artikel dan produk dari tag sumber ke tag tujuan, hapus tag sumber, dan alihkan
     * URL tag lama ke URL tag tujuan (301) agar tautan dan peringkat Google tidak hilang.
     *
     * @return int jumlah artikel/produk yang dipindahkan ke tag tujuan
     */
    public static function mergeTags(Tag $source, Tag $target): int
    {
        return DB::transaction(function () use ($source, $target) {
            $existing = DB::table('taggables')->where('tag_id', $target->id)
                ->get(['taggable_type', 'taggable_id'])
                ->map(fn (object $row) => $row->taggable_type.'#'.$row->taggable_id)
                ->flip();

            $moved = DB::table('taggables')->where('tag_id', $source->id)->get(['taggable_type', 'taggable_id'])
                ->reject(fn (object $row) => $existing->has($row->taggable_type.'#'.$row->taggable_id))
                ->map(fn (object $row) => ['tag_id' => $target->id, 'taggable_type' => $row->taggable_type, 'taggable_id' => $row->taggable_id])
                ->values()->all();

            DB::table('taggables')->insert($moved);
            $source->delete();

            SlugRedirects::record(route('article.tag', $source->slug, false), route('article.tag', $target->slug, false));

            return count($moved);
        });
    }

    private static function uniqueSlug(Model $model, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        for ($n = 2; $model->newQuery()->where('slug', $slug)->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
