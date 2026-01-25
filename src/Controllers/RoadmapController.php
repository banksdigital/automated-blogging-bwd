<?php

namespace App\Controllers;

use App\Helpers\Database;

class RoadmapController
{
    public function __construct(array $config) {}

    public function index(array $params): void
    {
        $year = (int)($params['year'] ?? date('Y'));
        
        // Get all events for the year
        $events = Database::query(
            "SELECT * FROM seasonal_events 
             WHERE YEAR(start_date) = ? OR YEAR(end_date) = ?
             ORDER BY start_date",
            [$year, $year]
        );

        // Get all posts for the year
        $posts = Database::query(
            "SELECT p.id, p.title, p.status, p.scheduled_date, se.name as event_name
             FROM posts p
             LEFT JOIN seasonal_events se ON p.seasonal_event_id = se.id
             WHERE YEAR(p.scheduled_date) = ? OR (p.scheduled_date IS NULL AND YEAR(p.created_at) = ?)
             ORDER BY p.scheduled_date",
            [$year, $year]
        );

        echo json_encode([
            'success' => true,
            'data' => [
                'year' => $year,
                'events' => $events,
                'posts' => $posts
            ]
        ]);
    }

    public function month(int $year, int $month): void
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        // Get events active during this month
        $events = Database::query(
            "SELECT * FROM seasonal_events 
             WHERE start_date <= ? AND end_date >= ?
             ORDER BY priority DESC, start_date",
            [$endDate, $startDate]
        );

        // Get posts scheduled for this month
        $posts = Database::query(
            "SELECT p.*, se.name as event_name
             FROM posts p
             LEFT JOIN seasonal_events se ON p.seasonal_event_id = se.id
             WHERE p.scheduled_date BETWEEN ? AND ?
             ORDER BY p.scheduled_date",
            [$startDate, $endDate]
        );

        // Generate calendar data
        $calendar = [];
        $date = new \DateTime($startDate);
        $endDateTime = new \DateTime($endDate);
        
        while ($date <= $endDateTime) {
            $dateStr = $date->format('Y-m-d');
            $dayPosts = array_filter($posts, fn($p) => $p['scheduled_date'] === $dateStr);
            $calendar[] = [
                'date' => $dateStr,
                'day' => (int)$date->format('j'),
                'posts' => array_values($dayPosts)
            ];
            $date->modify('+1 day');
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'year' => $year,
                'month' => $month,
                'events' => $events,
                'calendar' => $calendar
            ]
        ]);
    }
        /**
     * Get upcoming posts for timeline view
     */
    public function upcoming(): void
    {
        try {
            $posts = Database::query(
                "SELECT 
                    p.id,
                    p.title,
                    p.status,
                    p.scheduled_date,
                    p.meta_description,
                    p.wp_post_id,
                    se.name as event_name,
                    ct.name as template_name,
                    wc.name as category_name,
                    (SELECT COUNT(*) FROM post_sections WHERE post_id = p.id) as section_count
                 FROM posts p
                 LEFT JOIN seasonal_events se ON p.seasonal_event_id = se.id
                 LEFT JOIN content_templates ct ON p.template_id = ct.id
                 LEFT JOIN wp_categories wc ON p.wp_category_id = wc.id
                 WHERE p.scheduled_date IS NOT NULL
                   AND p.scheduled_date >= CURDATE() - INTERVAL 7 DAY
                 ORDER BY p.scheduled_date ASC
                 LIMIT 50"
            );
            
            echo json_encode([
                'success' => true,
                'data' => $posts
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ]);
        }
    }
}
