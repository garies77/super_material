<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    /**
     * URL marketplace yang aman. Null bila host tidak ada di daftar yang diizinkan
     * oleh kanal (mencegah /go/ dipakai sebagai open redirect).
     */
    public function safeUrl(): ?string
    {
        $channel = $this->channel;
        if (! $channel || ! $this->is_active || ! $this->url) {
            return null;
        }

        $host = strtolower((string) parse_url($this->url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($this->url, PHP_URL_SCHEME));
        if (! $host || ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        foreach ($channel->allowed_hosts ?: [] as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.'.$allowed))) {
                return $this->url;
            }
        }

        return null;
    }
}
