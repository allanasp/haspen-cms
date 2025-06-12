<?php

echo "🧪 Story Management System - Comprehensive Test Suite\n";
echo "====================================================\n\n";

// Test configuration
$testSuites = [
    'Unit Tests' => [
        'ComponentValidationTest' => 'tests/Unit/Models/ComponentValidationTest.php',
        'StoryLockingTest' => 'tests/Unit/Models/StoryLockingTest.php', 
        'ContentTemplatesTest' => 'tests/Unit/Models/ContentTemplatesTest.php',
        'TranslationWorkflowTest' => 'tests/Unit/Models/TranslationWorkflowTest.php',
        'StoryServiceTest' => 'tests/Unit/Services/StoryServiceTest.php',
        'VersionManagerTest' => 'tests/Unit/Services/VersionManagerTest.php'
    ],
    'Feature Tests' => [
        'StoryManagementTest' => 'tests/Feature/Api/Management/StoryManagementTest.php',
        'ContentDeliveryTest' => 'tests/Feature/Api/Cdn/ContentDeliveryTest.php'
    ]
];

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

echo "📋 Test Suite Overview:\n";
foreach ($testSuites as $suiteName => $tests) {
    echo "  {$suiteName}:\n";
    foreach ($tests as $testName => $testFile) {
        $totalTests++;
        echo "    - {$testName}\n";
    }
}
echo "\n";

// Function to run individual test
function runTest(string $testFile, string $testName): array {
    $command = "./vendor/bin/phpunit {$testFile} --testdox";
    
    echo "🔄 Running {$testName}...\n";
    
    $output = [];
    $returnCode = 0;
    
    exec($command . ' 2>&1', $output, $returnCode);
    
    $success = $returnCode === 0;
    $outputText = implode("\n", $output);
    
    if ($success) {
        echo "✅ {$testName} - PASSED\n";
    } else {
        echo "❌ {$testName} - FAILED\n";
        echo "   Error output:\n";
        foreach ($output as $line) {
            echo "   {$line}\n";
        }
    }
    
    echo "\n";
    
    return [
        'success' => $success,
        'output' => $outputText,
        'test_count' => substr_count($outputText, '✓') ?: 1
    ];
}

// Function to check if test files exist
function checkTestFiles(array $testSuites): array {
    $missing = [];
    
    foreach ($testSuites as $suiteName => $tests) {
        foreach ($tests as $testName => $testFile) {
            if (!file_exists($testFile)) {
                $missing[] = "{$testName} ({$testFile})";
            }
        }
    }
    
    return $missing;
}

// Check if all test files exist
echo "🔍 Checking test files...\n";
$missingFiles = checkTestFiles($testSuites);

if (!empty($missingFiles)) {
    echo "❌ Missing test files:\n";
    foreach ($missingFiles as $missing) {
        echo "   - {$missing}\n";
    }
    echo "\nPlease ensure all test files are created before running the test suite.\n";
    exit(1);
}

echo "✅ All test files found!\n\n";

// Check if vendor/bin/phpunit exists
if (!file_exists('./vendor/bin/phpunit')) {
    echo "❌ PHPUnit not found. Please run 'composer install' first.\n";
    exit(1);
}

echo "🚀 Starting test execution...\n\n";

$results = [];

// Run all test suites
foreach ($testSuites as $suiteName => $tests) {
    echo "📦 {$suiteName}\n";
    echo str_repeat("=", strlen($suiteName) + 4) . "\n\n";
    
    foreach ($tests as $testName => $testFile) {
        $result = runTest($testFile, $testName);
        $results[$testName] = $result;
        
        if ($result['success']) {
            $passedTests += $result['test_count'];
        } else {
            $failedTests += $result['test_count'];
        }
    }
    
    echo "\n";
}

// Generate summary report
echo "📊 Test Execution Summary\n";
echo "========================\n\n";

echo "Test Suite Results:\n";
foreach ($results as $testName => $result) {
    $status = $result['success'] ? '✅ PASSED' : '❌ FAILED';
    echo "  {$testName}: {$status}\n";
}

echo "\n";

echo "Overall Statistics:\n";
echo "  Total Test Suites: " . count($results) . "\n";
echo "  Passed Test Suites: " . count(array_filter($results, fn($r) => $r['success'])) . "\n";
echo "  Failed Test Suites: " . count(array_filter($results, fn($r) => !$r['success'])) . "\n";

$successRate = count($results) > 0 ? round((count(array_filter($results, fn($r) => $r['success'])) / count($results)) * 100, 1) : 0;
echo "  Success Rate: {$successRate}%\n\n";

// Feature coverage report
echo "🎯 Feature Coverage Report\n";
echo "==========================\n\n";

$features = [
    'Component Validation' => ['ComponentValidationTest'],
    'Content Locking' => ['StoryLockingTest', 'StoryManagementTest'],
    'Content Templates' => ['ContentTemplatesTest', 'StoryManagementTest'],
    'Translation Workflow' => ['TranslationWorkflowTest', 'StoryManagementTest'],
    'Advanced Search' => ['StoryServiceTest', 'StoryManagementTest'],
    'Version Management' => ['VersionManagerTest'],
    'CDN API' => ['ContentDeliveryTest'],
    'Management API' => ['StoryManagementTest']
];

foreach ($features as $feature => $relatedTests) {
    $featureTests = array_intersect_key($results, array_flip($relatedTests));
    $featurePassed = !empty($featureTests) && !in_array(false, array_column($featureTests, 'success'));
    $status = $featurePassed ? '✅' : '❌';
    
    echo "  {$status} {$feature}\n";
    foreach ($relatedTests as $testName) {
        if (isset($results[$testName])) {
            $testStatus = $results[$testName]['success'] ? '✅' : '❌';
            echo "     {$testStatus} {$testName}\n";
        }
    }
    echo "\n";
}

// Quick start guide
echo "🚀 Quick Commands\n";
echo "================\n\n";

echo "Run individual test suites:\n";
foreach ($testSuites as $suiteName => $tests) {
    foreach ($tests as $testName => $testFile) {
        echo "  ./vendor/bin/phpunit {$testFile}\n";
    }
}

echo "\nRun all Story Management tests:\n";
echo "  ./vendor/bin/phpunit --group=story-management\n";

echo "\nRun with coverage:\n";
echo "  ./vendor/bin/phpunit --group=story-management --coverage-html coverage\n";

echo "\nRun tests in parallel:\n";
echo "  ./vendor/bin/paratest --group=story-management\n\n";

// Final result
if ($failedTests === 0) {
    echo "🎉 All tests passed! The Story Management System is working correctly.\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Please review the errors above and fix the issues.\n";
    exit(1);
}