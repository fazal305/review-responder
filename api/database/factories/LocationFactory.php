<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'google_location_name' => 'locations/'.fake()->unique()->numerify('##########'),
            'title' => fake()->company(),
            'address_line' => fake()->streetAddress(),
            'timezone' => 'UTC',
        ];
    }
}
