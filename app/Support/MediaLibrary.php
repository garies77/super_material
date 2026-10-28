<?php

namespace App\Support;

use App\Filament\Support\DescriptiveFileName;
use App\Models\Media;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Pustaka media: mendaftarkan berkas gambar di disk publik dan mencegah unggahan ganda.
 * Berkas yang isinya identik (hash sha256 sama) tidak disimpan ulang; path lama yang dipakai.
 */
class MediaLibrary
{
    /** Direktori turunan otomatis dan sementara; bukan aset yang dipilih pengguna. */
    public const IGNORED_DIRECTORIES = ['derivatives', 'livewire-tmp'];

    /**
     * Penyimpan unggahan untuk setiap FileUpload Filament (dipasang di AppServiceProvider).
     * Unggahan ke disk publik dicek duplikatnya lalu didaftarkan; disk lain disimpan seperti biasa.
     */
    public static function storeFileUpload(BaseFileUpload $component, TemporaryUploadedFile $file): ?string
    {
        if ($component->getDiskName() !== Media::DISK || ! self::isImageMime((string) $file->getMimeType())) {
            return $component->saveUploadedFile($file);
        }

        if ($existing = self::findDuplicate($file->getRealPath())) {
            return $existing->path;
        }

        $path = $component->saveUploadedFile($file);

        if ($path) {
            self::register($path, $file->getClientOriginalName());
        }

        return $path;
    }

    /**
     * Simpan unggahan di luar FileUpload Filament (modal pustaka, API) dengan nama berkas deskriptif.
     * SVG dibersihkan dulu; berkas identik yang sudah terdaftar dipakai ulang.
     */
    public static function storeUploadedFile(UploadedFile $file, string $directory, string $nameSource, ?string $suffix = null): string
    {
        SvgSanitizer::cleanInPlace($file);

        if ($existing = self::findDuplicate($file->getRealPath())) {
            return $existing->path;
        }

        $path = $file->storeAs($directory, DescriptiveFileName::for($file, $nameSource, $directory, $suffix), Media::DISK);
        self::register($path, $file->getClientOriginalName());

        return $path;
    }

    /** Berkas terdaftar yang isinya sama persis dengan berkas lokal ini dan masih ada di disk. */
    public static function findDuplicate(string $localPath): ?Media
    {
        $hash = hash_file('sha256', $localPath);

        return Media::where('hash', $hash)->get()
            ->first(fn (Media $media) => Storage::disk(Media::DISK)->exists($media->path));
    }

    /**
     * Daftarkan berkas yang sudah ada di disk publik. Mengembalikan null bila berkas tidak ada,
     * bukan gambar, atau berada di direktori turunan/sementara.
     */
    public static function register(string $path, ?string $originalName = null): ?Media
    {
        $path = ltrim($path, '/');
        $disk = Storage::disk(Media::DISK);

        if (in_array(self::folderFor($path), self::IGNORED_DIRECTORIES, true) || ! $disk->exists($path)) {
            return null;
        }

        if ($media = Media::firstWhere('path', $path)) {
            return $media;
        }

        $mime = (string) $disk->mimeType($path);
        if (! self::isImageMime($mime)) {
            return null;
        }

        $absolute = $disk->path($path);
        $dimensions = ImageDerivatives::dimensions($absolute);

        $media = Media::create([
            'path' => $path,
            'folder' => self::folderFor($path),
            'name' => Str::limit($originalName ?: basename($path), 250, ''),
            'mime_type' => $mime,
            'size' => (int) $disk->size($path),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'hash' => hash_file('sha256', $absolute),
            'uploaded_by' => auth()->id(),
        ]);

        // Sekali saat unggah, bukan saat grid dirender
        $media->generateThumbnail();

        return $media;
    }

    /**
     * Salinan gambar yang dipotong tengah ke rasio section dan diperkecil ke lebar maksimum (tanpa memperbesar).
     * Berkas asli tidak diubah karena bisa dipakai konten lain. SVG dan format lain dikembalikan apa adanya.
     */
    public static function cropToRatio(Media $media, float $ratio, ?int $maxWidth = null): Media
    {
        if (! $media->isCroppable()) {
            return $media;
        }

        $cropWidth = min($media->width, (int) floor($media->height * $ratio));
        $width = min($cropWidth, $maxWidth ?? $cropWidth);
        $height = (int) round($width / $ratio);
        $path = self::folderFor($media->path).'/'.pathinfo($media->path, PATHINFO_FILENAME)."-{$width}x{$height}.webp";

        if ($existing = Media::firstWhere('path', $path)) {
            return $existing;
        }

        $disk = Storage::disk(Media::DISK);
        if (! ImageDerivatives::createDerivative($disk->path($media->path), $disk->path($path), $width, $height) || ! $disk->exists($path)) {
            return $media;
        }

        $crop = self::register($path, basename($path));
        $crop?->update(['alt_text' => $media->alt_text]);

        return $crop ?? $media;
    }

    public static function folderFor(string $path): string
    {
        return str_contains($path, '/') ? Str::before($path, '/') : 'media';
    }

    public static function isImageMime(string $mime): bool
    {
        return str_starts_with($mime, 'image/');
    }
}
