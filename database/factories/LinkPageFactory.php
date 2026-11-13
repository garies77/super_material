<?php

namespace Database\Factories;

use App\Models\LinkPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LinkPage>
 */
class LinkPageFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Jaya Intero '.fake()->unique()->words(2, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'bio' => 'Bengkel las pagar, kanopi, dan teralis.',
            'theme' => 'light',
            'socials' => [['platform' => 'instagram', 'url' => 'https://www.instagram.com/jayaintero']],
            'is_published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }
}
