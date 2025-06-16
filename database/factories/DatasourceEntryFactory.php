<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Datasource;
use App\Models\DatasourceEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DatasourceEntry>
 */
final class DatasourceEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = DatasourceEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'datasource_id' => Datasource::factory(),
            'external_id' => $this->faker->unique()->numberBetween(1, 10000),
            'data' => $this->generateRealisticData(),
            'position' => $this->faker->numberBetween(0, 1000),
            'status' => $this->faker->randomElement([
                DatasourceEntry::STATUS_PUBLISHED,
                DatasourceEntry::STATUS_DRAFT,
                DatasourceEntry::STATUS_ARCHIVED,
            ]),
            'metadata' => [
                'synced_at' => $this->faker->dateTimeThisMonth()->format('c'),
                'source_version' => $this->faker->randomFloat(2, 1, 3),
                'import_batch' => $this->faker->uuid(),
                'quality_score' => $this->faker->randomFloat(2, 0.5, 1.0),
                'validation_errors' => $this->faker->optional(0.1)->randomElement([
                    ['Missing required field: title'],
                    ['Invalid date format', 'Field too long: description'],
                    ['Duplicate entry detected'],
                ]),
            ],
            'published_at' => $this->faker->optional(0.8)->dateTimeThisYear(),
        ];
    }

    /**
     * Generate realistic data based on common content types.
     */
    private function generateRealisticData(): array
    {
        $dataTypes = [
            'blog_post',
            'product',
            'event',
            'news_article',
            'team_member',
            'testimonial',
            'faq',
        ];

        $type = $this->faker->randomElement($dataTypes);

        return match ($type) {
            'blog_post' => $this->generateBlogPostData(),
            'product' => $this->generateProductData(),
            'event' => $this->generateEventData(),
            'news_article' => $this->generateNewsArticleData(),
            'team_member' => $this->generateTeamMemberData(),
            'testimonial' => $this->generateTestimonialData(),
            'faq' => $this->generateFaqData(),
            default => $this->generateGenericData(),
        };
    }

    /**
     * Generate blog post data.
     */
    private function generateBlogPostData(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1, 10000),
            'title' => $this->faker->sentence(6),
            'slug' => $this->faker->slug(3),
            'content' => $this->faker->paragraphs(5, true),
            'excerpt' => $this->faker->paragraph(),
            'author' => [
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
                'bio' => $this->faker->sentence(),
            ],
            'category' => $this->faker->randomElement(['Technology', 'Business', 'Design', 'Marketing', 'Development']),
            'tags' => $this->faker->words($this->faker->numberBetween(2, 6)),
            'featured_image' => $this->faker->imageUrl(1200, 630, 'business'),
            'reading_time' => $this->faker->numberBetween(2, 15),
            'published_at' => $this->faker->dateTimeThisYear()->format('Y-m-d H:i:s'),
            'updated_at' => $this->faker->dateTimeThisMonth()->format('Y-m-d H:i:s'),
            'status' => $this->faker->randomElement(['published', 'draft', 'archived']),
            'seo' => [
                'meta_title' => $this->faker->sentence(8),
                'meta_description' => $this->faker->paragraph(1),
                'og_image' => $this->faker->imageUrl(1200, 630),
            ],
        ];
    }

    /**
     * Generate product data.
     */
    private function generateProductData(): array
    {
        $price = $this->faker->randomFloat(2, 10, 1000);
        $salePrice = $this->faker->optional(0.3)->randomFloat(2, $price * 0.5, $price * 0.9);

        return [
            'id' => $this->faker->unique()->numberBetween(1, 10000),
            'name' => $this->faker->words(3, true),
            'sku' => strtoupper($this->faker->bothify('??###')),
            'description' => $this->faker->paragraphs(3, true),
            'short_description' => $this->faker->sentence(),
            'price' => $price,
            'sale_price' => $salePrice,
            'currency' => $this->faker->currencyCode(),
            'category' => $this->faker->randomElement(['Electronics', 'Clothing', 'Home & Garden', 'Books', 'Sports']),
            'brand' => $this->faker->company(),
            'images' => [
                $this->faker->imageUrl(800, 600, 'business'),
                $this->faker->imageUrl(800, 600, 'business'),
                $this->faker->imageUrl(800, 600, 'business'),
            ],
            'attributes' => [
                'color' => $this->faker->colorName(),
                'size' => $this->faker->randomElement(['XS', 'S', 'M', 'L', 'XL']),
                'material' => $this->faker->randomElement(['Cotton', 'Polyester', 'Wool', 'Silk']),
                'weight' => $this->faker->randomFloat(2, 0.1, 5.0) . 'kg',
            ],
            'inventory' => [
                'stock_quantity' => $this->faker->numberBetween(0, 100),
                'in_stock' => $this->faker->boolean(80),
                'low_stock_threshold' => 5,
            ],
            'rating' => [
                'average' => $this->faker->randomFloat(1, 1, 5),
                'count' => $this->faker->numberBetween(0, 500),
            ],
            'status' => $this->faker->randomElement(['active', 'inactive', 'out_of_stock']),
        ];
    }

    /**
     * Generate event data.
     */
    private function generateEventData(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+6 months');
        $endDate = $this->faker->dateTimeBetween($startDate, $startDate->format('Y-m-d') . ' +1 week');

        return [
            'id' => $this->faker->unique()->numberBetween(1, 10000),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraphs(2, true),
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
            'timezone' => $this->faker->timezone(),
            'location' => [
                'name' => $this->faker->company() . ' Center',
                'address' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'state' => $this->faker->state(),
                'country' => $this->faker->country(),
                'coordinates' => [
                    'lat' => $this->faker->latitude(),
                    'lng' => $this->faker->longitude(),
                ],
            ],
            'organizer' => [
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
                'phone' => $this->faker->phoneNumber(),
            ],
            'category' => $this->faker->randomElement(['Conference', 'Workshop', 'Meetup', 'Webinar', 'Festival']),
            'tags' => $this->faker->words($this->faker->numberBetween(2, 5)),
            'ticket_price' => $this->faker->optional(0.7)->randomFloat(2, 0, 500),
            'max_attendees' => $this->faker->numberBetween(10, 1000),
            'current_attendees' => $this->faker->numberBetween(0, 500),
            'featured_image' => $this->faker->imageUrl(1200, 600, 'business'),
            'status' => $this->faker->randomElement(['upcoming', 'ongoing', 'completed', 'cancelled']),
        ];
    }

    /**
     * Generate news article data.
     */
    private function generateNewsArticleData(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1, 10000),
            'headline' => $this->faker->sentence(8),
            'subheading' => $this->faker->sentence(12),
            'content' => $this->faker->paragraphs(8, true),
            'summary' => $this->faker->paragraph(),
            'byline' => $this->faker->name(),
            'publication_date' => $this->faker->dateTimeThisYear()->format('Y-m-d H:i:s'),
            'section' => $this->faker->randomElement(['Politics', 'Business', 'Technology', 'Sports', 'Entertainment']),
            'location' => $this->faker->city() . ', ' . $this->faker->stateAbbr(),
            'featured_image' => [
                'url' => $this->faker->imageUrl(1200, 600, 'business'),
                'caption' => $this->faker->sentence(),
                'credit' => $this->faker->name(),
            ],
            'priority' => $this->faker->randomElement(['breaking', 'high', 'normal', 'low']),
            'external_links' => [
                $this->faker->url(),
                $this->faker->url(),
            ],
            'reading_time' => $this->faker->numberBetween(2, 20),
            'status' => $this->faker->randomElement(['published', 'draft', 'review']),
        ];
    }

    /**
     * Generate team member data.
     */
    private function generateTeamMemberData(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1, 1000),
            'name' => $this->faker->name(),
            'position' => $this->faker->jobTitle(),
            'department' => $this->faker->randomElement(['Engineering', 'Marketing', 'Sales', 'Design', 'Operations']),
            'bio' => $this->faker->paragraphs(2, true),
            'avatar' => $this->faker->imageUrl(400, 400, 'people'),
            'contact' => [
                'email' => $this->faker->email(),
                'phone' => $this->faker->phoneNumber(),
                'linkedin' => 'https://linkedin.com/in/' . $this->faker->userName(),
                'twitter' => '@' . $this->faker->userName(),
            ],
            'skills' => $this->faker->words($this->faker->numberBetween(3, 8)),
            'start_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'location' => $this->faker->city() . ', ' . $this->faker->stateAbbr(),
            'favorite_quote' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['active', 'on_leave', 'former']),
        ];
    }

    /**
     * Generate testimonial data.
     */
    private function generateTestimonialData(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1, 10000),
            'content' => $this->faker->paragraph(4),
            'rating' => $this->faker->numberBetween(3, 5),
            'customer' => [
                'name' => $this->faker->name(),
                'position' => $this->faker->jobTitle(),
                'company' => $this->faker->company(),
                'avatar' => $this->faker->imageUrl(200, 200, 'people'),
                'location' => $this->faker->city() . ', ' . $this->faker->country(),
            ],
            'product_service' => $this->faker->words(2, true),
            'featured' => $this->faker->boolean(20),
            'verified' => $this->faker->boolean(80),
            'date_submitted' => $this->faker->dateTimeThisYear()->format('Y-m-d'),
            'tags' => $this->faker->words($this->faker->numberBetween(1, 4)),
            'status' => $this->faker->randomElement(['approved', 'pending', 'rejected']),
        ];
    }

    /**
     * Generate FAQ data.
     */
    private function generateFaqData(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1, 1000),
            'question' => $this->faker->sentence() . '?',
            'answer' => $this->faker->paragraphs(2, true),
            'category' => $this->faker->randomElement(['General', 'Billing', 'Technical', 'Account', 'Features']),
            'tags' => $this->faker->words($this->faker->numberBetween(2, 5)),
            'helpful_count' => $this->faker->numberBetween(0, 100),
            'not_helpful_count' => $this->faker->numberBetween(0, 20),
            'last_updated' => $this->faker->dateTimeThisYear()->format('Y-m-d H:i:s'),
            'author' => $this->faker->name(),
            'priority' => $this->faker->numberBetween(1, 10),
            'status' => $this->faker->randomElement(['published', 'draft', 'archived']),
        ];
    }

    /**
     * Generate generic data.
     */
    private function generateGenericData(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1, 10000),
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'created_at' => $this->faker->dateTimeThisYear()->format('Y-m-d H:i:s'),
            'updated_at' => $this->faker->dateTimeThisMonth()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create a blog post entry.
     */
    public function blogPost(): static
    {
        return $this->state([
            'data' => $this->generateBlogPostData(),
            'status' => DatasourceEntry::STATUS_PUBLISHED,
        ]);
    }

    /**
     * Create a product entry.
     */
    public function product(): static
    {
        return $this->state([
            'data' => $this->generateProductData(),
            'status' => DatasourceEntry::STATUS_PUBLISHED,
        ]);
    }

    /**
     * Create a published entry.
     */
    public function published(): static
    {
        return $this->state([
            'status' => DatasourceEntry::STATUS_PUBLISHED,
            'published_at' => $this->faker->dateTimeThisYear(),
        ]);
    }

    /**
     * Create a draft entry.
     */
    public function draft(): static
    {
        return $this->state([
            'status' => DatasourceEntry::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }
}