<?php

namespace App\Services;

use App\Helpers\Database;

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
        $products = $params['products'] ?? [];
        
        $productContext = '';
        if (!empty($products)) {
            $productContext = "\n\nAvailable products to feature (choose the most relevant ones):\n";
            foreach (array_slice($products, 0, 15) as $p) {
                $productContext .= "- {$p['title']} by {$p['brand_name']} (£{$p['price']})\n";
            }
        }

        $systemPrompt = <<<PROMPT
You are a content writer for Black White Denim, a UK-based premium fashion retailer. 

Brand Voice Guidelines:
{$brandVoice}

Writing Style:
- Warm, knowledgeable, and inspiring
- Fashion-forward but accessible
- Never pushy or salesy
- Use British English spelling
- Write for a sophisticated audience who appreciates quality fashion

Format Requirements:
- Create engaging, SEO-friendly content
- Each section should be 100-150 words
- Include natural product mentions where relevant
- Use headers that are descriptive and engaging
PROMPT;

        $userPrompt = <<<PROMPT
{$params['prompt']}
{$productContext}

Please generate a complete blog post with the following JSON structure:
{
    "title": "Engaging SEO-friendly title",
    "meta_description": "155 character meta description for SEO",
    "intro": "Engaging introduction paragraph (100-150 words)",
    "sections": [
        {
            "heading": "Section heading",
            "content": "Section content (100-150 words)",
            "cta_text": "Shop Now or similar",
            "cta_url": "/shop/category"
        }
    ],
    "outro": "Closing paragraph with call to action (50-100 words)"
}

Generate 3-5 sections depending on the content type. Return ONLY valid JSON, no markdown or explanation.
PROMPT;

        $response = $this->callApi($systemPrompt, $userPrompt);
        
        // Parse JSON response
        $content = $this->parseJsonResponse($response);
        
        return $content;
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
