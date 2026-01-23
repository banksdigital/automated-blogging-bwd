<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\ShortcodeBuilder;

class PostController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * List all posts with optional filters
     */
    public function index(array $params): void
    {
        $status = $params['status'] ?? null;
        $limit = min((int)($params['limit'] ?? 50), 100);
        $offset = (int)($params['offset'] ?? 0);

        $sql = "SELECT p.*, 
                       se.name as event_name,
                       (SELECT COUNT(*) FROM post_sections WHERE post_id = p.id) as section_count
                FROM posts p
                LEFT JOIN seasonal_events se ON p.seasonal_event_id = se.id
                WHERE 1=1";
        $bindings = [];

        if ($status) {
            $statuses = explode(',', $status);
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $sql .= " AND p.status IN ({$placeholders})";
            $bindings = array_merge($bindings, $statuses);
        }

        $sql .= " ORDER BY 
                  CASE p.status 
                    WHEN 'scheduled' THEN 1 
                    WHEN 'review' THEN 2 
                    WHEN 'draft' THEN 3 
                    WHEN 'idea' THEN 4 
                    ELSE 5 
                  END,
                  p.scheduled_date ASC,
                  p.updated_at DESC
                  LIMIT ? OFFSET ?";
        
        $bindings[] = $limit;
        $bindings[] = $offset;

        $posts = Database::query($sql, $bindings);

        echo json_encode([
            'success' => true,
            'data' => $posts
        ]);
    }

    /**
     * Get single post with sections
     */
    public function show(int $id): void
    {
        $post = Database::queryOne(
            "SELECT p.*, se.name as event_name, se.slug as event_slug
             FROM posts p
             LEFT JOIN seasonal_events se ON p.seasonal_event_id = se.id
             WHERE p.id = ?",
            [$id]
        );

        if (!$post) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Post not found']
            ]);
            return;
        }

        // Get sections
        $sections = Database::query(
            "SELECT * FROM post_sections WHERE post_id = ? ORDER BY section_index",
            [$id]
        );

        // Get products for each section
        foreach ($sections as &$section) {
            $section['products'] = Database::query(
                "SELECT pp.*, wp.title, wp.image_url, wp.permalink, wp.stock_status
                 FROM post_products pp
                 LEFT JOIN wp_products wp ON pp.wc_product_id = wp.wc_product_id
                 WHERE pp.section_id = ?
                 ORDER BY pp.display_order",
                [$section['id']]
            );
        }

        $post['sections'] = $sections;

        echo json_encode([
            'success' => true,
            'data' => $post
        ]);
    }

    /**
     * Create new post
     */
    public function store(array $input): void
    {
        $title = trim($input['title'] ?? '');
        
        if (empty($title)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required']
            ]);
            return;
        }

        try {
            Database::beginTransaction();

            $postId = Database::insert(
                "INSERT INTO posts (title, slug, intro_content, outro_content, meta_description, 
                                   status, wp_category_id, wp_author_id, seasonal_event_id, 
                                   scheduled_date, scheduled_time, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $title,
                    $this->generateSlug($title),
                    $input['intro_content'] ?? null,
                    $input['outro_content'] ?? null,
                    $input['meta_description'] ?? null,
                    $input['status'] ?? 'idea',
                    $input['wp_category_id'] ?? null,
                    $input['wp_author_id'] ?? null,
                    $input['seasonal_event_id'] ?? null,
                    $input['scheduled_date'] ?? null,
                    $input['scheduled_time'] ?? '09:00:00',
                    $_SESSION['user_id']
                ]
            );

            // Create sections if provided
            if (!empty($input['sections'])) {
                foreach ($input['sections'] as $index => $section) {
                    $this->createSection($postId, $index, $section);
                }
            }

            Database::commit();

            $this->logActivity('post_created', 'post', $postId, ['title' => $title]);

            echo json_encode([
                'success' => true,
                'data' => ['id' => $postId, 'message' => 'Post created successfully']
            ]);

        } catch (\Exception $e) {
            Database::rollback();
            error_log("Post creation error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'CREATE_ERROR', 'message' => 'Failed to create post']
            ]);
        }
    }

    /**
     * Update existing post
     */
    public function update(int $id, array $input): void
    {
        $post = Database::queryOne("SELECT id, title FROM posts WHERE id = ?", [$id]);
        
        if (!$post) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Post not found']
            ]);
            return;
        }

        try {
            $fields = [];
            $values = [];

            $allowedFields = [
                'title', 'intro_content', 'outro_content', 'meta_description',
                'status', 'wp_category_id', 'wp_author_id', 'seasonal_event_id',
                'scheduled_date', 'scheduled_time'
            ];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $input)) {
                    $fields[] = "{$field} = ?";
                    $values[] = $input[$field];
                }
            }

            // Update slug if title changed
            if (isset($input['title'])) {
                $fields[] = "slug = ?";
                $values[] = $this->generateSlug($input['title']);
            }

            if (!empty($fields)) {
                $values[] = $id;
                Database::execute(
                    "UPDATE posts SET " . implode(', ', $fields) . " WHERE id = ?",
                    $values
                );
            }

            $this->logActivity('post_updated', 'post', $id);

            echo json_encode([
                'success' => true,
                'data' => ['message' => 'Post updated successfully']
            ]);

        } catch (\Exception $e) {
            error_log("Post update error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'UPDATE_ERROR', 'message' => 'Failed to update post']
            ]);
        }
    }

    /**
     * Delete post
     */
    public function delete(int $id): void
    {
        $post = Database::queryOne("SELECT id, title FROM posts WHERE id = ?", [$id]);
        
        if (!$post) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Post not found']
            ]);
            return;
        }

        Database::execute("DELETE FROM posts WHERE id = ?", [$id]);

        $this->logActivity('post_deleted', 'post', $id, ['title' => $post['title']]);

        echo json_encode([
            'success' => true,
            'data' => ['message' => 'Post deleted successfully']
        ]);
    }

    /**
     * Publish post to WordPress
     */
    public function publish(int $id): void
    {
        $post = Database::queryOne(
            "SELECT p.*, wc.wp_category_id as category_wp_id, wa.wp_user_id as author_wp_id
             FROM posts p
             LEFT JOIN wp_categories wc ON p.wp_category_id = wc.id
             LEFT JOIN wp_authors wa ON p.wp_author_id = wa.id
             WHERE p.id = ?",
            [$id]
        );

        if (!$post) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Post not found']
            ]);
            return;
        }

        // Get sections
        $sections = Database::query(
            "SELECT * FROM post_sections WHERE post_id = ? ORDER BY section_index",
            [$id]
        );

        // Build shortcode content
        $builder = new ShortcodeBuilder($this->config);
        $wpContent = $builder->buildPost($post, $sections);

        // Update post with generated content
        Database::execute(
            "UPDATE posts SET wp_content = ? WHERE id = ?",
            [$wpContent, $id]
        );

        try {
            // Publish to WordPress
            $wpService = new \App\Services\WordPressService($this->config);
            
            $wpData = [
                'title' => $post['title'],
                'content' => $wpContent,
                'status' => 'publish',
                'meta_description' => $post['meta_description']
            ];

            if ($post['category_wp_id']) {
                $wpData['categories'] = [$post['category_wp_id']];
            }
            if ($post['author_wp_id']) {
                $wpData['author'] = $post['author_wp_id'];
            }

            if ($post['wp_post_id']) {
                // Update existing
                $result = $wpService->updatePost($post['wp_post_id'], $wpData);
            } else {
                // Create new
                $result = $wpService->createPost($wpData);
            }

            // Update local record
            Database::execute(
                "UPDATE posts SET status = 'published', wp_post_id = ?, published_date = NOW() WHERE id = ?",
                [$result['id'], $id]
            );

            $this->logActivity('post_published', 'post', $id, ['wp_post_id' => $result['id']]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'message' => 'Post published successfully',
                    'wp_post_id' => $result['id'],
                    'url' => $result['link'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            error_log("WordPress publish error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'PUBLISH_ERROR', 'message' => 'Failed to publish to WordPress: ' . $e->getMessage()]
            ]);
        }
    }

    /**
     * Preview generated shortcode
     */
    public function preview(int $id): void
    {
        $post = Database::queryOne("SELECT * FROM posts WHERE id = ?", [$id]);

        if (!$post) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Post not found']
            ]);
            return;
        }

        $sections = Database::query(
            "SELECT * FROM post_sections WHERE post_id = ? ORDER BY section_index",
            [$id]
        );

        $builder = new ShortcodeBuilder($this->config);
        $shortcode = $builder->buildPost($post, $sections);
        $previewHtml = $builder->generatePreviewHtml($shortcode);

        echo json_encode([
            'success' => true,
            'data' => [
                'shortcode' => $shortcode,
                'preview_html' => $previewHtml
            ]
        ]);
    }

    /**
     * Create a section for a post
     */
    private function createSection(int $postId, int $index, array $data): int
    {
        return Database::insert(
            "INSERT INTO post_sections (post_id, section_index, heading, content, cta_text, cta_url,
                                       carousel_brand_slug, carousel_category_slug, carousel_taxonomy_filter,
                                       fallback_block_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $postId,
                $index,
                $data['heading'] ?? '',
                $data['content'] ?? '',
                $data['cta_text'] ?? null,
                $data['cta_url'] ?? null,
                $data['carousel_brand_slug'] ?? null,
                $data['carousel_category_slug'] ?? null,
                isset($data['carousel_taxonomy_filter']) ? json_encode($data['carousel_taxonomy_filter']) : null,
                $data['fallback_block_id'] ?? null
            ]
        );
    }

    /**
     * Generate URL slug from title
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Log activity
     */
    private function logActivity(string $action, string $entityType, int $entityId, array $details = []): void
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
     * Delete a post and its sections
     */
    public function delete(int $id): void
    {
        try {
            // Check post exists
            $post = Database::queryOne("SELECT id FROM posts WHERE id = ?", [$id]);
            
            if (!$post) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => ['message' => 'Post not found']]);
                return;
            }
            
            // Reset any scheduled content pointing to this post
            Database::execute(
                "UPDATE scheduled_content SET status = 'pending', post_id = NULL WHERE post_id = ?",
                [$id]
            );
            
            // Delete sections first
            Database::execute("DELETE FROM post_sections WHERE post_id = ?", [$id]);
            
            // Delete the post
            Database::execute("DELETE FROM posts WHERE id = ?", [$id]);
            
            echo json_encode(['success' => true, 'message' => 'Post deleted']);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ]);
        }
    }
}
