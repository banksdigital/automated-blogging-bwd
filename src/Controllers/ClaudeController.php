<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\ClaudeService;

class ClaudeController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Brainstorm blog ideas
     */
    public function brainstorm(array $input): void
    {
        $prompt = $input['prompt'] ?? '';
        $eventId = $input['event_id'] ?? null;

        if (empty($prompt)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Prompt is required']
            ]);
            return;
        }

        // Get seasonal context if event specified
        $seasonalContext = null;
        if ($eventId) {
            $event = Database::queryOne("SELECT name, content_themes FROM seasonal_events WHERE id = ?", [$eventId]);
            if ($event) {
                $themes = json_decode($event['content_themes'], true) ?: [];
                $seasonalContext = $event['name'] . ": " . implode(', ', $themes);
            }
        }

        try {
            $service = new ClaudeService($this->config);
            
            // Pass as array - ClaudeService::brainstorm expects array $params
            $result = $service->brainstorm([
                'prompt' => $prompt,
                'topic' => $prompt,
                'seasonal_context' => $seasonalContext
            ]);

            $this->logActivity('ai_brainstorm', 'claude', null, ['prompt' => substr($prompt, 0, 100)]);

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            error_log("Claude brainstorm error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'AI_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Generate single section content
     */
    public function generateSection(array $input): void
    {
        $topic = $input['topic'] ?? '';
        $sectionFocus = $input['section_focus'] ?? '';
        $context = $input['context'] ?? null;

        if (empty($sectionFocus)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Section focus is required']
            ]);
            return;
        }

        try {
            $service = new ClaudeService($this->config);
            $result = $service->generateSection($topic, $sectionFocus, $context);

            $this->logActivity('ai_generate_section', 'claude', null);

            echo json_encode([
                'success' => $result['success'] ?? true,
                'data' => $result['data'] ?? $result,
                'error' => $result['error'] ?? null
            ]);

        } catch (\Exception $e) {
            error_log("Claude section error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'AI_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Generate full blog post
     */
    public function generatePost(array $input): void
    {
        $topic = $input['topic'] ?? '';
        $sections = $input['sections'] ?? [];
        $eventId = $input['event_id'] ?? null;

        if (empty($topic)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Topic is required']
            ]);
            return;
        }

        // Get seasonal context
        $seasonalContext = null;
        if ($eventId) {
            $event = Database::queryOne("SELECT name FROM seasonal_events WHERE id = ?", [$eventId]);
            if ($event) {
                $seasonalContext = $event['name'];
            }
        }

        try {
            $service = new ClaudeService($this->config);
            $result = $service->generateBlogPost($topic, $sections, $seasonalContext);

            $this->logActivity('ai_generate_post', 'claude', null, ['topic' => $topic]);

            echo json_encode([
                'success' => $result['success'] ?? true,
                'data' => $result['data'] ?? $result,
                'error' => $result['error'] ?? null
            ]);

        } catch (\Exception $e) {
            error_log("Claude post error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'AI_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Improve existing content
     */
    public function improve(array $input): void
    {
        $content = $input['content'] ?? '';
        $instruction = $input['instruction'] ?? 'Make this more engaging';

        if (empty($content)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Content is required']
            ]);
            return;
        }

        try {
            $service = new ClaudeService($this->config);
            $result = $service->improveContent($content, $instruction);

            echo json_encode([
                'success' => $result['success'] ?? true,
                'data' => $result['data'] ?? $result,
                'error' => $result['error'] ?? null
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'AI_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Suggest products for content
     */
    public function suggestProducts(array $input): void
    {
        $topic = $input['topic'] ?? '';
        $brandSlug = $input['brand_slug'] ?? null;
        $categorySlug = $input['category_slug'] ?? null;

        if (empty($topic)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Topic is required']
            ]);
            return;
        }

        // Get available products
        $sql = "SELECT wc_product_id, title, brand_name, category_names FROM wp_products WHERE stock_status = 'instock'";
        $bindings = [];

        if ($brandSlug) {
            $sql .= " AND brand_slug = ?";
            $bindings[] = $brandSlug;
        }
        if ($categorySlug) {
            $sql .= " AND JSON_CONTAINS(category_slugs, ?)";
            $bindings[] = json_encode($categorySlug);
        }

        $sql .= " LIMIT 200";
        $products = Database::query($sql, $bindings);

        try {
            $service = new ClaudeService($this->config);
            $result = $service->suggestProducts($topic, $products);

            if (($result['success'] ?? true) && is_array($result['data'] ?? $result)) {
                // Get full product details for suggested IDs
                $data = $result['data'] ?? $result;
                $suggestedIds = array_map('intval', $data);
                if (!empty($suggestedIds)) {
                    $placeholders = implode(',', array_fill(0, count($suggestedIds), '?'));
                    $suggestedProducts = Database::query(
                        "SELECT * FROM wp_products WHERE wc_product_id IN ({$placeholders})",
                        $suggestedIds
                    );
                    $result['data'] = $suggestedProducts;
                }
            }

            echo json_encode([
                'success' => $result['success'] ?? true,
                'data' => $result['data'] ?? $result,
                'error' => $result['error'] ?? null
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'AI_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * Generate meta description
     */
    public function metaDescription(array $input): void
    {
        $title = $input['title'] ?? '';
        $content = $input['content'] ?? '';

        if (empty($title)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required']
            ]);
            return;
        }

        try {
            $service = new ClaudeService($this->config);
            $result = $service->generateMetaDescription($title, $content);

            echo json_encode([
                'success' => $result['success'] ?? true,
                'data' => $result['data'] ?? $result,
                'error' => $result['error'] ?? null
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'AI_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * General chat interface
     */
    public function chat(array $input): void
    {
        $message = $input['message'] ?? '';
        $context = $input['context'] ?? [];

        if (empty($message)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Message is required']
            ]);
            return;
        }

        try {
            $service = new ClaudeService($this->config);
            $result = $service->chat($message, $context);

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'AI_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }

    /**
     * AI Assistant for refining a specific post
     */
    public function postAssistant(array $input): void
    {
        try {
            $service = new ClaudeService($this->config);
            $result = $service->postAssistant($input);
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            error_log("Post assistant error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ]);
        }
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
