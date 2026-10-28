<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Support\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Mendaftarkan gambar yang sudah ada di disk publik ke Pustaka Media agar bisa dipilih ulang,
 * lalu membuat thumbnail untuk entri lama yang belum punya. Aman dijalankan berulang;
 * berkas yang sudah terdaftar dilewati dan tidak ada yang dihapus.
 */
class SyncMediaLibrary extends Command
{
    protected $signature = 'media:sync';

    protected $description = 'Daftarkan gambar yang sudah ada di storage publik ke Pustaka Media dan lengkapi thumbnail-nya';

    public function handle(): int
    {
        $registered = 0;
        $skipped = 0;
        $known = Media::pluck('path')->flip();

        foreach (Storage::disk(Media::DISK)->allFiles() as $path) {
            if (str_starts_with(basename($path), '.') || $known->has($path)) {
                $skipped++;

                continue;
            }

            MediaLibrary::register($path) ? $registered++ : $skipped++;
        }

        $thumbnails = 0;
        Media::whereNull('thumbnail_path')->each(function (Media $media) use (&$thumbnails): void {
            $media->generateThumbnail();
            $thumbnails++;
        });

        $this->info("{$registered} gambar didaftarkan, {$skipped} berkas dilewati (sudah terdaftar, bukan gambar, atau berkas sementara), {$thumbnails} thumbnail dibuat.");

        return self::SUCCESS;
    }
}
