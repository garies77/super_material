<?php

namespace App\Support\Seo;

use App\Support\YouTube;
use Carbon\CarbonInterface;

/**
 * Data video yang sudah dinormalisasi dari Video (artikel) atau ProductMedia (produk), siap dijadikan VideoObject.
 */
final readonly class VideoData
{
    /**
     * @param  array<int, array{name: string, start: int, end: ?int}>  $clips
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $thumbnailUrl,
        public ?CarbonInterface $uploadDate,
        public ?int $durationSeconds,
        public ?string $youtubeId,
        public ?string $fileUrl,
        public string $pageUrl,
        public array $clips = [],
        public ?string $creatorName = null,
        public ?string $creatorUrl = null,
    ) {}

    public function embedUrl(?int $start = null): ?string
    {
        return $this->youtubeId ? YouTube::embedUrl($this->youtubeId, $start) : null;
    }

    /** Lengkap = memenuhi tiga properti wajib Google (name, thumbnailUrl, uploadDate) dan punya sumber video. */
    public function isComplete(): bool
    {
        return trim($this->title) !== ''
            && $this->thumbnailUrl
            && $this->uploadDate
            && ($this->youtubeId || $this->fileUrl);
    }

    /** Bagian yang belum diisi, untuk ditampilkan di admin. */
    public function missingFields(): array
    {
        return array_keys(array_filter([
            'judul' => trim($this->title) === '',
            'thumbnail' => ! $this->thumbnailUrl,
            'tanggal unggah' => ! $this->uploadDate,
            'URL video' => ! $this->youtubeId && ! $this->fileUrl,
        ]));
    }
}
