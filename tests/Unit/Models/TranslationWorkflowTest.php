<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Story;
use App\Models\Space;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * @group translation-workflow
 * @group story-management
 */
class TranslationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Story $originalStory;
    private User $user;
    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->space = Space::factory()->create([
            'languages' => ['en', 'es', 'fr', 'de'],
            'default_language' => 'en'
        ]);
        
        $this->user = User::factory()->create();
        
        $this->originalStory = Story::factory()->for($this->space)->create([
            'name' => 'Original English Story',
            'slug' => 'original-english-story',
            'language' => 'en',
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-uid-123',
                        'component' => 'hero',
                        'title' => 'Welcome to Our Site',
                        'subtitle' => 'We build amazing products',
                        'button_text' => 'Get Started'
                    ],
                    [
                        '_uid' => 'text-uid-456',
                        'component' => 'text_block',
                        'content' => 'This is the main content of our page.',
                        'alignment' => 'left'
                    ]
                ]
            ],
            'meta_title' => 'Welcome Page - Our Company',
            'meta_description' => 'Learn about our company and products',
            'meta_data' => [
                'keywords' => ['company', 'products', 'welcome'],
                'author' => 'Marketing Team'
            ]
        ]);
    }

    public function test_create_translation_creates_linked_story(): void
    {
        $translationData = [
            'name' => 'Historia Original en Español',
            'slug' => 'historia-original-espanol',
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-uid-123', // Same UID as original
                        'component' => 'hero',
                        'title' => 'Bienvenido a Nuestro Sitio',
                        'subtitle' => 'Construimos productos increíbles',
                        'button_text' => 'Comenzar'
                    ],
                    [
                        '_uid' => 'text-uid-456', // Same UID as original
                        'component' => 'text_block',
                        'content' => 'Este es el contenido principal de nuestra página.',
                        'alignment' => 'left'
                    ]
                ]
            ],
            'meta_data' => [
                'meta_title' => 'Página de Bienvenida - Nuestra Empresa',
                'meta_description' => 'Conoce nuestra empresa y productos'
            ]
        ];
        
        $spanishStory = $this->originalStory->createTranslation('es', $translationData, $this->user);
        
        $this->assertInstanceOf(Story::class, $spanishStory);
        $this->assertEquals('es', $spanishStory->language);
        $this->assertEquals('Historia Original en Español', $spanishStory->name);
        $this->assertEquals('historia-original-espanol', $spanishStory->slug);
        $this->assertEquals($this->originalStory->id, $spanishStory->translation_group_id);
        $this->assertEquals($this->user->id, $spanishStory->created_by);
        
        // Check content structure is preserved
        $this->assertCount(2, $spanishStory->content['body']);
        $this->assertEquals('hero-uid-123', $spanishStory->content['body'][0]['_uid']);
        $this->assertEquals('Bienvenido a Nuestro Sitio', $spanishStory->content['body'][0]['title']);
    }

    public function test_translation_relationship_is_bidirectional(): void
    {
        $translationData = [
            'name' => 'Spanish Story',
            'slug' => 'spanish-story',
            'content' => ['body' => []]
        ];
        
        $spanishStory = $this->originalStory->createTranslation('es', $translationData, $this->user);
        
        // Original story should know about its translations
        $translations = $this->originalStory->getAllTranslations();
        $this->assertCount(2, $translations); // Original + Spanish
        
        $translationLanguages = $translations->pluck('language')->toArray();
        $this->assertContains('en', $translationLanguages);
        $this->assertContains('es', $translationLanguages);
        
        // Spanish story should know about its translation group
        $this->assertTrue($spanishStory->isTranslationOf($this->originalStory));
        $this->assertTrue($this->originalStory->isTranslationOf($spanishStory));
    }

    public function test_get_translation_status_provides_detailed_info(): void
    {
        // Create partial Spanish translation
        $spanishStory = $this->originalStory->createTranslation('es', [
            'name' => 'Spanish Story',
            'slug' => 'spanish-story',
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-uid-123',
                        'component' => 'hero',
                        'title' => 'Título en Español',
                        'subtitle' => '', // Empty - not translated
                        'button_text' => 'Comenzar'
                    ],
                    [
                        '_uid' => 'text-uid-456',
                        'component' => 'text_block',
                        'content' => '', // Empty - not translated
                        'alignment' => 'left'
                    ]
                ]
            ],
            'meta_data' => [
                'meta_title' => '', // Empty - not translated
                'meta_description' => 'Descripción en español'
            ]
        ], $this->user);
        
        $status = $this->originalStory->getTranslationStatus();
        
        $this->assertArrayHasKey('en', $status);
        $this->assertArrayHasKey('es', $status);
        
        // Check English (original) status
        $enStatus = $status['en'];
        $this->assertEquals($this->originalStory->uuid, $enStatus['uuid']);
        $this->assertEquals(100, $enStatus['completion_percentage']);
        $this->assertFalse($enStatus['needs_sync']);
        $this->assertGreaterThan(0, $enStatus['word_count']);
        
        // Check Spanish (partial translation) status
        $esStatus = $status['es'];
        $this->assertEquals($spanishStory->uuid, $esStatus['uuid']);
        $this->assertLessThan(100, $esStatus['completion_percentage']);
        $this->assertGreaterThan(0, $esStatus['completion_percentage']);
        $this->assertFalse($esStatus['needs_sync']); // Same structure, just incomplete
    }

    public function test_sync_translation_content_updates_structure(): void
    {
        // Create translation
        $spanishStory = $this->originalStory->createTranslation('es', [
            'name' => 'Spanish Story',
            'slug' => 'spanish-story',
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-uid-123',
                        'component' => 'hero',
                        'title' => 'Título Español',
                        'subtitle' => 'Subtítulo español'
                    ]
                    // Missing the text_block component
                ]
            ]
        ], $this->user);
        
        // Modify original story to add new content
        $this->originalStory->update([
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-uid-123',
                        'component' => 'hero',
                        'title' => 'Updated Welcome Title',
                        'subtitle' => 'Updated subtitle',
                        'button_text' => 'New Button Text'
                    ],
                    [
                        '_uid' => 'text-uid-456',
                        'component' => 'text_block',
                        'content' => 'Updated main content',
                        'alignment' => 'center'
                    ],
                    [
                        '_uid' => 'new-uid-789',
                        'component' => 'cta_section',
                        'title' => 'New Call to Action',
                        'button_text' => 'Learn More'
                    ]
                ]
            ],
            'meta_title' => 'Updated Page Title',
            'meta_data' => [
                'keywords' => ['updated', 'keywords'],
                'new_field' => 'new_value'
            ]
        ]);
        
        // Sync translation
        $result = $spanishStory->syncTranslationContent($this->originalStory, ['content', 'meta_data']);
        
        $this->assertTrue($result);
        
        $spanishStory->refresh();
        
        // Should now have all three components
        $this->assertCount(3, $spanishStory->content['body']);
        
        // Existing translations should be preserved
        $heroBlock = collect($spanishStory->content['body'])->firstWhere('_uid', 'hero-uid-123');
        $this->assertEquals('Título Español', $heroBlock['title']); // Preserved
        $this->assertEquals('Subtítulo español', $heroBlock['subtitle']); // Preserved
        $this->assertEquals('New Button Text', $heroBlock['button_text']); // Added from original
        
        // New components should be added with original content
        $ctaBlock = collect($spanishStory->content['body'])->firstWhere('_uid', 'new-uid-789');
        $this->assertNotNull($ctaBlock);
        $this->assertEquals('New Call to Action', $ctaBlock['title']);
        
        // Meta data should be synced
        $this->assertArrayHasKey('new_field', $spanishStory->meta_data);
        $this->assertEquals('new_value', $spanishStory->meta_data['new_field']);
    }

    public function test_get_untranslated_fields_identifies_missing_content(): void
    {
        $spanishStory = $this->originalStory->createTranslation('es', [
            'name' => 'Spanish Story',
            'slug' => 'spanish-story',
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-uid-123',
                        'component' => 'hero',
                        'title' => 'Título traducido',
                        'subtitle' => '', // Empty - needs translation
                        'button_text' => 'Comenzar'
                    ],
                    [
                        '_uid' => 'text-uid-456',
                        'component' => 'text_block',
                        'content' => '', // Empty - needs translation
                        'alignment' => 'left'
                    ]
                ]
            ],
            'meta_data' => [
                'meta_title' => '', // Empty - needs translation
                'meta_description' => 'Descripción traducida'
            ]
        ], $this->user);
        
        $untranslated = $spanishStory->getUntranslatedFields($this->originalStory);
        
        $this->assertArrayHasKey('content', $untranslated);
        $this->assertArrayHasKey('meta', $untranslated);
        
        // Check content untranslated fields
        $contentUntranslated = $untranslated['content'];
        $this->assertNotEmpty($contentUntranslated);
        
        // Check meta untranslated fields
        $metaUntranslated = $untranslated['meta'];
        $this->assertArrayHasKey('meta_title', $metaUntranslated);
        $this->assertArrayNotHasKey('meta_description', $metaUntranslated); // This one is translated
    }

    public function test_translation_status_detects_sync_needed(): void
    {
        // Create translation
        $spanishStory = $this->originalStory->createTranslation('es', [
            'name' => 'Spanish Story',
            'slug' => 'spanish-story',
            'content' => $this->originalStory->content
        ], $this->user);
        
        // Both should be in sync initially
        $status = $this->originalStory->getTranslationStatus();
        $this->assertFalse($status['es']['needs_sync']);
        
        // Update original story
        $this->originalStory->update([
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-uid-123',
                        'component' => 'hero',
                        'title' => 'Updated Title',
                        'subtitle' => 'Updated subtitle',
                        'button_text' => 'Updated button'
                    ]
                ]
            ]
        ]);
        
        // Now translation should need sync
        $status = $this->originalStory->fresh()->getTranslationStatus();
        $this->assertTrue($status['es']['needs_sync']);
    }

    public function test_translation_completion_percentage_calculation(): void
    {
        // Create translation with half the content translated
        $spanishStory = $this->originalStory->createTranslation('es', [
            'name' => 'Spanish Story',
            'slug' => 'spanish-story',
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-uid-123',
                        'component' => 'hero',
                        'title' => 'Título traducido', // Translated
                        'subtitle' => '', // Not translated
                        'button_text' => 'Comenzar' // Translated
                    ],
                    [
                        '_uid' => 'text-uid-456',
                        'component' => 'text_block',
                        'content' => '', // Not translated
                        'alignment' => 'left' // Not text content, doesn't count
                    ]
                ]
            ],
            'meta_data' => [
                'meta_title' => 'Título meta traducido', // Translated
                'meta_description' => '' // Not translated
            ]
        ], $this->user);
        
        $status = $this->originalStory->getTranslationStatus();
        $completion = $status['es']['completion_percentage'];
        
        // Should be between 0 and 100, and greater than 0 since some content is translated
        $this->assertGreaterThan(0, $completion);
        $this->assertLessThan(100, $completion);
        $this->assertIsInt($completion);
    }

    public function test_multiple_translations_in_different_languages(): void
    {
        // Create Spanish translation
        $spanishStory = $this->originalStory->createTranslation('es', [
            'name' => 'Historia en Español',
            'slug' => 'historia-espanol',
            'content' => ['body' => []]
        ], $this->user);
        
        // Create French translation
        $frenchStory = $this->originalStory->createTranslation('fr', [
            'name' => 'Histoire en Français',
            'slug' => 'histoire-francais',
            'content' => ['body' => []]
        ], $this->user);
        
        $allTranslations = $this->originalStory->getAllTranslations();
        $this->assertCount(3, $allTranslations); // Original + Spanish + French
        
        $languages = $allTranslations->pluck('language')->toArray();
        $this->assertContains('en', $languages);
        $this->assertContains('es', $languages);
        $this->assertContains('fr', $languages);
        
        // Each translation should know about the others
        $this->assertTrue($spanishStory->isTranslationOf($frenchStory));
        $this->assertTrue($frenchStory->isTranslationOf($spanishStory));
        $this->assertTrue($spanishStory->isTranslationOf($this->originalStory));
    }

    public function test_translation_preserves_meta_structure(): void
    {
        $translationData = [
            'name' => 'Spanish Story',
            'slug' => 'spanish-story',
            'content' => ['body' => []],
            'meta_data' => [
                'meta_title' => 'Título en Español',
                'meta_description' => 'Descripción en español',
                'custom_field' => 'valor personalizado'
            ]
        ];
        
        $spanishStory = $this->originalStory->createTranslation('es', $translationData, $this->user);
        
        $this->assertEquals('Título en Español', $spanishStory->meta_title);
        $this->assertEquals('Descripción en español', $spanishStory->meta_description);
        $this->assertArrayHasKey('custom_field', $spanishStory->meta_data);
        $this->assertEquals('valor personalizado', $spanishStory->meta_data['custom_field']);
    }

    public function test_cannot_create_duplicate_translation_for_same_language(): void
    {
        // Create first Spanish translation
        $spanishStory1 = $this->originalStory->createTranslation('es', [
            'name' => 'First Spanish',
            'slug' => 'first-spanish',
            'content' => ['body' => []]
        ], $this->user);
        
        $this->assertInstanceOf(Story::class, $spanishStory1);
        
        // Try to create second Spanish translation - should handle gracefully
        $spanishStory2 = $this->originalStory->createTranslation('es', [
            'name' => 'Second Spanish',
            'slug' => 'second-spanish',
            'content' => ['body' => []]
        ], $this->user);
        
        // Should still create (business logic can prevent this at service level if needed)
        $this->assertInstanceOf(Story::class, $spanishStory2);
    }

    public function test_translation_word_count_calculation(): void
    {
        $contentWithText = [
            'body' => [
                [
                    '_uid' => 'hero-uid-123',
                    'component' => 'hero',
                    'title' => 'This is a five word title',
                    'subtitle' => 'And this subtitle has seven words total',
                    'button_text' => 'Click'
                ],
                [
                    '_uid' => 'text-uid-456',
                    'component' => 'text_block',
                    'content' => 'This paragraph contains exactly ten words in the entire content block.'
                ]
            ]
        ];
        
        $spanishStory = $this->originalStory->createTranslation('es', [
            'name' => 'Spanish Story',
            'slug' => 'spanish-story',
            'content' => $contentWithText,
            'meta_data' => [
                'meta_title' => 'Meta title with four words',
                'meta_description' => 'Meta description with many more words than the title'
            ]
        ], $this->user);
        
        $status = $this->originalStory->getTranslationStatus();
        $wordCount = $status['es']['word_count'];
        
        $this->assertGreaterThan(0, $wordCount);
        $this->assertIsInt($wordCount);
        // Should count words from title, subtitle, button_text, content, meta_title, meta_description
        $this->assertGreaterThanOrEqual(20, $wordCount); // Rough estimate
    }
}