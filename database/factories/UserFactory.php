<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 * @psalm-suppress UnusedClass
 */
final class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\User>
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * CMS-specific job titles for realistic user profiles.
     *
     * @var array<string, array<string>>
     */
    private static array $cmsJobTitles = [
        'content' => [
            'Content Manager', 'Content Editor', 'Content Writer', 'Content Strategist',
            'Content Marketing Manager', 'Blog Editor', 'Copy Editor', 'Technical Writer',
            'Content Specialist', 'Editorial Manager', 'Content Producer', 'Content Coordinator'
        ],
        'design' => [
            'UX Designer', 'UI Designer', 'Web Designer', 'Graphic Designer',
            'Creative Director', 'Visual Designer', 'Product Designer', 'Brand Designer',
            'Art Director', 'Design Manager', 'Digital Designer', 'Frontend Designer'
        ],
        'development' => [
            'Frontend Developer', 'Backend Developer', 'Full Stack Developer', 'Web Developer',
            'Software Engineer', 'DevOps Engineer', 'Technical Lead', 'Senior Developer',
            'JavaScript Developer', 'PHP Developer', 'React Developer', 'Vue.js Developer'
        ],
        'marketing' => [
            'Digital Marketing Manager', 'SEO Specialist', 'Social Media Manager', 'Marketing Coordinator',
            'Growth Marketer', 'Performance Marketing Manager', 'Brand Manager', 'Campaign Manager',
            'Marketing Analytics Manager', 'Email Marketing Manager', 'Content Marketing Specialist'
        ],
        'management' => [
            'Project Manager', 'Product Manager', 'Team Lead', 'Department Head',
            'Operations Manager', 'Client Success Manager', 'Account Manager', 'Business Analyst',
            'Scrum Master', 'Product Owner', 'Strategy Manager', 'Director of Operations'
        ],
        'admin' => [
            'System Administrator', 'Site Administrator', 'CMS Administrator', 'Database Administrator',
            'IT Administrator', 'Platform Administrator', 'User Administrator', 'Security Administrator'
        ],
    ];

    /**
     * User expertise areas for CMS users.
     *
     * @var array<string>
     */
    private static array $expertiseAreas = [
        'Content Management', 'SEO Optimization', 'Web Analytics', 'Social Media',
        'Email Marketing', 'UX Design', 'Frontend Development', 'Backend Development',
        'Digital Marketing', 'Brand Management', 'Project Management', 'Data Analysis',
        'E-commerce', 'Mobile Development', 'API Integration', 'Performance Optimization',
        'Security', 'Accessibility', 'Multilingual Content', 'Workflow Automation'
    ];

    /**
     * User status options.
     *
     * @var array<string>
     */
    private static array $userStatuses = [
        'active', 'inactive', 'pending', 'suspended'
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $fullName = "{$firstName} {$lastName}";
        
        // Generate a professional email address
        $emailFormats = [
            strtolower($firstName . '.' . $lastName),
            strtolower($firstName . $lastName),
            strtolower(substr($firstName, 0, 1) . $lastName),
            strtolower($firstName) . $this->faker->numberBetween(1, 999),
        ];
        
        $emailPrefix = $this->faker->randomElement($emailFormats);
        $emailDomain = $this->faker->randomElement([
            'company.com', 'business.org', 'agency.co', 'studio.com',
            'tech.io', 'creative.com', 'digital.net', 'marketing.com'
        ]);
        
        return [
            'name' => $fullName,
            'email' => $emailPrefix . '@' . $emailDomain,
            'email_verified_at' => $this->faker->boolean(85) ? $this->faker->dateTimeBetween('-2 years', 'now') : null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => $this->faker->optional(0.3)->regexify('[A-Za-z0-9]{10}'),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'avatar' => $this->generateAvatar(),
            'bio' => $this->faker->optional(0.6)->paragraph(),
            'job_title' => $this->generateJobTitle(),
            'department' => $this->faker->optional(0.7)->randomElement([
                'Content', 'Design', 'Development', 'Marketing', 'Management', 'Administration'
            ]),
            'location' => $this->faker->optional(0.5)->city() . ', ' . $this->faker->optional(0.5)->country(),
            'timezone' => $this->faker->timezone(),
            'language' => $this->faker->randomElement(['en', 'en', 'en', 'es', 'fr', 'de', 'it']), // Weight towards English
            'phone' => $this->faker->optional(0.4)->phoneNumber(),
            'website' => $this->faker->optional(0.3)->url(),
            'social_links' => $this->generateSocialLinks(),
            'preferences' => $this->generateUserPreferences(),
            'expertise' => $this->faker->randomElements(self::$expertiseAreas, $this->faker->numberBetween(1, 5)),
            'two_factor_enabled' => $this->faker->boolean(30),
            'email_notifications' => $this->faker->boolean(70),
            'status' => $this->faker->randomElement([
                'active', 'active', 'active', 'active', // Weight heavily towards active
                'inactive', 'pending', 'suspended'
            ]),
            'last_login_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 month', 'now'),
            'last_activity_at' => $this->faker->optional(0.9)->dateTimeBetween('-1 week', 'now'),
            'login_count' => $this->faker->numberBetween(0, 500),
            'failed_login_attempts' => $this->faker->numberBetween(0, 3),
            'account_locked_until' => null, // Will be set for locked accounts
            'invitation_token' => null, // Will be set for pending users
            'invitation_sent_at' => null,
            'invitation_accepted_at' => null,
            'terms_accepted_at' => $this->faker->optional(0.9)->dateTimeBetween('-2 years', 'now'),
            'privacy_accepted_at' => $this->faker->optional(0.9)->dateTimeBetween('-2 years', 'now'),
            'marketing_consent' => $this->faker->boolean(40),
            'api_key' => $this->faker->optional(0.2)->sha256(),
            'api_requests_count' => $this->faker->numberBetween(0, 10000),
            'session_data' => $this->generateSessionData(),
            'notes' => $this->faker->optional(0.2)->sentence(),
        ];
    }

    /**
     * Generate avatar configuration.
     *
     * @return array<string, mixed>|null
     */
    private function generateAvatar(): ?array
    {
        if (!$this->faker->boolean(60)) {
            return null;
        }
        
        $avatarTypes = ['uploaded', 'gravatar', 'initials', 'generated'];
        $type = $this->faker->randomElement($avatarTypes);
        
        return match ($type) {
            'uploaded' => [
                'type' => 'uploaded',
                'path' => 'avatars/' . $this->faker->uuid() . '.jpg',
                'url' => $this->faker->imageUrl(200, 200, 'people'),
            ],
            'gravatar' => [
                'type' => 'gravatar',
                'hash' => md5(strtolower(trim($this->faker->email()))),
                'size' => 200,
            ],
            'initials' => [
                'type' => 'initials',
                'background_color' => $this->faker->hexColor(),
                'text_color' => '#ffffff',
            ],
            'generated' => [
                'type' => 'generated',
                'seed' => $this->faker->uuid(),
                'style' => $this->faker->randomElement(['avataaars', 'bottts', 'identicon']),
            ],
            default => null,
        };
    }

    /**
     * Generate a realistic job title based on CMS context.
     */
    private function generateJobTitle(): string
    {
        $category = $this->faker->randomElement(array_keys(self::$cmsJobTitles));
        $titles = self::$cmsJobTitles[$category];
        
        $baseTitle = $this->faker->randomElement($titles);
        
        // Add seniority level occasionally
        if ($this->faker->boolean(30)) {
            $levels = ['Junior', 'Senior', 'Lead', 'Principal'];
            $level = $this->faker->randomElement($levels);
            return "{$level} {$baseTitle}";
        }
        
        return $baseTitle;
    }

    /**
     * Generate social media links.
     *
     * @return array<string, string>|null
     */
    private function generateSocialLinks(): ?array
    {
        if (!$this->faker->boolean(40)) {
            return null;
        }
        
        $platforms = ['twitter', 'linkedin', 'github', 'dribbble', 'behance', 'instagram'];
        $selectedPlatforms = $this->faker->randomElements($platforms, $this->faker->numberBetween(1, 3));
        
        $links = [];
        foreach ($selectedPlatforms as $platform) {
            $username = strtolower($this->faker->userName());
            $links[$platform] = "https://{$platform}.com/{$username}";
        }
        
        return $links;
    }

    /**
     * Generate user preferences for the CMS.
     *
     * @return array<string, mixed>
     */
    private function generateUserPreferences(): array
    {
        return [
            'interface' => [
                'theme' => $this->faker->randomElement(['light', 'dark', 'auto']),
                'sidebar_collapsed' => $this->faker->boolean(),
                'items_per_page' => $this->faker->randomElement([10, 25, 50, 100]),
                'date_format' => $this->faker->randomElement(['MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD']),
                'time_format' => $this->faker->randomElement(['12h', '24h']),
            ],
            'editor' => [
                'default_editor' => $this->faker->randomElement(['rich', 'markdown', 'code']),
                'auto_save' => $this->faker->boolean(80),
                'auto_save_interval' => $this->faker->randomElement([30, 60, 120, 300]), // seconds
                'spell_check' => $this->faker->boolean(70),
                'word_wrap' => $this->faker->boolean(60),
            ],
            'content' => [
                'default_language' => $this->faker->randomElement(['en', 'es', 'fr', 'de']),
                'show_drafts' => $this->faker->boolean(80),
                'image_quality' => $this->faker->randomElement(['low', 'medium', 'high']),
                'auto_generate_slug' => $this->faker->boolean(90),
            ],
            'notifications' => [
                'email_digest' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'never']),
                'browser_notifications' => $this->faker->boolean(40),
                'content_published' => $this->faker->boolean(80),
                'comment_added' => $this->faker->boolean(60),
                'user_mentioned' => $this->faker->boolean(85),
                'system_updates' => $this->faker->boolean(50),
            ],
            'security' => [
                'session_timeout' => $this->faker->randomElement([30, 60, 120, 240]), // minutes
                'require_2fa' => $this->faker->boolean(20),
                'log_activity' => $this->faker->boolean(70),
                'email_login_alerts' => $this->faker->boolean(60),
            ],
        ];
    }

    /**
     * Generate session data.
     *
     * @return array<string, mixed>|null
     */
    private function generateSessionData(): ?array
    {
        if (!$this->faker->boolean(60)) {
            return null;
        }
        
        return [
            'last_ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera']),
            'platform' => $this->faker->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
            'screen_resolution' => $this->faker->randomElement(['1920x1080', '1366x768', '2560x1440', '1440x900']),
            'active_sessions' => $this->faker->numberBetween(1, 3),
            'current_workspace' => $this->faker->optional(0.7)->numberBetween(1, 10),
        ];
    }

    /**
     * State for admin users.
     */
    public function admin(): static
    {
        return $this->state([
            'job_title' => $this->faker->randomElement([
                'System Administrator', 'Platform Administrator', 'Technical Director',
                'CTO', 'Head of IT', 'Senior Developer'
            ]),
            'department' => 'Administration',
            'email_verified_at' => now(),
            'two_factor_enabled' => true,
            'status' => 'active',
            'expertise' => ['System Administration', 'Security', 'API Integration', 'Performance Optimization'],
            'preferences' => function ($attributes) {
                $prefs = $attributes['preferences'] ?? $this->generateUserPreferences();
                $prefs['security']['require_2fa'] = true;
                $prefs['security']['log_activity'] = true;
                $prefs['notifications']['system_updates'] = true;
                return $prefs;
            },
        ]);
    }

    /**
     * State for editor users.
     */
    public function editor(): static
    {
        return $this->state([
            'job_title' => $this->faker->randomElement(self::$cmsJobTitles['content']),
            'department' => 'Content',
            'status' => 'active',
            'expertise' => $this->faker->randomElements([
                'Content Management', 'SEO Optimization', 'Content Strategy', 'Copy Writing'
            ], 3),
            'preferences' => function ($attributes) {
                $prefs = $attributes['preferences'] ?? $this->generateUserPreferences();
                $prefs['editor']['auto_save'] = true;
                $prefs['editor']['spell_check'] = true;
                $prefs['content']['auto_generate_slug'] = true;
                return $prefs;
            },
        ]);
    }

    /**
     * State for author users.
     */
    public function author(): static
    {
        return $this->state([
            'job_title' => $this->faker->randomElement([
                'Content Writer', 'Blog Writer', 'Technical Writer', 'Freelance Writer',
                'Content Creator', 'Content Specialist'
            ]),
            'department' => 'Content',
            'status' => 'active',
            'expertise' => $this->faker->randomElements([
                'Content Writing', 'Blog Writing', 'SEO Writing', 'Technical Writing'
            ], 2),
        ]);
    }

    /**
     * State for developer users.
     */
    public function developer(): static
    {
        return $this->state([
            'job_title' => $this->faker->randomElement(self::$cmsJobTitles['development']),
            'department' => 'Development',
            'status' => 'active',
            'expertise' => $this->faker->randomElements([
                'Frontend Development', 'Backend Development', 'API Integration', 'Performance Optimization'
            ], 3),
            'api_key' => $this->faker->sha256(),
            'api_requests_count' => $this->faker->numberBetween(100, 50000),
            'preferences' => function ($attributes) {
                $prefs = $attributes['preferences'] ?? $this->generateUserPreferences();
                $prefs['editor']['default_editor'] = 'code';
                $prefs['interface']['theme'] = 'dark';
                return $prefs;
            },
        ]);
    }

    /**
     * State for designer users.
     */
    public function designer(): static
    {
        return $this->state([
            'job_title' => $this->faker->randomElement(self::$cmsJobTitles['design']),
            'department' => 'Design',
            'status' => 'active',
            'expertise' => $this->faker->randomElements([
                'UX Design', 'UI Design', 'Web Design', 'Brand Design'
            ], 2),
            'social_links' => [
                'dribbble' => 'https://dribbble.com/' . strtolower($this->faker->userName()),
                'behance' => 'https://behance.net/' . strtolower($this->faker->userName()),
            ],
        ]);
    }

    /**
     * State for pending users (not yet activated).
     */
    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'email_verified_at' => null,
            'invitation_token' => Str::random(32),
            'invitation_sent_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'invitation_accepted_at' => null,
            'last_login_at' => null,
            'last_activity_at' => null,
            'login_count' => 0,
        ]);
    }

    /**
     * State for inactive users.
     */
    public function inactive(): static
    {
        return $this->state([
            'status' => 'inactive',
            'last_login_at' => $this->faker->optional(0.5)->dateTimeBetween('-1 year', '-3 months'),
            'last_activity_at' => $this->faker->optional(0.5)->dateTimeBetween('-1 year', '-3 months'),
        ]);
    }

    /**
     * State for suspended users.
     */
    public function suspended(): static
    {
        return $this->state([
            'status' => 'suspended',
            'failed_login_attempts' => $this->faker->numberBetween(3, 10),
            'account_locked_until' => $this->faker->dateTimeBetween('now', '+1 month'),
            'notes' => 'Account suspended due to ' . $this->faker->randomElement([
                'policy violation', 'security concerns', 'repeated failed login attempts'
            ]),
        ]);
    }

    /**
     * State for users with two-factor authentication enabled.
     */
    public function withTwoFactor(): static
    {
        return $this->state([
            'two_factor_enabled' => true,
            'preferences' => function ($attributes) {
                $prefs = $attributes['preferences'] ?? $this->generateUserPreferences();
                $prefs['security']['require_2fa'] = true;
                $prefs['security']['email_login_alerts'] = true;
                return $prefs;
            },
        ]);
    }

    /**
     * State for recently active users.
     */
    public function recentlyActive(): static
    {
        return $this->state([
            'last_login_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'last_activity_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'login_count' => $this->faker->numberBetween(50, 1000),
            'status' => 'active',
        ]);
    }

    /**
     * State for power users (heavy CMS usage).
     */
    public function powerUser(): static
    {
        return $this->state([
            'login_count' => $this->faker->numberBetween(500, 5000),
            'api_requests_count' => $this->faker->numberBetween(10000, 100000),
            'expertise' => $this->faker->randomElements(self::$expertiseAreas, 6),
            'status' => 'active',
            'two_factor_enabled' => true,
            'api_key' => $this->faker->sha256(),
            'preferences' => function ($attributes) {
                $prefs = $attributes['preferences'] ?? $this->generateUserPreferences();
                $prefs['interface']['items_per_page'] = 100;
                $prefs['editor']['auto_save_interval'] = 30;
                $prefs['security']['session_timeout'] = 240;
                return $prefs;
            },
        ]);
    }

    /**
     * State for verified users.
     */
    public function verified(): static
    {
        return $this->state([
            'email_verified_at' => $this->faker->dateTimeBetween('-2 years', '-1 week'),
            'terms_accepted_at' => $this->faker->dateTimeBetween('-2 years', '-1 week'),
            'privacy_accepted_at' => $this->faker->dateTimeBetween('-2 years', '-1 week'),
            'status' => 'active',
        ]);
    }

    /**
     * State for unverified users.
     */
    public function unverified(): static
    {
        return $this->state([
            'email_verified_at' => null,
            'status' => $this->faker->randomElement(['pending', 'inactive']),
        ]);
    }

    /**
     * State for users with complete profiles.
     */
    public function completeProfile(): static
    {
        return $this->state([
            'bio' => $this->faker->paragraph(),
            'job_title' => $this->generateJobTitle(),
            'department' => $this->faker->randomElement([
                'Content', 'Design', 'Development', 'Marketing', 'Management'
            ]),
            'location' => $this->faker->city() . ', ' . $this->faker->country(),
            'phone' => $this->faker->phoneNumber(),
            'website' => $this->faker->url(),
            'avatar' => $this->generateAvatar(),
            'social_links' => $this->generateSocialLinks(),
            'expertise' => $this->faker->randomElements(self::$expertiseAreas, 4),
        ]);
    }
}
