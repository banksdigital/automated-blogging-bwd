<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\WordPressService;

/**
 * EditSuggestionController
 * 
 * Manages edit suggestions, product matching, and WordPress sync
 */
class EditSuggestionController
{
    private array $config;
    
    // Default edit templates for different occasions
    private array $editTemplates = [
        // Calendar/Event-based
        [
            'name' => "Valentine's Day Gifting",
            'source_type' => 'calendar',
            'event_keywords' => ['valentine'],
            'description' => 'Curated gifts and styles perfect for Valentine\'s Day',
            'rules' => [
                'categories' => ['dresses', 'accessories', 'jewellery', 'knitwear', 'bags', 'tops'],
                'keywords' => ['gift', 'love', 'heart', 'romantic', 'date', 'evening', 'silk', 'satin'],
                'colors' => ['red', 'pink', 'burgundy', 'blush', 'rose'],
                'exclude_categories' => ['loungewear', 'sportswear', 'swimwear']
            ]
        ],
        [
            'name' => "Mother's Day Gifts",
            'source_type' => 'calendar',
            'event_keywords' => ['mother'],
            'description' => 'Thoughtful gifts she\'ll treasure',
            'rules' => [
                'categories' => ['accessories', 'jewellery', 'bags', 'scarves', 'knitwear', 'blouses'],
                'keywords' => ['gift', 'elegant', 'timeless', 'classic', 'luxe', 'cashmere', 'silk'],
                'colors' => [],
                'exclude_categories' => ['sportswear', 'swimwear']
            ]
        ],
        [
            'name' => 'Christmas Gift Guide',
            'source_type' => 'calendar',
            'event_keywords' => ['christmas'],
            'description' => 'Luxurious gifts for the festive season',
            'rules' => [
                'categories' => ['accessories', 'jewellery', 'bags', 'scarves', 'knitwear', 'coats'],
                'keywords' => ['gift', 'christmas', 'festive', 'cosy', 'luxe', 'cashmere', 'wool'],
                'colors' => ['red', 'green', 'gold', 'silver', 'cream', 'burgundy'],
                'exclude_categories' => ['swimwear']
            ]
        ],
        [
            'name' => 'Party Season Edit',
            'source_type' => 'calendar',
            'event_keywords' => ['christmas', 'new year', 'party'],
            'description' => 'Standout pieces for festive celebrations',
            'rules' => [
                'categories' => ['dresses', 'tops', 'skirts', 'heels', 'clutch', 'jewellery'],
                'keywords' => ['party', 'evening', 'cocktail', 'sequin', 'sparkle', 'festive', 'glitter', 'velvet', 'satin'],
                'colors' => ['black', 'gold', 'silver', 'red', 'metallic', 'emerald'],
                'exclude_categories' => ['loungewear', 'sportswear']
            ]
        ],
        [
            'name' => "New Year's Eve",
            'source_type' => 'calendar',
            'event_keywords' => ['new year'],
            'description' => 'Ring in the new year in style',
            'rules' => [
                'categories' => ['dresses', 'jumpsuits', 'tops', 'heels', 'jewellery', 'clutch'],
                'keywords' => ['party', 'evening', 'sequin', 'sparkle', 'metallic', 'glamour'],
                'colors' => ['black', 'gold', 'silver', 'champagne'],
                'exclude_categories' => ['loungewear', 'sportswear', 'swimwear']
            ]
        ],
        [
            'name' => 'Easter Dressing',
            'source_type' => 'calendar',
            'event_keywords' => ['easter'],
            'description' => 'Fresh spring looks for Easter celebrations',
            'rules' => [
                'categories' => ['dresses', 'blouses', 'skirts', 'blazers', 'accessories'],
                'keywords' => ['spring', 'floral', 'pastel', 'elegant', 'brunch'],
                'colors' => ['pastel', 'cream', 'yellow', 'lavender', 'sage', 'pink', 'floral'],
                'exclude_categories' => ['swimwear', 'sportswear']
            ]
        ],
        
        // Seasonal
        [
            'name' => 'Spring Edit',
            'source_type' => 'seasonal',
            'description' => 'Fresh styles for the new season',
            'rules' => [
                'categories' => ['dresses', 'blouses', 'skirts', 'trench', 'loafers', 'blazers'],
                'keywords' => ['spring', 'floral', 'fresh', 'light', 'pastel', 'cotton', 'linen'],
                'colors' => ['pastel', 'floral', 'cream', 'sage', 'lavender', 'pink', 'white'],
                'exclude_categories' => ['coats', 'knitwear', 'boots']
            ]
        ],
        [
            'name' => 'Summer Edit',
            'source_type' => 'seasonal',
            'description' => 'Light and effortless summer styles',
            'rules' => [
                'categories' => ['dresses', 'shorts', 'sandals', 'linen', 'sunglasses', 'swimwear', 'tops'],
                'keywords' => ['summer', 'light', 'linen', 'cotton', 'floral', 'bright', 'beach', 'holiday'],
                'colors' => ['white', 'cream', 'coral', 'yellow', 'turquoise', 'blue', 'orange'],
                'exclude_categories' => ['coats', 'knitwear', 'boots', 'wool']
            ]
        ],
        [
            'name' => 'Autumn Edit',
            'source_type' => 'seasonal',
            'description' => 'Cosy layers and rich tones for autumn',
            'rules' => [
                'categories' => ['knitwear', 'coats', 'boots', 'jeans', 'blazers', 'scarves'],
                'keywords' => ['autumn', 'fall', 'cosy', 'layering', 'knit', 'wool', 'leather'],
                'colors' => ['burgundy', 'rust', 'camel', 'brown', 'olive', 'mustard', 'tan'],
                'exclude_categories' => ['swimwear', 'sandals', 'shorts']
            ]
        ],
        [
            'name' => 'Winter Edit',
            'source_type' => 'seasonal',
            'description' => 'Warm and stylish winter essentials',
            'rules' => [
                'categories' => ['coats', 'knitwear', 'boots', 'scarves', 'wool', 'jackets'],
                'keywords' => ['winter', 'warm', 'cosy', 'wool', 'cashmere', 'chunky', 'layer'],
                'colors' => ['black', 'grey', 'cream', 'camel', 'navy', 'charcoal'],
                'exclude_categories' => ['swimwear', 'sandals', 'shorts', 'linen']
            ]
        ],
        
        // Occasion-based
        [
            'name' => 'Wedding Guest',
            'source_type' => 'occasion',
            'description' => 'Elegant styles for wedding celebrations',
            'rules' => [
                'categories' => ['dresses', 'jumpsuits', 'accessories', 'heels', 'bags', 'fascinators'],
                'keywords' => ['wedding', 'guest', 'occasion', 'elegant', 'formal', 'midi', 'maxi', 'cocktail'],
                'colors' => ['pastel', 'floral', 'neutral', 'blush', 'sage', 'blue', 'coral', 'green'],
                'exclude_categories' => ['white', 'cream', 'bridal', 'loungewear', 'sportswear'],
                'exclude_keywords' => ['white', 'bridal', 'wedding dress']
            ]
        ],
        [
            'name' => 'Holiday Packing',
            'source_type' => 'occasion',
            'description' => 'Everything you need for your getaway',
            'rules' => [
                'categories' => ['dresses', 'swimwear', 'sandals', 'shorts', 'linen', 'sunglasses', 'bags'],
                'keywords' => ['holiday', 'vacation', 'summer', 'beach', 'resort', 'linen', 'cotton', 'swim', 'kaftan'],
                'colors' => [],
                'exclude_categories' => ['coats', 'knitwear', 'boots', 'wool']
            ]
        ],
        [
            'name' => 'Workwear Edit',
            'source_type' => 'occasion',
            'description' => 'Polished pieces for the modern professional',
            'rules' => [
                'categories' => ['blazers', 'trousers', 'blouses', 'shirts', 'loafers', 'dresses', 'skirts'],
                'keywords' => ['work', 'office', 'professional', 'tailored', 'smart', 'business', 'structured'],
                'colors' => ['black', 'navy', 'white', 'cream', 'grey', 'camel'],
                'exclude_categories' => ['swimwear', 'sportswear', 'loungewear']
            ]
        ],
        [
            'name' => 'Date Night',
            'source_type' => 'occasion',
            'description' => 'Romantic styles for special evenings',
            'rules' => [
                'categories' => ['dresses', 'tops', 'skirts', 'heels', 'jewellery', 'bags'],
                'keywords' => ['date', 'evening', 'romantic', 'elegant', 'sexy', 'dinner', 'silk', 'satin'],
                'colors' => ['black', 'red', 'burgundy', 'blush'],
                'exclude_categories' => ['loungewear', 'sportswear', 'swimwear']
            ]
        ],
        [
            'name' => 'Weekend Casual',
            'source_type' => 'occasion',
            'description' => 'Relaxed styles for off-duty days',
            'rules' => [
                'categories' => ['jeans', 'trainers', 't-shirts', 'sweatshirts', 'hoodies', 'knits', 'sneakers'],
                'keywords' => ['casual', 'relaxed', 'comfortable', 'weekend', 'easy', 'everyday', 'cotton'],
                'colors' => [],
                'exclude_categories' => ['formal', 'evening', 'heels']
            ]
        ],
        [
            'name' => 'Brunch Edit',
            'source_type' => 'occasion',
            'description' => 'Effortlessly chic for weekend brunches',
            'rules' => [
                'categories' => ['dresses', 'blouses', 'skirts', 'jeans', 'loafers', 'sandals'],
                'keywords' => ['brunch', 'weekend', 'casual', 'chic', 'day', 'relaxed', 'midi'],
                'colors' => [],
                'exclude_categories' => ['formal', 'evening', 'sportswear']
            ]
        ],
        [
            'name' => 'Evening Out',
            'source_type' => 'occasion',
            'description' => 'Sophisticated styles for evening events',
            'rules' => [
                'categories' => ['dresses', 'jumpsuits', 'tops', 'heels', 'clutch', 'jewellery'],
                'keywords' => ['evening', 'dinner', 'cocktail', 'elegant', 'sophisticated', 'silk', 'satin'],
                'colors' => ['black', 'navy', 'emerald', 'burgundy'],
                'exclude_categories' => ['loungewear', 'sportswear', 'swimwear', 'casual']
            ]
        ],
        
        // Category-focused
        [
            'name' => 'The Denim Edit',
            'source_type' => 'category',
            'description' => 'Our curated collection of premium denim',
            'rules' => [
                'categories' => ['jeans', 'denim'],
                'keywords' => ['denim', 'jeans', 'jean'],
                'colors' => [],
                'exclude_categories' => []
            ]
        ],
        [
            'name' => 'The Knitwear Edit',
            'source_type' => 'category',
            'description' => 'Luxurious knits for layering',
            'rules' => [
                'categories' => ['knitwear', 'jumpers', 'cardigans', 'sweaters'],
                'keywords' => ['knit', 'cashmere', 'wool', 'jumper', 'cardigan', 'sweater', 'merino'],
                'colors' => [],
                'exclude_categories' => []
            ]
        ],
        [
            'name' => 'The Blazer Edit',
            'source_type' => 'category',
            'description' => 'Timeless blazers for every occasion',
            'rules' => [
                'categories' => ['blazers', 'jackets'],
                'keywords' => ['blazer', 'tailored', 'structured', 'jacket'],
                'colors' => [],
                'exclude_categories' => ['leather jacket', 'bomber']
            ]
        ],
        [
            'name' => 'The Dress Edit',
            'source_type' => 'category',
            'description' => 'Dresses for every moment',
            'rules' => [
                'categories' => ['dresses'],
                'keywords' => ['dress', 'midi', 'maxi', 'mini'],
                'colors' => [],
                'exclude_categories' => []
            ]
        ],
        [
            'name' => 'The Accessories Edit',
            'source_type' => 'category',
            'description' => 'Finishing touches that make the outfit',
            'rules' => [
                'categories' => ['accessories', 'bags', 'jewellery', 'scarves', 'belts', 'hats'],
                'keywords' => ['accessory', 'bag', 'jewellery', 'scarf', 'belt'],
                'colors' => [],
                'exclude_categories' => []
            ]
        ],
        [
            'name' => 'The Coat Edit',
            'source_type' => 'category',
            'description' => 'Investment outerwear for every season',
            'rules' => [
                'categories' => ['coats', 'outerwear', 'jackets'],
                'keywords' => ['coat', 'trench', 'wool coat', 'overcoat', 'parka'],
                'colors' => [],
                'exclude_categories' => ['blazers']
            ]
        ]
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * List all edit suggestions with stats
     */
    public function index(): void
    {
        try {
            $edits = Database::query(
                "SELECT es.*,
                        (SELECT COUNT(*) FROM edit_products ep WHERE ep.edit_suggestion_id = es.id AND ep.status != 'rejected') as total_products,
                        (SELECT COUNT(*) FROM edit_products ep 
                         JOIN wp_products p ON ep.wc_product_id = p.wc_product_id 
                         WHERE ep.edit_suggestion_id = es.id AND ep.status != 'rejected' AND p.stock_status = 'instock') as in_stock_products,
                        (SELECT COUNT(*) FROM edit_products ep WHERE ep.edit_suggestion_id = es.id AND ep.status = 'pending') as pending_products,
                        (SELECT COUNT(*) FROM edit_products ep WHERE ep.edit_suggestion_id = es.id AND ep.synced_to_wp = 1) as synced_products
                 FROM edit_suggestions es
                 ORDER BY 
                    FIELD(es.status, 'active', 'created', 'approved', 'suggested', 'archived'),
                    es.name ASC"
            );
            
            // Decode JSON fields
            foreach ($edits as &$edit) {
                $edit['matching_rules'] = json_decode($edit['matching_rules'] ?? '{}', true);
            }

            echo json_encode([
                'success' => true,
                'data' => $edits
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Get single edit with full product list
     */
    public function show(int $id): void
    {
        try {
            $edit = Database::queryOne(
                "SELECT * FROM edit_suggestions WHERE id = ?",
                [$id]
            );

            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not found']]);
                return;
            }

            $edit['matching_rules'] = json_decode($edit['matching_rules'] ?? '{}', true);

            // Get products with full details
            $products = Database::query(
                "SELECT ep.*, p.title, p.price, p.image_url, p.stock_status, p.brand_name, 
                        p.permalink, p.category_names
                 FROM edit_products ep
                 JOIN wp_products p ON ep.wc_product_id = p.wc_product_id
                 WHERE ep.edit_suggestion_id = ?
                 ORDER BY ep.status ASC, ep.match_score DESC",
                [$id]
            );

            foreach ($products as &$product) {
                $product['match_reasons'] = json_decode($product['match_reasons'] ?? '[]', true);
            }

            $edit['products'] = $products;
            $edit['stats'] = [
                'total' => count($products),
                'in_stock' => count(array_filter($products, fn($p) => $p['stock_status'] === 'instock')),
                'out_of_stock' => count(array_filter($products, fn($p) => $p['stock_status'] !== 'instock')),
                'pending' => count(array_filter($products, fn($p) => $p['status'] === 'pending')),
                'approved' => count(array_filter($products, fn($p) => $p['status'] === 'approved')),
                'synced' => count(array_filter($products, fn($p) => $p['synced_to_wp']))
            ];

            echo json_encode([
                'success' => true,
                'data' => $edit
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Generate edit suggestions from templates and calendar
     */
    public function generateSuggestions(): void
    {
        try {
            $created = 0;
            $skipped = 0;

            // Get upcoming calendar events for context
            $events = Database::query(
                "SELECT * FROM seasonal_events 
                 WHERE start_date >= CURDATE() AND is_active = 1
                 ORDER BY start_date ASC"
            );

            foreach ($this->editTemplates as $template) {
                // Check if edit already exists
                $slug = $this->slugify($template['name']);
                $existing = Database::queryOne(
                    "SELECT id FROM edit_suggestions WHERE slug = ?",
                    [$slug]
                );

                if ($existing) {
                    $skipped++;
                    continue;
                }

                // For calendar-based edits, link to event if found
                $eventId = null;
                if ($template['source_type'] === 'calendar' && !empty($template['event_keywords'])) {
                    foreach ($events as $event) {
                        foreach ($template['event_keywords'] as $keyword) {
                            if (stripos($event['name'], $keyword) !== false) {
                                $eventId = $event['id'];
                                break 2;
                            }
                        }
                    }
                }

                Database::insert(
                    "INSERT INTO edit_suggestions (name, slug, description, source_type, source_event_id, matching_rules, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'suggested')",
                    [
                        $template['name'],
                        $slug,
                        $template['description'] ?? '',
                        $template['source_type'],
                        $eventId,
                        json_encode($template['rules'])
                    ]
                );
                $created++;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'created' => $created,
                    'skipped' => $skipped
                ],
                'message' => "Generated {$created} new edit suggestions ({$skipped} already existed)"
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Preview products that would match an edit's rules (without saving)
     */
    public function previewProducts(int $id): void
    {
        try {
            $edit = Database::queryOne(
                "SELECT * FROM edit_suggestions WHERE id = ?",
                [$id]
            );

            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not found']]);
                return;
            }

            $rules = json_decode($edit['matching_rules'] ?? '{}', true);
            $products = $this->findMatchingProducts($rules, $edit['max_products'] ?? 100);

            echo json_encode([
                'success' => true,
                'data' => [
                    'edit' => [
                        'id' => $edit['id'],
                        'name' => $edit['name'],
                        'matching_rules' => $rules
                    ],
                    'products' => $products,
                    'stats' => [
                        'total' => count($products),
                        'in_stock' => count(array_filter($products, fn($p) => $p['stock_status'] === 'instock'))
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Regenerate products for an edit using its matching rules
     */
    public function regenerateProducts(int $id): void
    {
        try {
            $edit = Database::queryOne(
                "SELECT * FROM edit_suggestions WHERE id = ?",
                [$id]
            );

            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not found']]);
                return;
            }

            $rules = json_decode($edit['matching_rules'] ?? '{}', true);
            $maxProducts = $edit['max_products'] ?? 100;

            // Get currently assigned products
            $currentProducts = Database::query(
                "SELECT wc_product_id, assignment_type, status FROM edit_products 
                 WHERE edit_suggestion_id = ?",
                [$id]
            );
            $currentMap = [];
            foreach ($currentProducts as $p) {
                $currentMap[$p['wc_product_id']] = $p;
            }
            
            // Find matching products
            $matchedProducts = $this->findMatchingProducts($rules, $maxProducts);
            $matchedIds = array_column($matchedProducts, 'wc_product_id');

            $added = 0;
            $updated = 0;
            $toRemove = [];

            // Add/update matched products
            foreach ($matchedProducts as $product) {
                $existing = $currentMap[$product['wc_product_id']] ?? null;
                
                if (!$existing) {
                    // New product
                    Database::insert(
                        "INSERT INTO edit_products (edit_suggestion_id, wc_product_id, assignment_type, match_score, match_reasons, status)
                         VALUES (?, ?, 'auto', ?, ?, 'pending')",
                        [
                            $id,
                            $product['wc_product_id'],
                            $product['match_score'],
                            json_encode($product['match_reasons'])
                        ]
                    );
                    $added++;
                } else {
                    // Update existing
                    Database::execute(
                        "UPDATE edit_products SET match_score = ?, match_reasons = ? 
                         WHERE edit_suggestion_id = ? AND wc_product_id = ?",
                        [
                            $product['match_score'],
                            json_encode($product['match_reasons']),
                            $id,
                            $product['wc_product_id']
                        ]
                    );
                    $updated++;
                }
            }

            // Find products to remove (were assigned but no longer match)
            foreach ($currentProducts as $current) {
                if (!in_array($current['wc_product_id'], $matchedIds)) {
                    // Only auto-remove if it was auto-assigned
                    if ($current['assignment_type'] === 'auto') {
                        $toRemove[] = $current['wc_product_id'];
                    }
                }
            }

            // Mark removed products
            $removed = 0;
            if (!empty($toRemove)) {
                foreach ($toRemove as $wcProductId) {
                    Database::execute(
                        "UPDATE edit_products SET status = 'rejected' 
                         WHERE edit_suggestion_id = ? AND wc_product_id = ? AND assignment_type = 'auto'",
                        [$id, $wcProductId]
                    );
                    $removed++;
                }
            }

            // Update stats
            Database::execute(
                "UPDATE edit_suggestions SET last_regenerated_at = NOW(), product_count = ?, in_stock_count = ? WHERE id = ?",
                [
                    count($matchedProducts),
                    count(array_filter($matchedProducts, fn($p) => $p['stock_status'] === 'instock')),
                    $id
                ]
            );

            echo json_encode([
                'success' => true,
                'data' => [
                    'added' => $added,
                    'updated' => $updated,
                    'removed' => $removed,
                    'total' => count($matchedProducts)
                ],
                'message' => "Regenerated: {$added} added, {$updated} updated, {$removed} removed"
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Update matching rules for an edit
     */
    public function updateRules(int $id, array $input): void
    {
        try {
            $edit = Database::queryOne("SELECT * FROM edit_suggestions WHERE id = ?", [$id]);

            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not found']]);
                return;
            }

            $rules = [
                'categories' => $input['categories'] ?? [],
                'keywords' => $input['keywords'] ?? [],
                'colors' => $input['colors'] ?? [],
                'price_min' => $input['price_min'] ?? null,
                'price_max' => $input['price_max'] ?? null,
                'exclude_categories' => $input['exclude_categories'] ?? [],
                'exclude_keywords' => $input['exclude_keywords'] ?? []
            ];

            Database::execute(
                "UPDATE edit_suggestions SET 
                    name = COALESCE(?, name),
                    description = COALESCE(?, description),
                    matching_rules = ?,
                    auto_regenerate = ?,
                    regenerate_frequency = ?,
                    min_products = ?,
                    max_products = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $input['name'] ?? null,
                    $input['description'] ?? null,
                    json_encode($rules),
                    $input['auto_regenerate'] ?? false,
                    $input['regenerate_frequency'] ?? 'monthly',
                    $input['min_products'] ?? 10,
                    $input['max_products'] ?? 100,
                    $id
                ]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Edit rules updated'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Approve products (move from pending to approved)
     */
    public function approveProducts(int $id, array $input): void
    {
        try {
            $productIds = $input['wc_product_ids'] ?? [];
            $approveAll = $input['approve_all'] ?? false;

            if ($approveAll) {
                Database::execute(
                    "UPDATE edit_products SET status = 'approved' WHERE edit_suggestion_id = ? AND status = 'pending'",
                    [$id]
                );
                $count = Database::queryOne(
                    "SELECT ROW_COUNT() as cnt"
                )['cnt'] ?? 0;
            } else if (!empty($productIds)) {
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                Database::execute(
                    "UPDATE edit_products SET status = 'approved' 
                     WHERE edit_suggestion_id = ? AND wc_product_id IN ({$placeholders})",
                    array_merge([$id], $productIds)
                );
                $count = count($productIds);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'No products specified']]);
                return;
            }

            // Update edit status if still suggested
            Database::execute(
                "UPDATE edit_suggestions SET status = 'approved' WHERE id = ? AND status = 'suggested'",
                [$id]
            );

            echo json_encode([
                'success' => true,
                'data' => ['approved' => $count],
                'message' => "{$count} products approved"
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Reject/remove products
     */
    public function rejectProducts(int $id, array $input): void
    {
        try {
            $productIds = $input['wc_product_ids'] ?? [];

            if (empty($productIds)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'No products specified']]);
                return;
            }

            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            Database::execute(
                "UPDATE edit_products SET status = 'rejected' 
                 WHERE edit_suggestion_id = ? AND wc_product_id IN ({$placeholders})",
                array_merge([$id], $productIds)
            );

            echo json_encode([
                'success' => true,
                'message' => count($productIds) . ' products removed'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Manually add a product to an edit
     */
    public function addProduct(int $id, array $input): void
    {
        try {
            $wcProductId = $input['wc_product_id'] ?? null;

            if (!$wcProductId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'Product ID required']]);
                return;
            }

            Database::execute(
                "INSERT INTO edit_products (edit_suggestion_id, wc_product_id, assignment_type, status, match_score, match_reasons)
                 VALUES (?, ?, 'manual', 'approved', 100, ?)
                 ON DUPLICATE KEY UPDATE status = 'approved', assignment_type = 'manual'",
                [$id, $wcProductId, json_encode(['Manually added'])]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Product added'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Create edit in WordPress
     */
    public function createInWordPress(int $id): void
    {
        try {
            $edit = Database::queryOne("SELECT * FROM edit_suggestions WHERE id = ?", [$id]);

            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not found']]);
                return;
            }

            if ($edit['wp_term_id']) {
                echo json_encode([
                    'success' => true,
                    'data' => ['wp_term_id' => $edit['wp_term_id']],
                    'message' => 'Edit already exists in WordPress'
                ]);
                return;
            }

            $service = new WordPressService($this->config);
            $termId = $service->createEditTerm($edit['name'], $edit['slug']);

            if (!$termId) {
                throw new \Exception('Failed to create edit term in WordPress');
            }

            // Update local record
            Database::execute(
                "UPDATE edit_suggestions SET wp_term_id = ?, status = 'created', wp_synced_at = NOW() WHERE id = ?",
                [$termId, $id]
            );

            // Also create in wp_edits table for SEO management
            Database::execute(
                "INSERT INTO wp_edits (wp_term_id, name, slug, synced_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), synced_at = NOW()",
                [$termId, $edit['name'], $edit['slug']]
            );

            echo json_encode([
                'success' => true,
                'data' => ['wp_term_id' => $termId],
                'message' => "Edit '{$edit['name']}' created in WordPress"
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Sync products to WordPress (assign edit taxonomy to products)
     */
    public function syncToWordPress(int $id): void
    {
        try {
            $edit = Database::queryOne("SELECT * FROM edit_suggestions WHERE id = ?", [$id]);

            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not found']]);
                return;
            }

            if (!$edit['wp_term_id']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not created in WordPress yet']]);
                return;
            }

            $service = new WordPressService($this->config);

            // Get approved products to add
            $toAdd = Database::query(
                "SELECT * FROM edit_products 
                 WHERE edit_suggestion_id = ? AND status = 'approved' AND synced_to_wp = 0",
                [$id]
            );

            // Get rejected products that were previously synced (need to remove)
            $toRemove = Database::query(
                "SELECT * FROM edit_products 
                 WHERE edit_suggestion_id = ? AND status = 'rejected' AND synced_to_wp = 1",
                [$id]
            );

            $added = 0;
            $removed = 0;
            $errors = [];

            // Add products
            foreach ($toAdd as $product) {
                try {
                    $success = $service->assignProductToEdit($product['wc_product_id'], $edit['wp_term_id']);
                    if ($success) {
                        Database::execute(
                            "UPDATE edit_products SET synced_to_wp = 1, synced_at = NOW(), status = 'synced' WHERE id = ?",
                            [$product['id']]
                        );
                        $added++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Product {$product['wc_product_id']}: " . $e->getMessage();
                }
            }

            // Remove products
            foreach ($toRemove as $product) {
                try {
                    $success = $service->removeProductFromEdit($product['wc_product_id'], $edit['wp_term_id']);
                    if ($success) {
                        Database::execute(
                            "UPDATE edit_products SET synced_to_wp = 0, synced_at = NOW() WHERE id = ?",
                            [$product['id']]
                        );
                        $removed++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Remove {$product['wc_product_id']}: " . $e->getMessage();
                }
            }

            // Update edit status
            if ($added > 0) {
                Database::execute(
                    "UPDATE edit_suggestions SET status = 'active', wp_synced_at = NOW() WHERE id = ?",
                    [$id]
                );
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'added' => $added,
                    'removed' => $removed,
                    'errors' => $errors
                ],
                'message' => "Synced: {$added} added, {$removed} removed" . (count($errors) > 0 ? " ({$errors} errors)" : "")
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Create a new custom edit
     */
    public function create(array $input): void
    {
        try {
            $name = $input['name'] ?? '';
            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'Name is required']]);
                return;
            }

            $slug = $this->slugify($name);

            $existing = Database::queryOne("SELECT id FROM edit_suggestions WHERE slug = ?", [$slug]);
            if ($existing) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit with this name already exists']]);
                return;
            }

            $rules = [
                'categories' => $input['categories'] ?? [],
                'keywords' => $input['keywords'] ?? [],
                'colors' => $input['colors'] ?? [],
                'exclude_categories' => $input['exclude_categories'] ?? []
            ];

            $id = Database::insert(
                "INSERT INTO edit_suggestions (name, slug, description, source_type, matching_rules, status)
                 VALUES (?, ?, ?, 'manual', ?, 'suggested')",
                [$name, $slug, $input['description'] ?? '', json_encode($rules)]
            );

            echo json_encode([
                'success' => true,
                'data' => ['id' => $id],
                'message' => "Edit '{$name}' created"
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Get available categories for rule building
     */
    public function getCategories(): void
    {
        try {
            $categories = Database::query(
                "SELECT DISTINCT slug, name, count 
                 FROM wp_product_categories 
                 WHERE count > 0 
                 ORDER BY name ASC"
            );

            echo json_encode([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Search products for manual adding
     */
    public function searchProducts(array $input): void
    {
        try {
            $query = $input['query'] ?? '';
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                return;
            }

            $products = Database::query(
                "SELECT wc_product_id, title, price, image_url, stock_status, brand_name, permalink
                 FROM wp_products 
                 WHERE (title LIKE ? OR brand_name LIKE ?)
                 AND stock_status = 'instock'
                 ORDER BY title ASC
                 LIMIT 50",
                ["%{$query}%", "%{$query}%"]
            );

            echo json_encode([
                'success' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Delete an edit suggestion
     */
    public function delete(int $id): void
    {
        try {
            $edit = Database::queryOne("SELECT * FROM edit_suggestions WHERE id = ?", [$id]);

            if (!$edit) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Edit not found']]);
                return;
            }

            // Don't allow deleting if active in WordPress
            if ($edit['status'] === 'active' && $edit['wp_term_id']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ['message' => 'Cannot delete active edit. Archive it first.']]);
                return;
            }

            // Delete products first (cascade should handle this but being explicit)
            Database::execute("DELETE FROM edit_products WHERE edit_suggestion_id = ?", [$id]);
            Database::execute("DELETE FROM edit_suggestions WHERE id = ?", [$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Edit deleted'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }

    /**
     * Find products matching the given rules
     */
    private function findMatchingProducts(array $rules, int $limit = 100): array
    {
        $categories = $rules['categories'] ?? [];
        $keywords = $rules['keywords'] ?? [];
        $colors = $rules['colors'] ?? [];
        $excludeCategories = $rules['exclude_categories'] ?? [];
        $excludeKeywords = $rules['exclude_keywords'] ?? [];
        $priceMin = $rules['price_min'] ?? null;
        $priceMax = $rules['price_max'] ?? null;

        // Base query - only in-stock products
        $sql = "SELECT p.wc_product_id, p.title, p.price, p.image_url, p.stock_status, 
                       p.brand_name, p.permalink, p.category_slugs, p.category_names,
                       p.description, p.short_description
                FROM wp_products p
                WHERE p.stock_status = 'instock'";
        
        $params = [];

        // Price filters
        if ($priceMin !== null) {
            $sql .= " AND CAST(p.price AS DECIMAL(10,2)) >= ?";
            $params[] = $priceMin;
        }
        if ($priceMax !== null) {
            $sql .= " AND CAST(p.price AS DECIMAL(10,2)) <= ?";
            $params[] = $priceMax;
        }

        $sql .= " ORDER BY p.title ASC LIMIT 500"; // Get more than needed for scoring

        $products = Database::query($sql, $params);

        // Score and filter products
        $matched = [];

        foreach ($products as $product) {
            $score = 0;
            $reasons = [];
            $productCategories = json_decode($product['category_slugs'] ?? '[]', true);
            $searchText = strtolower(
                $product['title'] . ' ' . 
                ($product['description'] ?? '') . ' ' . 
                ($product['short_description'] ?? '') . ' ' .
                ($product['category_names'] ?? '')
            );

            // Check exclude categories
            $excluded = false;
            foreach ($excludeCategories as $exc) {
                if (in_array(strtolower($exc), array_map('strtolower', $productCategories))) {
                    $excluded = true;
                    break;
                }
                // Also check in text
                if (stripos($searchText, $exc) !== false) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) continue;

            // Check exclude keywords
            foreach ($excludeKeywords as $exc) {
                if (stripos($searchText, $exc) !== false) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) continue;

            // Category matching (high weight)
            $matchedCats = [];
            foreach ($categories as $cat) {
                foreach ($productCategories as $pCat) {
                    if (stripos($pCat, $cat) !== false || stripos($cat, $pCat) !== false) {
                        $matchedCats[] = $cat;
                        $score += 30;
                        break;
                    }
                }
            }
            if (!empty($matchedCats)) {
                $reasons[] = 'Categories: ' . implode(', ', array_unique($matchedCats));
            }

            // Keyword matching (medium weight)
            $matchedKeywords = [];
            foreach ($keywords as $keyword) {
                if (stripos($searchText, $keyword) !== false) {
                    $matchedKeywords[] = $keyword;
                    $score += 10;
                }
            }
            if (!empty($matchedKeywords)) {
                $reasons[] = 'Keywords: ' . implode(', ', array_unique($matchedKeywords));
            }

            // Color matching (lower weight)
            $matchedColors = [];
            foreach ($colors as $color) {
                if (stripos($searchText, $color) !== false) {
                    $matchedColors[] = $color;
                    $score += 5;
                }
            }
            if (!empty($matchedColors)) {
                $reasons[] = 'Colors: ' . implode(', ', array_unique($matchedColors));
            }

            // Only include if score > 0 (at least one match)
            if ($score > 0) {
                $product['match_score'] = min(100, $score);
                $product['match_reasons'] = $reasons;
                $matched[] = $product;
            }
        }

        // Sort by score and limit
        usort($matched, fn($a, $b) => $b['match_score'] - $a['match_score']);
        
        return array_slice($matched, 0, $limit);
    }

    /**
     * Generate slug from string
     */
    private function slugify(string $text): string
    {
        $slug = strtolower($text);
        $slug = str_replace("'", '', $slug);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }
}
