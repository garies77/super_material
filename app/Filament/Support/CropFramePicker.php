<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\Field;

/**
 * Pratinjau potongan: bingkai dengan rasio section di atas gambar yang bisa digeser dan diperbesar.
 * State: ['x' => 0..100, 'y' => 0..100, 'zoom' => 1..3]; x/y = pusat potongan dalam persen gambar.
 */
class CropFramePicker extends Field
{
    public const MAX_ZOOM = 3;

    protected string $view = 'filament.forms.components.crop-frame-picker';

    protected string|Closure|null $imageUrl = null;

    protected float|Closure|null $imageRatio = null;

    protected float|Closure|null $targetRatio = null;

    protected int|Closure|null $sourceWidth = null;

    protected int|Closure|null $maxOutputWidth = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(['x' => 50, 'y' => 50, 'zoom' => 1]);
    }

    public function image(string|Closure|null $url, float|Closure|null $ratio, int|Closure|null $width = null): static
    {
        $this->imageUrl = $url;
        $this->imageRatio = $ratio;
        $this->sourceWidth = $width;

        return $this;
    }

    public function targetRatio(float|Closure|null $ratio): static
    {
        $this->targetRatio = $ratio;

        return $this;
    }

    public function maxOutputWidth(int|Closure|null $width): static
    {
        $this->maxOutputWidth = $width;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->evaluate($this->imageUrl);
    }

    public function getImageRatio(): float
    {
        return (float) ($this->evaluate($this->imageRatio) ?: 1);
    }

    public function getTargetRatio(): float
    {
        return (float) ($this->evaluate($this->targetRatio) ?: 1);
    }

    public function getSourceWidth(): ?int
    {
        return $this->evaluate($this->sourceWidth);
    }

    public function getMaxOutputWidth(): ?int
    {
        return $this->evaluate($this->maxOutputWidth);
    }

    /**
     * Nilai state yang aman untuk dipakai server (dijepit ke rentang yang sah).
     *
     * @return array{x: int, y: int, zoom: float}
     */
    public static function normalize(mixed $state): array
    {
        $state = is_array($state) ? $state : [];
        $percent = fn (string $key) => (int) max(0, min(100, round((float) ($state[$key] ?? 50))));

        return [
            'x' => $percent('x'),
            'y' => $percent('y'),
            'zoom' => round(max(1, min(self::MAX_ZOOM, (float) ($state['zoom'] ?? 1))), 2),
        ];
    }
}
