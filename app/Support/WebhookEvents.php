<?php

namespace App\Support;

use App\Jobs\SendWebhook;
use App\Models\Article;
use App\Models\Product;
use App\Models\Webhook;
use Illuminate\Support\Str;

/**
 * Pemicu webhook keluar: membentuk payload dan mengantrekan satu SendWebhook per tujuan yang berlangganan event.
 * Dipanggil dari event model Product/Article dan perintah webhooks:announce-scheduled.
 */
class WebhookEvents
{
    /** @param  array<string, mixed>  $data */
    public static function dispatch(string $event, array $data): int
    {
        $webhooks = Webhook::query()->subscribedTo($event)->get(['id']);

        foreach ($webhooks as $webhook) {
            SendWebhook::dispatch($webhook->id, self::payload($event, $data));
        }

        return $webhooks->count();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{event: string, delivery_id: string, occurred_at: string, data: array<string, mixed>}
     */
    public static function payload(string $event, array $data): array
    {
        return [
            'event' => $event,
            'delivery_id' => (string) Str::uuid(),
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
        ];
    }

    /** Produk baru terbit, atau harga acuan produk terbit berubah. */
    public static function productSaved(Product $product): void
    {
        if (! $product->is_published) {
            return;
        }

        // wasRecentlyCreated tetap true pada penyimpanan berikutnya di objek yang sama; getChanges() kosong
        // hanya pada penyimpanan pertama (insert), jadi "baru dibuat" dibatasi ke sana
        $justCreated = $product->wasRecentlyCreated && $product->getChanges() === [];
        if ($product->wasChanged('is_published') || $justCreated) {
            self::dispatch('product.published', self::productData($product));

            return;
        }

        if ($product->wasChanged(['price_min', 'price_max'])) {
            self::dispatch('product.price_changed', self::productData($product) + [
                'previous' => ['price_min' => $product->getOriginal('price_min'), 'price_max' => $product->getOriginal('price_max')],
            ]);
        }
    }

    /**
     * Artikel yang sudah tayang (published_at ≤ sekarang) dan belum diumumkan. Artikel terjadwal diumumkan
     * oleh perintah webhooks:announce-scheduled saat waktunya tiba.
     */
    public static function announceArticle(Article $article): void
    {
        if ($article->announced_at || ! $article->published_at || $article->published_at->isFuture() || $article->trashed()) {
            return;
        }

        $article->forceFill(['announced_at' => now()])->saveQuietly();
        self::dispatch('article.published', [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->title,
            'summary' => $article->summary,
            'url' => $article->url(),
            'image_url' => $article->imageUrl(),
            'published_at' => $article->published_at->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    private static function productData(Product $product): array
    {
        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'url' => $product->url(),
            'price_min' => $product->price_min,
            'price_max' => $product->price_max,
            'availability' => $product->availability,
        ];
    }
}
