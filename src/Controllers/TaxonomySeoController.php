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
            
            // Get categories this brand has products in
            $categories = Database::query(
                "SELECT DISTINCT pc.name, pc.slug, COUNT(p.id) as product_count
                 FROM wp_products p
                 JOIN wp_product_categories pc ON JSON_CONTAINS(p.category_slugs, CONCAT('\"', pc.slug, '\"'))
                 WHERE p.brand_id = ? AND p.stock_status = 'instock' AND pc.parent_id = 0
                 GROUP BY pc.id
                 ORDER BY product_count DESC
                 LIMIT 10",
                [$id]
            );
            
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
                'message' => 'Brand SEO content generated'
            ]);
        } catch (\Exception $e) {
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
            
            // Get brands that have products in this category
            $brands = Database::query(
                "SELECT DISTINCT b.name, b.slug, COUNT(p.id) as product_count
                 FROM wp_products p
                 JOIN wp_brands b ON p.brand_id = b.id
                 WHERE JSON_CONTAINS(p.category_slugs, CONCAT('\"', ?, '\"')) AND p.stock_status = 'instock'
                 GROUP BY b.id
                 ORDER BY product_count DESC
                 LIMIT 10",
                [$category['slug']]
            );
            
            $claudeService = new ClaudeService($this->config);
            $content = $claudeService->generateCategorySeo($category, $brands);
            
            // Save to database
            Database::execute(
                "UPDATE wp_product_categories SET seo_description = ?, seo_meta_description = ?, seo_updated_at = NOW() WHERE id = ?",
                [$content['description'], $content['meta_description'], $id]
            );
            
            echo json_encode([
                'success' => true,
                'data' => $content,
                'message' => 'Category SEO content generated'
            ]);
        } catch (\Exception $e) {
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
                'taxonomy_description' => $brand['seo_description'] ?? '',
                'taxonomy_seo_description' => $brand['seo_meta_description'] ?? ''
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
                'taxonomy_description' => $category['seo_description'] ?? '',
                'taxonomy_seo_description' => $category['seo_meta_description'] ?? ''
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
                [$seoData['taxonomy_description'] ?? '', $seoData['taxonomy_seo_description'] ?? '', $id]
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
                [$seoData['taxonomy_description'] ?? '', $seoData['taxonomy_seo_description'] ?? '', $id]
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
