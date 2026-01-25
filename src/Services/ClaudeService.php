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
                b.slug as brand_slug,
                pc.wp_term_id as category_id,
                pc.name as category_name,
                pc.slug as category_slug,
                COUNT(*) as product_count
             FROM wp_products p
             JOIN wp_brands b ON p.brand_id = b.wp_term_id
             JOIN wp_product_categories pc ON JSON_CONTAINS(p.category_slugs, CONCAT('\"', pc.slug, '\"'))
             WHERE p.stock_status = 'instock'
             GROUP BY b.wp_term_id, b.name, b.slug, pc.wp_term_id, pc.name, pc.slug
             HAVING product_count >= 3
             ORDER BY b.name, pc.name
             LIMIT 100"
        );
        
        // Also get brands with their total product counts (for brand-only carousels)
        $brandsOnly = Database::query(
            "SELECT 
                b.wp_term_id as brand_id,
                b.name as brand_name,
                b.slug as brand_slug,
                COUNT(*) as product_count
             FROM wp_products p
             JOIN wp_brands b ON p.brand_id = b.wp_term_id
             WHERE p.stock_status = 'instock'
             GROUP BY b.wp_term_id, b.name, b.slug
             HAVING product_count >= 5
             ORDER BY product_count DESC
             LIMIT 50"
        );
        
        $comboList = '';
        if (!empty($validCombos)) {
            $comboList = "\n\nVALID BRAND + CATEGORY COMBINATIONS (these have actual products in stock):\n";
            $comboList .= "Format: Brand Name (brand_id, slug) + Category Name (category_id, slug) - X products\n";
            foreach ($validCombos as $c) {
                $comboList .= "- {$c['brand_name']} (ID:{$c['brand_id']}, slug:{$c['brand_slug']}) + {$c['category_name']} (ID:{$c['category_id']}, slug:{$c['category_slug']}) - {$c['product_count']} products\n";
            }
        }
        
        $brandOnlyList = '';
        if (!empty($brandsOnly)) {
            $brandOnlyList = "\n\nBRANDS FOR BRAND-ONLY CAROUSELS (no category filter):\n";
            $brandOnlyList .= "Use the slug for CTA URLs: /brand/slug/\n";
            foreach ($brandsOnly as $b) {
                $brandOnlyList .= "- {$b['brand_name']} (ID:{$b['brand_id']}, slug:{$b['brand_slug']}) - {$b['product_count']} products\n";
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
- ALWAYS use British English spelling (colour, favourite, accessorise, centre, organised, realise, etc.)
- Write like a stylish friend giving advice, not a corporate copywriter

BRITISH ENGLISH IS MANDATORY - Examples:
- colour NOT color
- favourite NOT favorite  
- accessorise NOT accessorize
- centre NOT center
- organised NOT organized
- realise NOT realize
- travelling NOT traveling
- jewellery NOT jewelry

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
            "cta_url": "/brand/brand-slug/",
            "carousel_brand_id": 123,
            "carousel_category_id": 456
        }
    ],
    "outro": "Closing paragraph with call to action (50-100 words)"
}

CRITICAL RULES FOR SECTIONS AND CAROUSELS:

1. NUMBER OF SECTIONS: Create only as many sections as the topic naturally requires. If discussing one brand with 2 product categories, you only need 2 sections. Don't pad with extra sections.

2. CAROUSEL UNIQUENESS: Every carousel MUST show different products. Never create two sections with the same brand+category combination. If you can't show something different, DON'T add a carousel to that section.

3. SECTION WITHOUT CAROUSEL: It's perfectly fine for a section to have NO carousel (set both carousel_brand_id and carousel_category_id to null). Use this when:
   - The section is introductory or transitional
   - You've already used all relevant brand/category combos
   - The section discusses styling tips or general advice

4. MATCHING CONTENT TO CAROUSELS: Only add a carousel if that section specifically discusses those products. Don't force carousels into every section.

5. CTA URLs: Use /brand/brand-slug/ for brand pages, /product-category/category-slug/ for categories.

EXAMPLE: If writing about "IZIPIZI Reading Glasses and Sunglasses":
- Section 1: Reading glasses content → carousel: IZIPIZI + Reading Glasses
- Section 2: Sunglasses content → carousel: IZIPIZI + Sunglasses  
- Section 3 (if needed): Styling tips → NO carousel (null values)
- WRONG: 5 sections all trying to show IZIPIZI products with repeated carousels

