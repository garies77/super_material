<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->slug(3);

        return [
            'path' => "products/{$name}.jpg",
            'folder' => 'products',
            'name' => "{$name}.jpg",
            'mime_type' => 'image/jpeg',
            'size' => 120_000,
            'width' => 1200,
            'height' => 900,
            'hash' => hash('sha256', $name),
            'alt_text' => null,
        ];
    }
}
