<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\ClaudeService;
use App\Services\WordPressService;

class TaxonomySeoController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get all brands with their SEO status
     */
    public function brands(): void
    {
        try {
            $brands = Database::query(
                "SELECT 
                    b.id,
                    b.wp_term_id,
                    b.name,
                    b.slug,
                    b.count as product_count,
                    b.seo_description,
                    b.seo_meta_description,
                    b.seo_updated_at,
                    (SELECT COUNT(DISTINCT pc.id) 
                     FROM wp_products p 
                     JOIN wp_product_categories pc ON JSON_CONTAINS(p.category_slugs, CONCAT('\"', pc.slug, '\"'))
                     WHERE p.brand_id = b.id AND p.stock_status = 'instock' AND pc.parent_id = 0
                    ) as category_count
                 FROM wp_brands b
                 WHERE b.count > 0
                 ORDER BY b.name ASC"
            );
            
            echo json_encode([
                'success' => true,
                'data' => $brands
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Get all categories with their SEO status
     */
    public function categories(): void
    {
        try {
            $categories = Database::query(
                "SELECT 
                    c.id,
                    c.wp_term_id,
                    c.name,
                    c.slug,
                    c.parent_id,
                    c.count as product_count,
                    c.seo_description,
                    c.seo_meta_description,
                    c.seo_updated_at,
                    p.name as parent_name,
                    (SELECT COUNT(DISTINCT b.id) 
                     FROM wp_products pr 
                     JOIN wp_brands b ON pr.brand_id = b.id
                     WHERE JSON_CONTAINS(pr.category_slugs, CONCAT('\"', c.slug, '\"')) AND pr.stock_status = 'instock'
                    ) as brand_count
                 FROM wp_product_categories c
                 LEFT JOIN wp_product_categories p ON c.parent_id = p.wp_term_id
                 WHERE c.count > 0
                 ORDER BY COALESCE(p.name, c.name), c.parent_id, c.name ASC"
            );
            
            echo json_encode([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Get brand details with categories it has products in
     */
    public function brandDetails(int $id): void
    {
        try {
            $brand = Database::queryOne(
                "SELECT * FROM wp_brands WHERE id = ?",
                [$id]
            );
            
            if (!$brand) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Brand not found']]);
                return;
            }
            
            // Get categories this brand has products in
            $categories = Database::query(
                "SELECT DISTINCT pc.id, pc.name, pc.slug, COUNT(p.id) as product_count
                 FROM wp_products p
                 JOIN wp_product_categories pc ON JSON_CONTAINS(p.category_slugs, CONCAT('\"', pc.slug, '\"'))
                 WHERE p.brand_id = ? AND p.stock_status = 'instock' AND pc.parent_id = 0
                 GROUP BY pc.id
                 ORDER BY product_count DESC",
                [$id]
            );
            
            $brand['categories'] = $categories;
            
            echo json_encode([
                'success' => true,
                'data' => $brand
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Get category details with brands that have products in it
     */
    public function categoryDetails(int $id): void
    {
        try {
            $category = Database::queryOne(
                "SELECT * FROM wp_product_categories WHERE id = ?",
                [$id]
            );
            
            if (!$category) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Category not found']]);
                return;
            }
            
            // Get brands that have products in this category
            $brands = Database::query(
                "SELECT DISTINCT b.id, b.name, b.slug, COUNT(p.id) as product_count
                 FROM wp_products p
                 JOIN wp_brands b ON p.brand_id = b.id
                 WHERE JSON_CONTAINS(p.category_slugs, CONCAT('\"', ?, '\"')) AND p.stock_status = 'instock'
                 GROUP BY b.id
                 ORDER BY product_count DESC",
                [$category['slug']]
            );
            
            $category['brands'] = $brands;
            
            echo json_encode([
                'success' => true,
                'data' => $category
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Generate SEO content for a brand
     */
    public function generateBrandSeo(int $id): void
    {
        try {
            $brand = Database::queryOne("SELECT * FROM wp_brands WHERE id = ?", [$id]);
            
            if (!$brand) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Brand not found']]);
                return;
            }
            
            // Get categories this brand has products in (parent categories only for cleaner links)
            $categories = Database::query(
                "SELECT DISTINCT pc.name, pc.slug, COUNT(p.id) as product_count
                 FROM wp_products p
                 JOIN wp_product_categories pc ON JSON_CONTAINS(p.category_slugs, CONCAT('\"', pc.slug, '\"'))
                 WHERE p.brand_id = ? AND p.stock_status = 'instock' AND pc.parent_id = 0
                 GROUP BY pc.id, pc.name, pc.slug
                 ORDER BY product_count DESC
                 LIMIT 10",
                [$id]
            );
            
            // Debug log
            error_log("generateBrandSeo: Brand '{$brand['name']}' has " . count($categories) . " categories");
            if (!empty($categories)) {
                error_log("Categories: " . json_encode(array_column($categories, 'name')));
            }
            
            if (empty($categories)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => ['message' => "No products found for brand '{$brand['name']}'. Sync products first."]
                ]);
                return;
            }
            
            $claudeService = new ClaudeService($this->config);
            $content = $claudeService->generateBrandSeo($brand, $categories);
            
            // Save to database
            Database::execute(
                "UPDATE wp_brands SET seo_description = ?, seo_meta_description = ?, seo_updated_at = NOW() WHERE id = ?",
                [$content['description'], $content['meta_description'], $id]
            );
            
            echo json_encode([
                'success' => true,
                'data' => $content,
                'context' => [
                    'categories_used' => array_map(fn($c) => $c['name'], $categories)
                ],
                'message' => 'Brand SEO content generated using ' . count($categories) . ' categories'
            ]);
        } catch (\Exception $e) {
            error_log("generateBrandSeo error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Generate SEO content for a category
     */
    public function generateCategorySeo(int $id): void
    {
        try {
            $category = Database::queryOne("SELECT * FROM wp_product_categories WHERE id = ?", [$id]);
            
            if (!$category) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Category not found']]);
                return;
            }
            
            // Debug: Log what we're searching for
            error_log("generateCategorySeo: Looking for products in category '{$category['name']}' with slug '{$category['slug']}'");
            
            // Get brands that have products in this category
            // Use LEFT JOIN to find products even if brand_id is NULL
            $brands = Database::query(
                "SELECT DISTINCT b.name, b.slug, COUNT(p.id) as product_count
                 FROM wp_products p
                 LEFT JOIN wp_brands b ON p.brand_id = b.id
                 WHERE JSON_CONTAINS(p.category_slugs, CONCAT('\"', ?, '\"')) 
                   AND p.stock_status = 'instock'
                   AND b.id IS NOT NULL
                 GROUP BY b.id, b.name, b.slug
                 ORDER BY product_count DESC
                 LIMIT 15",
                [$category['slug']]
            );
            
            // Also check how many products exist (even without brands)
            $productCount = Database::queryOne(
                "SELECT COUNT(*) as cnt FROM wp_products 
                 WHERE JSON_CONTAINS(category_slugs, CONCAT('\"', ?, '\"')) AND stock_status = 'instock'",
                [$category['slug']]
            );
            
            error_log("generateCategorySeo: Found {$productCount['cnt']} products, " . count($brands) . " with brand links");
            
            // If no brands found, try checking if there are ANY products with this category
            if (empty($brands)) {
                // Check if products exist but maybe out of stock
                $anyProducts = Database::queryOne(
                    "SELECT COUNT(*) as cnt FROM wp_products WHERE JSON_CONTAINS(category_slugs, CONCAT('\"', ?, '\"'))",
                    [$category['slug']]
                );
                
                // Also check child categories if this is a parent
                $childSlugs = Database::query(
                    "SELECT slug FROM wp_product_categories WHERE parent_id = ?",
                    [$category['wp_term_id']]
                );
                
                $debugInfo = "Slug searched: '{$category['slug']}'. ";
                $debugInfo .= "Products found (any stock): {$anyProducts['cnt']}. ";
                
                if (!empty($childSlugs)) {
                    // Try to find products in child categories
                    $childSlugList = array_column($childSlugs, 'slug');
                    $debugInfo .= "Has " . count($childSlugs) . " child categories. ";
                    
                    // Search in child categories
                    $placeholders = implode(',', array_fill(0, count($childSlugList), '?'));
                    $childBrands = Database::query(
                        "SELECT DISTINCT b.name, b.slug, COUNT(p.id) as product_count
                         FROM wp_products p
                         JOIN wp_brands b ON p.brand_id = b.id
                         WHERE p.stock_status = 'instock' 
                         AND (" . implode(' OR ', array_map(fn($s) => "JSON_CONTAINS(p.category_slugs, '\"$s\"')", $childSlugList)) . ")
                         GROUP BY b.id, b.name, b.slug
                         ORDER BY product_count DESC
                         LIMIT 15"
                    );
                    
                    if (!empty($childBrands)) {
                        $brands = $childBrands;
                        $debugInfo .= "Found " . count($childBrands) . " brands via child categories.";
                        error_log("generateCategorySeo: Using child category products for parent '{$category['name']}'");
                    }
                }
                
                error_log("generateCategorySeo debug: " . $debugInfo);
            }
            
            // Get related categories (sibling categories - same parent, or children if this is a parent)
            $relatedCategories = [];
            
            if ($category['parent_id'] > 0) {
                // This is a child category - get siblings (same parent)
                $relatedCategories = Database::query(
                    "SELECT name, slug FROM wp_product_categories 
                     WHERE parent_id = ? AND id != ? AND count > 0
                     ORDER BY count DESC LIMIT 5",
                    [$category['parent_id'], $id]
                );
                
                // Also get parent category
                $parent = Database::queryOne(
                    "SELECT name, slug FROM wp_product_categories WHERE wp_term_id = ?",
                    [$category['parent_id']]
                );
                if ($parent) {
                    array_unshift($relatedCategories, $parent);
                }
            } else {
                // This is a parent category - get child categories
                $relatedCategories = Database::query(
                    "SELECT name, slug FROM wp_product_categories 
                     WHERE parent_id = ? AND count > 0
                     ORDER BY count DESC LIMIT 8",
                    [$category['wp_term_id']]
                );
            }
            
            // Debug log
            error_log("generateCategorySeo: Category '{$category['name']}' has " . count($brands) . " brands");
            if (!empty($brands)) {
                error_log("Brands: " . json_encode(array_column($brands, 'name')));
            }
            error_log("Related categories: " . count($relatedCategories));
            
            // Check if we have products but no brand links
            $productCheck = Database::queryOne(
                "SELECT COUNT(*) as cnt FROM wp_products 
                 WHERE JSON_CONTAINS(category_slugs, CONCAT('\"', ?, '\"')) AND stock_status = 'instock'",
                [$category['slug']]
            );
            
            if (empty($brands) && $productCheck['cnt'] == 0) {
                // No products at all
                $errorMsg = "No in-stock products found in category '{$category['name']}' (slug: {$category['slug']}).";
                $errorMsg .= " Products may be tagged with sub-categories only. Check if products are synced.";
                
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => ['message' => $errorMsg]
                ]);
                return;
            }
            
            if (empty($brands) && $productCheck['cnt'] > 0) {
                // Products exist but no brand links - still generate but without brand links
                error_log("generateCategorySeo: {$productCheck['cnt']} products found but no brand_id set. Generating without brand links.");
            }
            
            $claudeService = new ClaudeService($this->config);
            $content = $claudeService->generateCategorySeo($category, $brands, $relatedCategories);
            
            // Save to database
            Database::execute(
                "UPDATE wp_product_categories SET seo_description = ?, seo_meta_description = ?, seo_updated_at = NOW() WHERE id = ?",
                [$content['description'], $content['meta_description'], $id]
            );
            
            echo json_encode([
                'success' => true,
                'data' => $content,
                'context' => [
                    'brands_used' => array_map(fn($b) => $b['name'], $brands),
                    'related_categories' => array_map(fn($c) => $c['name'], $relatedCategories)
                ],
                'message' => 'Category SEO content generated using ' . count($brands) . ' brands'
            ]);
        } catch (\Exception $e) {
            error_log("generateCategorySeo error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Save manually edited SEO content for a brand
     */
    public function saveBrandSeo(int $id, array $input): void
    {
        try {
            Database::execute(
                "UPDATE wp_brands SET seo_description = ?, seo_meta_description = ?, seo_updated_at = NOW() WHERE id = ?",
                [$input['seo_description'] ?? '', $input['seo_meta_description'] ?? '', $id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Brand SEO content saved'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Save manually edited SEO content for a category
     */
    public function saveCategorySeo(int $id, array $input): void
    {
        try {
            Database::execute(
                "UPDATE wp_product_categories SET seo_description = ?, seo_meta_description = ?, seo_updated_at = NOW() WHERE id = ?",
                [$input['seo_description'] ?? '', $input['seo_meta_description'] ?? '', $id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Category SEO content saved'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Push brand SEO content to WordPress
     */
    public function pushBrandToWordPress(int $id): void
    {
        try {
            $brand = Database::queryOne("SELECT * FROM wp_brands WHERE id = ?", [$id]);
            
            if (!$brand || !$brand['wp_term_id']) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Brand not found or missing WordPress term ID']]);
                return;
            }
            
            $wpService = new WordPressService($this->config);
            $result = $wpService->updateTaxonomySeo('brand', $brand['wp_term_id'], [
                'description' => $brand['seo_description'] ?? '',
                'meta_description' => $brand['seo_meta_description'] ?? ''
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Brand SEO pushed to WordPress'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Push category SEO content to WordPress
     */
    public function pushCategoryToWordPress(int $id): void
    {
        try {
            $category = Database::queryOne("SELECT * FROM wp_product_categories WHERE id = ?", [$id]);
            
            if (!$category || !$category['wp_term_id']) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Category not found or missing WordPress term ID']]);
                return;
            }
            
            $wpService = new WordPressService($this->config);
            $result = $wpService->updateTaxonomySeo('product_cat', $category['wp_term_id'], [
                'description' => $category['seo_description'] ?? '',
                'meta_description' => $category['seo_meta_description'] ?? ''
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Category SEO pushed to WordPress'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Pull brand SEO content from WordPress
     */
    public function pullBrandFromWordPress(int $id): void
    {
        try {
            $brand = Database::queryOne("SELECT * FROM wp_brands WHERE id = ?", [$id]);
            
            if (!$brand || !$brand['wp_term_id']) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Brand not found or missing WordPress term ID']]);
                return;
            }
            
            $wpService = new WordPressService($this->config);
            $seoData = $wpService->getTaxonomySeo('brand', $brand['wp_term_id']);
            
            // Update local database
            Database::execute(
                "UPDATE wp_brands SET seo_description = ?, seo_meta_description = ?, seo_updated_at = NOW() WHERE id = ?",
                [$seoData['description'] ?? '', $seoData['meta_description'] ?? '', $id]
            );
            
            echo json_encode([
                'success' => true,
                'data' => $seoData,
                'message' => 'Brand SEO pulled from WordPress'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Pull category SEO content from WordPress
     */
    public function pullCategoryFromWordPress(int $id): void
    {
        try {
            $category = Database::queryOne("SELECT * FROM wp_product_categories WHERE id = ?", [$id]);
            
            if (!$category || !$category['wp_term_id']) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Category not found or missing WordPress term ID']]);
                return;
            }
            
            $wpService = new WordPressService($this->config);
            $seoData = $wpService->getTaxonomySeo('product_cat', $category['wp_term_id']);
            
            // Update local database
            Database::execute(
                "UPDATE wp_product_categories SET seo_description = ?, seo_meta_description = ?, seo_updated_at = NOW() WHERE id = ?",
                [$seoData['description'] ?? '', $seoData['meta_description'] ?? '', $id]
            );
            
            echo json_encode([
                'success' => true,
                'data' => $seoData,
                'message' => 'Category SEO pulled from WordPress'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }
}
