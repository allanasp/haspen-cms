<?php

require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Space;
use App\Models\User;
use App\Models\Component;
use App\Models\Story;

echo "🧪 Testing Story Management System Features\n";
echo "============================================\n\n";

// Get test data
$space = Space::first();
$user = User::first();
$component = Component::first();
$story = Story::first();

if (!$space || !$user || !$component || !$story) {
    echo "❌ Test data not found. Run: php artisan test:create-data\n";
    exit(1);
}

echo "📋 Test Data Found:\n";
echo "- Space: {$space->name} ({$space->uuid})\n";
echo "- User: {$user->name} ({$user->id})\n";
echo "- Component: {$component->name} ({$component->uuid})\n";
echo "- Story: {$story->name} ({$story->uuid})\n\n";

// Test 1: Component Validation
echo "🔍 Test 1: Component Schema Validation\n";
echo "--------------------------------------\n";

$validData = [
    'title' => 'Test Title',
    'content' => 'Test content here',
    'featured' => true
];

$invalidData = [
    'content' => 'Missing required title',
    'featured' => 'invalid_boolean'
];

$validationErrors = $component->validateData($validData);
echo "✅ Valid data errors: " . (empty($validationErrors) ? 'None' : json_encode($validationErrors)) . "\n";

$validationErrors = $component->validateData($invalidData);
echo "❌ Invalid data errors: " . json_encode($validationErrors) . "\n\n";

// Test 2: Content Locking
echo "🔒 Test 2: Content Locking\n";
echo "---------------------------\n";

$lockResult = $story->lock($user, 'test-session', 30);
echo "Lock acquired: " . ($lockResult ? 'Yes' : 'No') . "\n";

$lockInfo = $story->getLockInfo();
echo "Lock info: " . json_encode($lockInfo, JSON_PRETTY_PRINT) . "\n";

$story->unlock($user, 'test-session');
echo "Lock released\n\n";

// Test 3: Template Creation
echo "📋 Test 3: Template Creation\n";
echo "-----------------------------\n";

$template = $story->createTemplate('Test Template', 'A template for testing');
echo "Template created:\n";
echo "- Name: {$template['name']}\n";
echo "- Description: {$template['description']}\n";
echo "- Content keys: " . implode(', ', array_keys($template['content'])) . "\n\n";

// Test 4: Translation Management  
echo "🌐 Test 4: Translation Features\n";
echo "--------------------------------\n";

$translationData = [
    'name' => 'Test Story (Spanish)',
    'slug' => 'test-story-es',
    'content' => [
        'body' => [
            [
                '_uid' => $story->content['body'][0]['_uid'] ?? \Illuminate\Support\Str::uuid(),
                'component' => 'test_component',
                'title' => 'Hola Mundo',
                'content' => 'Este es contenido de prueba',
                'featured' => true
            ]
        ]
    ]
];

try {
    $translation = $story->createTranslation('es', $translationData, $user);
    echo "✅ Translation created: {$translation->name} ({$translation->uuid})\n";
    
    $translations = $story->getAllTranslations();
    echo "📊 Total translations: " . $translations->count() . "\n";
    
    $status = $story->getTranslationStatus();
    echo "📈 Translation status:\n";
    foreach ($status as $lang => $info) {
        echo "  - {$lang}: {$info['status']} ({$info['completion_percentage']}% complete)\n";
    }
} catch (\Exception $e) {
    echo "❌ Translation error: " . $e->getMessage() . "\n";
}

echo "\n✅ All basic functionality tests completed!\n";
echo "\n📝 Next Steps for Full Testing:\n";
echo "1. Start Laravel server: php artisan serve\n";
echo "2. Test API endpoints with Postman/curl\n";
echo "3. Run: php artisan test (when test suite is implemented)\n";