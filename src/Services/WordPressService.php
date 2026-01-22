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
    private string $username;
    private string $password;
    private array $headers;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['wordpress']['api_url'], '/');
        $this->username = $config['wordpress']['username'] ?? '';
        $this->password = $config['wordpress']['password'] ?? '';
        
        $this->headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password)
        ];
    }

    /**
     * Test the API connection
     */
    public function testConnection(): array
    {
        try {
            $result = $this->request('GET', '/wp/v2/users/me');
            return [
                'success' => true,
                'user' => $result['name'] ?? 'Unknown',
                'message' => 'Connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // ==================== POSTS ====================

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
        
        // Add Yoast SEO meta if provided
        if (!empty($data['meta_description'])) {
            $payload['meta'] = [
                '_yoast_wpseo_metadesc' => $data['meta_description']
            ];
        }
        
        // Schedule post if date provided
        if (!empty($data['date'])) {
            $payload['date'] = $data['date'];
            if ($payload['status'] === 'publish') {
                $payload['status'] = 'future';
            }
        }

        return $this->request('POST', '/wp/v2/posts', $payload);
    }

    /**
     * Update an existing WordPress post
     */
    public function updatePost(int $postId, array $data): array
    {
        return $this->request('PUT', "/wp/v2/posts/{$postId}", $data);
    }

    /**
     * Get a WordPress post
     */
    public function getPost(int $postId): array
    {
        return $this->request('GET', "/wp/v2/posts/{$postId}");
    }

    // ==================== CATEGORIES ====================

    /**
     * Get all blog categories
     */
    public function getCategories(int $perPage = 100): array
    {
        return $this->request('GET', '/wp/v2/categories', ['per_page' => $perPage]);
    }

    // ==================== AUTHORS ====================

    /**
     * Get all authors/users who can create posts
     */
    public function getAuthors(int $perPage = 100): array
    {
        return $this->request('GET', '/wp/v2/users', [
            'per_page' => $perPage,
            'who' => 'authors'
        ]);
    }

    // ==================== PAGE BLOCKS (Impreza) ====================

    /**
     * Get all reusable page blocks
     */
    public function getPageBlocks(int $perPage = 100): array
    {
        return $this->request('GET', '/wp/v2/us_page_block', [
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
            'orderby' => 'date',
            'order' => 'desc'
        ];
        
        return $this->request('GET', '/wc/v3/products', array_merge($defaults, $params));
    }

    /**
     * Get all products (paginated)
     */
    public function getAllProducts(): array
    {
        $allProducts = [];
        $page = 1;
        $perPage = 100;
        
        do {
            $products = $this->request('GET', '/wc/v3/products', [
                'per_page' => $perPage,
                'page' => $page,
                'status' => 'publish'
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
        return $this->request('GET', '/wc/v3/products/categories', [
            'per_page' => $perPage,
            'hide_empty' => false
        ]);
    }

    /**
     * Get product brands (custom taxonomy)
     * Note: Requires WooCommerce Brands plugin or custom taxonomy
     */
    public function getProductBrands(): array
    {
        try {
            // Try standard brands endpoint first
            return $this->request('GET', '/wc/v3/products/brands', ['per_page' => 100]);
        } catch (\Exception $e) {
            // Try as custom taxonomy
            try {
                return $this->request('GET', '/wp/v2/brand', ['per_page' => 100]);
            } catch (\Exception $e2) {
                // Extract brands from products as fallback
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
            // Check attributes for brand
            if (!empty($product['attributes'])) {
                foreach ($product['attributes'] as $attr) {
                    if (strtolower($attr['name']) === 'brand' && !empty($attr['options'])) {
                        foreach ($attr['options'] as $brand) {
                            $slug = sanitize_title($brand);
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
        return $this->request('GET', "/wp/v2/media/{$mediaId}");
    }

    // ==================== HTTP REQUEST ====================

    /**
     * Make HTTP request to WordPress API
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        
        // Add query params for GET requests
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        $ch = curl_init($url);
        
        $options = [
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
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
            throw new \Exception("WordPress API Error: {$message}");
        }
        
        return $decoded ?? [];
    }
}

/**
 * Helper function to create URL-safe slugs
 */
function sanitize_title(string $title): string
{
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}
