<?php

namespace App\Controllers;

use App\Helpers\Database;

class EventController
{
    public function __construct(array $config) {}

    public function index(): void
    {
        $events = Database::query(
            "SELECT * FROM seasonal_events WHERE is_active = TRUE ORDER BY start_date"
        );
        echo json_encode(['success' => true, 'data' => $events]);
    }

    public function store(array $input): void
    {
        $id = Database::insert(
            "INSERT INTO seasonal_events (name, slug, start_date, end_date, priority, content_themes, keywords)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $input['name'],
                $this->slugify($input['name']),
                $input['start_date'],
                $input['end_date'],
                $input['priority'] ?? 5,
                json_encode($input['content_themes'] ?? []),
                json_encode($input['keywords'] ?? [])
            ]
        );
        echo json_encode(['success' => true, 'data' => ['id' => $id]]);
    }

    public function update(int $id, array $input): void
    {
        $fields = [];
        $values = [];
        foreach (['name', 'start_date', 'end_date', 'priority', 'is_active'] as $field) {
            if (array_key_exists($field, $input)) {
                $fields[] = "{$field} = ?";
                $values[] = $input[$field];
            }
        }
        if (isset($input['content_themes'])) {
            $fields[] = "content_themes = ?";
            $values[] = json_encode($input['content_themes']);
        }
        if (isset($input['keywords'])) {
            $fields[] = "keywords = ?";
            $values[] = json_encode($input['keywords']);
        }
        if (!empty($fields)) {
            $values[] = $id;
            Database::execute("UPDATE seasonal_events SET " . implode(', ', $fields) . " WHERE id = ?", $values);
        }
        echo json_encode(['success' => true, 'data' => ['message' => 'Event updated']]);
    }

    private function slugify(string $text): string
    {
        return trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^a-z0-9\s-]/', '', strtolower($text))), '-');
    }
}
