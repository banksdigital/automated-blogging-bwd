<?php

/**
 * Application Entry Point
 * 
 * Handles all incoming requests and routes them appropriately
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load configuration
require_once dirname(__DIR__) . '/config/constants.php';
$config = require dirname(__DIR__) . '/config/config.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = SRC_PATH . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize database
\App\Helpers\Database::init($config['database']);

// Start session
session_name($config['session']['name']);
session_start();

// CSRF Token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get request info
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove trailing slash
$path = rtrim($path, '/') ?: '/';

// API Routes
if (strpos($path, '/api/') === 0) {
    header('Content-Type: application/json');
    
    // CORS headers
    header('Access-Control-Allow-Origin: ' . $config['app']['url']);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');
    
    if ($requestMethod === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    // Route API requests
    $apiPath = substr($path, 4); // Remove '/api'
    
    try {
        routeApi($apiPath, $requestMethod, $config);
    } catch (\Exception $e) {
        error_log("API Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'SERVER_ERROR',
                'message' => $config['app']['debug'] ? $e->getMessage() : 'An error occurred'
            ]
        ]);
    }
    exit;
}

// Web Routes
try {
    routeWeb($path, $requestMethod, $config);
} catch (\Exception $e) {
    error_log("Web Error: " . $e->getMessage());
    http_response_code(500);
    echo "An error occurred. Please try again.";
}

/**
 * Route API requests
 */
