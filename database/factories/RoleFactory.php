<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 * @psalm-suppress UnusedClass
 */
final class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Role>
     */
    protected $model = \App\Models\Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['admin', 'editor', 'viewer']),
            'display_name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'permissions' => [],
            'is_system' => false,
        ];
    }
}