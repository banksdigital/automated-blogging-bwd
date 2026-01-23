<?php

namespace App\Controllers;

use App\Helpers\Database;

class MaintenanceController
{
    /**
     * Get maintenance stats
     */
    public function stats(): void
    {
        $posts = Database::queryOne("SELECT COUNT(*) as cnt FROM posts")['cnt'] ?? 0;
        $sections = Database::queryOne("SELECT COUNT(*) as cnt FROM post_sections")['cnt'] ?? 0;
        $scheduled = Database::queryOne("SELECT COUNT(*) as cnt FROM scheduled_content")['cnt'] ?? 0;
        $events = Database::queryOne("SELECT COUNT(*) as cnt FROM seasonal_events")['cnt'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'posts' => (int)$posts,
                'sections' => (int)$sections,
                'scheduled' => (int)$scheduled,
                'events' => (int)$events
            ]
        ]);
    }
    
    /**
     * Clear all posts and sections
     */
    public function clearPosts(): void
    {
        try {
            // Reset scheduled content first (remove foreign key references)
            Database::execute("UPDATE scheduled_content SET status = 'pending', post_id = NULL, generated_at = NULL");
            
            // Delete all sections
            $sectionsDeleted = Database::execute("DELETE FROM post_sections");
            
            // Delete all posts
            $postsDeleted = Database::execute("DELETE FROM posts");
            
            echo json_encode([
                'success' => true,
                'message' => "Deleted all posts and sections. Scheduled content reset to pending."
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
     * Reset scheduled content to pending
     */
    public function resetScheduled(): void
    {
        try {
            Database::execute("UPDATE scheduled_content SET status = 'pending', post_id = NULL, generated_at = NULL, error_message = NULL");
            
            echo json_encode([
                'success' => true,
                'message' => "All scheduled content reset to pending."
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
     * Re-seed seasonal events
     */
    public function reseedEvents(): void
    {
        try {
            $events = [
                ['name' => "Valentine's Day", 'slug' => 'valentines-day', 'start_date' => '2026-02-01', 'end_date' => '2026-02-14', 'event_type' => 'holiday', 'priority' => 8],
                ['name' => "Mother's Day UK", 'slug' => 'mothers-day-uk', 'start_date' => '2026-03-15', 'end_date' => '2026-03-22', 'event_type' => 'holiday', 'priority' => 9],
                ['name' => 'Easter', 'slug' => 'easter', 'start_date' => '2026-03-29', 'end_date' => '2026-04-05', 'event_type' => 'holiday', 'priority' => 7],
                ['name' => 'Spring Collection', 'slug' => 'spring-collection', 'start_date' => '2026-03-01', 'end_date' => '2026-05-31', 'event_type' => 'seasonal', 'priority' => 6],
                ['name' => 'Summer Collection', 'slug' => 'summer-collection', 'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'event_type' => 'seasonal', 'priority' => 6],
                ['name' => 'Festival Season', 'slug' => 'festival-season', 'start_date' => '2026-06-15', 'end_date' => '2026-08-31', 'event_type' => 'seasonal', 'priority' => 7],
                ['name' => 'Back to Work', 'slug' => 'back-to-work', 'start_date' => '2026-09-01', 'end_date' => '2026-09-15', 'event_type' => 'seasonal', 'priority' => 5],
                ['name' => 'Autumn Collection', 'slug' => 'autumn-collection', 'start_date' => '2026-09-01', 'end_date' => '2026-11-30', 'event_type' => 'seasonal', 'priority' => 6],
                ['name' => 'Black Friday', 'slug' => 'black-friday', 'start_date' => '2026-11-20', 'end_date' => '2026-11-27', 'event_type' => 'sale', 'priority' => 10],
                ['name' => 'Cyber Monday', 'slug' => 'cyber-monday', 'start_date' => '2026-11-28', 'end_date' => '2026-11-30', 'event_type' => 'sale', 'priority' => 9],
                ['name' => 'Christmas Gift Guide', 'slug' => 'christmas-gift-guide', 'start_date' => '2026-11-15', 'end_date' => '2026-12-24', 'event_type' => 'holiday', 'priority' => 10],
                ['name' => 'Boxing Day Sale', 'slug' => 'boxing-day-sale', 'start_date' => '2026-12-26', 'end_date' => '2027-01-02', 'event_type' => 'sale', 'priority' => 9],
                ['name' => 'Winter Collection', 'slug' => 'winter-collection', 'start_date' => '2026-12-01', 'end_date' => '2027-02-28', 'event_type' => 'seasonal', 'priority' => 6],
                ['name' => 'New Year New Wardrobe', 'slug' => 'new-year-new-wardrobe', 'start_date' => '2027-01-01', 'end_date' => '2027-01-31', 'event_type' => 'seasonal', 'priority' => 7],
                ['name' => "Valentine's Day 2027", 'slug' => 'valentines-day-2027', 'start_date' => '2027-02-01', 'end_date' => '2027-02-14', 'event_type' => 'holiday', 'priority' => 8],
            ];
            
            $added = 0;
            foreach ($events as $event) {
                // Only insert if not exists (by slug)
                $exists = Database::queryOne(
                    "SELECT id FROM seasonal_events WHERE slug = ?",
                    [$event['slug']]
                );
                
                if (!$exists) {
                    Database::insert(
                        "INSERT INTO seasonal_events (name, slug, start_date, end_date, event_type, priority)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $event['name'],
                            $event['slug'],
                            $event['start_date'],
                            $event['end_date'],
                            $event['event_type'],
                            $event['priority']
                        ]
                    );
                    $added++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => $added > 0 ? "Added {$added} new seasonal events." : "All events already exist."
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
