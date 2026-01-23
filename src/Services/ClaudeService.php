<?php

namespace App\Services;

use App\Helpers\Database;
use App\Controllers\WritingGuidelinesController;

/**
 * Claude AI Service
 * Handles all AI content generation
 */
class ClaudeService
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private string $baseUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct(array $config)
    {
        $this->apiKey = $config['claude']['api_key'] ?? getenv('CLAUDE_API_KEY');
        $this->model = $config['claude']['model'] ?? 'claude-sonnet-4-20250514';
        $this->maxTokens = $config['claude']['max_tokens'] ?? 4096;
    }

    /**
     * Generate a complete blog post
     */
    public function generateBlogPost(array $params): array
    {
        $brandVoice = $this->getBrandVoice();
        $writingGuidelines = WritingGuidelinesController::getForPrompt();
        $products = $params['products'] ?? [];
        
        // Get valid brand + category combinations that actually have products in stock
        $validCombos = Database::query(
            "SELECT 
                b.wp_term_id as brand_id,
                b.name as brand_name,
                pc.wp_term_id as category_id,
                pc.name as category_name,
                COUNT(*) as product_count
             FROM wp_products p
             JOIN wp_brands b ON p.brand_id = b.wp_term_id
             JOIN wp_product_categories pc ON JSON_CONTAINS(p.category_slugs, CONCAT('\"', pc.slug, '\"'))
             WHERE p.stock_status = 'instock'
             GROUP BY b.wp_term_id, b.name, pc.wp_term_id, pc.name
             HAVING product_count >= 3
             ORDER BY b.name, pc.name
             LIMIT 100"
        );
        
        // Also get brands with their total product counts (for brand-only carousels)
        $brandsOnly = Database::query(
            "SELECT 
                b.wp_term_id as brand_id,
                b.name as brand_name,
                COUNT(*) as product_count
             FROM wp_products p
             JOIN wp_brands b ON p.brand_id = b.wp_term_id
             WHERE p.stock_status = 'instock'
             GROUP BY b.wp_term_id, b.name
             HAVING product_count >= 5
             ORDER BY product_count DESC
             LIMIT 50"
        );
        
        $comboList = '';
        if (!empty($validCombos)) {
            $comboList = "\n\nVALID BRAND + CATEGORY COMBINATIONS (these have actual products in stock):\n";
            $comboList .= "Format: Brand Name (brand_id) + Category Name (category_id) - X products\n";
            foreach ($validCombos as $c) {
                $comboList .= "- {$c['brand_name']} ({$c['brand_id']}) + {$c['category_name']} ({$c['category_id']}) - {$c['product_count']} products\n";
            }
        }
        
        $brandOnlyList = '';
        if (!empty($brandsOnly)) {
            $brandOnlyList = "\n\nBRANDS FOR BRAND-ONLY CAROUSELS (no category filter):\n";
            foreach ($brandsOnly as $b) {
                $brandOnlyList .= "- {$b['brand_name']} (brand_id: {$b['brand_id']}) - {$b['product_count']} products\n";
            }
        }
        
        $productContext = '';
        if (!empty($products)) {
            $productContext = "\n\nSample products available:\n";
            foreach (array_slice($products, 0, 15) as $p) {
                $productContext .= "- {$p['title']} by {$p['brand_name']} (£{$p['price']})\n";
            }
        }

        $systemPrompt = <<<PROMPT
You are a content writer for Black White Denim, a UK-based premium fashion retailer. 

Brand Voice Guidelines:
{$brandVoice}
{$writingGuidelines}

Writing Style:
- Warm, knowledgeable, and inspiring
- Fashion-forward but accessible
- Never pushy or salesy
- Use British English spelling
- Write like a stylish friend giving advice, not a corporate copywriter

Format Requirements:
- Create engaging, SEO-friendly content
- Each section should be 100-150 words
- Include natural product mentions where relevant
- Use headers that are descriptive and engaging

CRITICAL: You MUST only use brand/category combinations from the provided lists. These are the only combinations that have actual products. Do not invent or assume combinations exist.
PROMPT;

        $userPrompt = <<<PROMPT
{$params['prompt']}
{$comboList}
{$brandOnlyList}
{$productContext}

Please generate a complete blog post with the following JSON structure:
{
    "title": "Engaging SEO-friendly title",
    "meta_description": "155 character meta description for SEO",
    "intro": "Engaging introduction paragraph (100-150 words)",
    "sections": [
        {
            "heading": "Section heading",
            "content": "Section content (100-150 words) - mention the featured brand naturally",
            "cta_text": "Shop Now or similar",
            "cta_url": "/shop/category",
            "carousel_brand_id": 123,
            "carousel_category_id": 456
        }
    ],
    "outro": "Closing paragraph with call to action (50-100 words)"
}

IMPORTANT RULES FOR CAROUSELS:
1. ONLY use brand_id and category_id combinations from the VALID COMBINATIONS list above
2. If using a brand-only carousel (no category filter), set carousel_category_id to null
3. Never invent combinations - if a brand+category isn't in the list, don't use it
4. Each section should feature a different brand for variety

Generate 3-5 sections. Return ONLY valid JSON, no markdown or explanation.
PROMPT;

        $response = $this->callApi($systemPrompt, $userPrompt);
        
        // Parse JSON response
        $content = $this->parseJsonResponse($response);
        
        // Validate carousel combinations have actual products
        if (!empty($content['sections'])) {
            foreach ($content['sections'] as &$section) {
                $brandId = $section['carousel_brand_id'] ?? null;
                $categoryId = $section['carousel_category_id'] ?? null;
                
                if ($brandId) {
                    // Check if this combination has products
                    $hasProducts = $this->validateCarouselCombo($brandId, $categoryId);
                    
                    if (!$hasProducts) {
                        // Try brand-only (remove category)
                        if ($this->validateCarouselCombo($brandId, null)) {
                            $section['carousel_category_id'] = null;
                            error_log("Carousel validation: Removed invalid category for brand {$brandId}");
                        } else {
                            // No products at all - clear carousel
                            $section['carousel_brand_id'] = null;
                            $section['carousel_category_id'] = null;
                            error_log("Carousel validation: Cleared invalid carousel for brand {$brandId}");
                        }
                    }
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Validate that a brand/category combination has products in stock
     */
    private function validateCarouselCombo(?int $brandId, ?int $categoryId): bool
    {
        if (!$brandId) {
            return false;
        }
        
        if ($categoryId) {
            // Check brand + category combo
            $result = Database::queryOne(
                "SELECT COUNT(*) as cnt FROM wp_products p
                 JOIN wp_product_categories pc ON JSON_CONTAINS(p.category_slugs, CONCAT('\"', pc.slug, '\"'))
                 WHERE p.brand_id = ? AND pc.wp_term_id = ? AND p.stock_status = 'instock'",
                [$brandId, $categoryId]
            );
        } else {
            // Check brand only
            $result = Database::queryOne(
                "SELECT COUNT(*) as cnt FROM wp_products 
                 WHERE brand_id = ? AND stock_status = 'instock'",
                [$brandId]
            );
        }
        
        return ($result['cnt'] ?? 0) >= 3;
    }

    /**
     * Generate content for a single section
     */
    public function generateSection(array $params): array
    {
        $brandVoice = $this->getBrandVoice();
        
        $systemPrompt = "You are a content writer for Black White Denim, a UK premium fashion retailer. {$brandVoice}";
        
        $userPrompt = <<<PROMPT
Write a blog section about: {$params['topic']}

Context: {$params['context']}

Return JSON:
{
    "heading": "Section heading",
    "content": "Section content (100-150 words)",
    "cta_text": "Call to action button text",
    "cta_url": "/relevant/url"
}

Return ONLY valid JSON.
PROMPT;

        $response = $this->callApi($systemPrompt, $userPrompt);
        return $this->parseJsonResponse($response);
    }

    /**
     * Generate meta description
     */
    public function generateMetaDescription(string $title, string $content): string
    {
        $systemPrompt = "You are an SEO specialist. Generate concise, compelling meta descriptions.";
        
        $userPrompt = <<<PROMPT
Generate a meta description for this blog post:
Title: {$title}
Content preview: {$content}

Requirements:
- Exactly 150-155 characters
- Include relevant keywords naturally
- Compelling and click-worthy
- British English

Return ONLY the meta description text, nothing else.
PROMPT;

        return trim($this->callApi($systemPrompt, $userPrompt));
    }

    /**
     * Suggest products for a blog post
     */
    public function suggestProducts(array $params): array
    {
        $topic = $params['topic'] ?? '';
        $contentType = $params['content_type'] ?? 'general';
        
        // Get available products
        $products = Database::query(
            "SELECT wc_product_id, title, brand_name, price, category_names 
             FROM wp_products 
             WHERE stock_status = 'instock' 
             ORDER BY RAND() 
             LIMIT 50"
        );
        
        $productList = '';
        foreach ($products as $p) {
            $productList .= "ID:{$p['wc_product_id']} - {$p['title']} by {$p['brand_name']} (£{$p['price']})\n";
        }
        
        $systemPrompt = "You are a fashion merchandiser selecting products for blog posts.";
        
        $userPrompt = <<<PROMPT
Select the 8 most relevant products for a blog post about: {$topic}
Content type: {$contentType}

Available products:
{$productList}

Return a JSON array of product IDs that best fit the topic:
["12345", "67890", ...]

Consider:
- Relevance to the topic
- Mix of price points
- Variety of brands
- Visual appeal

Return ONLY the JSON array.
PROMPT;

        $response = $this->callApi($systemPrompt, $userPrompt);
        $productIds = $this->parseJsonResponse($response);
        
        if (!is_array($productIds)) {
            return [];
        }
        
        // Get full product details
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        return Database::query(
            "SELECT * FROM wp_products WHERE wc_product_id IN ({$placeholders})",
            $productIds
        );
    }

    /**
     * Brainstorm content ideas
     */
    public function brainstorm(array $params): array
    {
        $topic = $params['topic'] ?? 'fashion content';
        $count = $params['count'] ?? 5;
        $brandVoice = $this->getBrandVoice();
        
        $systemPrompt = "You are a creative content strategist for a UK premium fashion retailer.";
        
        $userPrompt = <<<PROMPT
Generate {$count} blog post ideas related to: {$topic}

Brand context: {$brandVoice}

For each idea, provide:
{
    "ideas": [
        {
            "title": "Suggested blog title",
            "description": "Brief description of the post (1-2 sentences)",
            "content_type": "gift-guide|style-guide|trend-report|brand-spotlight|new-arrivals|curated-edit",
            "target_audience": "Who this post is for",
            "estimated_products": 5-10
        }
    ]
}

Return ONLY valid JSON.
PROMPT;

        $response = $this->callApi($systemPrompt, $userPrompt);
        return $this->parseJsonResponse($response);
    }

    /**
     * Chat with Claude
     */
    public function chat(string $message, array $context = []): string
    {
        $brandVoice = $this->getBrandVoice();
        
        $systemPrompt = <<<PROMPT
You are a helpful AI assistant for Black White Denim's blog platform. You help with:
- Content ideas and strategy
- Writing and editing blog posts
- Product selection and merchandising
- SEO optimization

Brand context: {$brandVoice}

Be helpful, creative, and knowledgeable about fashion and content marketing.
PROMPT;

        return $this->callApi($systemPrompt, $message);
    }

    /**
     * Make API call to Claude
     */
    private function callApi(string $systemPrompt, string $userPrompt): string
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt]
            ]
        ];

        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Claude API error: {$error}");
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $message = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \Exception("Claude API error: {$message}");
        }

        $decoded = json_decode($response, true);
        return $decoded['content'][0]['text'] ?? '';
    }

    /**
     * Parse JSON from Claude response
     */
    private function parseJsonResponse(string $response): array
    {
        // Try to extract JSON from response
        $response = trim($response);
        
        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $response = $matches[1];
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to parse Claude JSON response: " . json_last_error_msg());
            error_log("Response was: " . substr($response, 0, 500));
            return [];
        }
        
        return $decoded;
    }

    /**
     * Get brand voice from database
     */
    private function getBrandVoice(): string
    {
        $voice = Database::query(
            "SELECT attribute, description FROM brand_voice WHERE is_active = 1"
        );
        
        if (empty($voice)) {
            return "Professional, fashion-forward UK retailer targeting style-conscious customers.";
        }
        
        $voiceText = "Brand voice attributes:\n";
        foreach ($voice as $v) {
            $voiceText .= "- {$v['attribute']}: {$v['description']}\n";
        }
        
        return $voiceText;
    }
}
