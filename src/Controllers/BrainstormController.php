<?php

namespace App\Controllers;

use App\Helpers\Database;

class BrainstormController
{
    public function __construct(array $config) {}

    public function index(array $params): void
    {
        $status = $params['status'] ?? null;
        $sql = "SELECT bi.*, se.name as event_name FROM brainstorm_ideas bi
                LEFT JOIN seasonal_events se ON bi.suggested_event_id = se.id
                WHERE 1=1";
        $bindings = [];

        if ($status) {
            $sql .= " AND bi.status = ?";
            $bindings[] = $status;
        }

        $sql .= " ORDER BY bi.created_at DESC";
        $ideas = Database::query($sql, $bindings);
        echo json_encode(['success' => true, 'data' => $ideas]);
    }

    public function store(array $input): void
    {
        $id = Database::insert(
            "INSERT INTO brainstorm_ideas (title, description, suggested_event_id, suggested_products, ai_expanded_notes)
             VALUES (?, ?, ?, ?, ?)",
            [
                $input['title'],
                $input['description'] ?? null,
                $input['suggested_event_id'] ?? null,
                json_encode($input['suggested_products'] ?? []),
                json_encode($input['ai_expanded_notes'] ?? [])
            ]
        );
        echo json_encode(['success' => true, 'data' => ['id' => $id]]);
    }

    public function update(int $id, array $input): void
    {
        $fields = [];
        $values = [];
        foreach (['title', 'description', 'status', 'suggested_event_id'] as $field) {
            if (array_key_exists($field, $input)) {
                $fields[] = "{$field} = ?";
                $values[] = $input[$field];
            }
        }
        if (!empty($fields)) {
            $values[] = $id;
            Database::execute("UPDATE brainstorm_ideas SET " . implode(', ', $fields) . " WHERE id = ?", $values);
        }
        echo json_encode(['success' => true, 'data' => ['message' => 'Idea updated']]);
    }

    public function convert(int $id): void
    {
        $idea = Database::queryOne("SELECT * FROM brainstorm_ideas WHERE id = ?", [$id]);
        if (!$idea) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => ['message' => 'Idea not found']]);
            return;
        }

        // Create post from idea
        $postId = Database::insert(
            "INSERT INTO posts (title, status, seasonal_event_id, created_by) VALUES (?, 'idea', ?, ?)",
            [$idea['title'], $idea['suggested_event_id'], $_SESSION['user_id'] ?? null]
        );

        // Update idea
        Database::execute(
            "UPDATE brainstorm_ideas SET status = 'converted', converted_post_id = ? WHERE id = ?",
            [$postId, $id]
        );

        echo json_encode(['success' => true, 'data' => ['post_id' => $postId]]);
    }
}
