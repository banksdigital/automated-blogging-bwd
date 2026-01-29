<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\WordPressService;

class EditSuggestionController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    private function tablesExist(): bool
    {
        try {
            $result = Database::query(
                "SELECT COUNT(*) as cnt FROM information_schema.tables 
                 WHERE table_schema = DATABASE() 
                 AND table_name IN ('edit_suggestions', 'edit_products')"
            );
            return (int)($result[0]['cnt'] ?? 0) >= 2;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function index(): void
    {
        try {
            if (!$this->tablesExist()) {
                echo json_encode([
                    'success' => true,
                    'data' => [],
                    'message' => 'Tables not created yet. Run the SQL migration.'
                ]);
                return;
            }

            $edits = Database::query("SELECT * FROM edit_suggestions ORDER BY name ASC");

            foreach ($edits as &$edit) {
                $edit['matching_rules'] = json_decode($edit['matching_rules'] ?? '{}', true);
                $edit['total_products'] = 0;
                $edit['in_stock_products'] = 0;
                $edit['pending_products'] = 0;
                $edit['synced_products'] = 0;
            }

            echo json_encode(['success' => true, 'data' => $edits]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function show(int $id): void
    {
        try {
            $edit = Database::queryOne("SELECT * FROM edit_suggestions WHERE id = ?", [$id]);
            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not found']]);
                return;
            }
            
            $edit['matching_rules'] = json_decode($edit['matching_rules'] ?? '{}', true) ?: [];
            
            // Load products from edit_products joined with wp_products
            $products = Database::query(
                "SELECT ep.*, p.title, p.price, p.image_url, p.stock_status, p.brand_name, p.permalink
                 FROM edit_products ep
                 LEFT JOIN wp_products p ON ep.wc_product_id = p.wc_product_id
                 WHERE ep.edit_suggestion_id = ?
                 ORDER BY ep.status ASC, ep.match_score DESC",
                [$id]
            );
            
            // Parse match_reasons JSON
            foreach ($products as &$product) {
                $product['match_reasons'] = json_decode($product['match_reasons'] ?? '[]', true) ?: [];
            }
            
            $edit['products'] = $products;
            
            // Calculate stats
            $edit['stats'] = [
                'total' => count($products),
                'in_stock' => count(array_filter($products, fn($p) => ($p['stock_status'] ?? '') === 'instock')),
                'out_of_stock' => count(array_filter($products, fn($p) => ($p['stock_status'] ?? '') !== 'instock')),
                'pending' => count(array_filter($products, fn($p) => ($p['status'] ?? '') === 'pending')),
                'approved' => count(array_filter($products, fn($p) => ($p['status'] ?? '') === 'approved')),
                'synced' => count(array_filter($products, fn($p) => !empty($p['synced_to_wp'])))
            ];
            
            echo json_encode(['success' => true, 'data' => $edit]);
        } catch (\Throwable $e) {
            error_log("show edit error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function generateSuggestions(): void
    {
        try {
            if (!$this->tablesExist()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'Run SQL migration first']]);
                return;
            }

            $templates = [
                ['name' => "Valentine's Day Gifting", 'type' => 'occasion', 'desc' => 'Valentine gifts', 'rules' => ['categories' => ['dresses', 'accessories'], 'keywords' => ['gift', 'romantic'], 'colors' => ['red', 'pink']]],
                ['name' => "Mother's Day Gifts", 'type' => 'occasion', 'desc' => 'Gifts for mum', 'rules' => ['categories' => ['accessories', 'jewellery', 'bags'], 'keywords' => ['gift', 'elegant'], 'colors' => []]],
                ['name' => 'Wedding Guest', 'type' => 'occasion', 'desc' => 'Wedding guest outfits', 'rules' => ['categories' => ['dresses', 'jumpsuits'], 'keywords' => ['wedding', 'guest', 'occasion'], 'colors' => ['pastel']]],
                ['name' => 'Holiday Packing', 'type' => 'occasion', 'desc' => 'Holiday essentials', 'rules' => ['categories' => ['dresses', 'swimwear', 'sandals'], 'keywords' => ['holiday', 'summer', 'beach'], 'colors' => []]],
                ['name' => 'Party Season', 'type' => 'seasonal', 'desc' => 'Party pieces', 'rules' => ['categories' => ['dresses', 'tops', 'heels'], 'keywords' => ['party', 'evening', 'sparkle'], 'colors' => ['black', 'gold']]],
                ['name' => 'Spring Edit', 'type' => 'seasonal', 'desc' => 'Spring styles', 'rules' => ['categories' => ['dresses', 'blouses'], 'keywords' => ['spring', 'floral'], 'colors' => ['pastel']]],
                ['name' => 'Summer Edit', 'type' => 'seasonal', 'desc' => 'Summer styles', 'rules' => ['categories' => ['dresses', 'shorts', 'sandals'], 'keywords' => ['summer', 'light', 'linen'], 'colors' => ['white']]],
                ['name' => 'Autumn Edit', 'type' => 'seasonal', 'desc' => 'Autumn layers', 'rules' => ['categories' => ['knitwear', 'coats', 'boots'], 'keywords' => ['autumn', 'cosy'], 'colors' => ['burgundy', 'camel']]],
                ['name' => 'Winter Edit', 'type' => 'seasonal', 'desc' => 'Winter warmers', 'rules' => ['categories' => ['coats', 'knitwear', 'boots'], 'keywords' => ['winter', 'warm', 'wool'], 'colors' => ['black', 'grey']]],
                ['name' => 'The Denim Edit', 'type' => 'category', 'desc' => 'Premium denim', 'rules' => ['categories' => ['jeans', 'denim'], 'keywords' => ['denim', 'jeans'], 'colors' => []]],
                ['name' => 'The Knitwear Edit', 'type' => 'category', 'desc' => 'Luxurious knits', 'rules' => ['categories' => ['knitwear', 'jumpers'], 'keywords' => ['knit', 'cashmere'], 'colors' => []]],
                ['name' => 'Workwear Edit', 'type' => 'occasion', 'desc' => 'Office styles', 'rules' => ['categories' => ['blazers', 'trousers', 'blouses'], 'keywords' => ['work', 'office'], 'colors' => ['black', 'navy']]],
                ['name' => 'Date Night', 'type' => 'occasion', 'desc' => 'Evening styles', 'rules' => ['categories' => ['dresses', 'tops', 'heels'], 'keywords' => ['date', 'evening'], 'colors' => ['black', 'red']]],
            ];

            $created = 0;
            $skipped = 0;

            foreach ($templates as $t) {
                $slug = $this->slugify($t['name']);
                $existing = Database::queryOne("SELECT id FROM edit_suggestions WHERE slug = ?", [$slug]);
                if ($existing) {
                    $skipped++;
                    continue;
                }
                Database::insert(
                    "INSERT INTO edit_suggestions (name, slug, description, source_type, matching_rules, status) VALUES (?, ?, ?, ?, ?, 'suggested')",
                    [$t['name'], $slug, $t['desc'], $t['type'], json_encode($t['rules'])]
                );
                $created++;
            }

            echo json_encode(['success' => true, 'data' => ['created' => $created, 'skipped' => $skipped], 'message' => "Created {$created}, skipped {$skipped}"]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function previewProducts(int $id): void
    {
        try {
            $edit = Database::queryOne("SELECT * FROM edit_suggestions WHERE id = ?", [$id]);
            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not found']]);
                return;
            }

            $rules = json_decode($edit['matching_rules'] ?? '{}', true) ?: [];
            $products = $this->findMatchingProducts($rules, 100);

            echo json_encode([
                'success' => true,
                'data' => [
                    'edit' => ['id' => $edit['id'], 'name' => $edit['name'], 'matching_rules' => $rules],
                    'products' => $products,
                    'stats' => ['total' => count($products), 'in_stock' => count($products)]
                ]
            ]);
        } catch (\Throwable $e) {
            error_log("previewProducts error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function regenerateProducts(int $id): void
    {
        try {
            $edit = Database::queryOne("SELECT * FROM edit_suggestions WHERE id = ?", [$id]);
            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not found']]);
                return;
            }

            $rules = json_decode($edit['matching_rules'] ?? '{}', true) ?: [];
            
            if (empty($rules['categories']) && empty($rules['keywords']) && empty($rules['colors'])) {
                echo json_encode(['success' => true, 'data' => ['added' => 0, 'total' => 0], 'message' => 'No matching rules defined']);
                return;
            }
            
            $matched = $this->findMatchingProducts($rules, 100);

            $added = 0;
            foreach ($matched as $p) {
                try {
                    $existing = Database::queryOne(
                        "SELECT id FROM edit_products WHERE edit_suggestion_id = ? AND wc_product_id = ?",
                        [$id, $p['wc_product_id']]
                    );
                    if (!$existing) {
                        Database::insert(
                            "INSERT INTO edit_products (edit_suggestion_id, wc_product_id, match_score, match_reasons, status) VALUES (?, ?, ?, ?, 'pending')",
                            [$id, $p['wc_product_id'], $p['match_score'], json_encode($p['match_reasons'] ?? [])]
                        );
                        $added++;
                    }
                } catch (\Throwable $pe) {
                    error_log("Error adding product {$p['wc_product_id']}: " . $pe->getMessage());
                }
            }

            Database::execute("UPDATE edit_suggestions SET last_regenerated_at = NOW() WHERE id = ?", [$id]);

            echo json_encode(['success' => true, 'data' => ['added' => $added, 'total' => count($matched)]]);
        } catch (\Throwable $e) {
            error_log("regenerateProducts error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function approveProducts(int $id, array $input): void
    {
        try {
            if (!empty($input['approve_all'])) {
                Database::execute("UPDATE edit_products SET status = 'approved' WHERE edit_suggestion_id = ? AND status = 'pending'", [$id]);
            } elseif (!empty($input['wc_product_ids'])) {
                $ids = $input['wc_product_ids'];
                $ph = implode(',', array_fill(0, count($ids), '?'));
                Database::execute("UPDATE edit_products SET status = 'approved' WHERE edit_suggestion_id = ? AND wc_product_id IN ({$ph})", array_merge([$id], $ids));
            }
            Database::execute("UPDATE edit_suggestions SET status = 'approved' WHERE id = ? AND status = 'suggested'", [$id]);
            echo json_encode(['success' => true, 'message' => 'Products approved']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function rejectProducts(int $id, array $input): void
    {
        try {
            $ids = $input['wc_product_ids'] ?? [];
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                Database::execute("UPDATE edit_products SET status = 'rejected' WHERE edit_suggestion_id = ? AND wc_product_id IN ({$ph})", array_merge([$id], $ids));
            }
            echo json_encode(['success' => true, 'message' => 'Products rejected']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function updateRules(int $id, array $input): void
    {
        try {
            $rules = [
                'categories' => $input['categories'] ?? [], 
                'keywords' => $input['keywords'] ?? [], 
                'colors' => $input['colors'] ?? [], 
                'exclude_categories' => $input['exclude_categories'] ?? []
            ];
            
            // Cast to int - handles empty string, false, null, etc.
            $autoRegenerate = !empty($input['auto_regenerate']) ? 1 : 0;
            
            Database::execute(
                "UPDATE edit_suggestions SET matching_rules = ?, auto_regenerate = ? WHERE id = ?", 
                [json_encode($rules), $autoRegenerate, $id]
            );
            echo json_encode(['success' => true, 'message' => 'Rules updated']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function createInWordPress(int $id): void
    {
        try {
            $edit = Database::queryOne("SELECT * FROM edit_suggestions WHERE id = ?", [$id]);
            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Not found']]);
                return;
            }
            if ($edit['wp_term_id']) {
                echo json_encode(['success' => true, 'message' => 'Already exists']);
                return;
            }

            $service = new WordPressService($this->config);
            $termId = $service->createEditTerm($edit['name'], $edit['slug']);
            if (!$termId) {
                throw new \Exception('Failed to create in WordPress');
            }

            Database::execute("UPDATE edit_suggestions SET wp_term_id = ?, status = 'created' WHERE id = ?", [$termId, $id]);
            Database::execute("INSERT INTO wp_edits (wp_term_id, name, slug, synced_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE name = VALUES(name)", [$termId, $edit['name'], $edit['slug']]);

            echo json_encode(['success' => true, 'message' => 'Created in WordPress']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function syncToWordPress(int $id): void
    {
        try {
            $edit = Database::queryOne("SELECT * FROM edit_suggestions WHERE id = ?", [$id]);
            if (!$edit || !$edit['wp_term_id']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not created in WordPress yet. Click "Create in WP" first.']]);
                return;
            }

            $service = new WordPressService($this->config);
            
            // Get products to sync - both 'approved' AND 'pending' (auto-approve pending on sync)
            $toAdd = Database::query(
                "SELECT * FROM edit_products WHERE edit_suggestion_id = ? AND status IN ('approved', 'pending') AND synced_to_wp = 0", 
                [$id]
            );
            
            if (empty($toAdd)) {
                echo json_encode([
                    'success' => true, 
                    'data' => ['added' => 0, 'total' => 0],
                    'message' => 'No products to sync'
                ]);
                return;
            }

            $added = 0;
            $failed = 0;
            $errorMessages = [];
            
            foreach ($toAdd as $p) {
                try {
                    $result = $service->assignProductToEdit($p['wc_product_id'], $edit['wp_term_id']);
                    if ($result) {
                        Database::execute(
                            "UPDATE edit_products SET synced_to_wp = 1, status = 'synced', synced_at = NOW() WHERE id = ?", 
                            [$p['id']]
                        );
                        $added++;
                    } else {
                        $failed++;
                        $errorMessages[] = "Product {$p['wc_product_id']}: Unknown error";
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $errorMessages[] = "Product {$p['wc_product_id']}: " . $e->getMessage();
                    error_log("Sync product {$p['wc_product_id']} to edit {$edit['wp_term_id']} failed: " . $e->getMessage());
                }
            }

            if ($added > 0) {
                Database::execute("UPDATE edit_suggestions SET status = 'active', wp_synced_at = NOW() WHERE id = ?", [$id]);
            }

            $response = [
                'success' => true, 
                'data' => [
                    'added' => $added, 
                    'failed' => $failed,
                    'total' => count($toAdd)
                ]
            ];
            
            if (!empty($errorMessages)) {
                $response['data']['errors'] = array_slice($errorMessages, 0, 5); // First 5 errors
            }
            
            echo json_encode($response);
            
        } catch (\Throwable $e) {
            error_log("syncToWordPress error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => 'Sync failed: ' . $e->getMessage()]]);
        }
    }

    public function create(array $input): void
    {
        try {
            if (!$this->tablesExist()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'Run SQL migration first']]);
                return;
            }
            $name = $input['name'] ?? '';
            if (!$name) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'Name required']]);
                return;
            }
            $slug = $this->slugify($name);
            $rules = ['categories' => $input['categories'] ?? [], 'keywords' => $input['keywords'] ?? [], 'colors' => $input['colors'] ?? []];
            $id = Database::insert("INSERT INTO edit_suggestions (name, slug, description, source_type, matching_rules, status) VALUES (?, ?, ?, 'manual', ?, 'suggested')", [$name, $slug, $input['description'] ?? '', json_encode($rules)]);
            echo json_encode(['success' => true, 'data' => ['id' => $id]]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function getCategories(): void
    {
        try {
            $cats = Database::query("SELECT slug, name FROM wp_product_categories WHERE count > 0 ORDER BY name");
            echo json_encode(['success' => true, 'data' => $cats]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function searchProducts(array $input): void
    {
        try {
            $q = $input['query'] ?? '';
            if (strlen($q) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                return;
            }
            $products = Database::query("SELECT wc_product_id, title, price, image_url, stock_status, brand_name FROM wp_products WHERE title LIKE ? LIMIT 50", ["%{$q}%"]);
            echo json_encode(['success' => true, 'data' => $products]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function addProduct(int $id, array $input): void
    {
        try {
            $wcId = $input['wc_product_id'] ?? null;
            if (!$wcId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'Product ID required']]);
                return;
            }
            Database::execute("INSERT INTO edit_products (edit_suggestion_id, wc_product_id, assignment_type, status, match_score) VALUES (?, ?, 'manual', 'approved', 100) ON DUPLICATE KEY UPDATE status = 'approved'", [$id, $wcId]);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    public function delete(int $id): void
    {
        try {
            Database::execute("DELETE FROM edit_products WHERE edit_suggestion_id = ?", [$id]);
            Database::execute("DELETE FROM edit_suggestions WHERE id = ?", [$id]);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    private function findMatchingProducts(array $rules, int $limit): array
    {
        try {
            $categories = is_array($rules['categories'] ?? null) ? $rules['categories'] : [];
            $keywords = is_array($rules['keywords'] ?? null) ? $rules['keywords'] : [];
            $colors = is_array($rules['colors'] ?? null) ? $rules['colors'] : [];
            $excludeCategories = is_array($rules['exclude_categories'] ?? null) ? $rules['exclude_categories'] : [];

            $products = Database::query("SELECT wc_product_id, title, price, image_url, stock_status, brand_name, permalink, category_slugs, description FROM wp_products WHERE stock_status = 'instock' LIMIT 500");

            if (empty($products)) {
                return [];
            }

            $matched = [];
            foreach ($products as $p) {
                $score = 0;
                $reasons = [];
                
                // Parse category slugs safely
                $pCats = [];
                if (!empty($p['category_slugs'])) {
                    $decoded = json_decode($p['category_slugs'], true);
                    $pCats = is_array($decoded) ? $decoded : [];
                }
                
                // Check exclusions first
                $excluded = false;
                foreach ($excludeCategories as $exc) {
                    if (empty($exc)) continue;
                    foreach ($pCats as $pc) {
                        if (stripos($pc, $exc) !== false) {
                            $excluded = true;
                            break 2;
                        }
                    }
                }
                if ($excluded) continue;
                
                $text = strtolower(($p['title'] ?? '') . ' ' . ($p['description'] ?? ''));

                foreach ($categories as $c) {
                    if (empty($c)) continue;
                    foreach ($pCats as $pc) {
                        if (stripos($pc, $c) !== false) {
                            $score += 30;
                            $reasons[] = "cat:{$c}";
                            break;
                        }
                    }
                }
                foreach ($keywords as $k) {
                    if (empty($k)) continue;
                    if (stripos($text, $k) !== false) {
                        $score += 10;
                        $reasons[] = "kw:{$k}";
                    }
                }
                foreach ($colors as $c) {
                    if (empty($c)) continue;
                    if (stripos($text, $c) !== false) {
                        $score += 5;
                        $reasons[] = "col:{$c}";
                    }
                }

                if ($score > 0) {
                    $p['match_score'] = min(100, $score);
                    $p['match_reasons'] = $reasons;
                    $matched[] = $p;
                }
            }

            usort($matched, fn($a, $b) => ($b['match_score'] ?? 0) - ($a['match_score'] ?? 0));
            return array_slice($matched, 0, $limit);
        } catch (\Throwable $e) {
            error_log("findMatchingProducts error: " . $e->getMessage());
            return [];
        }
    }

    private function slugify(string $text): string
    {
        $s = strtolower($text);
        $s = str_replace("'", '', $s);
        $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
        $s = preg_replace('/[\s-]+/', '-', $s);
        return trim($s, '-');
    }
}
