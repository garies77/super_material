<?php

namespace App\Jobs;

use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Mengirim satu event ke satu webhook. Tanda tangan: HMAC-SHA256 atas "{timestamp}.{body}" dengan secret webhook,
 * dikirim di X-Supermaterial-Signature (format "sha256=..."). Gagal (bukan 2xx atau tidak terhubung) dicoba ulang.
 */
class SendWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> detik sebelum percobaan ke-2, 3, 4, 5 */
    public array $backoff = [60, 300, 900, 3600];

    /** @param  array{event: string, delivery_id: string, occurred_at: string, data: array<string, mixed>}  $payload */
    public function __construct(public int $webhookId, public array $payload) {}

    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);
        if (! $webhook || (! $webhook->is_active && $this->payload['event'] !== Webhook::PING_EVENT)) {
            return;
        }

        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;

        try {
            $response = Http::timeout(10)->connectTimeout(5)
                ->withHeaders(self::headers($this->payload, $timestamp, $body, (string) $webhook->secret))
                ->withBody($body, 'application/json')
                ->post($webhook->url);
        } catch (Throwable $exception) {
            $this->record($webhook, null, Str::limit($exception->getMessage(), 480));

            throw $exception;
        }

        if (! $response->successful()) {
            $this->record($webhook, $response->status(), 'Respons HTTP '.$response->status().': '.Str::limit($response->body(), 400));

            throw new RuntimeException("Webhook {$webhook->id} merespons HTTP {$response->status()}.");
        }

        $this->record($webhook, $response->status(), null);
    }

    /**
     * @param  array{event: string, delivery_id: string}  $payload
     * @return array<string, string>
     */
    public static function headers(array $payload, string $timestamp, string $body, string $secret): array
    {
        return [
            'User-Agent' => 'SupermaterialWebhook/1.0',
            'X-Supermaterial-Event' => $payload['event'],
            'X-Supermaterial-Delivery' => $payload['delivery_id'],
            'X-Supermaterial-Timestamp' => $timestamp,
            'X-Supermaterial-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ];
    }

    private function record(Webhook $webhook, ?int $status, ?string $error): void
    {
        $webhook->forceFill(['last_status' => $status, 'last_error' => $error, 'last_sent_at' => now()])->saveQuietly();
    }
}
