<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Component>
 * @psalm-suppress UnusedClass
 */
final class ComponentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Component>
     */
    protected $model = \App\Models\Component::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'technical_name' => $this->faker->slug(2),
            'description' => $this->faker->sentence(),
            'schema' => [
                'title' => [
                    'type' => 'text',
                    'required' => true,
                    'max_length' => 255
                ]
            ],
            'is_root' => $this->faker->boolean(),
            'is_nestable' => $this->faker->boolean(),
            'status' => 'active',
            'version' => 1,
            'icon' => 'component',
            'color' => '#3b82f6'
        ];
    }
}
