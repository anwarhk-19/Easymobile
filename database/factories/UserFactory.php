<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => (string) fake()->unique()->numberBetween(9000000000, 9999999999),
            'role' => 'customer',
            'status' => 'active',
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function staff(string $role = 'admin'): static
    {
        return $this->state(fn () => ['role' => $role, 'phone' => null, 'email' => fake()->unique()->safeEmail(), 'password' => 'Secret@12345']);
    }
}
