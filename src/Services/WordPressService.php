<?php

namespace App\Services;

/**
 * WordPress Service
 * 
 * Handles all communication with WordPress and WooCommerce REST APIs
 */
class WordPressService
{
    private string $baseUrl;
    private string $wpUsername;
    private string $wpPassword;
    private string $wcKey;
    private string $wcSecret;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['wordpress']['api_url'], '/');
        $this->wpUsername = $config['wordpress']['username'] ?? '';
        $this->wpPassword = $config['wordpress']['password'] ?? '';
        $this->wcKey = getenv('WC_CONSUMER_KEY') ?: '';
        $this->wcSecret = getenv('WC_CONSUMER_SECRET') ?: '';
    }

    /**
     * Test the WordPress API connection
     */
    public function testConnection(): array
    {
        try {
            $result = $this->wpRequest('GET', '/wp/v2/users/me');
            return [
                'success' => true,
                'user' => $result['name'] ?? 'Unknown',
                'message' => 'WordPress connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Test the WooCommerce API connection
     */
    public function testWooCommerceConnection(): array
    {
        try {
            $result = $this->wcRequest('GET', '/wc/v3/system_status');
            return [
                'success' => true,
                'message' => 'WooCommerce connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // ==================== POSTS (WordPress API) ====================

    /**
     * Create a new WordPress post
     */
    public function createPost(array $data): array
    {
        $payload = [
            'title' => $data['title'],
            'content' => $data['content'],
            'status' => $data['status'] ?? 'draft',
            'categories' => $data['categories'] ?? [],
            'author' => $data['author'] ?? null,
        ];
        
        if (!empty($data['meta_description'])) {
            $payload['meta'] = [
                '_yoast_wpseo_metadesc' => $data['meta_description']
            ];
        }
        
        if (!empty($data['date'])) {
            $payload['date'] = $data['date'];
            if ($payload['status'] === 'publish') {
                $payload['status'] = 'future';
            }
        }

        return $this->wpRequest('POST', '/wp/v2/posts', $payload);
    }

    /**
     * Update an existing WordPress post
     */
    public function updatePost(int $postId, array $data): array
    {
        return $this->wpRequest('PUT', "/wp/v2/posts/{$postId}", $data);
    }

    /**
     * Get a WordPress post
     */
    public function getPost(int $postId): array
    {
        return $this->wpRequest('GET', "/wp/v2/posts/{$postId}");
    }
    
    /**
     * Build Visual Composer shortcode content from post data
     */
    public function buildVisualComposerContent(array $post, array $sections): string
    {
        $content = '[vc_row columns="1"][vc_column]';
        
        // Intro
        if (!empty($post['intro_content'])) {
            $content .= '[vc_column_text]' . $this->formatContent($post['intro_content']) . '[/vc_column_text]';
        }
        
        // Divider image (using a placeholder - you may want to set a specific image ID)
        $dividerImage = '[us_image image="57585" align="center" css="%7B%22default%22%3A%7B%22max-width%22%3A%22300px%22%2C%22margin-left%22%3A%22auto%22%2C%22margin-top%22%3A%223.5rem%22%2C%22margin-bottom%22%3A%223.5rem%22%2C%22margin-right%22%3A%22auto%22%7D%7D"]';
        
        // Sections
        foreach ($sections as $section) {
            // Add divider before section
            $content .= $dividerImage;
            
            // Section text block with styling
            $content .= '[vc_column_text css="%7B%22default%22%3A%7B%22background-color%22%3A%22_content_bg_alt%22%2C%22padding-left%22%3A%222.5rem%22%2C%22padding-top%22%3A%222.5rem%22%2C%22padding-bottom%22%3A%222.5rem%22%2C%22padding-right%22%3A%222.5rem%22%7D%2C%22mobiles%22%3A%7B%22padding-left%22%3A%221.5rem%22%2C%22padding-right%22%3A%221.5rem%22%7D%7D"]';
            
            // Heading
            if (!empty($section['heading'])) {
                $content .= '<h2>' . htmlspecialchars($section['heading']) . '</h2>' . "\n";
            }
            
            // Content
            if (!empty($section['content'])) {
                $content .= $this->formatContent($section['content']);
            }
            
            // CTA link
            if (!empty($section['cta_text']) && !empty($section['cta_url'])) {
                $content .= "\n\n" . '<strong><a href="' . htmlspecialchars($section['cta_url']) . '" target="_blank" rel="noopener">' . htmlspecialchars($section['cta_text']) . '</a></strong>';
            }
            
            $content .= '[/vc_column_text]';
            
            // Product carousel if brand is set
            if (!empty($section['carousel_brand_id'])) {
                $content .= $this->buildProductCarousel($section['carousel_brand_id'], $section['carousel_category_id'] ?? null);
            }
        }
        
        // Outro
        if (!empty($post['outro_content'])) {
            $content .= $dividerImage;
            $content .= '[vc_column_text css="%7B%22default%22%3A%7B%22background-color%22%3A%22_content_bg_alt%22%2C%22padding-left%22%3A%222.5rem%22%2C%22padding-top%22%3A%222.5rem%22%2C%22padding-bottom%22%3A%222.5rem%22%2C%22padding-right%22%3A%222.5rem%22%7D%7D"]';
            $content .= $this->formatContent($post['outro_content']);
            $content .= '[/vc_column_text]';
        }
        
        $content .= '[/vc_column][/vc_row]';
        
        return $content;
    }
    
    /**
     * Build product carousel shortcode
     */
    private function buildProductCarousel(int $brandId, ?int $categoryId = null): string
    {
        // Build tax_query JSON
        $taxQuery = [];
        
        // Brand filter
        $taxQuery[] = [
            'operator' => 'IN',
            'taxonomy' => 'brand',
            'terms' => (string)$brandId,
            'include_children' => 0
        ];
        
        // Category filter (if provided)
        if ($categoryId) {
            $taxQuery[] = [
                'operator' => 'IN',
                'taxonomy' => 'product_cat',
                'terms' => (string)$categoryId,
                'include_children' => 0
            ];
        }
        
        // URL encode the JSON
        $taxQueryEncoded = urlencode(json_encode($taxQuery));
        
        $carousel = '[us_product_carousel';
        $carousel .= ' items_layout="443"';
        $carousel .= ' items_gap="0.25rem"';
        $carousel .= ' dots="1"';
        $carousel .= ' dots_style="dash"';
        $carousel .= ' responsive="%5B%7B%22breakpoint%22%3A%22mobiles%22%2C%22breakpoint_width%22%3A%221024px%22%2C%22items%22%3A%221%22%2C%22items_offset%22%3A%2250px%22%2C%22center_item%22%3A0%2C%22autoheight%22%3A0%2C%22loop%22%3A0%2C%22autoplay%22%3A0%2C%22arrows%22%3A0%2C%22dots%22%3A1%7D%5D"';
        $carousel .= ' css="%7B%22default%22%3A%7B%22margin-top%22%3A%222.5rem%22%2C%22margin-bottom%22%3A%222.5rem%22%7D%7D"';
        $carousel .= ' next_item_offset="50px"';
        $carousel .= ' arrows="1"';
        $carousel .= ' arrows_style="10"';
        $carousel .= ' no_items_action="page_block"';
        $carousel .= ' no_items_page_block="59375"';
        $carousel .= ' items="2"';
        $carousel .= ' loop="1"';
        $carousel .= ' apply_url_params="1"';
        $carousel .= ' source="all"';
        $carousel .= ' ignore_sticky_posts="0"';
        $carousel .= ' tax_query_relation="AND"';
        $carousel .= ' tax_query="' . $taxQueryEncoded . '"';
        $carousel .= ' order_invert="1"';
        $carousel .= ' exclude_out_of_stock="1"';
        $carousel .= ' quantity="10"';
        $carousel .= ' exclude_past_events="1"';
        $carousel .= ' popup_page_template="0"';
        $carousel .= ']';
        
        return $carousel;
    }
    
    /**
     * Format content for WordPress (convert line breaks to paragraphs)
     */
    private function formatContent(string $content): string
    {
        // Convert double line breaks to paragraphs
        $paragraphs = preg_split('/\n\s*\n/', trim($content));
        $formatted = '';
        
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if (!empty($p)) {
                // Don't wrap if already has HTML tags
                if (!preg_match('/<[^>]+>/', $p)) {
                    $formatted .= $p . "\n\n";
                } else {
                    $formatted .= $p . "\n\n";
                }
            }
        }
        
        return trim($formatted);
    }

    // ==================== CATEGORIES (WordPress API) ====================

    /**
     * Get all blog categories
     */
    public function getCategories(int $perPage = 100): array
    {
        return $this->wpRequest('GET', '/wp/v2/categories', ['per_page' => $perPage]);
    }

    // ==================== AUTHORS (WordPress API) ====================

    /**
     * Get all authors/users who can create posts
     */
    public function getAuthors(int $perPage = 100): array
    {
        return $this->wpRequest('GET', '/wp/v2/users', [
            'per_page' => $perPage,
            'who' => 'authors'
        ]);
    }

    // ==================== PAGE BLOCKS (WordPress API) ====================

    /**
     * Get all reusable page blocks
     */
    public function getPageBlocks(int $perPage = 100): array
    {
        return $this->wpRequest('GET', '/wp/v2/us_page_block', [
            'per_page' => $perPage,
            'status' => 'publish'
        ]);
    }

    // ==================== WOOCOMMERCE PRODUCTS ====================

    /**
     * Get products from WooCommerce
     */
    public function getProducts(array $params = []): array
    {
        $defaults = [
            'per_page' => 100,
            'status' => 'publish',
            'stock_status' => 'instock',
            'orderby' => 'date',
            'order' => 'desc'
        ];
        
        return $this->wcRequest('GET', '/wc/v3/products', array_merge($defaults, $params));
    }

    /**
     * Get all in-stock products (paginated)
     */
    public function getAllProducts(): array
    {
        $allProducts = [];
        $page = 1;
        $perPage = 100;
        
        do {
            $products = $this->wcRequest('GET', '/wc/v3/products', [
                'per_page' => $perPage,
                'page' => $page,
                'status' => 'publish',
                'stock_status' => 'instock'
            ]);
            
            $allProducts = array_merge($allProducts, $products);
            $page++;
            
            // Safety limit
            if ($page > 50) break;
            
        } while (count($products) === $perPage);
        
        return $allProducts;
    }

    /**
     * Get product categories
     */
    public function getProductCategories(int $perPage = 100): array
    {
        return $this->wcRequest('GET', '/wc/v3/products/categories', [
            'per_page' => $perPage,
            'hide_empty' => false
        ]);
    }

    /**
     * Get product brands (custom taxonomy)
     */
    public function getProductBrands(): array
    {
        try {
            return $this->wcRequest('GET', '/wc/v3/products/brands', ['per_page' => 100]);
        } catch (\Exception $e) {
            try {
                return $this->wpRequest('GET', '/wp/v2/brand', ['per_page' => 100]);
            } catch (\Exception $e2) {
                return $this->extractBrandsFromProducts();
            }
        }
    }

    /**
     * Extract unique brands from products
     */
    private function extractBrandsFromProducts(): array
    {
        $products = $this->getAllProducts();
        $brands = [];
        
        foreach ($products as $product) {
            if (!empty($product['attributes'])) {
                foreach ($product['attributes'] as $attr) {
                    if (strtolower($attr['name']) === 'brand' && !empty($attr['options'])) {
                        foreach ($attr['options'] as $brand) {
                            $slug = $this->slugify($brand);
                            if (!isset($brands[$slug])) {
                                $brands[$slug] = [
                                    'name' => $brand,
                                    'slug' => $slug,
                                    'count' => 0
                                ];
                            }
                            $brands[$slug]['count']++;
                        }
                    }
                }
            }
        }
        
        return array_values($brands);
    }

    // ==================== MEDIA ====================

    /**
     * Get media item
     */
    public function getMedia(int $mediaId): array
    {
        return $this->wpRequest('GET', "/wp/v2/media/{$mediaId}");
    }

    // ==================== HTTP REQUESTS ====================

    /**
     * Make WordPress API request (Basic Auth)
     */
    private function wpRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->wpUsername . ':' . $this->wpPassword)
        ];
        
        return $this->makeRequest($method, $url, $headers, $data);
    }

    /**
     * Make WooCommerce API request (OAuth)
     */
    private function wcRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        
        // Add OAuth credentials to URL for WooCommerce
        $separator = strpos($url, '?') === false ? '?' : '&';
        $url .= $separator . 'consumer_key=' . $this->wcKey . '&consumer_secret=' . $this->wcSecret;
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '&' . http_build_query($data);
        }
        
        $headers = ['Content-Type: application/json'];
        
        return $this->makeRequest($method, $url, $headers, $data);
    }

    /**
     * Make HTTP request
     */
    private function makeRequest(string $method, string $url, array $headers, array $data = []): array
    {
        $ch = curl_init($url);
        
        $options = [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'DELETE') {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("cURL Error: {$error}");
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $message = $decoded['message'] ?? "HTTP {$httpCode}";
            throw new \Exception("API Error: {$message}");
        }
        
        return $decoded ?? [];
    }

    /**
     * Helper to create URL-safe slugs
     */
    private function slugify(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }
    
    
    /**
 * Get all brand terms from WordPress REST API
 */
