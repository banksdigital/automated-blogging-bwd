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
            $brands = $service->getAllBrands();

            $synced = 0;
            foreach ($brands as $brand) {
                Database::execute(
                    "INSERT INTO wp_brands (wp_term_id, name, slug, description, count, synced_at)
                     VALUES (?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug), 
                     description = VALUES(description), count = VALUES(count), synced_at = NOW()",
                    [
                        $brand['id'],
                        html_entity_decode($brand['name']),
                        $brand['slug'],
                        $brand['description'] ?? null,
                        $brand['count'] ?? 0
                    ]
                );
                $synced++;
            }

            $this->logActivity('sync_brands', 'system', null, ['count' => $synced]);

            echo json_encode([
                'success' => true,
                'data' => ['synced' => $synced, 'message' => "Synced {$synced} brands"]
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
            $categories = $service->getAllProductCategories();

            $synced = 0;
            foreach ($categories as $cat) {
                Database::execute(
                    "INSERT INTO wp_product_categories (wp_term_id, parent_id, name, slug, description, count, synced_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE parent_id = VALUES(parent_id), name = VALUES(name), 
                     slug = VALUES(slug), description = VALUES(description), count = VALUES(count), synced_at = NOW()",
                    [
                        $cat['id'],
                        $cat['parent'] ?? 0,
                        html_entity_decode($cat['name']),
                        $cat['slug'],
                        $cat['description'] ?? null,
                        $cat['count'] ?? 0
                    ]
                );
                $synced++;
            }

            $this->logActivity('sync_product_categories', 'system', null, ['count' => $synced]);

            echo json_encode([
                'success' => true,
                'data' => ['synced' => $synced, 'message' => "Synced {$synced} product categories"]
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
        
        // Build product -> brand map by querying each brand's products (much faster than per-product lookup)
        $allBrands = $service->getAllBrands();
        $productBrandMap = [];
        
        error_log("Building brand map from " . count($allBrands) . " brands...");
        
        foreach ($allBrands as $brand) {
            // Get all product IDs for this brand
            $brandProductIds = $service->getProductIdsByBrand($brand['id']);
            foreach ($brandProductIds as $productId) {
                $productBrandMap[$productId] = [
                    'id' => $brand['id'],
                    'name' => html_entity_decode($brand['name']),
                    'slug' => $brand['slug']
                ];
            }
        }
        
        error_log("Brand map built: " . count($productBrandMap) . " products have brands assigned");
        
        // Get all products from WooCommerce
        $products = $service->getAllProducts();
        error_log("Fetched " . count($products) . " products from WooCommerce");

        $synced = 0;
        foreach ($products as $product) {
            // Look up brand from pre-built map (no API call needed)
            $brandSlug = null;
            $brandName = null;
            $brandId = null;
            
            if (isset($productBrandMap[$product['id']])) {
                $brandId = $productBrandMap[$product['id']]['id'];
                $brandName = $productBrandMap[$product['id']]['name'];
                $brandSlug = $productBrandMap[$product['id']]['slug'];
            }
            
            // Extract categories with IDs
            $categorySlugs = [];
            $categoryNames = [];
            $categoryIds = [];
            if (!empty($product['categories'])) {
                foreach ($product['categories'] as $cat) {
                    $categorySlugs[] = $cat['slug'];
                    $categoryNames[] = $cat['name'];
                    $categoryIds[] = $cat['id'];
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

            // Upsert product
            Database::execute(
                "INSERT INTO wp_products (wc_product_id, title, description, short_description, price, regular_price, sale_price, stock_status, brand_slug, brand_name, brand_id, category_slugs, category_names, tags, image_url, permalink, sku, synced_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                short_description = VALUES(short_description),
                price = VALUES(price),
                regular_price = VALUES(regular_price),
                sale_price = VALUES(sale_price),
                stock_status = VALUES(stock_status),
                brand_slug = VALUES(brand_slug),
                brand_name = VALUES(brand_name),
                brand_id = VALUES(brand_id),
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
                    $brandSlug,
                    $brandName,
                    $brandId,
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

        $this->logActivity('sync_products', 'system', null, ['count' => $synced]);

        echo json_encode([
            'success' => true,
            'data' => ['synced' => $synced, 'message' => "Synced {$synced} products"]
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
                'meta_description' => $post['meta_description'] ?? '',
            ];
            
            // Add category if set
            if (!empty($post['wp_category_id'])) {
                $wpData['categories'] = [$post['wp_category_id']];
            }
            
            // Add author if set
            if (!empty($post['author_id'])) {
                $wpData['author'] = $post['author_id'];
            }
            
            // Add scheduled date if provided
            if (!empty($input['scheduled_date'])) {
                $wpData['date'] = $input['scheduled_date'];
            } elseif (!empty($post['scheduled_date'])) {
                $wpData['date'] = $post['scheduled_date'];
            }
            
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
}
