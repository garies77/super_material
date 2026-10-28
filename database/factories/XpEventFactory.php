<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\XpEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<XpEvent>
 */
class XpEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'points' => 40,
            'reason' => 'Produk disetujui',
            'award_key' => 'test:'.fake()->unique()->uuid(),
            'meta' => ['score' => 75, 'first_time_right' => false],
        ];
    }
}
