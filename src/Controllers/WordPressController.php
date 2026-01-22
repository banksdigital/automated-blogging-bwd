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
     * Sync products from WooCommerce
     */
    public function syncProducts(): void
    {
        try {
            $service = new WordPressService($this->config);
            $products = $service->getAllProducts();

            $synced = 0;
            foreach ($products as $product) {
                // Extract brand from attributes or tags
                $brandSlug = null;
                $brandName = null;
                
                if (!empty($product['attributes'])) {
                    foreach ($product['attributes'] as $attr) {
                        if (strtolower($attr['name']) === 'brand' && !empty($attr['options'])) {
                            $brandName = $attr['options'][0];
                            $brandSlug = $this->slugify($brandName);
                            break;
                        }
                    }
                }

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
                        $tags[] = $tag['name'];
                    }
                }

                // Get image URL
                $imageUrl = null;
                if (!empty($product['images']) && !empty($product['images'][0]['src'])) {
                    $imageUrl = $product['images'][0]['src'];
                }

                Database::execute(
                    "INSERT INTO wp_products (wc_product_id, title, description, short_description,
                                             price, regular_price, sale_price, stock_status,
                                             brand_slug, brand_name, category_slugs, category_names,
                                             tags, image_url, permalink, sku, synced_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
                        json_encode($categorySlugs),
                        json_encode($categoryNames),
                        json_encode($tags),
                        $imageUrl,
                        $product['permalink'] ?? null,
                        $product['sku'] ?? null
                    ]
                );
                $synced++;
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
            'categories' => $this->getLastSync('wp_categories'),
            'authors' => $this->getLastSync('wp_authors'),
            'products' => $this->getLastSync('wp_products'),
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
}