public function getAllBrands(): array
{
    $brands = [];
    $page = 1;
    $perPage = 100;
    
    do {
        $url = $this->baseUrl . "/wp/v2/brand?per_page={$perPage}&page={$page}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            break;
        }
        
        $pageBrands = json_decode($response, true);
        if (empty($pageBrands)) {
            break;
        }
        
        $brands = array_merge($brands, $pageBrands);
        $page++;
        
    } while (count($pageBrands) === $perPage);
    
    return $brands;
}

/**
 * Get all product categories from WooCommerce REST API
 */
public function getAllProductCategories(): array
{
    $categories = [];
    $page = 1;
    $perPage = 100;
    
    do {
        $result = $this->wcRequest('GET', "/wc/v3/products/categories?per_page={$perPage}&page={$page}");
        
        if (empty($result)) {
            break;
        }
        
        $categories = array_merge($categories, $result);
        $page++;
        
    } while (count($result) === $perPage);
    
    return $categories;
}

/**
 * Get brand for a specific product from WordPress REST API
 */
public function getProductBrand(int $productId): ?array
{
    $url = $this->baseUrl . "/wp/v2/brand?post={$productId}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null;
    }
    
    $brands = json_decode($response, true);
    
    if (!empty($brands) && isset($brands[0])) {
        return [
            'id' => $brands[0]['id'],
            'name' => $brands[0]['name'],
            'slug' => $brands[0]['slug']
        ];
    }
    
    return null;
}

/**
 * Get all product IDs that belong to a specific brand
 */
public function getProductIdsByBrand(int $brandId): array
{
    $productIds = [];
    $page = 1;
    $perPage = 100;
    
    do {
        $url = $this->baseUrl . "/wp/v2/product?brand={$brandId}&per_page={$perPage}&page={$page}&_fields=id";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            break;
        }
        
        $products = json_decode($response, true);
        if (empty($products)) {
            break;
        }
        
        foreach ($products as $product) {
            $productIds[] = $product['id'];
        }
        
        $page++;
        
    } while (count($products) === $perPage);
    
    return $productIds;
}
}