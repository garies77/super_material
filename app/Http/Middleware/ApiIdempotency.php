<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use App\Support\Api\ApiError;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header Idempotency-Key pada POST admin: pengulangan dengan kunci sama dalam 24 jam
 * mengembalikan hasil semula tanpa membuat data ganda (PRD 8.12.4).
 */
class ApiIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        $tokenId = $request->user()?->currentAccessToken()?->id;

        if (! $request->isMethod('POST') || $key === '' || ! $tokenId) {
            return $next($request);
        }

        if (strlen($key) > 255) {
            return ApiError::response(422, 'Idempotency-Key maksimal 255 karakter.', 'validation_failed');
        }

        $hash = $this->fingerprint($request);
        IdempotencyKey::where('token_id', $tokenId)->where('key', $key)
            ->where('created_at', '<', now()->subHours(config('api.idempotency.ttl_hours', 24)))
            ->delete();

        try {
            $record = IdempotencyKey::create([
                'token_id' => $tokenId,
                'key' => $key,
                'request_hash' => $hash,
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->replay($tokenId, $key, $hash);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $record->delete();
            throw $e;
        }

        // Hanya hasil final yang disimpan; galat server dan batas laju boleh diulang
        if ($response->getStatusCode() >= 500 || $response->getStatusCode() === 429) {
            $record->delete();
        } else {
            $record->update([
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);
        }

        return $response;
    }

    private function replay(int $tokenId, string $key, string $hash): Response
    {
        $record = IdempotencyKey::where('token_id', $tokenId)->where('key', $key)->first();

        if (! $record || $record->request_hash !== $hash) {
            return ApiError::response(409, 'Idempotency-Key sudah dipakai untuk permintaan yang berbeda.', 'idempotency_key_reused');
        }

        if ($record->status_code === null) {
            return ApiError::response(409, 'Permintaan dengan Idempotency-Key ini masih diproses.', 'idempotency_in_progress');
        }

        return response((string) $record->response_body, $record->status_code, [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }

    private function fingerprint(Request $request): string
    {
        $files = collect($request->allFiles())->flatten()
            ->map(fn ($f) => $f->getClientOriginalName().':'.$f->getSize().':'.sha1_file($f->getRealPath()))
            ->all();

        return hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($request->post()).'|'.json_encode($files));
    }
}
