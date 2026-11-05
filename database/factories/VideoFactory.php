<?php

namespace Database\Factories;

use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'url' => 'https://www.youtube.com/watch?v='.Str::random(11),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'upload_date' => fake()->dateTimeBetween('-1 year'),
            'duration_seconds' => fake()->numberBetween(60, 900),
            'clips' => null,
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true]);
    }

    public function withClips(): static
    {
        return $this->state([
            'duration_seconds' => 300,
            'clips' => [
                ['name' => 'Persiapan alat', 'start' => 0, 'end' => null],
                ['name' => 'Mengaduk semen', 'start' => 90, 'end' => null],
                ['name' => 'Finishing', 'start' => 210, 'end' => null],
            ],
        ]);
    }

    /** Tanggal unggah tidak diisi admin (markup memakai waktu video ditambahkan). */
    public function withoutUploadDate(): static
    {
        return $this->state(['upload_date' => null]);
    }
}
