<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Component;
use App\Models\Space;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * @group component-validation
 * @group story-management
 */
class ComponentValidationTest extends TestCase
{
    use RefreshDatabase;

    private Component $component;
    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->space = Space::factory()->create();
        $this->component = Component::factory()->for($this->space)->create([
            'schema' => [
                'title' => ['type' => 'text', 'required' => true, 'max_length' => 100],
                'description' => ['type' => 'textarea', 'required' => false, 'max_length' => 500],
                'email' => ['type' => 'email', 'required' => false],
                'count' => ['type' => 'number', 'min' => 0, 'max' => 100],
                'featured' => ['type' => 'boolean', 'required' => false],
                'category' => ['type' => 'select', 'options' => ['tech', 'business', 'lifestyle']],
                'tags' => ['type' => 'multiselect', 'options' => ['new', 'featured', 'popular']],
                'published_date' => ['type' => 'date', 'required' => false],
                'website' => ['type' => 'url', 'required' => false],
            ]
        ]);
    }

    public function test_validates_required_fields_correctly(): void
    {
        $validData = [
            'title' => 'Test Title',
            'description' => 'Optional description'
        ];
        
        $errors = $this->component->validateData($validData);
        $this->assertEmpty($errors, 'Valid data should pass validation');

        $invalidData = [
            'description' => 'Missing required title field'
        ];
        
        $errors = $this->component->validateData($invalidData);
        $this->assertArrayHasKey('title', $errors);
        $this->assertStringContainsString('required', $errors['title']);
    }

    public function test_validates_string_field_types(): void
    {
        $testCases = [
            // Valid strings
            ['title' => 'Valid title', 'expected_errors' => []],
            ['title' => 'Short', 'expected_errors' => []],
            
            // Invalid: not a string
            ['title' => 123, 'expected_errors' => ['title']],
            ['title' => true, 'expected_errors' => ['title']],
            ['title' => [], 'expected_errors' => ['title']],
        ];

        foreach ($testCases as $case) {
            $errors = $this->component->validateData($case);
            
            if (empty($case['expected_errors'])) {
                $this->assertEmpty($errors, "Data should be valid: " . json_encode($case));
            } else {
                foreach ($case['expected_errors'] as $field) {
                    $this->assertArrayHasKey($field, $errors, "Field {$field} should have validation error");
                }
            }
        }
    }

    public function test_validates_string_length_constraints(): void
    {
        // Test max_length constraint
        $longTitle = str_repeat('a', 101); // Exceeds max_length of 100
        $errors = $this->component->validateData(['title' => $longTitle]);
        $this->assertArrayHasKey('title', $errors);
        $this->assertStringContainsString('100 characters', $errors['title']);

        // Test valid length
        $validTitle = str_repeat('a', 50);
        $errors = $this->component->validateData(['title' => $validTitle]);
        $this->assertArrayNotHasKey('title', $errors);
    }

    public function test_validates_email_field_type(): void
    {
        $testCases = [
            ['email' => 'valid@example.com', 'should_pass' => true],
            ['email' => 'user.name+tag@domain.co.uk', 'should_pass' => true],
            ['email' => 'invalid-email', 'should_pass' => false],
            ['email' => 'missing@', 'should_pass' => false],
            ['email' => '@missing-local.com', 'should_pass' => false],
            ['email' => 123, 'should_pass' => false],
        ];

        foreach ($testCases as $case) {
            $data = ['title' => 'Required title'] + $case;
            $errors = $this->component->validateData($data);
            
            if ($case['should_pass']) {
                $this->assertArrayNotHasKey('email', $errors, "Email should be valid: {$case['email']}");
            } else {
                $this->assertArrayHasKey('email', $errors, "Email should be invalid: {$case['email']}");
                $this->assertStringContainsString('email', $errors['email']);
            }
        }
    }

    public function test_validates_number_field_type(): void
    {
        $testCases = [
            ['count' => 50, 'should_pass' => true],
            ['count' => 0, 'should_pass' => true],
            ['count' => 100, 'should_pass' => true],
            ['count' => '75', 'should_pass' => true], // String numbers should be valid
            ['count' => -5, 'should_pass' => false], // Below min
            ['count' => 150, 'should_pass' => false], // Above max
            ['count' => 'not-a-number', 'should_pass' => false],
            ['count' => [], 'should_pass' => false],
        ];

        foreach ($testCases as $case) {
            $data = ['title' => 'Required title'] + $case;
            $errors = $this->component->validateData($data);
            
            if ($case['should_pass']) {
                $this->assertArrayNotHasKey('count', $errors, "Number should be valid: {$case['count']}");
            } else {
                $this->assertArrayHasKey('count', $errors, "Number should be invalid: {$case['count']}");
            }
        }
    }

    public function test_validates_boolean_field_type(): void
    {
        $testCases = [
            ['featured' => true, 'should_pass' => true],
            ['featured' => false, 'should_pass' => true],
            ['featured' => 1, 'should_pass' => true],
            ['featured' => 0, 'should_pass' => true],
            ['featured' => '1', 'should_pass' => true],
            ['featured' => '0', 'should_pass' => true],
            ['featured' => 'true', 'should_pass' => true],
            ['featured' => 'false', 'should_pass' => true],
            ['featured' => 'yes', 'should_pass' => false],
            ['featured' => 'no', 'should_pass' => false],
            ['featured' => 2, 'should_pass' => false],
            ['featured' => 'random', 'should_pass' => false],
        ];

        foreach ($testCases as $case) {
            $data = ['title' => 'Required title'] + $case;
            $errors = $this->component->validateData($data);
            
            if ($case['should_pass']) {
                $this->assertArrayNotHasKey('featured', $errors, "Boolean should be valid: " . json_encode($case['featured']));
            } else {
                $this->assertArrayHasKey('featured', $errors, "Boolean should be invalid: " . json_encode($case['featured']));
            }
        }
    }

    public function test_validates_url_field_type(): void
    {
        $testCases = [
            ['website' => 'https://example.com', 'should_pass' => true],
            ['website' => 'http://subdomain.example.com/path', 'should_pass' => true],
            ['website' => 'ftp://files.example.com', 'should_pass' => true],
            ['website' => 'not-a-url', 'should_pass' => false],
            ['website' => 'example.com', 'should_pass' => false], // Missing protocol
            ['website' => 'http://', 'should_pass' => false],
        ];

        foreach ($testCases as $case) {
            $data = ['title' => 'Required title'] + $case;
            $errors = $this->component->validateData($data);
            
            if ($case['should_pass']) {
                $this->assertArrayNotHasKey('website', $errors, "URL should be valid: {$case['website']}");
            } else {
                $this->assertArrayHasKey('website', $errors, "URL should be invalid: {$case['website']}");
            }
        }
    }

    public function test_validates_date_field_type(): void
    {
        $testCases = [
            ['published_date' => '2024-01-15', 'should_pass' => true],
            ['published_date' => '2024-01-15 10:30:00', 'should_pass' => true],
            ['published_date' => '2024-01-15T10:30:00Z', 'should_pass' => true],
            ['published_date' => 'January 15, 2024', 'should_pass' => true],
            ['published_date' => 'invalid-date', 'should_pass' => false],
            ['published_date' => '2024-13-45', 'should_pass' => false], // Invalid date
            ['published_date' => 123, 'should_pass' => false],
        ];

        foreach ($testCases as $case) {
            $data = ['title' => 'Required title'] + $case;
            $errors = $this->component->validateData($data);
            
            if ($case['should_pass']) {
                $this->assertArrayNotHasKey('published_date', $errors, "Date should be valid: {$case['published_date']}");
            } else {
                $this->assertArrayHasKey('published_date', $errors, "Date should be invalid: {$case['published_date']}");
            }
        }
    }

    public function test_validates_select_field_options(): void
    {
        $testCases = [
            ['category' => 'tech', 'should_pass' => true],
            ['category' => 'business', 'should_pass' => true],
            ['category' => 'lifestyle', 'should_pass' => true],
            ['category' => 'invalid-option', 'should_pass' => false],
            ['category' => '', 'should_pass' => false],
            ['category' => 123, 'should_pass' => false],
        ];

        foreach ($testCases as $case) {
            $data = ['title' => 'Required title'] + $case;
            $errors = $this->component->validateData($data);
            
            if ($case['should_pass']) {
                $this->assertArrayNotHasKey('category', $errors, "Select option should be valid: {$case['category']}");
            } else {
                $this->assertArrayHasKey('category', $errors, "Select option should be invalid: {$case['category']}");
            }
        }
    }

    public function test_validates_multiselect_field_options(): void
    {
        $testCases = [
            ['tags' => ['new'], 'should_pass' => true],
            ['tags' => ['new', 'featured'], 'should_pass' => true],
            ['tags' => ['new', 'featured', 'popular'], 'should_pass' => true],
            ['tags' => [], 'should_pass' => true], // Empty array is valid for optional field
            ['tags' => ['invalid-tag'], 'should_pass' => false],
            ['tags' => ['new', 'invalid-tag'], 'should_pass' => false],
            ['tags' => 'not-an-array', 'should_pass' => false],
        ];

        foreach ($testCases as $case) {
            $data = ['title' => 'Required title'] + $case;
            $errors = $this->component->validateData($data);
            
            if ($case['should_pass']) {
                $this->assertArrayNotHasKey('tags', $errors, "Multiselect should be valid: " . json_encode($case['tags']));
            } else {
                $this->assertArrayHasKey('tags', $errors, "Multiselect should be invalid: " . json_encode($case['tags']));
            }
        }
    }

    public function test_skips_validation_for_optional_empty_fields(): void
    {
        $data = [
            'title' => 'Required title',
            // All other fields are optional and omitted
        ];
        
        $errors = $this->component->validateData($data);
        $this->assertEmpty($errors, 'Optional empty fields should not cause validation errors');
        
        // Test with explicit null values
        $dataWithNulls = [
            'title' => 'Required title',
            'description' => null,
            'email' => null,
            'count' => null,
        ];
        
        $errors = $this->component->validateData($dataWithNulls);
        $this->assertEmpty($errors, 'Optional null fields should not cause validation errors');
    }

    public function test_validates_complex_nested_data(): void
    {
        $complexData = [
            'title' => 'Complex Story',
            'description' => 'A story with all types of data',
            'email' => 'author@example.com',
            'count' => 75,
            'featured' => true,
            'category' => 'tech',
            'tags' => ['new', 'featured'],
            'published_date' => '2024-01-15',
            'website' => 'https://example.com'
        ];
        
        $errors = $this->component->validateData($complexData);
        $this->assertEmpty($errors, 'Complex valid data should pass all validations');
    }

    public function test_returns_multiple_validation_errors(): void
    {
        $invalidData = [
            'title' => str_repeat('x', 150), // Too long
            'email' => 'invalid-email',
            'count' => 200, // Above max
            'featured' => 'invalid-boolean',
            'category' => 'invalid-category',
            'website' => 'not-a-url'
        ];
        
        $errors = $this->component->validateData($invalidData);
        
        $this->assertCount(6, $errors, 'Should return errors for all invalid fields');
        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('count', $errors);
        $this->assertArrayHasKey('featured', $errors);
        $this->assertArrayHasKey('category', $errors);
        $this->assertArrayHasKey('website', $errors);
    }

    public function test_handles_empty_or_invalid_schema(): void
    {
        $componentWithEmptySchema = Component::factory()->for($this->space)->create([
            'schema' => []
        ]);
        
        $errors = $componentWithEmptySchema->validateData(['any' => 'data']);
        $this->assertEmpty($errors, 'Empty schema should not cause validation errors');
        
        $componentWithNullSchema = Component::factory()->for($this->space)->create([
            'schema' => null
        ]);
        
        $errors = $componentWithNullSchema->validateData(['any' => 'data']);
        $this->assertEmpty($errors, 'Null schema should not cause validation errors');
    }
}