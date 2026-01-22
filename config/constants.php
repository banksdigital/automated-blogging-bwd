<?php

/**
 * Application Constants
 */

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('SRC_PATH', ROOT_PATH . '/src');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('TEMPLATE_PATH', ROOT_PATH . '/templates');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('LOG_PATH', STORAGE_PATH . '/logs');
define('CACHE_PATH', STORAGE_PATH . '/cache');

// Post Statuses
define('STATUS_IDEA', 'idea');
define('STATUS_DRAFT', 'draft');
define('STATUS_REVIEW', 'review');
define('STATUS_SCHEDULED', 'scheduled');
define('STATUS_PUBLISHED', 'published');
define('STATUS_ARCHIVED', 'archived');

// User Roles
define('ROLE_SUPER_ADMIN', 'super_admin');

// API Rate Limits
define('RATE_LIMIT_LOGIN', 5); // attempts
define('RATE_LIMIT_LOGIN_WINDOW', 900); // 15 minutes
define('RATE_LIMIT_API', 100); // requests
define('RATE_LIMIT_API_WINDOW', 60); // 1 minute

// Pagination
define('DEFAULT_PER_PAGE', 20);
define('MAX_PER_PAGE', 100);

// Cache TTL (seconds)
define('CACHE_TTL_PRODUCTS', 3600); // 1 hour
define('CACHE_TTL_CATEGORIES', 86400); // 24 hours

// WordPress/WooCommerce
define('WC_BRAND_TAXONOMY', 'brand');
define('WC_CATEGORY_TAXONOMY', 'product_cat');
