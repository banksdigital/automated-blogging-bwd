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
        $url = rtrim($this->config['wordpress']['api_url'], '/') . "/wp/v2/brand?per_page={$perPage}&page={$page}";
        
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
 * Get brand for a specific product from WordPress REST API
 */
public function getProductBrand(int $productId): ?array
{
    $url = rtrim($this->config['wordpress']['api_url'], '/') . "/wp/v2/brand?post={$productId}";
    
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
     * Get taxonomy SEO fields from WordPress via ACF
     */
    public function getTaxonomySeo(string $taxonomy, int $termId): array
    {
        try {
            // Use the WordPress REST API to get term with ACF fields
            $endpoint = "/wp/v2/{$taxonomy}/{$termId}";
            $result = $this->wpRequest('GET', $endpoint);
            
            // ACF fields are typically in the 'acf' key or 'meta' key
            $acf = $result['acf'] ?? [];
            
            return [
                'taxonomy_description' => $acf['taxonomy_description'] ?? $result['description'] ?? '',
                'taxonomy_seo_description' => $acf['taxonomy_seo_description'] ?? ''
            ];
        } catch (\Exception $e) {
            error_log("Failed to get taxonomy SEO for {$taxonomy}/{$termId}: " . $e->getMessage());
            return [
                'taxonomy_description' => '',
                'taxonomy_seo_description' => ''
            ];
        }
    }

    /**
     * Update taxonomy SEO fields in WordPress via ACF
     */
    public function updateTaxonomySeo(string $taxonomy, int $termId, array $seoData): bool
    {
        try {
            // Update via WordPress REST API with ACF fields
            $endpoint = "/wp/v2/{$taxonomy}/{$termId}";
            
            $data = [
                'acf' => [
                    'taxonomy_description' => $seoData['taxonomy_description'] ?? '',
                    'taxonomy_seo_description' => $seoData['taxonomy_seo_description'] ?? ''
                ]
            ];
            
            $result = $this->wpRequest('POST', $endpoint, $data);
            
            return isset($result['id']);
        } catch (\Exception $e) {
            error_log("Failed to update taxonomy SEO for {$taxonomy}/{$termId}: " . $e->getMessage());
            throw $e;
        }
    }
}