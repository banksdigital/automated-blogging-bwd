<?php

namespace App\Controllers;

use App\Helpers\Database;

class StatsController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get dashboard statistics
     */
    public function dashboard(): void
    {
        $currentMonth = date('Y-m-01');
        $nextMonth = date('Y-m-01', strtotime('+1 month'));

        // Count posts by status
        $stats = Database::queryOne(
            "SELECT 
                COUNT(CASE WHEN status = 'published' AND published_date >= ? THEN 1 END) as published_this_month,
                COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
                COUNT(CASE WHEN status = 'review' THEN 1 END) as review,
                COUNT(CASE WHEN status = 'idea' THEN 1 END) as ideas
             FROM posts",
            [$currentMonth]
        );

        // Get upcoming events
        $upcomingEvents = Database::query(
            "SELECT id, name, slug, start_date, end_date 
             FROM seasonal_events 
             WHERE end_date >= CURDATE() AND is_active = TRUE
             ORDER BY start_date ASC
             LIMIT 5"
        );

        echo json_encode([
            'success' => true,
            'data' => [
                'published_this_month' => (int)($stats['published_this_month'] ?? 0),
                'scheduled' => (int)($stats['scheduled'] ?? 0),
                'draft' => (int)($stats['draft'] ?? 0),
                'review' => (int)($stats['review'] ?? 0),
                'ideas' => (int)($stats['ideas'] ?? 0),
                'upcoming_events' => $upcomingEvents
            ]
        ]);
    }

    /**
     * Get activity log
     */
    public function activity(array $params): void
    {
        $limit = min((int)($params['limit'] ?? 10), 50);

        $activities = Database::query(
            "SELECT al.*, u.name as user_name
             FROM activity_log al
             LEFT JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC
             LIMIT ?",
            [$limit]
        );

        // Format activities for display
        foreach ($activities as &$activity) {
            $activity['description'] = $this->formatActivityDescription($activity);
        }

        echo json_encode([
            'success' => true,
            'data' => $activities
        ]);
    }

    /**
     * Format activity description for display
     */
    private function formatActivityDescription(array $activity): string
    {
        $details = json_decode($activity['details_json'] ?? '{}', true) ?: [];
        $action = $activity['action'];

        switch ($action) {
            case 'post_created':
                return "Created post: " . ($details['title'] ?? 'Untitled');
            case 'post_updated':
                return "Updated a post";
            case 'post_published':
                return "Published a post to WordPress";
            case 'post_deleted':
                return "Deleted post: " . ($details['title'] ?? 'Untitled');
            case 'ai_generated':
                return "Generated content with Claude AI";
            case 'sync_products':
                return "Synced " . ($details['count'] ?? 0) . " products from WooCommerce";
            case 'sync_categories':
                return "Synced " . ($details['count'] ?? 0) . " categories";
            case 'sync_authors':
                return "Synced " . ($details['count'] ?? 0) . " authors";
            case 'sync_blocks':
                return "Synced " . ($details['count'] ?? 0) . " page blocks";
            case 'login_success':
                return "Logged in";
            case 'logout':
                return "Logged out";
            default:
                return ucfirst(str_replace('_', ' ', $action));
        }
    }
}
