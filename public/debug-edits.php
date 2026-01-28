<?php
/**
 * Debug file - upload to public/debug-edits.php and visit /debug-edits.php
 * DELETE THIS FILE after debugging!
 */

header('Content-Type: application/json');

// Load the app
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';

use App\Helpers\Database;

try {
    Database::init($config['database']);
    
    $results = [
        'step1_connection' => 'OK',
    ];
    
    // Step 2: Check if table exists
    $tables = Database::query("SHOW TABLES LIKE 'edit_suggestions'");
    $results['step2_table_exists'] = count($tables) > 0 ? 'YES' : 'NO';
    
    if (count($tables) === 0) {
        echo json_encode(['success' => false, 'results' => $results, 'error' => 'Table does not exist']);
        exit;
    }
    
    // Step 3: Check table structure
    $columns = Database::query("DESCRIBE edit_suggestions");
    $results['step3_columns'] = array_column($columns, 'Field');
    
    // Step 4: Count rows
    $count = Database::query("SELECT COUNT(*) as cnt FROM edit_suggestions");
    $results['step4_row_count'] = (int)($count[0]['cnt'] ?? 0);
    
    // Step 5: Try a test insert
    $testSlug = 'debug-test-' . time();
    try {
        Database::insert(
            "INSERT INTO edit_suggestions (name, slug, description, source_type, matching_rules, status) VALUES (?, ?, ?, ?, ?, 'suggested')",
            ['Debug Test', $testSlug, 'Test description', 'occasion', json_encode(['categories' => ['test']])]
        );
        $results['step5_insert'] = 'OK';
        
        // Clean up test row
        Database::query("DELETE FROM edit_suggestions WHERE slug = ?", [$testSlug]);
        $results['step5_cleanup'] = 'OK';
    } catch (\Throwable $e) {
        $results['step5_insert_error'] = $e->getMessage();
    }
    
    // Step 6: Get sample data if exists
    if ($results['step4_row_count'] > 0) {
        $row = Database::query("SELECT * FROM edit_suggestions LIMIT 1");
        $results['step6_sample_row'] = $row[0] ?? null;
    }
    
    echo json_encode([
        'success' => true,
        'debug_results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
