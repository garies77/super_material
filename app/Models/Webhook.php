<?php

namespace App\Models;

use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tujuan webhook keluar (mis. n8n, Zapier, server sendiri). Setiap kiriman ditandatangani HMAC-SHA256 dengan
 * secret milik webhook ini; penerima wajib memverifikasi header X-Supermaterial-Signature.
 */
class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use HasFactory;

    /** @var array<string, string> event => label */
    public const EVENTS = [
        'product.published' => 'Produk terbit',
        'product.price_changed' => 'Harga produk terbit berubah',
        'article.published' => 'Artikel terbit (termasuk yang terjadwal)',
    ];

    public const PING_EVENT = 'webhook.ping';

    // secret tidak disembunyikan ($hidden) karena form Filament membaca atribut lewat attributesToArray;
    // model ini tidak pernah diekspos lewat API, dan halamannya khusus Admin
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    /** @param  Builder<self>  $query */
    public function scopeSubscribedTo(Builder $query, string $event): void
    {
        $query->where('is_active', true)->whereJsonContains('events', $event);
    }
}
