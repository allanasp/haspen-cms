<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Space>
 */
final class SpaceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Space>
     */
protected $model = \App\Models\Space::class;

/**
 * Define the model's default state.
 *
 * @return array<string, mixed>
 */
public function definition(): array
{
        $name = $this->faker->company();

        return [
            'uuid' => $this->faker->uuid(),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'domain' => $this->faker->optional(0.3)->domainName(),
            'description' => $this->faker->optional()->paragraph(),
            'settings' => [
                'timezone' => $this->faker->timezone(),
                'date_format' => $this->faker->randomElement(['MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD']),
                'currency' => $this->faker->currencyCode(),
                'image_optimization' => [
                    'enabled' => true,
                    'formats' => ['webp', 'avif'],
                    'quality' => $this->faker->numberBetween(70, 95),
                ],
                'seo' => [
                    'default_meta_title' => $name . ' - ' . $this->faker->catchPhrase(),
                    'default_meta_description' => $this->faker->sentence(20),
                    'robots_default' => 'index,follow',
                    'sitemap_enabled' => true,
                ],
            ],
            'environments' => [
                'development' => [
                    'api_url' => 'https://dev-api.' . \Illuminate\Support\Str::slug($name) . '.com',
                    'cdn_url' => 'https://dev-cdn.' . \Illuminate\Support\Str::slug($name) . '.com',
                    'cache_ttl' => 60,
                    'debug_mode' => true,
                ],
                'production' => [
                    'api_url' => 'https://api.' . \Illuminate\Support\Str::slug($name) . '.com',
                    'cdn_url' => 'https://cdn.' . \Illuminate\Support\Str::slug($name) . '.com',
                    'cache_ttl' => 3600,
                    'debug_mode' => false,
                ],
            ],
            'default_language' => 'en',
            'languages' => $this->faker->randomElements(['en', 'es', 'fr', 'de', 'it', 'pt'], $this->faker->numberBetween(1, 3)),
            'plan' => $this->faker->randomElement(['free', 'starter', 'professional', 'enterprise']),
            'story_limit' => $this->faker->optional(0.7)->numberBetween(10, 1000),
            'asset_limit' => $this->faker->optional(0.7)->numberBetween(100, 10000), // MB
            'api_limit' => $this->faker->optional(0.7)->numberBetween(1000, 100000),
            'status' => $this->faker->randomElement(['active', 'suspended', 'deleted']),
            'trial_ends_at' => $this->faker->optional(0.4)->dateTimeBetween('+1 week', '+1 month'),
        ];
    }
}