function routeApi(string $path, string $method, array $config): void
{
    // Public routes (no auth required)
    $publicRoutes = ['/auth/login', '/auth/logout', '/setup', '/setup/create'];
    
    // Check authentication for protected routes
    if (!in_array($path, $publicRoutes)) {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentication required']
            ]);
            return;
        }
    }
    
    // Verify CSRF for non-GET requests
    if ($method !== 'GET' && !in_array($path, $publicRoutes)) {
        $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrfHeader !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'CSRF_MISMATCH', 'message' => 'Invalid CSRF token']
            ]);
            return;
        }
    }
    
    // Get request body for POST/PUT
    $input = [];
    if (in_array($method, ['POST', 'PUT'])) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }
    
    // Route matching
    switch (true) {
        // Auth
        case $path === '/auth/login' && $method === 'POST':
            (new \App\Controllers\AuthController($config))->login($input);
            break;
        case $path === '/auth/logout' && $method === 'POST':
            (new \App\Controllers\AuthController($config))->logout();
            break;
        case $path === '/auth/session' && $method === 'GET':
            (new \App\Controllers\AuthController($config))->session();
            break;
            
        // Setup
        case $path === '/setup' && $method === 'GET':
            (new \App\Controllers\SetupController($config))->check();
            break;
        case $path === '/setup/create' && $method === 'POST':
            (new \App\Controllers\SetupController($config))->createAdmin($input);
            break;
            
        // Posts
        case $path === '/posts' && $method === 'GET':
            (new \App\Controllers\PostController($config))->index($_GET);
            break;
        case preg_match('#^/posts/(\d+)$#', $path, $m) && $method === 'GET':
            (new \App\Controllers\PostController($config))->show((int)$m[1]);
            break;
        case $path === '/posts' && $method === 'POST':
            (new \App\Controllers\PostController($config))->store($input);
            break;
        case preg_match('#^/posts/(\d+)$#', $path, $m) && $method === 'PUT':
            (new \App\Controllers\PostController($config))->update((int)$m[1], $input);
            break;
        case preg_match('#^/posts/(\d+)$#', $path, $m) && $method === 'DELETE':
            (new \App\Controllers\PostController($config))->delete((int)$m[1]);
            break;
        case preg_match('#^/posts/(\d+)/publish$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\PostController($config))->publish((int)$m[1]);
            break;
        case preg_match('#^/posts/(\d+)/preview$#', $path, $m) && $method === 'GET':
            (new \App\Controllers\PostController($config))->preview((int)$m[1]);
            break;
            
        // Sections
        case preg_match('#^/posts/(\d+)/sections$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\SectionController($config))->store((int)$m[1], $input);
            break;
        case preg_match('#^/posts/(\d+)/sections/(\d+)$#', $path, $m) && $method === 'PUT':
            (new \App\Controllers\SectionController($config))->update((int)$m[1], (int)$m[2], $input);
            break;
        case preg_match('#^/posts/(\d+)/sections/(\d+)$#', $path, $m) && $method === 'DELETE':
            (new \App\Controllers\SectionController($config))->delete((int)$m[1], (int)$m[2]);
            break;
            
        // WordPress Sync
        case $path === '/wordpress/sync/categories' && $method === 'POST':
            (new \App\Controllers\WordPressController($config))->syncCategories();
            break;
        case $path === '/wordpress/sync/authors' && $method === 'POST':
            (new \App\Controllers\WordPressController($config))->syncAuthors();
            break;
        case $path === '/wordpress/sync/brands' && $method === 'POST':
            (new \App\Controllers\WordPressController($config))->syncBrands();
            break;
        case $path === '/wordpress/sync/product-categories' && $method === 'POST':
            (new \App\Controllers\WordPressController($config))->syncProductCategories();
            break;
        case $path === '/wordpress/sync/products' && $method === 'POST':
            (new \App\Controllers\WordPressController($config))->syncProducts();
            break;
        case $path === '/wordpress/sync/blocks' && $method === 'POST':
            (new \App\Controllers\WordPressController($config))->syncBlocks();
            break;
        case $path === '/wordpress/sync/status' && $method === 'GET':
            (new \App\Controllers\WordPressController($config))->syncStatus();
            break;
        case $path === '/wordpress/test' && $method === 'POST':
            (new \App\Controllers\WordPressController($config))->testConnection($input);
            break;
        case preg_match('#^/wordpress/publish/(\d+)$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\WordPressController($config))->publishToWordPress((int)$m[1], $input);
            break;
            
        // Products
        case $path === '/products' && $method === 'GET':
            (new \App\Controllers\ProductController($config))->index($_GET);
            break;
        case $path === '/products/search' && $method === 'GET':
            (new \App\Controllers\ProductController($config))->search($_GET);
            break;
        case $path === '/products/brands' && $method === 'GET':
            (new \App\Controllers\ProductController($config))->brands();
            break;
        case $path === '/products/categories' && $method === 'GET':
            (new \App\Controllers\ProductController($config))->productCategories();
            break;
            
        // Categories, Authors, Blocks
        case $path === '/categories' && $method === 'GET':
            (new \App\Controllers\TaxonomyController($config))->categories();
            break;
        case $path === '/authors' && $method === 'GET':
            (new \App\Controllers\TaxonomyController($config))->authors();
            break;
        case $path === '/blocks' && $method === 'GET':
            (new \App\Controllers\TaxonomyController($config))->blocks();
            break;
            
        // Seasonal Events
        case $path === '/events' && $method === 'GET':
            (new \App\Controllers\EventController($config))->index();
            break;
        case $path === '/events' && $method === 'POST':
            (new \App\Controllers\EventController($config))->store($input);
            break;
        case preg_match('#^/events/(\d+)$#', $path, $m) && $method === 'PUT':
            (new \App\Controllers\EventController($config))->update((int)$m[1], $input);
            break;
        case preg_match('#^/events/(\d+)$#', $path, $m) && $method === 'DELETE':
            (new \App\Controllers\EventController($config))->delete((int)$m[1]);
            break;
            
        // Taxonomy SEO
        case $path === '/taxonomy-seo/brands' && $method === 'GET':
            (new \App\Controllers\TaxonomySeoController($config))->brands();
            break;
        case $path === '/taxonomy-seo/categories' && $method === 'GET':
            (new \App\Controllers\TaxonomySeoController($config))->categories();
            break;
        case preg_match('#^/taxonomy-seo/brands/(\d+)$#', $path, $m) && $method === 'GET':
            (new \App\Controllers\TaxonomySeoController($config))->brandDetails((int)$m[1]);
            break;
        case preg_match('#^/taxonomy-seo/categories/(\d+)$#', $path, $m) && $method === 'GET':
            (new \App\Controllers\TaxonomySeoController($config))->categoryDetails((int)$m[1]);
            break;
        case preg_match('#^/taxonomy-seo/brands/(\d+)/generate$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\TaxonomySeoController($config))->generateBrandSeo((int)$m[1]);
            break;
        case preg_match('#^/taxonomy-seo/categories/(\d+)/generate$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\TaxonomySeoController($config))->generateCategorySeo((int)$m[1]);
            break;
        case preg_match('#^/taxonomy-seo/brands/(\d+)$#', $path, $m) && $method === 'PUT':
            (new \App\Controllers\TaxonomySeoController($config))->saveBrandSeo((int)$m[1], $input);
            break;
        case preg_match('#^/taxonomy-seo/categories/(\d+)$#', $path, $m) && $method === 'PUT':
            (new \App\Controllers\TaxonomySeoController($config))->saveCategorySeo((int)$m[1], $input);
            break;
        case preg_match('#^/taxonomy-seo/brands/(\d+)/push$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\TaxonomySeoController($config))->pushBrandToWordPress((int)$m[1]);
            break;
        case preg_match('#^/taxonomy-seo/categories/(\d+)/push$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\TaxonomySeoController($config))->pushCategoryToWordPress((int)$m[1]);
            break;
        case preg_match('#^/taxonomy-seo/brands/(\d+)/pull$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\TaxonomySeoController($config))->pullBrandFromWordPress((int)$m[1]);
            break;
        case preg_match('#^/taxonomy-seo/categories/(\d+)/pull$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\TaxonomySeoController($config))->pullCategoryFromWordPress((int)$m[1]);
            break;
            
        // Roadmap
        case $path === '/roadmap' && $method === 'GET':
            (new \App\Controllers\RoadmapController($config))->index($_GET);
            break;
        case $path === '/roadmap/upcoming' && $method === 'GET':
            (new \App\Controllers\RoadmapController($config))->upcoming();
            break;
        case preg_match('#^/roadmap/(\d{4})/(\d{1,2})$#', $path, $m) && $method === 'GET':
            (new \App\Controllers\RoadmapController($config))->month((int)$m[1], (int)$m[2]);
            break;
            
        // Brainstorm
        case $path === '/brainstorm' && $method === 'GET':
            (new \App\Controllers\BrainstormController($config))->index($_GET);
            break;
        case $path === '/brainstorm' && $method === 'POST':
            (new \App\Controllers\BrainstormController($config))->store($input);
            break;
        case preg_match('#^/brainstorm/(\d+)$#', $path, $m) && $method === 'PUT':
            (new \App\Controllers\BrainstormController($config))->update((int)$m[1], $input);
            break;
        case preg_match('#^/brainstorm/(\d+)$#', $path, $m) && $method === 'DELETE':
            (new \App\Controllers\BrainstormController($config))->delete((int)$m[1]);
            break;
        case preg_match('#^/brainstorm/(\d+)/convert$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\BrainstormController($config))->convert((int)$m[1]);
            break;
            
        // Claude AI
        case $path === '/claude/brainstorm' && $method === 'POST':
            (new \App\Controllers\ClaudeController($config))->brainstorm($input);
            break;
        case $path === '/claude/generate-section' && $method === 'POST':
            (new \App\Controllers\ClaudeController($config))->generateSection($input);
            break;
        case $path === '/claude/generate-post' && $method === 'POST':
            (new \App\Controllers\ClaudeController($config))->generatePost($input);
            break;
        case $path === '/claude/improve' && $method === 'POST':
            (new \App\Controllers\ClaudeController($config))->improve($input);
            break;
        case $path === '/claude/suggest-products' && $method === 'POST':
            (new \App\Controllers\ClaudeController($config))->suggestProducts($input);
            break;
        case $path === '/claude/meta-description' && $method === 'POST':
            (new \App\Controllers\ClaudeController($config))->metaDescription($input);
            break;
        case $path === '/claude/chat' && $method === 'POST':
            (new \App\Controllers\ClaudeController($config))->chat($input);
            break;
        case $path === '/claude/post-assistant' && $method === 'POST':
            (new \App\Controllers\ClaudeController($config))->postAssistant($input);
            break;
            
        // Settings
        case $path === '/settings' && $method === 'GET':
            (new \App\Controllers\SettingsController($config))->index();
            break;
        case $path === '/settings' && $method === 'PUT':
            (new \App\Controllers\SettingsController($config))->update($input);
            break;
        case $path === '/settings/brand-voice' && $method === 'GET':
            (new \App\Controllers\SettingsController($config))->brandVoice();
            break;
        case $path === '/settings/brand-voice' && $method === 'PUT':
            (new \App\Controllers\SettingsController($config))->updateBrandVoice($input);
            break;
        case $path === '/settings/defaults' && $method === 'GET':
            (new \App\Controllers\SettingsController($config))->getDefaults();
            break;
        case $path === '/settings/defaults' && $method === 'POST':
            (new \App\Controllers\SettingsController($config))->saveDefaults($input);
            break;
            
        // Maintenance
        case $path === '/maintenance/stats' && $method === 'GET':
            (new \App\Controllers\MaintenanceController())->stats();
            break;
        case $path === '/maintenance/clear-posts' && $method === 'POST':
            (new \App\Controllers\MaintenanceController())->clearPosts();
            break;
        case $path === '/maintenance/reset-scheduled' && $method === 'POST':
            (new \App\Controllers\MaintenanceController())->resetScheduled();
            break;
        case $path === '/maintenance/reseed-events' && $method === 'POST':
            (new \App\Controllers\MaintenanceController())->reseedEvents();
            break;
            
        // Stats
        case $path === '/stats/dashboard' && $method === 'GET':
            (new \App\Controllers\StatsController($config))->dashboard();
            break;
        case $path === '/activity' && $method === 'GET':
            (new \App\Controllers\StatsController($config))->activity($_GET);
            break;
            
        // Content Engine (Auto-pilot)
        case $path === '/content/templates' && $method === 'GET':
            (new \App\Controllers\ContentEngine($config))->getTemplates();
            break;
        case $path === '/content/templates' && $method === 'POST':
            (new \App\Controllers\ContentEngine($config))->createTemplate($input);
            break;
        case $path === '/content/scheduled' && $method === 'GET':
            (new \App\Controllers\ContentEngine($config))->getScheduledContent();
            break;
        case $path === '/content/calendar/generate' && $method === 'POST':
            (new \App\Controllers\ContentEngine($config))->generateCalendar($input);
            break;
        case $path === '/content/generate-pending' && $method === 'POST':
            (new \App\Controllers\ContentEngine($config))->generatePendingContent();
            break;
        case $path === '/content/review-queue' && $method === 'GET':
            (new \App\Controllers\ContentEngine($config))->getReviewQueue();
            break;
        case preg_match('#^/content/approve/(\d+)$#', $path, $m) && $method === 'POST':
            (new \App\Controllers\ContentEngine($config))->approveContent((int)$m[1]);
            break;
        case $path === '/content/stats' && $method === 'GET':
            (new \App\Controllers\ContentEngine($config))->getStats();
            break;
        case $path === '/content/seed-events' && $method === 'POST':
            (new \App\Controllers\ContentEngine($config))->seedSeasonalEvents();
            break;
        case $path === '/content/seed-templates' && $method === 'POST':
            (new \App\Controllers\ContentEngine($config))->seedContentTemplates();
            break;
            
        // Writing Guidelines
        case $path === '/settings/writing-guidelines' && $method === 'GET':
            (new \App\Controllers\WritingGuidelinesController($config))->index();
            break;
        case $path === '/settings/writing-guidelines' && $method === 'POST':
            (new \App\Controllers\WritingGuidelinesController($config))->store($input);
            break;
        case $path === '/settings/writing-guidelines/bulk' && $method === 'POST':
            (new \App\Controllers\WritingGuidelinesController($config))->bulkStore($input);
            break;
        case preg_match('#^/settings/writing-guidelines/(\d+)$#', $path, $m) && $method === 'DELETE':
            (new \App\Controllers\WritingGuidelinesController($config))->delete((int)$m[1]);
            break;
        case $path === '/settings/writing-guidelines/seed' && $method === 'POST':
            (new \App\Controllers\WritingGuidelinesController($config))->seedDefaults();
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Endpoint not found']
            ]);
    }
}

/**
 * Route web requests
 */
function routeWeb(string $path, string $method, array $config): void
{
    // Check if setup is required
    $setupComplete = false;
    try {
        $result = \App\Helpers\Database::queryOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'setup_complete'"
        );
        $setupComplete = $result && json_decode($result['setting_value']) === true;
    } catch (\Exception $e) {
        // Database might not be set up yet
    }
    
    // Redirect to setup if not complete
    if (!$setupComplete && $path !== '/setup') {
        header('Location: /setup');
        exit;
    }
    
    // Redirect from setup if already complete
    if ($setupComplete && $path === '/setup') {
        header('Location: /');
        exit;
    }
    
    // Check authentication for protected routes
    $publicRoutes = ['/login', '/setup'];
    if (!in_array($path, $publicRoutes) && !isset($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
    
    // If logged in and on login page, redirect to dashboard
    if ($path === '/login' && isset($_SESSION['user_id'])) {
        header('Location: /');
        exit;
    }
    
    // Render templates
    $templateData = [
        'config' => $config,
        'user' => $_SESSION['user'] ?? null,
        'csrfToken' => $_SESSION['csrf_token'],
    ];
    
    switch ($path) {
        case '/setup':
            renderTemplate('setup', $templateData);
            break;
        case '/login':
            renderTemplate('login', $templateData);
            break;
        case '/':
        case '/dashboard':
        case '/posts':
        case '/posts/new':
        case '/roadmap':
        case '/calendar-events':
        case '/taxonomy-seo':
        case '/brainstorm':
        case '/products':
        case '/autopilot':
        case '/settings':
        case '/settings/defaults':
        case '/settings/brand-voice':
        case '/settings/writing-guidelines':
        case '/settings/sync':
        case '/settings/maintenance':
            // All dashboard routes use the same SPA template
            renderTemplate('dashboard', $templateData);
            break;
        default:
            // Check for post edit route
            if (preg_match('#^/posts/(\d+)$#', $path)) {
                renderTemplate('dashboard', $templateData);
            } else {
                http_response_code(404);
                renderTemplate('404', $templateData);
            }
    }
}

/**
 * Render a template
 */
function renderTemplate(string $name, array $data = []): void
{
    extract($data);
    $templateFile = TEMPLATE_PATH . '/' . $name . '.php';
    
    if (file_exists($templateFile)) {
        require $templateFile;
    } else {
        echo "Template not found: {$name}";
    }
}
