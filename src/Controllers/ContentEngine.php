<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\ClaudeService;

/**
 * Content Engine - Auto-pilot content generation system
 */
class ContentEngine
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get all content templates
     */
    public function getTemplates(): void
    {
        $templates = Database::query(
            "SELECT * FROM content_templates WHERE is_active = 1 ORDER BY category, name"
        );
        echo json_encode(['success' => true, 'data' => $templates]);
    }

    /**
     * Create a content template
     */
    public function createTemplate(array $input): void
    {
        $id = Database::insert(
            "INSERT INTO content_templates (name, category, content_type, prompt_template, frequency, lead_days, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)",
            [
                $input['name'],
                $input['category'], // seasonal, filler, evergreen
                $input['content_type'], // gift-guide, style-guide, brand-spotlight, etc.
                $input['prompt_template'],
                $input['frequency'] ?? null, // weekly, monthly, quarterly, or null for one-time
                $input['lead_days'] ?? 14
            ]
        );
        
        echo json_encode(['success' => true, 'data' => ['id' => $id]]);
    }

    /**
     * Get scheduled content (upcoming auto-generated posts)
     */
    public function getScheduledContent(): void
    {
        $scheduled = Database::query(
            "SELECT sc.*, ct.name as template_name, ct.content_type, se.name as event_name
             FROM scheduled_content sc
             LEFT JOIN content_templates ct ON sc.template_id = ct.id
             LEFT JOIN seasonal_events se ON sc.event_id = se.id
             WHERE sc.status IN ('pending', 'generating', 'review')
             ORDER BY sc.target_publish_date ASC"
        );
        echo json_encode(['success' => true, 'data' => $scheduled]);
    }

    /**
     * Generate content calendar for the next X months
     */
    public function generateCalendar(array $input): void
    {
        $months = (int)($input['months'] ?? 3);
        $startDate = new \DateTime();
        $endDate = (new \DateTime())->modify("+{$months} months");
        
        $created = 0;
        
        // 1. Get seasonal events in date range
        $events = Database::query(
            "SELECT * FROM seasonal_events 
             WHERE start_date BETWEEN ? AND ? AND is_active = 1
             ORDER BY start_date",
            [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]
        );
        
        // 2. Get seasonal templates
        $seasonalTemplates = Database::query(
            "SELECT * FROM content_templates WHERE category = 'seasonal' AND is_active = 1"
        );
        
        // 3. Create scheduled content for each event
        foreach ($events as $event) {
            foreach ($seasonalTemplates as $template) {
                // Check if already scheduled
                $existing = Database::queryOne(
                    "SELECT id FROM scheduled_content WHERE event_id = ? AND template_id = ?",
                    [$event['id'], $template['id']]
                );
                
                if (!$existing) {
                    $publishDate = (new \DateTime($event['start_date']))
                        ->modify("-{$template['lead_days']} days");
                    
                    // Only create if publish date is in the future
                    if ($publishDate > new \DateTime()) {
                        Database::insert(
                            "INSERT INTO scheduled_content (template_id, event_id, target_publish_date, status, created_at)
                             VALUES (?, ?, ?, 'pending', NOW())",
                            [$template['id'], $event['id'], $publishDate->format('Y-m-d')]
                        );
                        $created++;
                    }
                }
            }
        }
        
        // 4. Generate filler content slots
        $fillerTemplates = Database::query(
            "SELECT * FROM content_templates WHERE category = 'filler' AND is_active = 1"
        );
        
        foreach ($fillerTemplates as $template) {
            $interval = match($template['frequency']) {
                'weekly' => 7,
                'biweekly' => 14,
                'monthly' => 30,
                default => 30
            };
            
            $slotDate = (new \DateTime())->modify("+{$template['lead_days']} days");
            
            while ($slotDate < $endDate) {
                // Check if this week/month already has this template scheduled
                $weekStart = (clone $slotDate)->modify('monday this week')->format('Y-m-d');
                $weekEnd = (clone $slotDate)->modify('sunday this week')->format('Y-m-d');
                
                $existing = Database::queryOne(
                    "SELECT id FROM scheduled_content 
                     WHERE template_id = ? AND target_publish_date BETWEEN ? AND ?",
                    [$template['id'], $weekStart, $weekEnd]
                );
                
                if (!$existing) {
                    Database::insert(
                        "INSERT INTO scheduled_content (template_id, event_id, target_publish_date, status, created_at)
                         VALUES (?, NULL, ?, 'pending', NOW())",
                        [$template['id'], $slotDate->format('Y-m-d')]
                    );
                    $created++;
                }
                
                $slotDate->modify("+{$interval} days");
            }
        }
        
        echo json_encode([
            'success' => true, 
            'data' => ['created' => $created, 'message' => "Created {$created} scheduled content slots"]
        ]);
    }

    /**
     * Auto-generate content for pending scheduled items
     */
    public function generatePendingContent(): void
    {
        // Get pending items that should be generated (target date within 21 days)
        $pending = Database::query(
            "SELECT sc.*, ct.name as template_name, ct.content_type, ct.prompt_template,
                    se.name as event_name, se.start_date as event_date
             FROM scheduled_content sc
             JOIN content_templates ct ON sc.template_id = ct.id
             LEFT JOIN seasonal_events se ON sc.event_id = se.id
             WHERE sc.status = 'pending' 
             AND sc.target_publish_date <= DATE_ADD(NOW(), INTERVAL 21 DAY)
             ORDER BY sc.target_publish_date ASC
             LIMIT 5"
        );
        
        if (empty($pending)) {
            echo json_encode(['success' => true, 'data' => ['generated' => 0, 'message' => 'No pending content to generate']]);
            return;
        }
        
        $claude = new ClaudeService($this->config);
        $generated = 0;
        
        foreach ($pending as $item) {
            // Mark as generating
            Database::execute(
                "UPDATE scheduled_content SET status = 'generating' WHERE id = ?",
                [$item['id']]
            );
            
            try {
                // Build the prompt
                $prompt = $this->buildPrompt($item);
                
                // Generate with Claude
                $content = $claude->generateBlogPost($prompt);
                
                if ($content && !empty($content['title'])) {
                    // Create the post
                    $postId = Database::insert(
                        "INSERT INTO posts (title, intro_content, outro_content, meta_description, status, scheduled_date, created_by, created_at)
                         VALUES (?, ?, ?, ?, 'review', ?, 1, NOW())",
                        [
                            $content['title'],
                            $content['intro'] ?? '',
                            $content['outro'] ?? '',
                            $content['meta_description'] ?? '',
                            $item['target_publish_date']
                        ]
                    );
                    
                    // Create sections
                    if (!empty($content['sections'])) {
                        $index = 0;
                        foreach ($content['sections'] as $section) {
                            Database::insert(
                                "INSERT INTO post_sections (post_id, section_index, heading, content, cta_text, cta_url)
                                 VALUES (?, ?, ?, ?, ?, ?)",
                                [
                                    $postId,
                                    $index++,
                                    $section['heading'] ?? '',
                                    $section['content'] ?? '',
                                    $section['cta_text'] ?? 'Shop Now',
                                    $section['cta_url'] ?? ''
                                ]
                            );
                        }
                    }
                    
                    // Update scheduled content
                    Database::execute(
                        "UPDATE scheduled_content SET status = 'review', post_id = ?, generated_at = NOW() WHERE id = ?",
                        [$postId, $item['id']]
                    );
                    
                    $generated++;
                }
            } catch (\Exception $e) {
                error_log("Content generation failed for scheduled_content {$item['id']}: " . $e->getMessage());
                Database::execute(
                    "UPDATE scheduled_content SET status = 'failed', error_message = ? WHERE id = ?",
                    [$e->getMessage(), $item['id']]
                );
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => ['generated' => $generated, 'message' => "Generated {$generated} posts for review"]
        ]);
    }

    /**
     * Build prompt for content generation
     */
    private function buildPrompt(array $item): array
    {
        $prompt = $item['prompt_template'];
        
        // Replace placeholders
        $replacements = [
            '{{event_name}}' => $item['event_name'] ?? '',
            '{{event_date}}' => $item['event_date'] ?? '',
            '{{publish_date}}' => $item['target_publish_date'],
            '{{content_type}}' => $item['content_type'],
            '{{year}}' => date('Y'),
            '{{month}}' => date('F'),
            '{{season}}' => $this->getSeason()
        ];
        
        foreach ($replacements as $key => $value) {
            $prompt = str_replace($key, $value, $prompt);
        }
        
        // Get relevant products for context
        $products = $this->getRelevantProducts($item['content_type'], 20);
        
        return [
            'prompt' => $prompt,
            'content_type' => $item['content_type'],
            'products' => $products,
            'event' => $item['event_name'],
            'target_date' => $item['target_publish_date']
        ];
    }

    /**
     * Get relevant products for content type
     */
    private function getRelevantProducts(string $contentType, int $limit = 20): array
    {
        // Get a mix of products based on content type
        $sql = "SELECT wc_product_id, title, brand_name, price, image_url, permalink 
                FROM wp_products 
                WHERE stock_status = 'instock'";
        
        if ($contentType === 'new-arrivals') {
            $sql .= " ORDER BY synced_at DESC";
        } elseif ($contentType === 'best-sellers') {
            $sql .= " ORDER BY RAND()"; // Would need sales data for real best sellers
        } elseif ($contentType === 'sale') {
            $sql .= " AND sale_price IS NOT NULL AND sale_price > 0 ORDER BY (regular_price - sale_price) DESC";
        } else {
            $sql .= " ORDER BY RAND()";
        }
        
        $sql .= " LIMIT ?";
        
        return Database::query($sql, [$limit]);
    }

    /**
     * Get current season
     */
    private function getSeason(): string
    {
        $month = (int)date('n');
        if ($month >= 3 && $month <= 5) return 'Spring';
        if ($month >= 6 && $month <= 8) return 'Summer';
        if ($month >= 9 && $month <= 11) return 'Autumn';
        return 'Winter';
    }

    /**
     * Get content awaiting approval
     */
    public function getReviewQueue(): void
    {
        $queue = Database::query(
            "SELECT p.*, sc.target_publish_date, ct.name as template_name, ct.content_type,
                    se.name as event_name,
                    (SELECT COUNT(*) FROM post_sections WHERE post_id = p.id) as section_count
             FROM posts p
             JOIN scheduled_content sc ON sc.post_id = p.id
             LEFT JOIN content_templates ct ON sc.template_id = ct.id
             LEFT JOIN seasonal_events se ON sc.event_id = se.id
             WHERE p.status = 'review'
             ORDER BY sc.target_publish_date ASC"
        );
        
        echo json_encode(['success' => true, 'data' => $queue]);
    }

    /**
     * Approve content (move to scheduled)
     */
    public function approveContent(int $postId): void
    {
        Database::execute(
            "UPDATE posts SET status = 'scheduled' WHERE id = ? AND status = 'review'",
            [$postId]
        );
        
        Database::execute(
            "UPDATE scheduled_content SET status = 'approved' WHERE post_id = ?",
            [$postId]
        );
        
        echo json_encode(['success' => true, 'data' => ['message' => 'Content approved and scheduled']]);
    }

    /**
     * Get dashboard stats for auto-pilot
     */
    public function getStats(): void
    {
        $stats = [
            'pending_generation' => Database::queryOne(
                "SELECT COUNT(*) as count FROM scheduled_content WHERE status = 'pending'"
            )['count'] ?? 0,
            'awaiting_review' => Database::queryOne(
                "SELECT COUNT(*) as count FROM posts WHERE status = 'review'"
            )['count'] ?? 0,
            'scheduled' => Database::queryOne(
                "SELECT COUNT(*) as count FROM posts WHERE status = 'scheduled'"
            )['count'] ?? 0,
            'publishing_this_week' => Database::queryOne(
                "SELECT COUNT(*) as count FROM posts 
                 WHERE status = 'scheduled' 
                 AND scheduled_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)"
            )['count'] ?? 0,
            'next_publish' => Database::queryOne(
                "SELECT title, scheduled_date FROM posts 
                 WHERE status = 'scheduled' AND scheduled_date >= CURDATE()
                 ORDER BY scheduled_date ASC LIMIT 1"
            )
        ];
        
        echo json_encode(['success' => true, 'data' => $stats]);
    }

    /**
     * Seed default seasonal events
     */
    public function seedSeasonalEvents(): void
    {
        $year = (int)date('Y');
        $nextYear = $year + 1;
        
        // Format: [name, start_date, end_date, event_type, priority (1-10, 10=highest)]
        $events = [
            // 2026 events
            ['Valentine\'s Day', "{$year}-02-14", "{$year}-02-14", 'seasonal', 8],
            ['Mother\'s Day UK', "{$year}-03-30", "{$year}-03-30", 'seasonal', 8],
            ['Easter', "{$year}-04-20", "{$year}-04-21", 'seasonal', 6],
            ['Spring Sale', "{$year}-04-01", "{$year}-04-14", 'sale', 8],
            ['Father\'s Day UK', "{$year}-06-15", "{$year}-06-15", 'seasonal', 8],
            ['Summer Sale', "{$year}-07-01", "{$year}-07-31", 'sale', 10],
            ['Back to School', "{$year}-09-01", "{$year}-09-14", 'seasonal', 6],
            ['Autumn Collection', "{$year}-09-15", "{$year}-09-30", 'seasonal', 6],
            ['Black Friday', "{$year}-11-28", "{$year}-11-30", 'sale', 10],
            ['Cyber Monday', "{$year}-12-01", "{$year}-12-01", 'sale', 10],
            ['Christmas Gift Guide', "{$year}-12-01", "{$year}-12-20", 'seasonal', 10],
            ['Christmas', "{$year}-12-25", "{$year}-12-25", 'seasonal', 10],
            ['Boxing Day Sale', "{$year}-12-26", "{$year}-12-31", 'sale', 8],
            // 2027 events
            ['New Year Sale', "{$nextYear}-01-01", "{$nextYear}-01-14", 'sale', 8],
            ['Valentine\'s Day {$nextYear}', "{$nextYear}-02-14", "{$nextYear}-02-14", 'seasonal', 8],
        ];
        
        $created = 0;
        foreach ($events as $event) {
            $slug = $this->slugify($event[0] . '-' . substr($event[1], 0, 4));
            
            $existing = Database::queryOne(
                "SELECT id FROM seasonal_events WHERE slug = ?",
                [$slug]
            );
            
            if (!$existing) {
                Database::insert(
                    "INSERT INTO seasonal_events (name, slug, start_date, end_date, event_type, priority, is_active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
                    [$event[0], $slug, $event[1], $event[2], $event[3], $event[4]]
                );
                $created++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => ['created' => $created, 'message' => "Created {$created} seasonal events"]
        ]);
    }
    
    /**
     * Generate slug from string
     */
    private function slugify(string $text): string
    {
        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Seed default content templates
     */
    public function seedContentTemplates(): void
    {
        $templates = [
            // Seasonal templates
            [
                'name' => 'Gift Guide',
                'category' => 'seasonal',
                'content_type' => 'gift-guide',
                'frequency' => null,
                'lead_days' => 14,
                'prompt_template' => 'Create a comprehensive gift guide for {{event_name}}. Focus on fashion items perfect for gifting. Include a mix of price points from affordable treats to luxury splurges. The guide should be warm, helpful, and inspire gift-giving. Target publish date is {{publish_date}}.'
            ],
            [
                'name' => 'Sale Event',
                'category' => 'seasonal',
                'content_type' => 'sale',
                'frequency' => null,
                'lead_days' => 7,
                'prompt_template' => 'Create an exciting sale event blog post for {{event_name}}. Highlight the best deals and must-have items. Create urgency while remaining authentic. Include styling tips for sale purchases. Target publish date is {{publish_date}}.'
            ],
            [
                'name' => 'Holiday Style Guide',
                'category' => 'seasonal',
                'content_type' => 'style-guide',
                'frequency' => null,
                'lead_days' => 21,
                'prompt_template' => 'Create a style guide for {{event_name}} {{year}}. Cover outfit ideas for celebrations, what to wear, and how to accessorise. Be fashion-forward yet accessible. Target publish date is {{publish_date}}.'
            ],
            // Filler templates
            [
                'name' => 'Weekly New Arrivals',
                'category' => 'filler',
                'content_type' => 'new-arrivals',
                'frequency' => 'weekly',
                'lead_days' => 3,
                'prompt_template' => 'Create an engaging new arrivals blog post showcasing the latest additions to our collection. Highlight what makes each piece special and how to style them. Keep it fresh and exciting. Current season: {{season}} {{year}}.'
            ],
            [
                'name' => 'Brand Spotlight',
                'category' => 'filler',
                'content_type' => 'brand-spotlight',
                'frequency' => 'biweekly',
                'lead_days' => 7,
                'prompt_template' => 'Create a brand spotlight blog post featuring one of our premium brands. Tell the brand story, highlight key pieces, and explain what makes them special. Make readers fall in love with the brand. Current season: {{season}} {{year}}.'
            ],
            [
                'name' => 'Style Tips',
                'category' => 'filler',
                'content_type' => 'style-guide',
                'frequency' => 'weekly',
                'lead_days' => 5,
                'prompt_template' => 'Create a helpful style tips blog post for {{season}} {{year}}. Cover practical styling advice, wardrobe essentials, and outfit inspiration. Be helpful and inspiring without being preachy.'
            ],
            [
                'name' => 'Trend Report',
                'category' => 'filler',
                'content_type' => 'trend-report',
                'frequency' => 'monthly',
                'lead_days' => 10,
                'prompt_template' => 'Create a trend report for {{month}} {{year}}. Cover the key fashion trends of the moment and how to incorporate them into everyday style. Reference runway trends but make them wearable and accessible.'
            ],
            [
                'name' => 'Weekend Edit',
                'category' => 'filler',
                'content_type' => 'curated-edit',
                'frequency' => 'weekly',
                'lead_days' => 3,
                'prompt_template' => 'Create a curated weekend edit featuring our top picks for the week. Include a mix of casual and dressed-up options. Perfect for customers looking for quick inspiration. Current season: {{season}} {{year}}.'
            ]
        ];
        
        $created = 0;
        foreach ($templates as $t) {
            $existing = Database::queryOne(
                "SELECT id FROM content_templates WHERE name = ?",
                [$t['name']]
            );
            
            if (!$existing) {
                Database::insert(
                    "INSERT INTO content_templates (name, category, content_type, frequency, lead_days, prompt_template, is_active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
                    [$t['name'], $t['category'], $t['content_type'], $t['frequency'], $t['lead_days'], $t['prompt_template']]
                );
                $created++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => ['created' => $created, 'message' => "Created {$created} content templates"]
        ]);
    }
}
