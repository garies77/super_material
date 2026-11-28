<?php

namespace App\Support;

use Closure;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * Unduh berkas dari URL publik agar asisten AI (MCP) bisa mengirim gambar lewat tautan, bukan unggahan.
 * Pengaman SSRF: hanya https port 443, setiap host (termasuk tujuan redirect) harus mengarah ke IP publik,
 * dan koneksi dikunci ke IP yang sudah diperiksa (CURLOPT_RESOLVE) agar tidak bisa dibelokkan lewat DNS rebinding.
 * Hasilnya UploadedFile sementara sehingga validasi dan penyimpanan memakai jalur unggahan yang sudah ada.
 */
class RemoteFile
{
    public const MAX_REDIRECTS = 3;

    private const CHUNK_BYTES = 65536;

    /** @var (Closure(string): array<int, string>)|null */
    private static ?Closure $resolver = null;

    /**
     * Ganti penerjemah nama host → daftar IP (untuk tes). null = DNS sistem.
     *
     * @param  (Closure(string): array<int, string>)|null  $resolver
     */
    public static function resolveHostsUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * @param  string  $attribute  Nama argumen untuk pesan error validasi.
     *
     * @throws ValidationException
     */
    public static function download(string $url, int $maxKilobytes, string $attribute = 'url'): UploadedFile
    {
        $maxBytes = $maxKilobytes * 1024;
        $temporaryPath = tempnam(sys_get_temp_dir(), 'remote-');

        try {
            for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
                [$host, $ip] = self::guard($url, $attribute);
                $tooLarge = false;

                try {
                    // Body langsung ditulis ke berkas sementara; unduhan dihentikan begitu melewati batas ukuran
                    $response = Http::timeout(20)->connectTimeout(5)->withoutRedirecting()
                        ->withUserAgent('SupermaterialBot/1.0 (+'.url('/').')')
                        ->withOptions([
                            'sink' => $temporaryPath,
                            'progress' => function (int $expected, int $received) use ($maxBytes, &$tooLarge) {
                                if ($expected > $maxBytes || $received > $maxBytes) {
                                    $tooLarge = true;
                                    throw new RuntimeException('Unduhan melewati batas ukuran.');
                                }
                            },
                            'curl' => [CURLOPT_RESOLVE => [sprintf('%s:443:%s', $host, str_contains($ip, ':') ? "[{$ip}]" : $ip)]],
                        ])
                        ->get($url);
                } catch (Throwable $exception) {
                    if ($tooLarge) {
                        self::fail($attribute, "Berkas lebih besar dari {$maxKilobytes} KB.");
                    }
                    if ($exception instanceof ConnectionException || $exception instanceof TransferException) {
                        self::fail($attribute, 'Server tujuan tidak bisa dihubungi atau terlalu lama merespons.');
                    }

                    throw $exception;
                }

                if ($response->redirect()) {
                    $url = self::redirectTarget($url, (string) $response->header('Location'), $attribute);

                    continue;
                }

                if (! $response->successful()) {
                    self::fail($attribute, "URL mengembalikan status HTTP {$response->status()}.");
                }

                // Klien palsu di tes tidak menulis ke sink: salin body ke berkas dengan batas yang sama
                clearstatcache(true, $temporaryPath);
                if (filesize($temporaryPath) === 0) {
                    self::copyBody($response->toPsrResponse()->getBody(), $temporaryPath, $maxBytes, $maxKilobytes, $attribute);
                }

                $size = filesize($temporaryPath);
                if ($size > $maxBytes) {
                    self::fail($attribute, "Berkas lebih besar dari {$maxKilobytes} KB.");
                }
                if ($size === 0) {
                    self::fail($attribute, 'Berkas dari URL kosong.');
                }

                $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'berkas';
                $file = new UploadedFile($temporaryPath, $name, Str::before((string) $response->header('Content-Type'), ';') ?: null, null, true);
                $temporaryPath = null;

                return $file;
            }

            self::fail($attribute, 'Terlalu banyak redirect (maks. '.self::MAX_REDIRECTS.').');
        } finally {
            if ($temporaryPath !== null) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * @return array{0: string, 1: string} host dan IP publik yang dipakai untuk koneksi
     *
     * @throws ValidationException
     */
    private static function guard(string $url, string $attribute): array
    {
        $parts = parse_url($url) ?: [];
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            self::fail($attribute, 'Hanya URL https publik yang diterima.');
        }
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            self::fail($attribute, 'URL tidak boleh memakai port selain 443.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);
        if ($ips === []) {
            self::fail($attribute, 'Nama domain pada URL tidak ditemukan.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                self::fail($attribute, 'URL mengarah ke alamat jaringan internal dan ditolak.');
            }
        }

        return [$host, $ips[0]];
    }

    /** @return array<int, string> */
    private static function resolve(string $host): array
    {
        if (self::$resolver) {
            return array_values((self::$resolver)($host));
        }

        $ipv4 = gethostbynamel($host) ?: [];
        $ipv6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_merge($ipv4, $ipv6));
    }

    /** @throws ValidationException */
    private static function redirectTarget(string $from, string $location, string $attribute): string
    {
        if ($location === '') {
            self::fail($attribute, 'Redirect tanpa alamat tujuan.');
        }
        if (Str::startsWith($location, '//')) {
            return 'https:'.$location;
        }
        if (Str::startsWith($location, '/')) {
            return 'https://'.parse_url($from, PHP_URL_HOST).$location;
        }

        return $location;
    }

    /** @throws ValidationException */
    private static function copyBody(StreamInterface $body, string $path, int $maxBytes, int $maxKilobytes, string $attribute): void
    {
        $handle = fopen($path, 'wb');
        $written = 0;

        try {
            while (! $body->eof()) {
                $chunk = $body->read(self::CHUNK_BYTES);
                $written += strlen($chunk);
                if ($written > $maxBytes) {
                    self::fail($attribute, "Berkas lebih besar dari {$maxKilobytes} KB.");
                }
                fwrite($handle, $chunk);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @throws ValidationException */
    private static function fail(string $attribute, string $message): never
    {
        throw ValidationException::withMessages([$attribute => $message]);
    }
}
