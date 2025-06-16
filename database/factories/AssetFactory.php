<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
final class AssetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Asset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contentTypes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'video/mp4' => ['mp4'],
            'video/quicktime' => ['mov'],
            'application/pdf' => ['pdf'],
            'text/plain' => ['txt'],
            'application/zip' => ['zip'],
        ];

        $contentType = $this->faker->randomKey($contentTypes);
        $extension = $this->faker->randomElement($contentTypes[$contentType]);
        $filename = $this->faker->slug(2) . '.' . $extension;
        $fileSize = $this->faker->numberBetween(1024, 10485760); // 1KB to 10MB

        return [
            'space_id' => Space::factory(),
            'filename' => $filename,
            'name' => $this->faker->optional(0.8)->words(3, true),
            'description' => $this->faker->optional(0.6)->sentence(),
            'alt_text' => str_starts_with($contentType, 'image/') 
                ? $this->faker->sentence(6) 
                : null,
            'content_type' => $contentType,
            'file_size' => $fileSize,
            'file_hash' => hash('sha256', $filename . $fileSize),
            'extension' => $extension,
            'path' => 'assets/' . date('Y/m/d') . '/' . $filename,
            'url' => '/storage/assets/' . date('Y/m/d') . '/' . $filename,
            'width' => str_starts_with($contentType, 'image/') 
                ? $this->faker->numberBetween(100, 4000) 
                : null,
            'height' => str_starts_with($contentType, 'image/') 
                ? $this->faker->numberBetween(100, 3000) 
                : null,
            'duration' => str_starts_with($contentType, 'video/') 
                ? $this->faker->numberBetween(10, 7200) 
                : null,
            'thumbnails' => str_starts_with($contentType, 'image/') ? [
                'small' => [
                    'url' => '/storage/thumbnails/small/' . pathinfo($filename, PATHINFO_FILENAME) . '.webp',
                    'width' => 150,
                    'height' => 150,
                ],
                'medium' => [
                    'url' => '/storage/thumbnails/medium/' . pathinfo($filename, PATHINFO_FILENAME) . '.webp',
                    'width' => 300,
                    'height' => 300,
                ],
                'large' => [
                    'url' => '/storage/thumbnails/large/' . pathinfo($filename, PATHINFO_FILENAME) . '.webp',
                    'width' => 800,
                    'height' => 600,
                ],
            ] : null,
            'metadata' => [
                'original_filename' => $this->faker->words(2, true) . '.' . $extension,
                'upload_source' => $this->faker->randomElement(['web', 'api', 'import']),
                'camera_info' => str_starts_with($contentType, 'image/') ? [
                    'make' => $this->faker->randomElement(['Canon', 'Nikon', 'Sony', 'Apple']),
                    'model' => $this->faker->word(),
                    'iso' => $this->faker->randomElement([100, 200, 400, 800, 1600]),
                    'focal_length' => $this->faker->numberBetween(24, 200) . 'mm',
                ] : null,
                'color_palette' => str_starts_with($contentType, 'image/') ? [
                    $this->faker->hexColor(),
                    $this->faker->hexColor(),
                    $this->faker->hexColor(),
                ] : null,
            ],
            'folder_id' => null,
            'processing_status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'processing_results' => [
                'processed_at' => $this->faker->dateTimeThisMonth()->format('Y-m-d H:i:s'),
                'thumbnails_generated' => str_starts_with($contentType, 'image/'),
                'optimization' => [
                    'original_size' => $fileSize,
                    'optimized_size' => (int) ($fileSize * $this->faker->randomFloat(2, 0.7, 0.95)),
                    'compression_ratio' => $this->faker->randomFloat(2, 0.05, 0.3),
                ],
            ],
            'access_count' => $this->faker->numberBetween(0, 1000),
            'last_accessed_at' => $this->faker->optional(0.7)->dateTimeThisYear(),
            'created_by' => User::factory(),
            'updated_by' => $this->faker->optional(0.3)->randomElement([1, 2, 3]),
        ];
    }

    /**
     * Create an image asset.
     */
    public function image(): static
    {
        return $this->state(function (array $attributes) {
            $contentType = $this->faker->randomElement(['image/jpeg', 'image/png', 'image/webp']);
            $extension = match ($contentType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            };
            $filename = $this->faker->slug(2) . '.' . $extension;

            return [
                'content_type' => $contentType,
                'extension' => $extension,
                'filename' => $filename,
                'path' => 'assets/images/' . date('Y/m/d') . '/' . $filename,
                'width' => $this->faker->numberBetween(800, 2000),
                'height' => $this->faker->numberBetween(600, 1500),
                'alt_text' => $this->faker->sentence(6),
                'thumbnails' => [
                    'small' => [
                        'url' => '/storage/thumbnails/small/' . pathinfo($filename, PATHINFO_FILENAME) . '.webp',
                        'width' => 150,
                        'height' => 150,
                    ],
                    'medium' => [
                        'url' => '/storage/thumbnails/medium/' . pathinfo($filename, PATHINFO_FILENAME) . '.webp',
                        'width' => 300,
                        'height' => 300,
                    ],
                    'large' => [
                        'url' => '/storage/thumbnails/large/' . pathinfo($filename, PATHINFO_FILENAME) . '.webp',
                        'width' => 800,
                        'height' => 600,
                    ],
                ],
            ];
        });
    }

    /**
     * Create a video asset.
     */
    public function video(): static
    {
        return $this->state(function (array $attributes) {
            $contentType = $this->faker->randomElement(['video/mp4', 'video/quicktime']);
            $extension = match ($contentType) {
                'video/mp4' => 'mp4',
                'video/quicktime' => 'mov',
            };
            $filename = $this->faker->slug(2) . '.' . $extension;

            return [
                'content_type' => $contentType,
                'extension' => $extension,
                'filename' => $filename,
                'path' => 'assets/videos/' . date('Y/m/d') . '/' . $filename,
                'width' => $this->faker->randomElement([1920, 1280, 854]),
                'height' => $this->faker->randomElement([1080, 720, 480]),
                'duration' => $this->faker->numberBetween(30, 3600),
                'thumbnails' => [
                    'poster' => [
                        'url' => '/storage/video-thumbnails/' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg',
                        'width' => 1280,
                        'height' => 720,
                    ],
                ],
            ];
        });
    }

    /**
     * Create a document asset.
     */
    public function document(): static
    {
        return $this->state(function (array $attributes) {
            $contentType = $this->faker->randomElement(['application/pdf', 'text/plain', 'application/zip']);
            $extension = match ($contentType) {
                'application/pdf' => 'pdf',
                'text/plain' => 'txt',
                'application/zip' => 'zip',
            };
            $filename = $this->faker->slug(3) . '.' . $extension;

            return [
                'content_type' => $contentType,
                'extension' => $extension,
                'filename' => $filename,
                'path' => 'assets/documents/' . date('Y/m/d') . '/' . $filename,
                'width' => null,
                'height' => null,
                'duration' => null,
                'alt_text' => null,
                'thumbnails' => null,
                'metadata' => [
                    'pages' => $contentType === 'application/pdf' ? $this->faker->numberBetween(1, 100) : null,
                    'word_count' => $contentType === 'text/plain' ? $this->faker->numberBetween(100, 5000) : null,
                ],
            ];
        });
    }
}