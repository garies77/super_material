<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Support\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Mendaftarkan gambar yang sudah ada di disk publik ke Pustaka Media agar bisa dipilih ulang.
 * Aman dijalankan berulang: berkas yang sudah terdaftar dilewati. Tidak menghapus apa pun.
 */
class SyncMediaLibrary extends Command
{
    protected $signature = 'media:sync';

    protected $description = 'Daftarkan gambar yang sudah ada di storage publik ke Pustaka Media';

    public function handle(): int
    {
        $registered = 0;
        $skipped = 0;

        foreach (Storage::disk(Media::DISK)->allFiles() as $path) {
            if (str_starts_with(basename($path), '.') || Media::where('path', $path)->exists()) {
                $skipped++;

                continue;
            }

            if ($media = MediaLibrary::register($path)) {
                $media->thumbnailUrl();
                $registered++;
            } else {
                $skipped++;
            }
        }

        $this->info("{$registered} gambar didaftarkan, {$skipped} berkas dilewati (sudah terdaftar, bukan gambar, atau berkas sementara).");

        return self::SUCCESS;
    }
}
