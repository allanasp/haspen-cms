<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Story;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Story>
 * @psalm-suppress UnusedClass
 */
final class StoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Story>
     */
    protected $model = Story::class;

    /**
     * Content templates for different story types.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $contentTemplates = [
        'homepage' => [
            'components' => ['hero_section', 'text_block', 'image_gallery', 'testimonial', 'contact_form'],
            'is_folder' => false,
            'is_startpage' => true,
        ],
        'about_page' => [
            'components' => ['hero_section', 'text_block', 'image_gallery', 'card_grid'],
            'is_folder' => false,
            'is_startpage' => false,
        ],
        'blog_post' => [
            'components' => ['text_block', 'image_gallery', 'video_player'],
            'is_folder' => false,
            'is_startpage' => false,
        ],
        'landing_page' => [
            'components' => ['hero_section', 'card_grid', 'testimonial', 'pricing_table', 'contact_form'],
            'is_folder' => false,
            'is_startpage' => false,
        ],
        'product_page' => [
            'components' => ['hero_section', 'image_gallery', 'text_block', 'pricing_table'],
            'is_folder' => false,
            'is_startpage' => false,
        ],
        'folder' => [
            'components' => [],
            'is_folder' => true,
            'is_startpage' => false,
        ],
    ];

    /**
     * Sample content data for components.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $sampleContent = [
        'hero_section' => [
            'headlines' => [
                'Welcome to Our Amazing Platform',
                'Transform Your Business Today',
                'Build Something Extraordinary',
                'Innovation Meets Excellence',
                'Your Success Story Starts Here',
            ],
            'subheadlines' => [
                'Discover powerful tools and features designed to help you achieve your goals faster than ever before.',
                'Join thousands of satisfied customers who have transformed their businesses with our solutions.',
                'Experience the perfect blend of innovation, reliability, and user-friendly design.',
                'Take your projects to the next level with our comprehensive suite of professional tools.',
            ],
            'cta_texts' => [
                'Get Started Today',
                'Learn More',
                'Start Free Trial',
                'Contact Us',
                'Sign Up Now',
            ],
        ],
        'text_block' => [
            'content_types' => ['about', 'feature', 'blog', 'service'],
            'about' => [
                'Our company has been at the forefront of innovation for over a decade, delivering cutting-edge solutions that empower businesses to thrive in today\'s competitive landscape. We believe in the power of technology to transform lives and create meaningful connections.',
                'Founded on the principles of excellence and customer satisfaction, we have grown from a small startup to a global leader in our industry. Our team of dedicated professionals works tirelessly to ensure that every client receives personalized attention and exceptional results.',
            ],
            'feature' => [
                'Our advanced analytics dashboard provides real-time insights into your business performance, helping you make data-driven decisions with confidence. Track key metrics, identify trends, and optimize your strategies for maximum impact.',
                'Seamless integration with over 100 popular tools and platforms means you can start using our solution immediately without disrupting your existing workflow. Our robust API ensures smooth data synchronization across all your systems.',
            ],
            'blog' => [
                'In today\'s rapidly evolving digital landscape, staying ahead of the curve requires more than just keeping up with trends—it demands strategic thinking and innovative approaches to problem-solving.',
                'The key to sustainable growth lies in understanding your customers\' needs and delivering value at every touchpoint. This comprehensive guide will walk you through proven strategies for building lasting relationships.',
            ],
            'service' => [
                'Our comprehensive consulting services are designed to help you navigate complex challenges and unlock new opportunities for growth. With decades of combined experience, our team brings deep industry knowledge to every engagement.',
                'From initial strategy development to implementation and ongoing support, we partner with you every step of the way to ensure your success. Our proven methodologies have helped hundreds of organizations achieve their objectives.',
            ],
        ],
        'testimonial' => [
            'quotes' => [
                'This platform has completely transformed how we manage our projects. The intuitive interface and powerful features have saved us countless hours.',
                'Outstanding customer service and a product that truly delivers on its promises. We couldn\'t be happier with our decision to switch.',
                'The ROI has been incredible. Within just three months, we saw a 40% increase in productivity across our entire team.',
                'Finally, a solution that actually works as advertised. The implementation was smooth and the results exceeded our expectations.',
                'Game-changing technology that has revolutionized our workflow. Highly recommended for any serious business.',
            ],
            'authors' => [
                ['name' => 'Sarah Johnson', 'title' => 'CEO', 'company' => 'TechStart Inc.'],
                ['name' => 'Michael Chen', 'title' => 'CTO', 'company' => 'Digital Solutions'],
                ['name' => 'Emily Rodriguez', 'title' => 'Marketing Director', 'company' => 'Growth Labs'],
                ['name' => 'David Thompson', 'title' => 'Operations Manager', 'company' => 'Efficiency Corp'],
                ['name' => 'Lisa Wang', 'title' => 'Founder', 'company' => 'Innovation Hub'],
            ],
        ],
    ];

    /**
     * Story types with their characteristics.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $storyTypes = [
        'page' => [
            'names' => ['Home', 'About Us', 'Services', 'Contact', 'Privacy Policy', 'Terms of Service'],
            'weight' => 40,
        ],
        'blog' => [
            'names' => ['How to Get Started', 'Best Practices Guide', 'Industry Insights', 'Case Study', 'Tutorial'],
            'weight' => 30,
        ],
        'landing' => [
            'names' => ['Product Launch', 'Special Offer', 'Free Trial', 'Webinar Registration', 'Download Guide'],
            'weight' => 20,
        ],
        'folder' => [
            'names' => ['Blog', 'Products', 'Resources', 'Support', 'Company'],
            'weight' => 10,
        ],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        // Select story type based on weights
        $storyType = $this->selectWeightedStoryType();
        $isFolder = $storyType === 'folder';
        
        $name = $this->generateStoryName($storyType);
        $slug = Str::slug($name);
        
        return [
            'name' => $name,
            'slug' => $slug,
            'full_slug' => $slug, // Will be updated by model boot method
            'content' => $isFolder ? [] : $this->generateContent($storyType),
            'language' => $this->faker->randomElement(['en', 'en', 'en', 'es', 'fr', 'de']), // Weight towards English
            'translated_languages' => [],
            'status' => $this->faker->randomElement([
                Story::STATUS_PUBLISHED,
                Story::STATUS_PUBLISHED,
                Story::STATUS_PUBLISHED, // Weight towards published
                Story::STATUS_DRAFT,
                Story::STATUS_REVIEW,
                Story::STATUS_SCHEDULED,
                Story::STATUS_ARCHIVED,
            ]),
            'is_folder' => $isFolder,
            'is_startpage' => $storyType === 'page' && $this->faker->boolean(10), // 10% chance for startpage
            'sort_order' => $this->faker->numberBetween(0, 1000),
            'path' => null, // Will be generated by model
            'breadcrumbs' => null, // Will be generated by model
            'meta_data' => $this->generateMetaData($storyType),
            'meta_title' => $this->faker->optional(0.7)->sentence(4),
            'meta_description' => $this->faker->optional(0.6)->text(160),
            'robots_meta' => $this->generateRobotsMeta(),
            'allowed_roles' => $this->faker->optional(0.2)->randomElements(['admin', 'editor', 'author'], rand(1, 2)),
            'published_at' => function ($attributes) {
                return $attributes['status'] === Story::STATUS_PUBLISHED
                    ? $this->faker->dateTimeBetween('-2 years', 'now')
                    : null;
            },
            'unpublished_at' => null,
            'scheduled_at' => function ($attributes) {
                return $attributes['status'] === Story::STATUS_SCHEDULED
                    ? $this->faker->dateTimeBetween('now', '+1 month')
                    : null;
            },
        ];
    }

    /**
     * Select story type based on weights.
     */
    private function selectWeightedStoryType(): string
    {
        $weightedArray = [];
        
        foreach (self::$storyTypes as $type => $config) {
            $weightedArray = array_merge(
                $weightedArray,
                array_fill(0, $config['weight'], $type)
            );
        }
        
        return $this->faker->randomElement($weightedArray);
    }

    /**
     * Generate story name based on type.
     */
    private function generateStoryName(string $type): string
    {
        $names = self::$storyTypes[$type]['names'];
        
        if ($type === 'blog') {
            // Generate more varied blog titles
            $formats = [
                '%s: A Complete Guide',
                'The Ultimate %s Tutorial',
                '10 Tips for Better %s',
                'Why %s Matters in 2024',
                'How to Master %s',
                '%s Best Practices',
            ];
            
            $format = $this->faker->randomElement($formats);
            $topic = $this->faker->randomElement([
                'Content Marketing', 'SEO Strategy', 'Social Media', 'Web Design',
                'Digital Marketing', 'E-commerce', 'User Experience', 'Analytics'
            ]);
            
            return sprintf($format, $topic);
        }
        
        if ($type === 'landing') {
            $prefix = $this->faker->randomElement(['Free', 'Premium', 'Exclusive', 'Limited Time']);
            $suffix = $this->faker->randomElement(['Offer', 'Access', 'Trial', 'Download']);
            return "{$prefix} {$suffix}";
        }
        
        return $this->faker->randomElement($names);
    }

    /**
     * Generate content based on story type.
     *
     * @return array<string, mixed>
     */
    private function generateContent(string $storyType): array
    {
        $templateKey = match ($storyType) {
            'page' => $this->faker->randomElement(['homepage', 'about_page']),
            'blog' => 'blog_post',
            'landing' => 'landing_page',
            default => 'about_page',
        };
        
        $template = self::$contentTemplates[$templateKey];
        $components = $template['components'];
        
        $content = [
            'component' => 'page',
            '_uid' => (string) Str::uuid(),
            'body' => [],
        ];
        
        // Generate component blocks
        $componentCount = $this->faker->numberBetween(2, min(6, count($components)));
        $selectedComponents = $this->faker->randomElements($components, $componentCount);
        
        foreach ($selectedComponents as $componentType) {
            $content['body'][] = $this->generateComponentBlock($componentType);
        }
        
        return $content;
    }

    /**
     * Generate a component block.
     *
     * @return array<string, mixed>
     */
    private function generateComponentBlock(string $componentType): array
    {
        $block = [
            'component' => $componentType,
            '_uid' => (string) Str::uuid(),
        ];
        
        switch ($componentType) {
            case 'hero_section':
                $block = array_merge($block, $this->generateHeroContent());
                break;
                
            case 'text_block':
                $block = array_merge($block, $this->generateTextContent());
                break;
                
            case 'image_gallery':
                $block = array_merge($block, $this->generateGalleryContent());
                break;
                
            case 'testimonial':
                $block = array_merge($block, $this->generateTestimonialContent());
                break;
                
            case 'contact_form':
                $block = array_merge($block, $this->generateFormContent());
                break;
                
            case 'card_grid':
                $block = array_merge($block, $this->generateCardGridContent());
                break;
                
            case 'video_player':
                $block = array_merge($block, $this->generateVideoContent());
                break;
                
            case 'pricing_table':
                $block = array_merge($block, $this->generatePricingContent());
                break;
                
            default:
                $block = array_merge($block, $this->generateGenericContent());
                break;
        }
        
        return $block;
    }

    /**
     * Generate hero section content.
     *
     * @return array<string, mixed>
     */
    private function generateHeroContent(): array
    {
        $sampleData = self::$sampleContent['hero_section'];
        
        return [
            'headline' => $this->faker->randomElement($sampleData['headlines']),
            'subheadline' => $this->faker->randomElement($sampleData['subheadlines']),
            'background_image' => [
                'id' => $this->faker->numberBetween(1, 100),
                'filename' => 'hero-bg-' . $this->faker->numberBetween(1, 10) . '.jpg',
                'alt' => 'Hero background image',
            ],
            'cta_text' => $this->faker->randomElement($sampleData['cta_texts']),
            'cta_link' => [
                'story' => ['id' => $this->faker->numberBetween(1, 50)],
                'linktype' => 'story',
            ],
            'layout' => $this->faker->randomElement(['centered', 'left', 'right']),
        ];
    }

    /**
     * Generate text block content.
     *
     * @return array<string, mixed>
     */
    private function generateTextContent(): array
    {
        $contentType = $this->faker->randomElement(self::$sampleContent['text_block']['content_types']);
        $contentOptions = self::$sampleContent['text_block'][$contentType];
        
        return [
            'content' => $this->faker->randomElement($contentOptions),
            'text_align' => $this->faker->randomElement(['left', 'center', 'right', 'justify']),
            'max_width' => $this->faker->randomElement(['sm', 'md', 'lg', 'full']),
        ];
    }

    /**
     * Generate image gallery content.
     *
     * @return array<string, mixed>
     */
    private function generateGalleryContent(): array
    {
        $imageCount = $this->faker->numberBetween(3, 12);
        $images = [];
        
        for ($i = 0; $i < $imageCount; $i++) {
            $images[] = [
                'id' => $this->faker->numberBetween(1, 200),
                'filename' => 'gallery-' . ($i + 1) . '.jpg',
                'alt' => $this->faker->sentence(3),
            ];
        }
        
        return [
            'title' => $this->faker->optional(0.6)->sentence(2),
            'images' => $images,
            'layout' => $this->faker->randomElement(['grid', 'masonry', 'carousel']),
            'columns' => $this->faker->numberBetween(2, 4),
            'show_captions' => $this->faker->boolean(),
        ];
    }

    /**
     * Generate testimonial content.
     *
     * @return array<string, mixed>
     */
    private function generateTestimonialContent(): array
    {
        $sampleData = self::$sampleContent['testimonial'];
        $author = $this->faker->randomElement($sampleData['authors']);
        
        return [
            'quote' => $this->faker->randomElement($sampleData['quotes']),
            'author_name' => $author['name'],
            'author_title' => $author['title'],
            'author_company' => $author['company'],
            'author_image' => [
                'id' => $this->faker->numberBetween(1, 50),
                'filename' => 'avatar-' . $this->faker->numberBetween(1, 20) . '.jpg',
                'alt' => $author['name'] . ' photo',
            ],
            'rating' => $this->faker->numberBetween(4, 5),
            'layout' => $this->faker->randomElement(['card', 'minimal', 'featured']),
        ];
    }

    /**
     * Generate contact form content.
     *
     * @return array<string, mixed>
     */
    private function generateFormContent(): array
    {
        return [
            'title' => $this->faker->randomElement(['Get in Touch', 'Contact Us', 'Send us a Message']),
            'description' => 'We\'d love to hear from you. Send us a message and we\'ll respond as soon as possible.',
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true, 'label' => 'Name'],
                ['name' => 'email', 'type' => 'email', 'required' => true, 'label' => 'Email'],
                ['name' => 'subject', 'type' => 'text', 'required' => false, 'label' => 'Subject'],
                ['name' => 'message', 'type' => 'textarea', 'required' => true, 'label' => 'Message'],
            ],
            'submit_text' => 'Send Message',
            'success_message' => 'Thank you for your message! We\'ll get back to you soon.',
            'recipient_email' => 'contact@example.com',
        ];
    }

    /**
     * Generate card grid content.
     *
     * @return array<string, mixed>
     */
    private function generateCardGridContent(): array
    {
        $cardCount = $this->faker->numberBetween(3, 6);
        $cards = [];
        
        for ($i = 0; $i < $cardCount; $i++) {
            $cards[] = [
                'component' => 'card',
                '_uid' => (string) Str::uuid(),
                'image' => [
                    'id' => $this->faker->numberBetween(1, 100),
                    'filename' => 'card-' . ($i + 1) . '.jpg',
                    'alt' => 'Card image',
                ],
                'title' => $this->faker->sentence(3),
                'description' => $this->faker->sentence(8),
                'link' => [
                    'story' => ['id' => $this->faker->numberBetween(1, 50)],
                    'linktype' => 'story',
                ],
                'badge' => $this->faker->optional(0.3)->word(),
                'badge_color' => $this->faker->hexColor(),
            ];
        }
        
        return [
            'title' => $this->faker->optional(0.7)->sentence(2),
            'cards' => $cards,
            'columns' => (string) $this->faker->numberBetween(2, 4),
            'gap' => $this->faker->randomElement(['sm', 'md', 'lg']),
        ];
    }

    /**
     * Generate video player content.
     *
     * @return array<string, mixed>
     */
    private function generateVideoContent(): array
    {
        $videoSource = $this->faker->randomElement(['youtube', 'vimeo', 'upload']);
        
        $content = [
            'video_source' => $videoSource,
            'poster_image' => [
                'id' => $this->faker->numberBetween(1, 50),
                'filename' => 'video-poster.jpg',
                'alt' => 'Video poster',
            ],
            'autoplay' => false,
            'controls' => true,
            'loop' => false,
        ];
        
        if ($videoSource === 'youtube') {
            $content['video_url'] = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        } elseif ($videoSource === 'vimeo') {
            $content['video_url'] = 'https://vimeo.com/123456789';
        } else {
            $content['video_file'] = [
                'id' => $this->faker->numberBetween(1, 20),
                'filename' => 'video-' . $this->faker->numberBetween(1, 5) . '.mp4',
            ];
        }
        
        return $content;
    }

    /**
     * Generate pricing table content.
     *
     * @return array<string, mixed>
     */
    private function generatePricingContent(): array
    {
        $planCount = $this->faker->numberBetween(2, 4);
        $plans = [];
        
        $planNames = ['Basic', 'Professional', 'Enterprise', 'Premium'];
        $basePrice = 9;
        
        for ($i = 0; $i < $planCount; $i++) {
            $plans[] = [
                'component' => 'pricing_plan',
                '_uid' => (string) Str::uuid(),
                'name' => $planNames[$i] ?? 'Plan ' . ($i + 1),
                'price' => $basePrice * ($i + 1),
                'period' => 'month',
                'features' => array_map(
                    fn() => $this->faker->sentence(4),
                    range(1, $this->faker->numberBetween(3, 7))
                ),
                'featured' => $i === 1, // Make second plan featured
                'cta_text' => 'Get Started',
            ];
        }
        
        return [
            'title' => 'Choose Your Plan',
            'subtitle' => 'Select the perfect plan for your needs and start building today.',
            'plans' => $plans,
            'billing_toggle' => $this->faker->boolean(),
        ];
    }

    /**
     * Generate generic component content.
     *
     * @return array<string, mixed>
     */
    private function generateGenericContent(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'content' => $this->faker->paragraph(),
            'enabled' => true,
        ];
    }

    /**
     * Generate meta data for the story.
     *
     * @return array<string, mixed>
     */
    private function generateMetaData(string $storyType): array
    {
        $metaData = [
            'story_type' => $storyType,
            'created_from_template' => $this->faker->optional(0.3)->randomElement(['homepage', 'blog_post', 'landing_page']),
            'word_count' => $this->faker->numberBetween(100, 2000),
            'reading_time' => $this->faker->numberBetween(1, 10),
        ];
        
        // Add type-specific metadata
        switch ($storyType) {
            case 'blog':
                $metaData['author'] = $this->faker->name();
                $metaData['category'] = $this->faker->randomElement(['Technology', 'Marketing', 'Business', 'Design']);
                $metaData['tags'] = $this->faker->words(rand(2, 5));
                $metaData['featured_image'] = [
                    'id' => $this->faker->numberBetween(1, 100),
                    'filename' => 'blog-featured.jpg',
                ];
                break;
                
            case 'landing':
                $metaData['campaign'] = $this->faker->word();
                $metaData['conversion_goal'] = $this->faker->randomElement(['signup', 'download', 'purchase']);
                $metaData['a_b_test'] = $this->faker->boolean(30);
                break;
                
            case 'page':
                $metaData['last_reviewed'] = $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
                $metaData['review_frequency'] = $this->faker->randomElement(['monthly', 'quarterly', 'yearly']);
                break;
        }
        
        // Add SEO data
        if ($this->faker->boolean(60)) {
            $metaData['seo'] = [
                'focus_keyword' => $this->faker->word(),
                'canonical_url' => $this->faker->optional(0.3)->url(),
                'schema_markup' => $this->faker->optional(0.2)->randomElement(['Article', 'WebPage', 'Product']),
            ];
        }
        
        return $metaData;
    }

    /**
     * Generate robots meta configuration.
     *
     * @return array<string, mixed>|null
     */
    private function generateRobotsMeta(): ?array
    {
        if (!$this->faker->boolean(40)) {
            return null;
        }
        
        return [
            'index' => $this->faker->boolean(90),
            'follow' => $this->faker->boolean(95),
            'noarchive' => $this->faker->boolean(10),
            'nosnippet' => $this->faker->boolean(5),
            'noimageindex' => $this->faker->boolean(5),
        ];
    }

    /**
     * State for homepage stories.
     */
    public function homepage(): static
    {
        return $this->state([
            'name' => 'Home',
            'slug' => 'home',
            'is_startpage' => true,
            'is_folder' => false,
            'status' => Story::STATUS_PUBLISHED,
            'content' => function () {
                return $this->generateContent('page');
            },
            'meta_data' => [
                'story_type' => 'homepage',
                'template' => 'homepage',
                'priority' => 'high',
            ],
        ]);
    }

    /**
     * State for blog post stories.
     */
    public function blogPost(): static
    {
        return $this->state(function () {
            $title = $this->generateStoryName('blog');
            
            return [
                'name' => $title,
                'slug' => Str::slug($title),
                'is_folder' => false,
                'status' => $this->faker->randomElement([Story::STATUS_PUBLISHED, Story::STATUS_DRAFT]),
                'content' => $this->generateContent('blog'),
                'meta_data' => $this->generateMetaData('blog'),
                'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            ];
        });
    }

    /**
     * State for landing page stories.
     */
    public function landingPage(): static
    {
        return $this->state(function () {
            $title = $this->generateStoryName('landing');
            
            return [
                'name' => $title,
                'slug' => Str::slug($title),
                'is_folder' => false,
                'status' => Story::STATUS_PUBLISHED,
                'content' => $this->generateContent('landing'),
                'meta_data' => $this->generateMetaData('landing'),
            ];
        });
    }

    /**
     * State for folder stories.
     */
    public function folder(): static
    {
        return $this->state([
            'is_folder' => true,
            'is_startpage' => false,
            'content' => [],
            'status' => Story::STATUS_PUBLISHED,
            'name' => function () {
                return $this->faker->randomElement(['Blog', 'Products', 'Resources', 'Support', 'Company']);
            },
        ]);
    }

    /**
     * State for published stories.
     */
    public function published(): static
    {
        return $this->state([
            'status' => Story::STATUS_PUBLISHED,
            'published_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ]);
    }

    /**
     * State for draft stories.
     */
    public function draft(): static
    {
        return $this->state([
            'status' => Story::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    /**
     * State for scheduled stories.
     */
    public function scheduled(): static
    {
        return $this->state([
            'status' => Story::STATUS_SCHEDULED,
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 month'),
            'published_at' => null,
        ]);
    }

    /**
     * State for archived stories.
     */
    public function archived(): static
    {
        return $this->state([
            'status' => Story::STATUS_ARCHIVED,
            'published_at' => $this->faker->optional(0.8)->dateTimeBetween('-2 years', '-3 months'),
        ]);
    }

    /**
     * State for rich content stories.
     */
    public function richContent(): static
    {
        return $this->state([
            'content' => function () {
                $components = ['hero_section', 'text_block', 'image_gallery', 'testimonial', 'card_grid', 'contact_form'];
                $content = [
                    'component' => 'page',
                    '_uid' => (string) Str::uuid(),
                    'body' => [],
                ];
                
                foreach ($this->faker->randomElements($components, 4) as $componentType) {
                    $content['body'][] = $this->generateComponentBlock($componentType);
                }
                
                return $content;
            },
            'meta_data' => function ($attributes) {
                $metaData = $attributes['meta_data'] ?? [];
                $metaData['content_complexity'] = 'high';
                $metaData['component_count'] = 4;
                return $metaData;
            },
        ]);
    }

    /**
     * State for minimal content stories.
     */
    public function minimal(): static
    {
        return $this->state([
            'content' => [
                'component' => 'page',
                '_uid' => (string) Str::uuid(),
                'body' => [
                    [
                        'component' => 'text_block',
                        '_uid' => (string) Str::uuid(),
                        'content' => $this->faker->paragraph(),
                        'text_align' => 'left',
                    ],
                ],
            ],
            'meta_data' => [
                'story_type' => 'simple',
                'content_complexity' => 'low',
                'component_count' => 1,
            ],
        ]);
    }

    /**
     * State for multilingual stories.
     */
    public function multilingual(): static
    {
        return $this->state([
            'language' => 'en',
            'translated_languages' => ['es', 'fr', 'de'],
            'meta_data' => function ($attributes) {
                $metaData = $attributes['meta_data'] ?? [];
                $metaData['is_multilingual'] = true;
                $metaData['translation_status'] = 'completed';
                return $metaData;
            },
        ]);
    }

    /**
     * State for recently created stories.
     */
    public function recent(): static
    {
        return $this->state(function () {
            $createdAt = $this->faker->dateTimeBetween('-1 week', 'now');
            
            return [
                'created_at' => $createdAt,
                'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
                'status' => $this->faker->randomElement([Story::STATUS_DRAFT, Story::STATUS_REVIEW, Story::STATUS_PUBLISHED]),
            ];
        });
    }

    /**
     * State for popular stories with high engagement.
     */
    public function popular(): static
    {
        return $this->state([
            'meta_data' => function ($attributes) {
                $metaData = $attributes['meta_data'] ?? [];
                $metaData['page_views'] = $this->faker->numberBetween(1000, 50000);
                $metaData['social_shares'] = $this->faker->numberBetween(50, 1000);
                $metaData['engagement_score'] = $this->faker->randomFloat(2, 0.7, 1.0);
                $metaData['featured'] = true;
                return $metaData;
            },
            'status' => Story::STATUS_PUBLISHED,
            'published_at' => $this->faker->dateTimeBetween('-1 year', '-1 month'),
        ]);
    }
}
