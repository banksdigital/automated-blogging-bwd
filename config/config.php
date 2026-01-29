<?php

/**
 * Application Configuration
 * 
 * Loads environment variables and provides configuration values
 */

// Load environment variables
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Helper function to get env values
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        // Handle boolean strings
        if (strtolower($value) === 'true') return true;
        if (strtolower($value) === 'false') return false;
        if (strtolower($value) === 'null') return null;
        return $value;
    }
}

return [
    // Application
    'app' => [
        'env' => env('APP_ENV', 'production'),
        'url' => env('APP_URL', 'https://automated-blogging-bwd-5x8fk.kinsta.app'),
        'secret' => env('APP_SECRET'),
        'debug' => env('APP_ENV') === 'development',
    ],
    
// Database
'database' => [
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT', 3306),
    'name' => env('DB_DATABASE', env('DB_NAME')),
    'user' => env('DB_USERNAME', env('DB_USER')),
    'password' => env('DB_PASSWORD'),
    'charset' => 'utf8mb4',
],
    
    // WordPress API
    'wordpress' => [
        'api_url' => env('WP_API_URL', 'https://blackwhitedenim.com/wp-json'),
        'username' => env('WP_API_USER'),
        'password' => env('WP_API_PASSWORD'),
    ],
    
    // Claude API
    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => 'claude-sonnet-4-5-20250929',
        'max_tokens' => 4096,
    ],
    
    // Email
    'mail' => [
        'host' => env('SMTP_HOST'),
        'port' => env('SMTP_PORT', 587),
        'username' => env('SMTP_USER'),
        'password' => env('SMTP_PASSWORD'),
        'from_email' => env('SMTP_FROM_EMAIL', 'noreply@blackwhitedenim.com'),
        'from_name' => env('SMTP_FROM_NAME', 'BWD Blog Platform'),
        'notification_email' => env('NOTIFICATION_EMAIL'),
    ],
    
    // Content Settings
    'content' => [
        'posts_per_month_target' => 6,
        'default_publish_time' => '09:00:00',
        'schedule_buffer_days' => 3,
        'divider_image_id' => 57585,
    ],
    
    // Session
    'session' => [
        'name' => 'bwd_session',
        'lifetime' => 86400, // 24 hours
        'secure' => env('APP_ENV') === 'production',
    ],
];
