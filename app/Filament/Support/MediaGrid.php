<?php

namespace App\Filament\Support;

use App\Models\Media;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

/**
 * Grid gambar pustaka media yang bisa dipilih (satu atau banyak). State berisi daftar path terpilih
 * sesuai urutan klik; urutan ini dipakai saat gambar ditambahkan ke galeri.
 */
class MediaGrid extends Field
{
    protected string $view = 'filament.forms.components.media-grid';

    protected bool|Closure $isMultiple = false;

    /** @var array<int, float> */
    protected array $targetRatios = [];

    /** @var Closure(): Collection<int, Media>|null */
    protected ?Closure $getItemsUsing = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
    }

    public function multiple(bool|Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    /** @param  array<int, float>  $ratios  rasio section; kosong = bebas */
    public function targetRatios(array $ratios): static
    {
        $this->targetRatios = $ratios;

        return $this;
    }

    /** null bila section tidak punya rasio baku; true/false = rasio gambar cocok. */
    public function fitsSection(Media $media): ?bool
    {
        if ($this->targetRatios === []) {
            return null;
        }

        return collect($this->targetRatios)->contains(fn (float $ratio) => $media->matchesRatio($ratio));
    }

    /** Sama dengan syarat MediaLibrary::cropToRatio(): satu rasio wajib dan gambar raster. */
    public function willCrop(Media $media): bool
    {
        return count($this->targetRatios) === 1 && $media->isCroppable();
    }

    public function items(Closure $callback): static
    {
        $this->getItemsUsing = $callback;

        return $this;
    }

    /** @return Collection<int, Media> */
    public function getItems(): Collection
    {
        return $this->getItemsUsing ? collect($this->evaluate($this->getItemsUsing)) : collect();
    }
}
