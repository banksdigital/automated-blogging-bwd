<?php

namespace App\Controllers;

use App\Helpers\Database;

class ProductController
{
    public function __construct(array $config) {}

    public function index(array $params): void
    {
        $brand = $params['brand'] ?? null;
        $category = $params['category'] ?? null;
        $stock = $params['stock'] ?? 'instock';
        $limit = min((int)($params['limit'] ?? 50), 200);
        $offset = (int)($params['offset'] ?? 0);

        $sql = "SELECT * FROM wp_products WHERE 1=1";
        $bindings = [];

        if ($brand) {
            $sql .= " AND brand_slug = ?";
            $bindings[] = $brand;
        }
        if ($category) {
            $sql .= " AND JSON_CONTAINS(category_slugs, ?)";
            $bindings[] = json_encode($category);
        }
        if ($stock) {
            $sql .= " AND stock_status = ?";
            $bindings[] = $stock;
        }

        $sql .= " ORDER BY title LIMIT ? OFFSET ?";
        $bindings[] = $limit;
        $bindings[] = $offset;

        $products = Database::query($sql, $bindings);
        echo json_encode(['success' => true, 'data' => $products]);
    }

    public function search(array $params): void
    {
        $query = $params['q'] ?? '';
        $limit = min((int)($params['limit'] ?? 20), 50);

        if (strlen($query) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }

        $products = Database::query(
            "SELECT * FROM wp_products 
             WHERE (title LIKE ? OR brand_name LIKE ?) AND stock_status = 'instock'
             ORDER BY title LIMIT ?",
            ["%{$query}%", "%{$query}%", $limit]
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
