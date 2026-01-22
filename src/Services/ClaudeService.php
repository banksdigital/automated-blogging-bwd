<?php

namespace App\Services;

use App\Helpers\Database;

/**
 * Claude AI Service
 * 
 * Handles all interactions with the Claude API for content generation
 */
class ClaudeService
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private array $brandVoice = [];

    public function __construct(array $config)
    {
        $this->apiKey = $config['claude']['api_key'];
        $this->model = $config['claude']['model'];
        $this->maxTokens = $config['claude']['max_tokens'];
        $this->loadBrandVoice();
    }

    /**
     * Load active brand voice attributes from database
     */
    private function loadBrandVoice(): void
    {
        try {
            $this->brandVoice = Database::query(
                "SELECT attribute, description, examples, weight FROM brand_voice WHERE is_active = TRUE ORDER BY weight DESC"
            );
        } catch (\Exception $e) {
            error_log("Failed to load brand voice: " . $e->getMessage());
            $this->brandVoice = [];
        }
    }

    /**
     * Build the brand voice system prompt
     */
    private function buildBrandVoicePrompt(): string
    {
        $prompt = "You are writing for Black White Denim, a women's designer fashion boutique in Wilmslow, UK.\n\n";
        $prompt .= "BRAND VOICE - Write in a tone that is:\n";

        foreach ($this->brandVoice as $voice) {
            $examples = json_decode($voice['examples'], true) ?? [];
            $prompt .= sprintf(
                "- **%s**: %s\n  Examples: %s\n",
                $voice['attribute'],
                $voice['description'],
                implode(', ', $examples)
            );
        }

        $prompt .= "\nIMPORTANT WRITING RULES:\n";
        $prompt .= "- Write in British English (colour, favourite, realise)\n";
        $prompt .= "- Never use clichéd words like 'elevate', 'curate', 'journey', 'aesthetic'\n";
        $prompt .= "- Keep paragraphs short - 2-3 sentences max\n";
        $prompt .= "- Avoid starting sentences with 'Whether' or 'From X to Y'\n";
        $prompt .= "- No excessive exclamation marks\n";
        $prompt .= "- Sound like a friend giving advice, not a magazine article\n";
        $prompt .= "- Be specific, not generic. Name actual situations.\n";
        $prompt .= "- Don't use hashtags or social media speak\n";

        return $prompt;
    }

    /**
     * Generate a complete blog post
     */
    public function generateBlogPost(string $topic, array $sectionTopics, ?string $seasonalContext = null): array
    {
        $systemPrompt = $this->buildBrandVoicePrompt();

        $userPrompt = "Write a blog post about: {$topic}\n\n";

        if ($seasonalContext) {
            $userPrompt .= "This is for the seasonal event: {$seasonalContext}\n\n";
        }

        $userPrompt .= "Structure the post with these sections:\n";
        foreach ($sectionTopics as $i => $section) {
            $userPrompt .= sprintf("%d. %s\n", $i + 1, $section);
        }

        $userPrompt .= "\nFor each section, provide:\n";
        $userPrompt .= "- A catchy H2 heading (not generic, make it interesting)\n";
        $userPrompt .= "- 2-3 short paragraphs of content\n";
        $userPrompt .= "- A suggested CTA link text (e.g., 'Shop Saint Laurent sunglasses')\n\n";

        $userPrompt .= "Also provide:\n";
        $userPrompt .= "- An engaging intro paragraph (no heading, just the hook to draw readers in)\n";
        $userPrompt .= "- A strong closing paragraph that wraps up naturally\n";
        $userPrompt .= "- A meta description for SEO (max 155 characters, compelling)\n\n";

        $userPrompt .= "Return ONLY valid JSON with this exact structure:\n";
        $userPrompt .= '{"intro": "intro text here", "sections": [{"heading": "H2 heading", "content": "paragraph content", "cta": "CTA text"}], "outro": "closing text", "meta_description": "SEO description"}';
        $userPrompt .= "\n\nDo not include any text outside the JSON. No markdown code blocks.";

        $response = $this->makeRequest($systemPrompt, $userPrompt);
        
        // Parse JSON from response
        if (isset($response['content'][0]['text'])) {
            $text = $response['content'][0]['text'];
            // Clean up any markdown code blocks
            $text = preg_replace('/```json\s*/', '', $text);
            $text = preg_replace('/```\s*/', '', $text);
            $text = trim($text);
            
            $parsed = json_decode($text, true);
            if ($parsed) {
                return ['success' => true, 'data' => $parsed];
            }
        }
        
        return ['success' => false, 'error' => 'Failed to parse AI response', 'raw' => $response];
    }

    /**
     * Generate a single section
     */
    public function generateSection(string $topic, string $sectionFocus, ?string $context = null): array
    {
        $systemPrompt = $this->buildBrandVoicePrompt();

        $userPrompt = "Write a single blog section about: {$sectionFocus}\n\n";
        
        if ($context) {
            $userPrompt .= "Context: This is part of a larger blog post about {$context}\n\n";
        }

        $userPrompt .= "Provide:\n";
        $userPrompt .= "- A catchy H2 heading\n";
        $userPrompt .= "- 2-3 paragraphs of content\n";
        $userPrompt .= "- A CTA text suggestion\n\n";

        $userPrompt .= "Return ONLY valid JSON:\n";
        $userPrompt .= '{"heading": "H2 heading", "content": "paragraph content here", "cta": "CTA text"}';

        $response = $this->makeRequest($systemPrompt, $userPrompt);
        
        if (isset($response['content'][0]['text'])) {
            $text = preg_replace('/```json\s*/', '', $response['content'][0]['text']);
            $text = preg_replace('/```\s*/', '', $text);
            $parsed = json_decode(trim($text), true);
            if ($parsed) {
                return ['success' => true, 'data' => $parsed];
            }
        }
        
        return ['success' => false, 'error' => 'Failed to parse AI response'];
    }

    /**
     * Brainstorm blog ideas
     */
    public function brainstorm(string $prompt, ?string $seasonalContext = null): array
    {
        $systemPrompt = $this->buildBrandVoicePrompt();
        $systemPrompt .= "\nYou are helping brainstorm blog content ideas for Black White Denim.";

        $userPrompt = $prompt . "\n\n";
        
        if ($seasonalContext) {
            $userPrompt .= "Consider this seasonal context: {$seasonalContext}\n\n";
        }

        $userPrompt .= "Provide 3-5 blog post ideas. For each idea, include:\n";
        $userPrompt .= "- A catchy title\n";
        $userPrompt .= "- A brief description (1-2 sentences)\n";
        $userPrompt .= "- Suggested section topics\n\n";

        $userPrompt .= "Return ONLY valid JSON array:\n";
        $userPrompt .= '[{"title": "Blog title", "description": "Brief description", "sections": ["Section 1", "Section 2"]}]';

        $response = $this->makeRequest($systemPrompt, $userPrompt);
        
        if (isset($response['content'][0]['text'])) {
            $text = preg_replace('/```json\s*/', '', $response['content'][0]['text']);
            $text = preg_replace('/```\s*/', '', $text);
            $parsed = json_decode(trim($text), true);
            if ($parsed) {
                return ['success' => true, 'data' => $parsed];
            }
        }
        
        return ['success' => false, 'error' => 'Failed to parse AI response'];
    }

    /**
     * Suggest products for a topic
     */
    public function suggestProducts(string $topic, array $availableProducts): array
    {
        $systemPrompt = "You are a fashion merchandising expert for Black White Denim, a women's designer boutique. ";
        $systemPrompt .= "Given a blog topic, suggest the most relevant products to feature.";

        // Build product list
        $productList = [];
        foreach ($availableProducts as $p) {
            $categories = is_string($p['category_names']) ? json_decode($p['category_names'], true) : ($p['category_names'] ?? []);
            $productList[] = sprintf(
                "ID:%d | %s | Brand:%s | Categories:%s",
                $p['wc_product_id'],
                $p['title'],
                $p['brand_name'] ?? 'Unknown',
                implode(', ', $categories ?: [])
            );
        }

        $userPrompt = "Blog topic: {$topic}\n\n";
        $userPrompt .= "Available products:\n" . implode("\n", array_slice($productList, 0, 100));
        $userPrompt .= "\n\nSelect 5-10 products that best match this topic.";
        $userPrompt .= "\nReturn ONLY a JSON array of product IDs: [123, 456, 789]";

        $response = $this->makeRequest($systemPrompt, $userPrompt);
        
        if (isset($response['content'][0]['text'])) {
            $text = preg_replace('/```json\s*/', '', $response['content'][0]['text']);
            $text = preg_replace('/```\s*/', '', $text);
            $parsed = json_decode(trim($text), true);
            if (is_array($parsed)) {
                return ['success' => true, 'data' => $parsed];
            }
        }
        
        return ['success' => false, 'error' => 'Failed to parse AI response'];
    }

    /**
     * Generate meta description
     */
    public function generateMetaDescription(string $title, string $content): array
    {
        $systemPrompt = "You are an SEO expert. Generate compelling meta descriptions for blog posts.";

        $userPrompt = "Blog title: {$title}\n\n";
        $userPrompt .= "Content summary: " . substr($content, 0, 500) . "...\n\n";
        $userPrompt .= "Write a meta description that:\n";
        $userPrompt .= "- Is exactly 150-155 characters\n";
        $userPrompt .= "- Includes a call to action\n";
        $userPrompt .= "- Is compelling and click-worthy\n";
        $userPrompt .= "- Uses British English\n\n";
        $userPrompt .= "Return ONLY the meta description text, nothing else.";

        $response = $this->makeRequest($systemPrompt, $userPrompt);
        
        if (isset($response['content'][0]['text'])) {
            $text = trim($response['content'][0]['text']);
            // Ensure it's within limits
            if (strlen($text) > 160) {
                $text = substr($text, 0, 155) . '...';
            }
            return ['success' => true, 'data' => $text];
        }
        
        return ['success' => false, 'error' => 'Failed to generate meta description'];
    }

    /**
     * Improve existing content
     */
    public function improveContent(string $content, string $instruction): array
    {
        $systemPrompt = $this->buildBrandVoicePrompt();

        $userPrompt = "Here is existing content:\n\n{$content}\n\n";
        $userPrompt .= "Instruction: {$instruction}\n\n";
        $userPrompt .= "Rewrite the content following the instruction while maintaining the brand voice.\n";
        $userPrompt .= "Return ONLY the improved content, no explanations.";

        $response = $this->makeRequest($systemPrompt, $userPrompt);
        
        if (isset($response['content'][0]['text'])) {
            return ['success' => true, 'data' => trim($response['content'][0]['text'])];
        }
        
        return ['success' => false, 'error' => 'Failed to improve content'];
    }

    /**
     * General chat for assistant panel
     */
    public function chat(string $message, array $context = []): array
    {
        $systemPrompt = $this->buildBrandVoicePrompt();
        $systemPrompt .= "\nYou are Claude, an AI assistant helping with the Black White Denim blog platform. ";
        $systemPrompt .= "You can help with:\n";
        $systemPrompt .= "- Brainstorming blog ideas\n";
        $systemPrompt .= "- Writing and improving content\n";
        $systemPrompt .= "- Suggesting products to feature\n";
        $systemPrompt .= "- SEO advice\n";
        $systemPrompt .= "- General questions about the platform\n";
        $systemPrompt .= "\nBe helpful, concise, and maintain the brand voice in any content suggestions.";

        $userPrompt = $message;
        
        if (!empty($context)) {
            $userPrompt .= "\n\nContext: " . json_encode($context);
        }

        $response = $this->makeRequest($systemPrompt, $userPrompt);
        
        if (isset($response['content'][0]['text'])) {
            return ['success' => true, 'data' => $response['content'][0]['text']];
        }
        
        return ['success' => false, 'error' => 'Failed to get response'];
    }

    /**
     * Make request to Claude API
     */
    private function makeRequest(string $system, string $user): array
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');

        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $user]
            ]
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Claude API curl error: " . $error);
            return ['error' => $error];
        }

        if ($httpCode !== 200) {
            error_log("Claude API HTTP error {$httpCode}: " . $response);
            return ['error' => "HTTP {$httpCode}", 'response' => $response];
        }

        return json_decode($response, true) ?? ['error' => 'Invalid JSON response'];
    }
}
