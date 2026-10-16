<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'timezone' => 'UTC',
        ];
    }

    public function admin(): static
    {
        return $this->afterMaking(fn (User $user) => $user->forceFill(['role' => 'admin']));
    }
}