Return ONLY valid JSON, no markdown or explanation.
PROMPT;

        $response = $this->callApi($systemPrompt, $userPrompt);
        
        // Parse JSON response
        $content = $this->parseJsonResponse($response);
        
        // Validate carousel combinations and remove duplicates
        if (!empty($content['sections'])) {
            $usedCombos = []; // Track used brand+category combinations
            
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
                            $categoryId = null;
                            error_log("Carousel validation: Removed invalid category for brand {$brandId}");
                        } else {
                            // No products at all - clear carousel
                            $section['carousel_brand_id'] = null;
                            $section['carousel_category_id'] = null;
                            error_log("Carousel validation: Cleared invalid carousel for brand {$brandId}");
                            continue;
                        }
                    }
                    
                    // Check for duplicate combo - this is critical
                    $comboKey = $brandId . '-' . ($categoryId ?? 'all');
                    if (in_array($comboKey, $usedCombos)) {
                        // Duplicate carousel - remove it, section can stay without carousel
                        $section['carousel_brand_id'] = null;
                        $section['carousel_category_id'] = null;
                        error_log("Carousel validation: Removed duplicate combo {$comboKey} - section kept without carousel");
                        continue;
                    }
                    
                    // Valid unique carousel - track it
                    $usedCombos[] = $comboKey;
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
        
        $systemPrompt = "You are a content writer for Black White Denim, a UK premium fashion retailer. ALWAYS use British English spelling (colour, favourite, accessorise, centre, organised, realise, jewellery). {$brandVoice}";
        
        $userPrompt = <<<PROMPT
Write a blog section about: {$params['topic']}

Context: {$params['context']}

IMPORTANT: Use British English spelling throughout (accessorise NOT accessorize, colour NOT color, etc.)

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
        $systemPrompt = "You are an SEO specialist for a UK fashion retailer. ALWAYS use British English spelling.";
        
        $userPrompt = <<<PROMPT
Generate a meta description for this blog post:
Title: {$title}
Content preview: {$content}

Requirements:
- Exactly 150-155 characters
- Include relevant keywords naturally
- Compelling and click-worthy
- MUST use British English spelling (colour, favourite, accessorise, centre, organised)

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
        $topic = $params['prompt'] ?? $params['topic'] ?? 'fashion content';
        $count = $params['count'] ?? 5;
        $seasonalContext = $params['seasonal_context'] ?? null;
        $brandVoice = $this->getBrandVoice();
        
        // Get actual brands from BWD
        $brands = Database::query(
            "SELECT name FROM wp_brands WHERE count > 0 ORDER BY count DESC LIMIT 30"
        );
        $brandList = implode(', ', array_column($brands, 'name'));
        
        // Get actual categories from BWD
        $categories = Database::query(
            "SELECT name FROM wp_product_categories WHERE count > 0 AND parent_id = 0 ORDER BY count DESC LIMIT 20"
        );
        $categoryList = implode(', ', array_column($categories, 'name'));
        
        // Get upcoming seasonal events
        $events = Database::query(
            "SELECT name, start_date FROM seasonal_events 
             WHERE start_date >= CURDATE() AND start_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
             ORDER BY start_date LIMIT 5"
        );
        $eventList = !empty($events) ? implode(', ', array_column($events, 'name')) : 'No upcoming events';
        
        $systemPrompt = "You are a creative content strategist for Black White Denim, a UK premium fashion retailer. ALWAYS use British English spelling (e.g., colour, favourites, accessorise, centre, organised).";
        
        $userPrompt = <<<PROMPT
Generate {$count} blog post ideas related to: {$topic}

BLACK WHITE DENIM CONTEXT:
- Brands we stock: {$brandList}
- Product categories: {$categoryList}
- Upcoming events/seasons: {$eventList}
{$seasonalContext}

Brand voice: {$brandVoice}

IMPORTANT RULES:
1. Ideas MUST feature brands and products that Black White Denim actually stocks (listed above)
2. Be specific - mention actual brand names and product types we sell
3. ALWAYS use British English spelling (colour NOT color, favourite NOT favorite, accessorise NOT accessorize, centre NOT center, organised NOT organized, etc.)

