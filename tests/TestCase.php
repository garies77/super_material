<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Config yang di-cache (php artisan optimize) mengabaikan DB :memory: dari phpunit.xml, sehingga
        // RefreshDatabase mengosongkan database lokal. Cek sebelum parent::setUp(), karena migrasi berjalan di sana.
        if (file_exists(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Test dihentikan sebelum menyentuh database: config ter-cache. Jalankan "php artisan optimize:clear" lalu ulangi.');
        }

        parent::setUp();

        // Selain :memory:, hanya database tes sekali pakai berakhiran "_test" (mis. MariaDB di GitHub Actions)
        $database = (string) config('database.connections.'.config('database.default').'.database');
        if ($database !== ':memory:' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException('Test hanya boleh memakai database :memory: atau database berakhiran "_test" (lihat phpunit.xml).');
        }
    }
}
