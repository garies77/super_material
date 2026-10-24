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

        if (config('database.connections.'.config('database.default').'.database') !== ':memory:') {
            throw new RuntimeException('Test hanya boleh memakai database :memory: (lihat phpunit.xml).');
        }
    }
}
