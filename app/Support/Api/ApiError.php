<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

/** Bentuk galat seragam API: {"error": {"code", "message", "fields"?}} (PRD 8.12.4). */
class ApiError
{
    public const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        415 => 'unsupported_media_type',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        500 => 'server_error',
        503 => 'service_unavailable',
    ];

    public const MESSAGES = [
        400 => 'Permintaan tidak valid.',
        401 => 'Token tidak ada, tidak valid, atau kedaluwarsa.',
        403 => 'Token atau peran tidak memiliki izin untuk tindakan ini.',
        404 => 'Data tidak ditemukan.',
        405 => 'Metode tidak didukung.',
        409 => 'Permintaan bertentangan dengan keadaan data saat ini.',
        413 => 'Berkas terlalu besar.',
        415 => 'Jenis konten tidak didukung.',
        422 => 'Data tidak valid.',
        429 => 'Terlalu banyak permintaan. Coba lagi nanti.',
        500 => 'Terjadi kesalahan pada server.',
        503 => 'Layanan sedang dalam pemeliharaan.',
    ];

    public static function response(int $status, ?string $message = null, ?string $code = null, array $fields = [], array $headers = []): JsonResponse
    {
        $error = [
            'code' => $code ?? (self::CODES[$status] ?? 'error'),
            'message' => $message ?: (self::MESSAGES[$status] ?? 'Terjadi kesalahan.'),
        ];
        if ($fields) {
            $error['fields'] = $fields;
        }

        return new JsonResponse(['error' => $error], $status, $headers);
    }
}
