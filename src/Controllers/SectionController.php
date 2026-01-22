<?php

namespace App\Controllers;

use App\Helpers\Database;

class SectionController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Add section to post
     */
    public function store(int $postId, array $input): void
    {
        // Verify post exists
        $post = Database::queryOne("SELECT id FROM posts WHERE id = ?", [$postId]);
        if (!$post) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => ['message' => 'Post not found']]);
            return;
        }

        // Get next section index
        $lastSection = Database::queryOne(
            "SELECT MAX(section_index) as max_index FROM post_sections WHERE post_id = ?",
            [$postId]
        );
        $nextIndex = ($lastSection['max_index'] ?? -1) + 1;

        $sectionId = Database::insert(
            "INSERT INTO post_sections (post_id, section_index, heading, content, cta_text, cta_url,
                                       carousel_brand_slug, carousel_category_slug, fallback_block_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $postId,
                $nextIndex,
                $input['heading'] ?? '',
                $input['content'] ?? '',
                $input['cta_text'] ?? null,
                $input['cta_url'] ?? null,
                $input['carousel_brand_slug'] ?? null,
                $input['carousel_category_slug'] ?? null,
                $input['fallback_block_id'] ?? null
            ]
        );

        echo json_encode([
            'success' => true,
            'data' => ['id' => $sectionId, 'section_index' => $nextIndex]
        ]);
    }

    /**
     * Update section
     */
    public function update(int $postId, int $sectionIndex, array $input): void
    {
        $section = Database::queryOne(
            "SELECT id FROM post_sections WHERE post_id = ? AND section_index = ?",
            [$postId, $sectionIndex]
        );

        if (!$section) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => ['message' => 'Section not found']]);
            return;
        }

        $fields = [];
        $values = [];

        $allowedFields = ['heading', 'content', 'cta_text', 'cta_url', 
                         'carousel_brand_slug', 'carousel_category_slug', 'fallback_block_id'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $fields[] = "{$field} = ?";
                $values[] = $input[$field];
            }
        }

        if (!empty($fields)) {
            $values[] = $section['id'];
            Database::execute(
                "UPDATE post_sections SET " . implode(', ', $fields) . " WHERE id = ?",
                $values
            );
        }

        echo json_encode(['success' => true, 'data' => ['message' => 'Section updated']]);
    }

    /**
     * Delete section
     */
    public function delete(int $postId, int $sectionIndex): void
    {
        Database::execute(
            "DELETE FROM post_sections WHERE post_id = ? AND section_index = ?",
            [$postId, $sectionIndex]
        );

        // Reindex remaining sections
        Database::execute(
            "SET @idx = -1; UPDATE post_sections SET section_index = (@idx := @idx + 1) 
             WHERE post_id = ? ORDER BY section_index",
            [$postId]
        );

        echo json_encode(['success' => true, 'data' => ['message' => 'Section deleted']]);
    }
}
