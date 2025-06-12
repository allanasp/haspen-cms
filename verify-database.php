<?php

echo "🗄️  Database Structure Verification\n";
echo "===================================\n\n";

try {
    $pdo = new PDO('sqlite:database/database.sqlite');
    
    // Check if our new tables exist
    $tables = [
        'stories' => 'Stories table with locking fields',
        'story_versions' => 'Story versions for version management',
        'components' => 'Components for schema validation',
        'spaces' => 'Spaces for multi-tenancy'
    ];
    
    foreach ($tables as $table => $description) {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if ($stmt->fetch()) {
            echo "✅ $table - $description\n";
            
            // Check locking fields for stories table
            if ($table === 'stories') {
                $stmt = $pdo->query("PRAGMA table_info(stories)");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $lockingFields = ['locked_by', 'locked_at', 'lock_expires_at', 'lock_session_id'];
                
                $foundLockingFields = [];
                foreach ($columns as $column) {
                    if (in_array($column['name'], $lockingFields)) {
                        $foundLockingFields[] = $column['name'];
                    }
                }
                
                if (count($foundLockingFields) === count($lockingFields)) {
                    echo "   ✅ Content locking fields present\n";
                } else {
                    echo "   ❌ Missing locking fields: " . implode(', ', array_diff($lockingFields, $foundLockingFields)) . "\n";
                }
            }
        } else {
            echo "❌ $table - Missing!\n";
        }
    }
    
    // Check for test data
    echo "\n📊 Test Data Check:\n";
    $dataChecks = [
        'SELECT COUNT(*) FROM spaces' => 'Spaces',
        'SELECT COUNT(*) FROM users' => 'Users', 
        'SELECT COUNT(*) FROM components' => 'Components',
        'SELECT COUNT(*) FROM stories' => 'Stories'
    ];
    
    foreach ($dataChecks as $query => $type) {
        $stmt = $pdo->query($query);
        $count = $stmt->fetchColumn();
        echo "📈 $type: $count records\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n✅ Database verification complete!\n";