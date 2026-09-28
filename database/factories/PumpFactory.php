<?php

namespace Database\Factories;

use App\Models\Pump;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pump>
 */
class PumpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Pump '.fake()->unique()->numerify('##'),
            'is_active' => true,
        ];
    }
}
