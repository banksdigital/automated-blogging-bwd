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
     * Convert a brainstorm idea to a draft post
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
            
            // Create a new post from the idea
            $postId = Database::insert(
                "INSERT INTO posts (title, intro_content, status, created_at, updated_at) 
                 VALUES (?, ?, 'draft', NOW(), NOW())",
                [
                    $idea['title'],
                    $idea['description'] ?? ''
                ]
            );
            
            // Optionally delete the idea after converting
            // Database::execute("DELETE FROM brainstorm_ideas WHERE id = ?", [$id]);
            
            // Mark idea as converted instead
            Database::execute(
                "UPDATE brainstorm_ideas SET converted_post_id = ? WHERE id = ?",
                [$postId, $id]
            );
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'post_id' => $postId,
                    'message' => 'Idea converted to post'
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
}
