<?php

namespace App\Controllers;

use App\Helpers\Database;

class WritingGuidelinesController
{
    public function __construct(array $config) {}

    /**
     * Get all guidelines grouped by category
     */
    public function index(): void
    {
        $guidelines = Database::query(
            "SELECT * FROM writing_guidelines WHERE is_active = 1 ORDER BY category, value"
        );
        
        $grouped = [
            'avoid_words' => [],
            'avoid_phrases' => [],
            'style_rules' => []
        ];
        
        foreach ($guidelines as $g) {
            $grouped[$g['category']][] = $g;
        }
        
        echo json_encode(['success' => true, 'data' => $grouped]);
    }

    /**
     * Add a new guideline
     */
    public function store(array $input): void
    {
        $category = $input['category'] ?? 'avoid_words';
        $value = trim($input['value'] ?? '');
        
        if (empty($value)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['message' => 'Value is required']]);
            return;
        }
        
        // Check for duplicate
        $existing = Database::queryOne(
            "SELECT id FROM writing_guidelines WHERE category = ? AND value = ?",
            [$category, $value]
        );
        
        if ($existing) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['message' => 'This guideline already exists']]);
            return;
        }
        
        $id = Database::insert(
            "INSERT INTO writing_guidelines (category, value, is_active) VALUES (?, ?, 1)",
            [$category, $value]
        );
        
        echo json_encode(['success' => true, 'data' => ['id' => $id]]);
    }

    /**
     * Add multiple guidelines at once
     */
    public function bulkStore(array $input): void
    {
        $category = $input['category'] ?? 'avoid_words';
        $values = $input['values'] ?? [];
        
        if (is_string($values)) {
            // Split by newlines or commas
            $values = preg_split('/[\n,]+/', $values);
        }
        
        $added = 0;
        foreach ($values as $value) {
            $value = trim($value);
            if (empty($value)) continue;
            
            $existing = Database::queryOne(
                "SELECT id FROM writing_guidelines WHERE category = ? AND value = ?",
                [$category, $value]
            );
            
            if (!$existing) {
                Database::insert(
                    "INSERT INTO writing_guidelines (category, value, is_active) VALUES (?, ?, 1)",
                    [$category, $value]
                );
                $added++;
            }
        }
        
        echo json_encode(['success' => true, 'data' => ['added' => $added]]);
    }

    /**
     * Delete a guideline
     */
    public function delete(int $id): void
    {
        Database::execute("DELETE FROM writing_guidelines WHERE id = ?", [$id]);
        echo json_encode(['success' => true]);
    }

    /**
     * Seed default guidelines
     */
    public function seedDefaults(): void
    {
        $defaults = [
            'avoid_words' => [
                'elevate', 'elevated', 'seamless', 'robust', 'powerful', 'effortless',
                'streamlined', 'intuitive', 'comprehensive', 'scalable', 'optimised',
                'dynamic', 'cutting-edge', 'innovative', 'versatile', 'strategic',
                'holistic', 'transformative', 'future-proof', 'next-generation',
                'best-in-class', 'world-class', 'industry-leading', 'purpose-built',
                'unlock', 'leverage', 'enhance', 'empower', 'maximise', 'supercharge',
                'redefine', 'amplify', 'delve', 'ecosystem', 'synergy', 'alignment',
                'impactful', 'value-driven', 'actionable', 'stakeholders', 'landscape'
            ],
            'avoid_phrases' => [
                "In today's fast-paced world",
                "At its core",
                "When it comes to",
                "That said",
                "With that in mind",
                "Not only that, but",
                "Whether you're X or Y",
                "Let's take a closer look",
                "Here's what you need to know",
                "In other words",
                "To put it simply",
                "You can rest assured",
                "Peace of mind",
                "Every step of the way",
                "Tailored to your needs",
                "Results-driven",
                "Built for modern teams",
                "Designed to help you",
                "This ensures that",
                "By doing so",
                "The result is",
                "In order to",
                "It's important to note",
                "A key benefit is",
                "This allows you to",
                "Not just X, but Y",
                "Simple yet powerful",
                "Take your X to the next level",
                "An elevated experience"
            ],
            'style_rules' => [
                "Never use em dashes (—) more than once per paragraph",
                "Avoid perfect parallelism in bullet points - vary the phrasing",
                "Don't use 'Key Takeaways', 'In Summary', or 'Final Thoughts' as headings",
                "Vary sentence length - mix short punchy sentences with longer ones",
                "Write like a knowledgeable friend, not a corporate brochure",
                "Use specific details rather than vague superlatives",
                "Show personality - it's okay to be slightly imperfect",
                "Avoid starting multiple sentences with the same word",
                "Use contractions naturally (it's, you're, we're)",
                "British English spelling (colour, favourite, organised)"
            ]
        ];
        
        $added = 0;
        foreach ($defaults as $category => $values) {
            foreach ($values as $value) {
                $existing = Database::queryOne(
                    "SELECT id FROM writing_guidelines WHERE category = ? AND value = ?",
                    [$category, $value]
                );
                
                if (!$existing) {
                    Database::insert(
                        "INSERT INTO writing_guidelines (category, value, is_active) VALUES (?, ?, 1)",
                        [$category, $value]
                    );
                    $added++;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => ['added' => $added, 'message' => "Added {$added} writing guidelines"]
        ]);
    }

    /**
     * Get guidelines formatted for Claude prompt
     */
    public static function getForPrompt(): string
    {
        $guidelines = Database::query(
            "SELECT category, value FROM writing_guidelines WHERE is_active = 1 ORDER BY category"
        );
        
        if (empty($guidelines)) {
            return '';
        }
        
        $grouped = [
            'avoid_words' => [],
            'avoid_phrases' => [],
            'style_rules' => []
        ];
        
        foreach ($guidelines as $g) {
            $grouped[$g['category']][] = $g['value'];
        }
        
        $prompt = "\n\nCRITICAL WRITING GUIDELINES - You MUST follow these:\n\n";
        
        if (!empty($grouped['avoid_words'])) {
            $prompt .= "WORDS TO NEVER USE:\n";
            $prompt .= implode(', ', $grouped['avoid_words']);
            $prompt .= "\n\n";
        }
        
        if (!empty($grouped['avoid_phrases'])) {
            $prompt .= "PHRASES TO NEVER USE:\n";
            foreach ($grouped['avoid_phrases'] as $phrase) {
                $prompt .= "- {$phrase}\n";
            }
            $prompt .= "\n";
        }
        
        if (!empty($grouped['style_rules'])) {
            $prompt .= "STYLE RULES:\n";
            foreach ($grouped['style_rules'] as $rule) {
                $prompt .= "- {$rule}\n";
            }
        }
        
        return $prompt;
    }
}
