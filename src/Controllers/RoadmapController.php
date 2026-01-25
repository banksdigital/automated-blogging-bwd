<?php

namespace App\Controllers;

use App\Helpers\Database;

class RoadmapController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get roadmap index
     */
    public function index(array $params = []): void
    {
        $now = new \DateTime();
        $this->month((int)$now->format('Y'), (int)$now->format('n'));
    }

    /**
     * Get roadmap for a specific month
     */
    public function month(int $year, int $month): void
    {
        try {
            // Get upcoming events (next 6 weeks from today, not just this month)
            $events = Database::query(
                "SELECT id, name, slug, start_date, end_date 
                 FROM seasonal_events 
                 WHERE (
                     -- Event starts within next 6 weeks
                     (start_date >= CURDATE() AND start_date <= DATE_ADD(CURDATE(), INTERVAL 6 WEEK))
                     OR
                     -- Event is currently active (started but not ended)
                     (start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE()))
                     OR
                     -- Event ends within next 6 weeks
                     (end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL 6 WEEK))
                 )
                 ORDER BY start_date ASC"
            );
            
            // Build calendar days for the requested month
            $calendar = $this->buildCalendar($year, $month);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'year' => $year,
                    'month' => $month,
                    'events' => $events,
                    'calendar' => $calendar
                ]
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
     * Build calendar array for a month
     */
    private function buildCalendar(int $year, int $month): array
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $calendar = [];
        
        // Get posts for this month
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        
        $posts = Database::query(
            "SELECT id, title, status, scheduled_date 
             FROM posts 
             WHERE scheduled_date >= ? AND scheduled_date <= ?
             ORDER BY scheduled_date ASC",
            [$startDate, $endDate]
        );
        
        // Group posts by day
        $postsByDay = [];
        foreach ($posts as $post) {
            $day = (int)date('j', strtotime($post['scheduled_date']));
            if (!isset($postsByDay[$day])) {
                $postsByDay[$day] = [];
            }
            $postsByDay[$day][] = $post;
        }
        
        // Build calendar days
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $calendar[] = [
                'day' => $day,
                'date' => $date,
                'posts' => $postsByDay[$day] ?? []
            ];
        }
        
        return $calendar;
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
                    wc.name as category_name,
                    (SELECT COUNT(*) FROM post_sections WHERE post_id = p.id) as section_count
                 FROM posts p
                 LEFT JOIN seasonal_events se ON p.seasonal_event_id = se.id
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
