<?php

namespace App\Support\Content;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Support\Api\HtmlSanitizer;
use App\Support\Catalog\ProductWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penulisan artikel lewat REST API admin dan MCP. Efek samping (redirect 301 saat slug berubah, aturan
 * penulis untuk peran Penulis, tanggal pembaruan konten) berjalan lewat event model yang sama dengan panel admin.
 * Isi HTML disanitasi; artikel baru selalu draf kecuali status "published" dikirim.
 */
class ArticleWriter
{
    private const SCALAR_FIELDS = [
        'title', 'slug', 'summary', 'schema_type', 'image_caption', 'image_credit', 'is_featured',
        'meta_title', 'meta_description', 'canonical_url', 'noindex', 'nofollow',
    ];

    /** @var array<int, string> */
    public const RELATIONS = ['categories', 'tags', 'products', 'faqs'];

    /** @param  array<string, mixed>  $data */
    public static function create(array $data, User $user): Article
    {
        return DB::transaction(function () use ($data, $user) {
            $article = new Article(['schema_type' => 'BlogPosting']);
            self::fill($article, $data + ['status' => 'draft'], $user);
            $article->slug = filled($data['slug'] ?? null) ? $data['slug'] : self::uniqueSlug($article->title);
            $article->author_id ??= $user->authorProfile()->id;
            $article->save();

            self::syncRelations($article, $data);

            return $article->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: mixed, to: mixed}> ringkasan perubahan untuk audit log
     */
    public static function update(Article $article, array $data, User $user): array
    {
        return DB::transaction(function () use ($article, $data, $user) {
            self::fill($article, $data, $user);
            $changes = ProductWriter::diff($article);
            $article->save();

            self::syncRelations($article, $data);
            foreach (self::RELATIONS as $relation) {
                if (array_key_exists($relation, $data)) {
                    $changes[$relation] = ['from' => null, 'to' => count($data[$relation]).' item'];
                }
            }

            return $changes;
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'artikel';
        $slug = $base;
        for ($n = 2; Article::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    /** @param  array<string, mixed>  $data */
    private static function fill(Article $article, array $data, User $user): void
    {
        foreach (self::SCALAR_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $article->{$field} = $data[$field];
            }
        }

        if (array_key_exists('content', $data)) {
            $article->content = HtmlSanitizer::clean($data['content']);
        }

        // Penulis & Kontributor selalu menulis atas nama profilnya sendiri (ditegakkan juga di model)
        if (array_key_exists('author', $data) && ! $user->writesAsOwnAuthor()) {
            $article->author_id = $data['author']
                ? Author::where('slug', $data['author'])->value('id')
                : $user->authorProfile()->id;
        }

        $publishAt = filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at']) : null;

        if (array_key_exists('status', $data)) {
            if ($data['status'] === 'draft') {
                $article->markAsDraft();
            } else {
                $article->published_at = $publishAt ?? $article->published_at ?? now();
            }
        } elseif (array_key_exists('published_at', $data)) {
            $article->published_at = $publishAt;
        }
    }

    /** @param  array<string, mixed>  $data */
    private static function syncRelations(Article $article, array $data): void
    {
        if (array_key_exists('categories', $data)) {
            $article->categories()->sync(Category::whereIn('slug', $data['categories'])->pluck('id'));
        }

        if (array_key_exists('products', $data)) {
            $article->products()->sync(Product::whereIn('slug', $data['products'])->pluck('id'));
        }

        if (array_key_exists('tags', $data)) {
            $tagIds = collect($data['tags'])
                ->map(fn (string $name) => trim($name))
                ->filter(fn (string $name) => Str::slug($name) !== '')
                ->map(fn (string $name) => Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id)
                ->unique();
            $article->tags()->sync($tagIds);
        }

        if (array_key_exists('faqs', $data)) {
            $article->faqs()->delete();
            foreach (array_values($data['faqs']) as $i => $faq) {
                $article->faqs()->create(['question' => $faq['question'], 'answer' => $faq['answer'], 'sort_order' => $i]);
            }
        }
    }
}
