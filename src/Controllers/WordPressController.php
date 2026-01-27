<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\WordPressService;

class WordPressController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Test WordPress API connection
     */
    public function testConnection(array $input): void
    {
        $testConfig = [
            'wordpress' => [
                'api_url' => $input['api_url'] ?? $this->config['wordpress']['api_url'],
                'username' => $input['username'] ?? $this->config['wordpress']['username'],
                'password' => $input['password'] ?? $this->config['wordpress']['password'],
            ]
        ];

        $service = new WordPressService($testConfig);
        $result = $service->testConnection();

        echo json_encode([
            'success' => $result['success'],
            'data' => $result
        ]);
    }

    /**
     * Sync categories from WordPress
     */
    public function syncCategories(): void
    {
        try {
            $service = new WordPressService($this->config);
            $categories = $service->getCategories();

            $synced = 0;
            foreach ($categories as $cat) {
                Database::execute(
                    "INSERT INTO wp_categories (wp_category_id, name, slug, parent_id, synced_at)
                     VALUES (?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE name = ?, slug = ?, parent_id = ?, synced_at = NOW()",
                    [
                        $cat['id'],
                        $cat['name'],
                        $cat['slug'],
                        $cat['parent'] ?: null,
                        $cat['name'],
                        $cat['slug'],
                        $cat['parent'] ?: null
                    ]
                );
                $synced++;
            }

            $this->logActivity('sync_categories', 'system', null, ['count' => $synced]);

            echo json_encode([
                'success' => true,
                'data' => ['synced' => $synced, 'message' => "Synced {$synced} categories"]
            ]);

        } catch (\Exception $e) {
            error_log("Category sync error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'SYNC_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Sync authors from WordPress
     */
    public function syncAuthors(): void
    {
        try {
            $service = new WordPressService($this->config);
            $authors = $service->getAuthors();

            $synced = 0;
            foreach ($authors as $author) {
                Database::execute(
                    "INSERT INTO wp_authors (wp_user_id, name, email, synced_at)
                     VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE name = ?, email = ?, synced_at = NOW()",
                    [
                        $author['id'],
                        $author['name'],
                        $author['email'] ?? null,
                        $author['name'],
                        $author['email'] ?? null
                    ]
                );
                $synced++;
            }

            $this->logActivity('sync_authors', 'system', null, ['count' => $synced]);

            echo json_encode([
                'success' => true,
                'data' => ['synced' => $synced, 'message' => "Synced {$synced} authors"]
            ]);

        } catch (\Exception $e) {
            error_log("Author sync error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'SYNC_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Sync brands from WordPress (product brand taxonomy)
     */
    public function syncBrands(): void
    {
        try {
            $service = new WordPressService($this->config);
            $brands = $service->getAllBrandsWithAcf();

            $synced = 0;
            $withSeo = 0;
            $hasAcfFields = false;
            $debugInfo = [];
            
            // Debug: log first brand's full structure
            if (!empty($brands[0])) {
                $debugInfo['first_brand_keys'] = array_keys($brands[0]);
                $debugInfo['has_acf_key'] = isset($brands[0]['acf']);
                if (isset($brands[0]['acf'])) {
                    $debugInfo['acf_content'] = $brands[0]['acf'];
                }
                error_log("First brand structure: " . json_encode(array_keys($brands[0])));
                if (isset($brands[0]['acf'])) {
                    error_log("First brand ACF fields: " . json_encode($brands[0]['acf']));
                } else {
                    error_log("First brand has NO 'acf' key. Full data: " . substr(json_encode($brands[0]), 0, 500));
                }
            }
            
            foreach ($brands as $brand) {
                // Check if any brand has ACF fields (indicates REST API is enabled)
                if (isset($brand['acf']) && !empty($brand['acf'])) {
                    $hasAcfFields = true;
                }
                
                // Extract ACF fields if present
                $acf = $brand['acf'] ?? [];
                $seoDescription = !empty($acf['taxonomy_description']) ? $acf['taxonomy_description'] : null;
                $seoMetaDescription = !empty($acf['taxonomy_seo_description']) ? $acf['taxonomy_seo_description'] : null;
                
                // FORCE truncate meta description to avoid column error
                if ($seoMetaDescription) {
                    $seoMetaDescription = mb_substr($seoMetaDescription, 0, 155);
                }
                
                // Track if this brand has SEO content
                if ($seoDescription || $seoMetaDescription) {
                    $withSeo++;
                }
                
                Database::execute(
                    "INSERT INTO wp_brands (wp_term_id, name, slug, description, count, seo_description, seo_meta_description, seo_updated_at, synced_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug), 
                     description = VALUES(description), count = VALUES(count),
                     seo_description = COALESCE(VALUES(seo_description), seo_description),
                     seo_meta_description = COALESCE(VALUES(seo_meta_description), seo_meta_description),
                     seo_updated_at = IF(VALUES(seo_description) IS NOT NULL OR VALUES(seo_meta_description) IS NOT NULL, NOW(), seo_updated_at),
                     synced_at = NOW()",
                    [
                        $brand['id'],
                        html_entity_decode($brand['name']),
                        $brand['slug'],
                        $brand['description'] ?? null,
                        $brand['count'] ?? 0,
                        $seoDescription,
                        $seoMetaDescription,
                        ($seoDescription || $seoMetaDescription) ? date('Y-m-d H:i:s') : null
                    ]
                );
                $synced++;
            }

            $this->logActivity('sync_brands', 'system', null, ['count' => $synced, 'with_seo' => $withSeo]);

            // Build message with warning if ACF fields not found
            $message = "Synced {$synced} brands ({$withSeo} with SEO content)";
            if (!$hasAcfFields && $synced > 0) {
                $message .= ". ⚠️ ACF fields not found in API response - check that 'Show in REST API' is enabled for the field group.";
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'synced' => $synced, 
                    'with_seo' => $withSeo,
                    'acf_enabled' => $hasAcfFields,
                    'message' => $message,
                    'debug' => $debugInfo
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Brand sync error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'SYNC_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Sync product categories from WooCommerce
     */
    public function syncProductCategories(): void
    {
        try {
            $service = new WordPressService($this->config);
            $categories = $service->getAllProductCategoriesWithAcf();

            $synced = 0;
            $withSeo = 0;
            $hasAcfFields = false;
            $debugInfo = [];
            
            // Debug: log first category's ACF data
            if (!empty($categories[0])) {
                $debugInfo['first_category_keys'] = array_keys($categories[0]);
                $debugInfo['has_acf_key'] = isset($categories[0]['acf']);
                if (isset($categories[0]['acf'])) {
                    $debugInfo['acf_content'] = $categories[0]['acf'];
                }
                error_log("First category keys: " . json_encode(array_keys($categories[0])));
                if (isset($categories[0]['acf'])) {
                    error_log("First category ACF: " . json_encode($categories[0]['acf']));
                } else {
                    error_log("First category has NO 'acf' key");
                }
            }
            
            foreach ($categories as $cat) {
                // Check if any category has ACF fields
                if (isset($cat['acf']) && !empty($cat['acf'])) {
                    $hasAcfFields = true;
                }
                
                // Extract ACF fields if present
                // NOTE: Categories use different field names than brands:
                // - category_description (not taxonomy_description)
                // - seo_description (not taxonomy_seo_description)
                $acf = $cat['acf'] ?? [];
                $seoDescription = !empty($acf['category_description']) ? $acf['category_description'] : null;
                $seoMetaDescription = !empty($acf['seo_description']) ? $acf['seo_description'] : null;
                
                // FORCE truncate meta description to avoid column error
                if ($seoMetaDescription) {
                    $seoMetaDescription = mb_substr($seoMetaDescription, 0, 155);
                }
                
                // Track if this category has SEO content
                if ($seoDescription || $seoMetaDescription) {
                    $withSeo++;
                }
                
                Database::execute(
                    "INSERT INTO wp_product_categories (wp_term_id, parent_id, name, slug, description, count, seo_description, seo_meta_description, seo_updated_at, synced_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE parent_id = VALUES(parent_id), name = VALUES(name), 
                     slug = VALUES(slug), description = VALUES(description), count = VALUES(count),
                     seo_description = COALESCE(VALUES(seo_description), seo_description),
                     seo_meta_description = COALESCE(VALUES(seo_meta_description), seo_meta_description),
                     seo_updated_at = IF(VALUES(seo_description) IS NOT NULL OR VALUES(seo_meta_description) IS NOT NULL, NOW(), seo_updated_at),
                     synced_at = NOW()",
                    [
                        $cat['id'],
                        $cat['parent'] ?? 0,
                        html_entity_decode($cat['name']),
                        $cat['slug'],
                        $cat['description'] ?? null,
                        $cat['count'] ?? 0,
                        $seoDescription,
                        $seoMetaDescription,
                        ($seoDescription || $seoMetaDescription) ? date('Y-m-d H:i:s') : null
                    ]
                );
                $synced++;
            }

            $this->logActivity('sync_product_categories', 'system', null, ['count' => $synced, 'with_seo' => $withSeo]);

            // Build message with warning if ACF fields not found
            $message = "Synced {$synced} product categories ({$withSeo} with SEO content)";
            if (!$hasAcfFields && $synced > 0) {
                $message .= ". ⚠️ ACF fields not found in API response.";
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'synced' => $synced,
                    'with_seo' => $withSeo,
                    'acf_enabled' => $hasAcfFields,
                    'message' => $message,
                    'debug' => $debugInfo
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Product category sync error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'SYNC_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

/**
 * Sync products from WooCommerce
 */
public function syncProducts(): void
{
    try {
        $service = new WordPressService($this->config);
        
        // Get all products from WooCommerce
        $products = $service->getAllProducts();
        error_log("Fetched " . count($products) . " products from WooCommerce");

        $synced = 0;
        
        // First pass: sync all products without brand info
        foreach ($products as $product) {
            // Extract categories
            $categorySlugs = [];
            $categoryNames = [];
            if (!empty($product['categories'])) {
                foreach ($product['categories'] as $cat) {
                    $categorySlugs[] = $cat['slug'];
                    $categoryNames[] = $cat['name'];
                }
            }

            // Extract tags
            $tags = [];
            if (!empty($product['tags'])) {
                foreach ($product['tags'] as $tag) {
                    $tags[] = $tag['slug'];
                }
            }

            // Get primary image
            $imageUrl = null;
            if (!empty($product['images']) && isset($product['images'][0]['src'])) {
                $imageUrl = $product['images'][0]['src'];
            }

            // Upsert product (without brand info initially)
            Database::execute(
                "INSERT INTO wp_products (wc_product_id, title, description, short_description, price, regular_price, sale_price, stock_status, category_slugs, category_names, tags, image_url, permalink, sku, synced_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                short_description = VALUES(short_description),
                price = VALUES(price),
                regular_price = VALUES(regular_price),
                sale_price = VALUES(sale_price),
                stock_status = VALUES(stock_status),
                category_slugs = VALUES(category_slugs),
                category_names = VALUES(category_names),
                tags = VALUES(tags),
                image_url = VALUES(image_url),
                permalink = VALUES(permalink),
                sku = VALUES(sku),
                synced_at = NOW()",
                [
                    $product['id'],
                    $product['name'],
                    $product['description'] ?? null,
                    $product['short_description'] ?? null,
                    $product['price'] ?: null,
                    $product['regular_price'] ?: null,
                    $product['sale_price'] ?: null,
                    $product['stock_status'] ?? 'instock',
                    json_encode($categorySlugs),
                    json_encode($categoryNames),
                    json_encode($tags),
                    $imageUrl,
                    $product['permalink'] ?? null,
                    $product['sku'] ?? null
                ]
            );
            $synced++;
            
            // Log progress every 100 products
            if ($synced % 100 === 0) {
                error_log("Synced {$synced} products...");
            }
        }
        
        error_log("Product sync phase 1 complete: {$synced} products synced");
        
        // Second pass: sync brand associations from brand taxonomy
        // Get all brands from local database
        $localBrands = Database::query("SELECT id, wp_term_id, name, slug FROM wp_brands");
        error_log("Syncing brand associations for " . count($localBrands) . " brands...");
        
        $brandIdSet = 0;
        foreach ($localBrands as $brand) {
            // Get product IDs for this brand from WordPress
            $wcProductIds = $service->getProductIdsByBrand($brand['wp_term_id']);
            
            if (!empty($wcProductIds)) {
                // Update products with this brand
                $placeholders = implode(',', array_fill(0, count($wcProductIds), '?'));
                $params = array_merge(
                    [$brand['id'], $brand['name'], $brand['slug']],
                    $wcProductIds
                );
                
                $updated = Database::execute(
                    "UPDATE wp_products 
                     SET brand_id = ?, brand_name = ?, brand_slug = ?
                     WHERE wc_product_id IN ({$placeholders})",
                    $params
                );
                
                $brandIdSet += count($wcProductIds);
                error_log("Brand '{$brand['name']}': assigned to " . count($wcProductIds) . " products");
            }
        }

        $this->logActivity('sync_products', 'system', null, ['count' => $synced, 'with_brands' => $brandIdSet]);
        
        $message = "Synced {$synced} products. Assigned brands to {$brandIdSet} products.";
        error_log("Product sync complete: {$synced} total, {$brandIdSet} with brand associations");

        echo json_encode([
            'success' => true,
            'data' => [
                'synced' => $synced, 
                'with_brand_id' => $brandIdSet,
                'message' => $message
            ]
        ]);

    } catch (\Exception $e) {
        error_log("Product sync error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => ['code' => 'SYNC_ERROR', 'message' => $e->getMessage()]
        ]);
    }
}

    /**
     * Sync reusable page blocks from Impreza theme
     */
    public function syncBlocks(): void
    {
        try {
            $service = new WordPressService($this->config);
            $blocks = $service->getPageBlocks();

            $synced = 0;
            foreach ($blocks as $block) {
                // Try to determine category from title (e.g., "Carousel - Sunglasses")
                $categorySlug = null;
                $categoryName = null;
                
                if (preg_match('/Carousel\s*-\s*(.+)/i', $block['title']['rendered'] ?? $block['title'], $matches)) {
                    $categoryName = trim($matches[1]);
                    $categorySlug = $this->slugify($categoryName);
                }

                Database::execute(
                    "INSERT INTO wp_page_blocks (wp_block_id, title, category_slug, category_name, synced_at)
                     VALUES (?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE title = ?, category_slug = ?, category_name = ?, synced_at = NOW()",
                    [
                        $block['id'],
                        $block['title']['rendered'] ?? $block['title'],
                        $categorySlug,
                        $categoryName,
                        $block['title']['rendered'] ?? $block['title'],
                        $categorySlug,
                        $categoryName
                    ]
                );
                $synced++;
            }

            $this->logActivity('sync_blocks', 'system', null, ['count' => $synced]);

            echo json_encode([
                'success' => true,
                'data' => ['synced' => $synced, 'message' => "Synced {$synced} page blocks"]
            ]);

        } catch (\Exception $e) {
            error_log("Block sync error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'SYNC_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Get sync status
     */
    public function syncStatus(): void
    {
        $status = [
            'brands' => $this->getLastSync('wp_brands'),
            'product_categories' => $this->getLastSync('wp_product_categories'),
            'products' => $this->getLastSync('wp_products'),
            'categories' => $this->getLastSync('wp_categories'),
            'authors' => $this->getLastSync('wp_authors'),
            'blocks' => $this->getLastSync('wp_page_blocks'),
        ];

        echo json_encode([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Get last sync info for a table
     */
    private function getLastSync(string $table): array
    {
        $result = Database::queryOne(
            "SELECT COUNT(*) as count, MAX(synced_at) as last_sync FROM {$table}"
        );

        return [
            'count' => (int)($result['count'] ?? 0),
            'last_sync' => $result['last_sync'] ?? null
        ];
    }

    /**
     * Generate slug from string
     */
    private function slugify(string $text): string
    {
        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Log activity
     */
    private function logActivity(string $action, string $entityType, ?int $entityId, array $details = []): void
    {
        try {
            Database::insert(
                "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details_json, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $_SESSION['user_id'] ?? null,
                    $action,
                    $entityType,
                    $entityId,
                    json_encode($details),
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]
            );
        } catch (\Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
    
    /**
     * Publish a post to WordPress
     */
    public function publishToWordPress(int $postId, array $input): void
    {
        try {
            // Get the post from our database
            $post = Database::queryOne(
                "SELECT p.*, c.wp_category_id, a.wp_user_id as author_id
                 FROM posts p
                 LEFT JOIN wp_categories c ON p.wp_category_id = c.id
                 LEFT JOIN wp_authors a ON p.wp_author_id = a.id
                 WHERE p.id = ?",
                [$postId]
            );
            
            if (!$post) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Post not found']]);
                return;
            }
            
            // Get sections
            $sections = Database::query(
                "SELECT * FROM post_sections WHERE post_id = ? ORDER BY section_index",
                [$postId]
            );
            
            // Build Visual Composer content
            $service = new WordPressService($this->config);
            $vcContent = $service->buildVisualComposerContent($post, $sections);
            
            // Prepare WordPress post data
            $wpData = [
                'title' => $post['title'],
                'content' => $vcContent,
                'status' => $input['wp_status'] ?? 'draft', // draft, publish, or future
            ];
            
            // Add category if set
            if (!empty($post['wp_category_id'])) {
                $wpData['categories'] = [$post['wp_category_id']];
            }
            
            // Add author if set
            if (!empty($post['author_id'])) {
                $wpData['author'] = $post['author_id'];
            }
            
            // NOTE: Skipping scheduled date for now - will add later if needed
            // WordPress date scheduling requires specific format and future status
            
            // Check if already published (update) or new (create)
            if (!empty($post['wp_post_id'])) {
                // Update existing WordPress post
                $result = $service->updatePost($post['wp_post_id'], $wpData);
                $wpPostId = $post['wp_post_id'];
            } else {
                // Create new WordPress post
                $result = $service->createPost($wpData);
                $wpPostId = $result['id'] ?? null;
            }
            
            if (!$wpPostId) {
                throw new \Exception('Failed to create/update WordPress post');
            }
            
            // Update our database
            Database::execute(
                "UPDATE posts SET wp_post_id = ?, status = 'published', published_at = NOW() WHERE id = ?",
                [$wpPostId, $postId]
            );
            
            $this->logActivity('publish_wordpress', 'post', $postId, [
                'wp_post_id' => $wpPostId,
                'status' => $wpData['status']
            ]);
            
            // Build the WordPress edit URL
            $siteUrl = rtrim($this->config['wordpress']['site_url'] ?? $this->config['wordpress']['api_url'], '/');
            $siteUrl = preg_replace('/\/wp-json$/', '', $siteUrl);
            $editUrl = $siteUrl . '/wp-admin/post.php?post=' . $wpPostId . '&action=edit';
            $viewUrl = $result['link'] ?? $siteUrl . '/?p=' . $wpPostId;
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'wp_post_id' => $wpPostId,
                    'edit_url' => $editUrl,
                    'view_url' => $viewUrl,
                    'message' => 'Post published to WordPress successfully'
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("WordPress publish error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Debug endpoint to test what WordPress API returns for a brand
     * Access via: GET /wordpress/debug/brand/{term_id}
     */
    public function debugBrandApi(int $termId): void
    {
        try {
            $baseUrl = rtrim($this->config['wordpress']['api_url'], '/');
            $username = $this->config['wordpress']['username'];
            $password = $this->config['wordpress']['password'];
            
            // Test 1: Single brand endpoint
            $url = $baseUrl . "/wp/v2/brand/{$termId}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($username . ':' . $password)
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            // Test 2: List endpoint (first page)
            $listUrl = $baseUrl . "/wp/v2/brand?per_page=1";
            $ch2 = curl_init($listUrl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($username . ':' . $password)
            ]);
            $listResponse = curl_exec($ch2);
            $listHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            
            $listData = json_decode($listResponse, true);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'single_endpoint' => [
                        'url' => $url,
                        'http_code' => $httpCode,
                        'curl_error' => $curlError ?: null,
                        'has_acf_key' => isset($data['acf']),
                        'acf_fields' => $data['acf'] ?? null,
                        'all_keys' => is_array($data) ? array_keys($data) : [],
                    ],
                    'list_endpoint' => [
                        'url' => $listUrl,
                        'http_code' => $listHttpCode,
                        'first_item_has_acf' => isset($listData[0]['acf']),
                        'first_item_acf' => $listData[0]['acf'] ?? null,
                        'first_item_keys' => isset($listData[0]) ? array_keys($listData[0]) : [],
                    ],
                    'raw_single_response' => substr($response, 0, 2000),
                    'tips' => [
                        'If has_acf_key is false, check:',
                        '1. ACF field group has "Show in REST API" enabled',
                        '2. Field group location rules target "Taxonomy" equals "brand"',
                        '3. Clear any WordPress/ACF caches'
                    ]
                ]
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Debug endpoint to test what WordPress API returns for a product category
     * Access via: GET /wordpress/debug/category/{term_id}
     */
    public function debugCategoryApi(int $termId): void
    {
        try {
            $baseUrl = rtrim($this->config['wordpress']['api_url'], '/');
            $username = $this->config['wordpress']['username'];
            $password = $this->config['wordpress']['password'];
            
            $url = $baseUrl . "/wp/v2/product_cat/{$termId}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($username . ':' . $password)
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'url' => $url,
                    'http_code' => $httpCode,
                    'curl_error' => $curlError ?: null,
                    'has_acf_key' => isset($data['acf']),
                    'acf_fields' => $data['acf'] ?? null,
                    'all_keys' => is_array($data) ? array_keys($data) : [],
                    'raw_response_preview' => substr($response, 0, 2000),
                    'tips' => [
                        'If has_acf_key is false, check:',
                        '1. ACF field group has "Show in REST API" enabled',
                        '2. Field group location rules target "Taxonomy" equals "product_cat"',
                        '3. Clear any WordPress/ACF caches'
                    ]
                ]
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }
}
