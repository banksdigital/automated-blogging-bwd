<?php

namespace App\Controllers;

use App\Helpers\Database;

class ProductController
{
    public function __construct(array $config) {}

    public function index(array $params): void
    {
        $search = trim($params['search'] ?? '');
        $brand = $params['brand'] ?? null;
        $category = $params['category'] ?? null;
        $stock = $params['stock'] ?? 'instock';
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min((int)($params['per_page'] ?? 50), 100);
        $offset = ($page - 1) * $perPage;

        // Build WHERE clause
        $where = "WHERE 1=1";
        $bindings = [];

        if ($search) {
            $where .= " AND (title LIKE ? OR brand_name LIKE ? OR sku LIKE ? OR description LIKE ?)";
            $searchTerm = "%{$search}%";
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
        }
        if ($brand) {
            $where .= " AND brand_slug = ?";
            $bindings[] = $brand;
        }
        if ($category) {
            $where .= " AND JSON_CONTAINS(category_slugs, ?)";
            $bindings[] = json_encode($category);
        }
        if ($stock) {
            $where .= " AND stock_status = ?";
            $bindings[] = $stock;
        }

        // Get total count
        $countResult = Database::queryOne("SELECT COUNT(*) as total FROM wp_products {$where}", $bindings);
        $total = (int)($countResult['total'] ?? 0);

        // Get paginated products
        $sql = "SELECT * FROM wp_products {$where} ORDER BY title LIMIT ? OFFSET ?";
        $bindings[] = $perPage;
        $bindings[] = $offset;

        $products = Database::query($sql, $bindings);

        echo json_encode([
            'success' => true,
            'data' => [
                'products' => $products,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => ceil($total / $perPage),
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total)
                ]
            ]
        ]);
    }

    public function search(array $params): void
    {
        $query = trim($params['q'] ?? '');
        $limit = min((int)($params['limit'] ?? 20), 50);

        if (strlen($query) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }

        $searchTerm = "%{$query}%";
        $products = Database::query(
            "SELECT * FROM wp_products 
             WHERE (title LIKE ? OR brand_name LIKE ? OR sku LIKE ? OR description LIKE ?) 
             AND stock_status = 'instock'
             ORDER BY title LIMIT ?",
            [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]
        );

        echo json_encode(['success' => true, 'data' => $products]);
    }

    public function brands(): void
    {
        $brands = Database::query(
            "SELECT brand_slug, brand_name, COUNT(*) as product_count 
             FROM wp_products 
             WHERE brand_slug IS NOT NULL AND stock_status = 'instock'
             GROUP BY brand_slug, brand_name 
             ORDER BY brand_name"
        );
        echo json_encode(['success' => true, 'data' => $brands]);
    }
}