For each idea, provide:
{
    "ideas": [
        {
            "title": "Suggested blog title",
            "description": "Brief description mentioning specific brands/products we stock",
            "content_type": "gift-guide|style-guide|trend-report|brand-spotlight|new-arrivals|seasonal",
            "target_audience": "Who this post is for",
            "suggested_brands": ["Brand1", "Brand2"],
            "suggested_categories": ["Category1", "Category2"]
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
You are a helpful AI assistant for Black White Denim's blog platform (UK-based). You help with:
- Content ideas and strategy
- Writing and editing blog posts
- Product selection and merchandising
- SEO optimization

Brand context: {$brandVoice}

Be helpful, creative, and knowledgeable about fashion and content marketing.
ALWAYS use British English spelling (colour, favourite, accessorise, centre, organised, realise, jewellery).
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
    
    /**
     * AI Assistant for refining a specific post
     */
    public function postAssistant(array $params): array
    {
        $message = $params['message'] ?? '';
        $post = $params['post'] ?? [];
        $history = $params['history'] ?? [];
        
        $brandVoice = $this->getBrandVoice();
        $writingGuidelines = WritingGuidelinesController::getForPrompt();
        
        // Build current post context
        $postContext = "CURRENT POST STATE:\n";
        $postContext .= "Title: " . ($post['title'] ?? 'Untitled') . "\n";
        $postContext .= "Intro: " . ($post['intro_content'] ?? 'Empty') . "\n";
        $postContext .= "Outro: " . ($post['outro_content'] ?? 'Empty') . "\n";
        $postContext .= "Meta Description: " . ($post['meta_description'] ?? 'Empty') . "\n\n";
        
        if (!empty($post['sections'])) {
            $postContext .= "SECTIONS:\n";
            foreach ($post['sections'] as $i => $section) {
                $postContext .= "Section " . ($i + 1) . " (index: {$i}):\n";
                $postContext .= "  Heading: " . ($section['heading'] ?? 'Empty') . "\n";
                $postContext .= "  Content: " . ($section['content'] ?? 'Empty') . "\n";
                $postContext .= "  CTA: " . ($section['cta_text'] ?? '') . " -> " . ($section['cta_url'] ?? '') . "\n";
                $postContext .= "  Carousel Brand ID: " . ($section['carousel_brand_id'] ?? 'None') . "\n";
                $postContext .= "  Carousel Category ID: " . ($section['carousel_category_id'] ?? 'None') . "\n\n";
            }
        }
        
        $systemPrompt = <<<PROMPT
You are an AI assistant helping refine a blog post for Black White Denim, a UK premium fashion retailer.

{$brandVoice}
{$writingGuidelines}

The user will ask you to make changes to their post. You should:
1. Understand what they want to change
2. Make the requested changes while maintaining the brand voice
3. Return a JSON response with your message and any updates

RESPONSE FORMAT - You MUST return valid JSON only:
{
    "message": "A friendly explanation of what you changed",
    "updates": {
        "title": "New title if changed",
        "intro_content": "New intro if changed",
        "outro_content": "New outro if changed",
        "meta_description": "New meta if changed",
        "sections": [
            {
                "index": 0,
                "heading": "New heading if changed",
                "content": "New content if changed",
                "cta_text": "New CTA text if changed",
                "cta_url": "New CTA URL if changed",
                "carousel_brand_id": null,
                "carousel_category_id": null
            }
        ]
    }
}

RULES:
- Only include fields in "updates" that you actually changed
- If no changes needed, set "updates" to null
- Section updates must include "index" (0-based)
- To remove a carousel, set carousel_brand_id and carousel_category_id to null
- Keep content concise and on-brand
- ALWAYS use British English spelling (colour, favourite, accessorise, centre, organised, realise, jewellery, travelling)
- Be conversational in your message
PROMPT;

        // Build conversation history
        $messages = [];
        foreach ($history as $msg) {
            if ($msg['role'] === 'user') {
                $messages[] = ['role' => 'user', 'content' => $msg['content']];
            } else {
                $messages[] = ['role' => 'assistant', 'content' => $msg['content']];
            }
        }
        
        // Add current request with post context
        $userMessage = "{$postContext}\n\nUSER REQUEST: {$message}";
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        
        // Call Claude API
        $response = $this->callApiWithMessages($systemPrompt, $messages);
        
        // Parse JSON response
        $result = $this->parseJsonResponse($response);
        
        if (empty($result)) {
            return [
                'message' => $response, // Return raw response if JSON parsing fails
                'updates' => null
            ];
        }
        
        return [
            'message' => $result['message'] ?? 'Changes applied.',
            'updates' => $result['updates'] ?? null
        ];
    }
    
    /**
     * Call Claude API with message history
     */
    private function callApiWithMessages(string $systemPrompt, array $messages): string
    {
        $apiKey = getenv('CLAUDE_API_KEY');
        
        if (!$apiKey) {
            throw new \Exception('Claude API key not configured');
        }
        
        $payload = [
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => $messages
        ];
        
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Claude API error: HTTP {$httpCode} - {$response}");
            throw new \Exception("Claude API error: HTTP {$httpCode}");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['content'][0]['text'])) {
            error_log("Unexpected Claude API response: " . $response);
            throw new \Exception('Unexpected API response format');
        }
        
        return $data['content'][0]['text'];
    }
}
