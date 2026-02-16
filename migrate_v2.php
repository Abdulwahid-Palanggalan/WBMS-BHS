<?php
// migrate_v2.php
require_once 'config/database.php';

echo "Starting Migration Phase 2...\n";

try {
    $sql = file_get_contents('db_expansion.sql');
    
    // Split SQL by semicolon, but handle cases where semicolon is inside values if needed
    // For simplicity with this specific file, we can use multi-query or loop
    
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
            echo "Executed: " . substr($query, 0, 50) . "...\n";
        }
    }
    
    echo "\nMigration successful!\n";
} catch (Exception $e) {
    echo "\nMigration failed: " . $e->getMessage() . "\n";
}
