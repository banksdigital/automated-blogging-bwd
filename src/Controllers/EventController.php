<?php

namespace App\Controllers;

use App\Helpers\Database;

class EventController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get all seasonal events
     */
    public function index(): void
    {
        try {
            // Try to get events with post count
            try {
                $events = Database::query(
                    "SELECT 
                        se.*,
                        (SELECT COUNT(*) FROM posts WHERE seasonal_event_id = se.id) as post_count
                     FROM seasonal_events se
                     ORDER BY se.start_date ASC"
                );
            } catch (\Exception $e) {
                // If seasonal_event_id column doesn't exist in posts, just get events
                $events = Database::query(
                    "SELECT se.*, 0 as post_count
                     FROM seasonal_events se
                     ORDER BY se.start_date ASC"
                );
            }
            
            echo json_encode([
                'success' => true,
                'data' => $events
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
     * Create a new seasonal event
     */
    public function store(array $input): void
    {
        $name = trim($input['name'] ?? '');
        $slug = $input['slug'] ?? $this->generateSlug($name);
        $startDate = $input['start_date'] ?? null;
        $endDate = $input['end_date'] ?? null;

        if (empty($name)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['message' => 'Event name is required']
            ]);
            return;
        }

        if (empty($startDate)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['message' => 'Start date is required']
            ]);
            return;
        }

        try {
            // Use basic columns that should exist
            $id = Database::insert(
                "INSERT INTO seasonal_events (name, slug, start_date, end_date) 
                 VALUES (?, ?, ?, ?)",
                [$name, $slug, $startDate, $endDate ?: null]
            );
            
            echo json_encode([
                'success' => true,
                'data' => ['id' => $id],
                'message' => 'Event created successfully'
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
     * Update an existing seasonal event
     */
    public function update(int $id, array $input): void
    {
        $name = trim($input['name'] ?? '');
        $slug = $input['slug'] ?? null;
        $startDate = $input['start_date'] ?? null;
        $endDate = $input['end_date'] ?? null;

        if (empty($name)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ['message' => 'Event name is required']
            ]);
            return;
        }

        try {
            // If slug not provided, generate from name
            if (empty($slug)) {
                $slug = $this->generateSlug($name);
            }

            Database::execute(
                "UPDATE seasonal_events 
                 SET name = ?, slug = ?, start_date = ?, end_date = ?
                 WHERE id = ?",
                [$name, $slug, $startDate, $endDate ?: null, $id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Event updated successfully'
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
     * Delete a seasonal event
     */
    public function delete(int $id): void
    {
        try {
            // Try to unlink any associated posts (ignore if column doesn't exist)
            try {
                Database::execute(
                    "UPDATE posts SET seasonal_event_id = NULL WHERE seasonal_event_id = ?",
                    [$id]
                );
            } catch (\Exception $e) {
                // Column may not exist, ignore
            }
            
            // Delete the event
            Database::execute(
                "DELETE FROM seasonal_events WHERE id = ?",
                [$id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Event deleted successfully'
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
     * Generate a URL-friendly slug from a name
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
}
