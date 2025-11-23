<?php
/**
 * PriceTracker Service
 * Integrates Amazon scraper with database models
 */

require_once __DIR__ . '/../../amazon_scraper.php';
require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Models/PriceHistory.php';

class PriceTracker {
    private $scraper;
    private $productModel;
    private $historyModel;
    private $exchangeRate;
    
    public function __construct($exchangeRate = 59.0) {
        $this->exchangeRate = $exchangeRate;
        $this->scraper = new AmazonScraper($exchangeRate);
        $this->productModel = new Product();
        $this->historyModel = new PriceHistory();
    }
    
    /**
     * Add a new product and immediately scrape it
     */
    public function addAndScrapeProduct($userId, $amazonUrl) {
        // Check if product already exists
        if ($this->productModel->productExists($userId, $amazonUrl)) {
            return [
                'success' => false,
                'error' => 'Product already exists in your tracking list'
            ];
        }
        
        // Scrape product data
        $scrapedData = $this->scraper->parseProduct($amazonUrl);
        
        if (!$scrapedData) {
            return [
                'success' => false,
                'error' => 'Failed to scrape product data from Amazon'
            ];
        }
        
        // Extract ASIN from URL
        $asin = Product::extractAsin($amazonUrl);
        
        // Add product to database
        $productId = $this->productModel->addProduct(
            $userId,
            $amazonUrl,
            $scrapedData['title'],
            $scrapedData['image_url'],
            $asin
        );
        
        if (!$productId) {
            return [
                'success' => false,
                'error' => 'Failed to add product to database'
            ];
        }
        
        // Add initial price entry
        $priceAdded = $this->historyModel->addPriceEntry(
            $productId,
            $scrapedData['price'],
            $scrapedData['raw_price']
        );
        
        if (!$priceAdded) {
            return [
                'success' => false,
                'error' => 'Product added but failed to record initial price'
            ];
        }
        
        return [
            'success' => true,
            'product_id' => $productId,
            'title' => $scrapedData['title'],
            'price' => $scrapedData['price'],
            'raw_price' => $scrapedData['raw_price']
        ];
    }
    
    /**
     * Track all products for a specific user
     */
    public function trackUserProducts($userId) {
        $products = $this->productModel->getUserProducts($userId);
        $results = [
            'total' => count($products),
            'successful' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        foreach ($products as $product) {
            $result = $this->trackSingleProduct($product, $userId);
            $results['details'][] = $result;
            
            if ($result['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
            
            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }
        
        return $results;
    }
    
    /**
     * Track a single product
     */
    private function trackSingleProduct($product, $userId) {
        try {
            $scrapedData = $this->scraper->parseProduct($product['amazon_url']);
            
            if (!$scrapedData) {
                return [
                    'success' => false,
                    'product_id' => $product['id'],
                    'title' => $product['title'],
                    'error' => 'Failed to scrape product'
                ];
            }
            
            // Update product info if title or image changed
            if ($product['title'] !== $scrapedData['title'] || 
                $product['image_url'] !== $scrapedData['image_url']) {
                $this->productModel->updateProduct(
                    $product['id'],
                    $userId,
                    $scrapedData['title'],
                    $scrapedData['image_url']
                );
            }
            
            // Add new price entry
            $this->historyModel->addPriceEntry(
                $product['id'],
                $scrapedData['price'],
                $scrapedData['raw_price']
            );
            
            // Get price change info
            $priceChange = $this->historyModel->getPriceChange($product['id']);
            
            return [
                'success' => true,
                'product_id' => $product['id'],
                'title' => $scrapedData['title'],
                'price' => $scrapedData['price'],
                'raw_price' => $scrapedData['raw_price'],
                'price_change' => $priceChange
            ];
        } catch (Exception $e) {
            error_log("Error tracking product {$product['id']}: " . $e->getMessage());
            return [
                'success' => false,
                'product_id' => $product['id'],
                'title' => $product['title'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update a single product by ID
     */
    public function updateProduct($productId, $userId) {
        $product = $this->productModel->getProductById($productId, $userId);
        
        if (!$product) {
            return [
                'success' => false,
                'error' => 'Product not found or access denied'
            ];
        }
        
        return $this->trackSingleProduct($product, $userId);
    }
    
    /**
     * Get product with latest price and change info
     */
    public function getProductWithPriceInfo($productId, $userId) {
        $product = $this->productModel->getProductById($productId, $userId);
        
        if (!$product) {
            return null;
        }
        
        $latestPrice = $this->historyModel->getLatestPrice($productId);
        $priceChange = $this->historyModel->getPriceChange($productId);
        $stats = $this->historyModel->getPriceStats($productId, $userId);
        
        return [
            'product' => $product,
            'latest_price' => $latestPrice,
            'price_change' => $priceChange,
            'stats' => $stats
        ];
    }
    
    /**
     * Get all products with their latest prices for a user
     */
    public function getUserProductsWithPrices($userId) {
        $products = $this->productModel->getUserProducts($userId);
        $result = [];
        
        foreach ($products as $product) {
            $priceChange = null;
            
            if ($product['check_count'] > 1) {
                $priceChange = $this->historyModel->getPriceChange($product['id']);
            }
            
            $result[] = [
                'id' => $product['id'],
                'title' => $product['title'],
                'image_url' => $product['image_url'],
                'amazon_url' => $product['amazon_url'],
                'latest_price' => $product['latest_price'],
                'latest_raw_price' => $product['latest_raw_price'],
                'last_checked' => $product['last_checked'],
                'check_count' => $product['check_count'],
                'price_change' => $priceChange,
                'created_at' => $product['created_at']
            ];
        }
        
        return $result;
    }
}