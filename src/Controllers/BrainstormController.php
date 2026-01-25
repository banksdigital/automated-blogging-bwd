<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\ClaudeService;

class BrainstormController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get all brainstorm ideas
     */
    public function index(array $params = []): void
    {
        try {
            $ideas = Database::query(
                "SELECT * FROM brainstorm_ideas ORDER BY created_at DESC"
            );
            
            echo json_encode([
                'success' => true,
                'data' => $ideas
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
     * Create a new brainstorm idea (store)
     */
    public function store(array $input): void
    {
        $title = $input['title'] ?? '';
        $description = $input['description'] ?? '';

        if (empty($title)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['message' => 'Title is required']
            ]);
            return;
        }

        try {
            $id = Database::insert(
                "INSERT INTO brainstorm_ideas (title, description, created_at) VALUES (?, ?, NOW())",
                [$title, $description]
            );
            
            echo json_encode([
                'success' => true,
                'data' => ['id' => $id]
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
     * Update a brainstorm idea
     */
    public function update(int $id, array $input): void
    {
        $title = $input['title'] ?? '';
        $description = $input['description'] ?? '';

        try {
            Database::execute(
                "UPDATE brainstorm_ideas SET title = ?, description = ? WHERE id = ?",
                [$title, $description, $id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Idea updated'
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
     * Delete a brainstorm idea
     */
    public function delete(int $id): void
    {
        try {
            Database::execute("DELETE FROM brainstorm_ideas WHERE id = ?", [$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Idea deleted'
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
     * Convert a brainstorm idea to a full draft post with AI-generated content
     */
    public function convert(int $id): void
    {
        try {
            // Get the idea
            $idea = Database::queryOne(
                "SELECT * FROM brainstorm_ideas WHERE id = ?",
                [$id]
            );
            
            if (!$idea) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => ['message' => 'Idea not found']
                ]);
                return;
            }
            
            // Get default settings
            $defaultAuthor = Database::queryOne(
                "SELECT setting_value FROM app_settings WHERE setting_key = 'default_author_id'"
            );
            $defaultCategory = Database::queryOne(
                "SELECT setting_value FROM app_settings WHERE setting_key = 'default_category_id'"
            );
            
            $defaultAuthorId = $defaultAuthor['setting_value'] ?? null;
            $defaultCategoryId = $defaultCategory['setting_value'] ?? null;
            
            // Try to match a seasonal event based on the idea title/description
            $seasonalEventId = $this->matchSeasonalEvent($idea['title'], $idea['description'] ?? '');
            
            // Generate full post content using Claude
            $claudeService = new \App\Services\ClaudeService($this->config);
            
            // Build prompt from the idea
            $prompt = $idea['title'];
            if (!empty($idea['description'])) {
                $prompt .= "\n\nContext: " . $idea['description'];
            }
            
            // Generate the full blog post
            $content = $claudeService->generateBlogPost(['prompt' => $prompt]);
            
            if (empty($content) || empty($content['title'])) {
                // Fallback: create basic post if generation fails
                $postId = Database::insert(
                    "INSERT INTO posts (title, intro_content, status, wp_author_id, wp_category_id, seasonal_event_id, created_at, updated_at) 
                     VALUES (?, ?, 'draft', ?, ?, ?, NOW(), NOW())",
                    [$idea['title'], $idea['description'] ?? '', $defaultAuthorId, $defaultCategoryId, $seasonalEventId]
                );
            } else {
                // Create full post with generated content and defaults
                $postId = Database::insert(
                    "INSERT INTO posts (title, intro_content, outro_content, meta_description, status, wp_author_id, wp_category_id, seasonal_event_id, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, NOW(), NOW())",
                    [
                        $content['title'] ?? $idea['title'],
                        $content['intro'] ?? '',
                        $content['outro'] ?? '',
                        $content['meta_description'] ?? '',
                        $defaultAuthorId,
                        $defaultCategoryId,
                        $seasonalEventId
                    ]
                );
                
                // Create sections with carousels
                if (!empty($content['sections']) && is_array($content['sections'])) {
                    foreach ($content['sections'] as $index => $section) {
                        Database::insert(
                            "INSERT INTO post_sections (post_id, section_index, heading, content, cta_text, cta_url, carousel_brand_id, carousel_category_id) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $postId,
                                $index,
                                $section['heading'] ?? '',
                                $section['content'] ?? '',
                                $section['cta_text'] ?? '',
                                $section['cta_url'] ?? '',
                                $section['carousel_brand_id'] ?? null,
                                $section['carousel_category_id'] ?? null
                            ]
                        );
                    }
                }
            }
            
            // Mark idea as converted
            Database::execute(
                "UPDATE brainstorm_ideas SET converted_post_id = ? WHERE id = ?",
                [$postId, $id]
            );
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'post_id' => $postId,
                    'message' => 'Idea converted to full post'
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("Convert idea error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ]);
        }
    }
    
    /**
     * Try to match a seasonal event based on text content
     */
    private function matchSeasonalEvent(string $title, string $description): ?int
    {
        $text = strtolower($title . ' ' . $description);
        
        // Get all upcoming seasonal events (next 3 months)
        $events = Database::query(
            "SELECT id, name, slug 
             FROM seasonal_events 
             WHERE start_date >= CURDATE() - INTERVAL 2 WEEK
               AND start_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
             ORDER BY start_date ASC"
        );
        
        // Keywords to match for each common event type
        $keywords = [
            'valentine' => ['valentine', 'galentine', 'love', 'romantic', 'hearts'],
            'christmas' => ['christmas', 'xmas', 'festive', 'holiday gift', 'stocking'],
            'mother' => ['mother', 'mum', 'mom', 'maternal'],
            'father' => ['father', 'dad', 'paternal'],
            'easter' => ['easter', 'spring break'],
            'summer' => ['summer', 'vacation', 'holiday wardrobe', 'beach'],
            'autumn' => ['autumn', 'fall', 'back to school'],
            'winter' => ['winter', 'cozy', 'layering'],
            'spring' => ['spring', 'new season', 'fresh'],
            'black friday' => ['black friday', 'cyber monday', 'sale'],
            'new year' => ['new year', 'nye', 'resolution'],
        ];
        
        foreach ($events as $event) {
            $eventSlug = strtolower($event['slug'] ?? '');
            $eventName = strtolower($event['name']);
            
            // Direct match on event name or slug
            if (strpos($text, $eventSlug) !== false || strpos($text, $eventName) !== false) {
                return (int)$event['id'];
            }
            
            // Check keyword matches
            foreach ($keywords as $key => $keywordList) {
                if (strpos($eventSlug, $key) !== false || strpos($eventName, $key) !== false) {
                    foreach ($keywordList as $keyword) {
                        if (strpos($text, $keyword) !== false) {
                            return (int)$event['id'];
                        }
                    }
                }
            }
        }
        
        return null;
    }
}
