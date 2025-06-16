<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Datasource;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Datasource>
 */
final class DatasourceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Datasource::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [Datasource::TYPE_JSON, Datasource::TYPE_CSV, Datasource::TYPE_API, Datasource::TYPE_CUSTOM];
        $type = $this->faker->randomElement($types);
        $name = $this->faker->words(3, true);

        return [
            'space_id' => Space::factory(),
            'name' => $name,
            'description' => $this->faker->optional(0.8)->sentence(),
            'type' => $type,
            'config' => $this->generateConfigForType($type),
            'schema' => $this->generateSchemaForType($type),
            'mapping' => $this->generateMapping(),
            'auth_config' => $this->faker->optional(0.3)->randomElement([
                [
                    'type' => 'bearer',
                    'token' => $this->faker->sha256(),
                ],
                [
                    'type' => 'basic',
                    'username' => $this->faker->userName(),
                    'password' => $this->faker->password(),
                ],
                [
                    'type' => 'api_key',
                    'header' => 'X-API-Key',
                    'key' => $this->faker->sha1(),
                ],
            ]),
            'headers' => $this->faker->optional(0.4)->randomElement([
                [
                    'User-Agent' => 'HeadlessCMS/1.0',
                    'Accept' => 'application/json',
                ],
                [
                    'Content-Type' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            ]),
            'cache_duration' => $this->faker->randomElement([300, 600, 1800, 3600, 7200]),
            'auto_sync' => $this->faker->boolean(70),
            'sync_frequency' => $this->faker->randomElement(['hourly', 'daily', 'weekly']),
            'filters' => $this->faker->optional(0.3)->randomElement([
                [
                    [
                        'field' => 'status',
                        'operator' => '=',
                        'value' => 'published',
                    ],
                ],
                [
                    [
                        'field' => 'category',
                        'operator' => 'contains',
                        'value' => 'blog',
                    ],
                    [
                        'field' => 'date',
                        'operator' => '>',
                        'value' => '2024-01-01',
                    ],
                ],
            ]),
            'transformations' => $this->faker->optional(0.3)->randomElement([
                [
                    [
                        'field' => 'title',
                        'type' => 'uppercase',
                    ],
                ],
                [
                    [
                        'field' => 'content',
                        'type' => 'trim',
                    ],
                    [
                        'field' => 'date',
                        'type' => 'date_format',
                        'format' => 'Y-m-d',
                    ],
                ],
            ]),
            'max_entries' => $this->faker->optional(0.6)->numberBetween(10, 1000),
            'status' => $this->faker->randomElement([
                Datasource::STATUS_ACTIVE,
                Datasource::STATUS_INACTIVE,
                Datasource::STATUS_ERROR,
            ]),
            'health_check' => $this->faker->optional(0.5)->randomElement([
                [
                    'enabled' => true,
                    'interval' => 3600,
                    'timeout' => 30,
                    'expected_status' => 200,
                ],
            ]),
            'last_synced_at' => $this->faker->optional(0.7)->dateTimeThisMonth(),
            'sync_status' => $this->faker->optional(0.7)->randomElement([
                [
                    'success' => true,
                    'timestamp' => $this->faker->dateTimeThisMonth()->format('c'),
                    'message' => 'Successfully synced 50 entries',
                ],
                [
                    'success' => false,
                    'timestamp' => $this->faker->dateTimeThisMonth()->format('c'),
                    'message' => 'Connection timeout',
                ],
            ]),
            'created_by' => User::factory(),
            'updated_by' => $this->faker->optional(0.3)->randomElement([1, 2, 3]),
        ];
    }

    /**
     * Generate config based on datasource type.
     */
    private function generateConfigForType(string $type): array
    {
        return match ($type) {
            Datasource::TYPE_JSON => [
                'url' => $this->faker->url() . '/api/data.json',
                'method' => 'GET',
                'timeout' => 30,
            ],
            Datasource::TYPE_CSV => [
                'url' => $this->faker->url() . '/data/export.csv',
                'delimiter' => ',',
                'enclosure' => '"',
                'escape' => '\\',
                'skip_first_row' => true,
            ],
            Datasource::TYPE_API => [
                'url' => $this->faker->url() . '/api/v1/items',
                'method' => 'GET',
                'timeout' => 30,
                'data_path' => 'data.items',
                'pagination' => [
                    'type' => 'page',
                    'page_param' => 'page',
                    'size_param' => 'per_page',
                    'default_size' => 50,
                ],
            ],
            Datasource::TYPE_DATABASE => [
                'connection' => 'external',
                'table' => 'products',
                'query' => 'SELECT * FROM products WHERE status = "active"',
            ],
            Datasource::TYPE_CUSTOM => [
                'handler' => 'App\\Datasources\\CustomHandler',
                'parameters' => [
                    'endpoint' => $this->faker->url(),
                    'api_key' => $this->faker->sha1(),
                ],
            ],
            default => [],
        };
    }

    /**
     * Generate schema based on datasource type.
     */
    private function generateSchemaForType(string $type): array
    {
        $baseSchema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
                'required' => ['id', 'title'],
            ],
        ];

        return match ($type) {
            Datasource::TYPE_JSON, Datasource::TYPE_API => array_merge_recursive($baseSchema, [
                'items' => [
                    'properties' => [
                        'category' => ['type' => 'string'],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'author' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'email' => ['type' => 'string', 'format' => 'email'],
                            ],
                        ],
                    ],
                ],
            ]),
            Datasource::TYPE_CSV => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'string'],
                        'product_name' => ['type' => 'string'],
                        'price' => ['type' => 'number'],
                        'category' => ['type' => 'string'],
                        'in_stock' => ['type' => 'boolean'],
                    ],
                ],
            ],
            default => $baseSchema,
        };
    }

    /**
     * Generate mapping configuration.
     */
    private function generateMapping(): array
    {
        return [
            'id' => 'id',
            'title' => 'name',
            'content' => 'description',
            'status' => 'status',
            'published_at' => 'created_at',
            'category' => 'category',
            'author' => 'author.name',
        ];
    }

    /**
     * Create a JSON datasource.
     */
    public function json(): static
    {
        return $this->state([
            'type' => Datasource::TYPE_JSON,
            'config' => [
                'url' => 'https://jsonplaceholder.typicode.com/posts',
                'method' => 'GET',
                'timeout' => 30,
            ],
        ]);
    }

    /**
     * Create a CSV datasource.
     */
    public function csv(): static
    {
        return $this->state([
            'type' => Datasource::TYPE_CSV,
            'config' => [
                'url' => $this->faker->url() . '/exports/products.csv',
                'delimiter' => ',',
                'enclosure' => '"',
                'skip_first_row' => true,
            ],
        ]);
    }

    /**
     * Create an API datasource.
     */
    public function api(): static
    {
        return $this->state([
            'type' => Datasource::TYPE_API,
            'config' => [
                'url' => $this->faker->url() . '/api/v1/content',
                'method' => 'GET',
                'timeout' => 30,
                'data_path' => 'data',
            ],
        ]);
    }

    /**
     * Create an active datasource.
     */
    public function active(): static
    {
        return $this->state([
            'status' => Datasource::STATUS_ACTIVE,
            'auto_sync' => true,
            'last_synced_at' => $this->faker->dateTimeThisWeek(),
            'sync_status' => [
                'success' => true,
                'timestamp' => now()->format('c'),
                'message' => 'Successfully synced',
            ],
        ]);
    }

    /**
     * Create an inactive datasource.
     */
    public function inactive(): static
    {
        return $this->state([
            'status' => Datasource::STATUS_INACTIVE,
            'auto_sync' => false,
        ]);
    }
}