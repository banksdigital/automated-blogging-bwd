<?php
/**
 * Debug file - test assigning a product to an Edit
 * Upload to public/debug-assign.php
 * Visit: /debug-assign.php?product_id=XXX&edit_term_id=YYY
 * DELETE THIS FILE after debugging!
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';

$productId = $_GET['product_id'] ?? null;
$editTermId = $_GET['edit_term_id'] ?? null;

if (!$productId || !$editTermId) {
    echo json_encode([
        'success' => false,
        'error' => 'Provide product_id and edit_term_id as query params',
        'example' => '/debug-assign.php?product_id=12345&edit_term_id=67',
        'hint' => 'Get edit_term_id from edit_suggestions.wp_term_id column'
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    $results = ['steps' => []];
    
    // Config check
    $results['config'] = [
        'api_url' => $config['wordpress']['api_url'] ?? 'NOT SET',
        'username_set' => !empty($config['wordpress']['username']),
        'password_set' => !empty($config['wordpress']['password']),
    ];
    
    $baseUrl = rtrim($config['wordpress']['api_url'], '/');
    $auth = base64_encode($config['wordpress']['username'] . ':' . $config['wordpress']['password']);
    
    // Step 1: Test WP REST API access
    $results['steps'][] = 'Step 1: Testing WP REST API access...';
    
    $ch = curl_init($baseUrl . '/wp/v2/users/me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . $auth
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results['step1_wp_api'] = [
        'http_code' => $httpCode,
        'success' => $httpCode === 200
    ];
    
    if ($httpCode !== 200) {
        $results['step1_error'] = json_decode($response, true);
    }
    
    // Step 2: Check if product endpoint exists
    $results['steps'][] = 'Step 2: Checking product endpoint...';
    
    $ch = curl_init($baseUrl . "/wp/v2/product/{$productId}?_fields=id,title,edit");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . $auth
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results['step2_product'] = [
        'http_code' => $httpCode,
        'success' => $httpCode === 200
    ];
    
    if ($httpCode === 200) {
        $productData = json_decode($response, true);
        $results['step2_product']['data'] = $productData;
        $results['step2_product']['has_edit_field'] = array_key_exists('edit', $productData);
        $results['step2_product']['current_edits'] = $productData['edit'] ?? 'NOT IN RESPONSE';
    } else {
        $results['step2_error'] = json_decode($response, true);
    }
    
    // Step 3: Check if edit taxonomy exists
    $results['steps'][] = 'Step 3: Checking edit taxonomy term...';
    
    $ch = curl_init($baseUrl . "/wp/v2/edit/{$editTermId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . $auth
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results['step3_edit_term'] = [
        'http_code' => $httpCode,
        'success' => $httpCode === 200
    ];
    
    if ($httpCode === 200) {
        $results['step3_edit_term']['data'] = json_decode($response, true);
    } else {
        $results['step3_error'] = json_decode($response, true);
    }
    
    // Step 4: Try to assign the edit
    $results['steps'][] = 'Step 4: Attempting to assign edit to product...';
    
    $currentEdits = $productData['edit'] ?? [];
    if (!in_array((int)$editTermId, $currentEdits)) {
        $currentEdits[] = (int)$editTermId;
    }
    
    $postData = json_encode(['edit' => $currentEdits]);
    
    $ch = curl_init($baseUrl . "/wp/v2/product/{$productId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . $auth
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results['step4_assign'] = [
        'http_code' => $httpCode,
        'success' => $httpCode === 200,
        'sent_data' => $postData
    ];
    
    if ($httpCode === 200) {
        $assignResult = json_decode($response, true);
        $results['step4_assign']['new_edits'] = $assignResult['edit'] ?? 'NOT IN RESPONSE';
    } else {
        $results['step4_error'] = json_decode($response, true);
    }
    
    // Summary
    $results['summary'] = [
        'wp_api_works' => $results['step1_wp_api']['success'] ?? false,
        'product_accessible' => $results['step2_product']['success'] ?? false,
        'edit_field_exists' => $results['step2_product']['has_edit_field'] ?? false,
        'edit_term_exists' => $results['step3_edit_term']['success'] ?? false,
        'assignment_worked' => $results['step4_assign']['success'] ?? false,
    ];
    
    if (!$results['summary']['edit_field_exists']) {
        $results['diagnosis'] = 'The "edit" taxonomy is not exposed in the WP REST API. You need to register it with show_in_rest => true in WordPress.';
    } elseif (!$results['summary']['assignment_worked']) {
        $results['diagnosis'] = 'API access works but assignment failed. Check the step4_error for details.';
    } else {
        $results['diagnosis'] = 'Everything looks good! Assignment should have worked.';
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
