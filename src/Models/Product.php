<?php
/**
 * Product Model
 * Handles all product-related database operations
 */

require_once __DIR__ . '/../../config/database.php';

class Product {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Add a new product for a user
     */
    public function addProduct($userId, $amazonUrl, $title = null, $imageUrl = null, $asin = null) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO products (user_id, amazon_url, title, image_url, asin) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            
            $stmt->execute([$userId, $amazonUrl, $title, $imageUrl, $asin]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error adding product: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all active products for a specific user
     */
    public function getUserProducts($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT p.*, 
                        (SELECT COUNT(*) FROM price_history WHERE product_id = p.id) as check_count,
                        (SELECT price FROM price_history WHERE product_id = p.id 
                         ORDER BY checked_at DESC LIMIT 1) as latest_price,
                        (SELECT raw_price FROM price_history WHERE product_id = p.id 
                         ORDER BY checked_at DESC LIMIT 1) as latest_raw_price,
                        (SELECT checked_at FROM price_history WHERE product_id = p.id 
                         ORDER BY checked_at DESC LIMIT 1) as last_checked
                 FROM products p
                 WHERE p.user_id = ? AND p.is_active = 1
                 ORDER BY p.created_at DESC"
            );
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting user products: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a specific product by ID (with user ownership verification)
     */
    public function getProductById($productId, $userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM products 
                 WHERE id = ? AND user_id = ? AND is_active = 1"
            );
            
            $stmt->execute([$productId, $userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting product: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if a product URL already exists for a user
     */
    public function productExists($userId, $amazonUrl) {
        try {
            $stmt = $this->db->prepare(
                "SELECT id FROM products 
                 WHERE user_id = ? AND amazon_url = ? AND is_active = 1"
            );
            
            $stmt->execute([$userId, $amazonUrl]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error checking product existence: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update product information (title, image)
     */
    public function updateProduct($productId, $userId, $title = null, $imageUrl = null, $asin = null) {
        try {
            $updates = [];
            $params = [];
            
            if ($title !== null) {
                $updates[] = "title = ?";
                $params[] = $title;
            }
            
            if ($imageUrl !== null) {
                $updates[] = "image_url = ?";
                $params[] = $imageUrl;
            }
            
            if ($asin !== null) {
                $updates[] = "asin = ?";
                $params[] = $asin;
            }
            
            if (empty($updates)) {
                return true; // Nothing to update
            }
            
            $params[] = $productId;
            $params[] = $userId;
            
            $sql = "UPDATE products SET " . implode(", ", $updates) . 
                   " WHERE id = ? AND user_id = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating product: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Soft delete a product (set is_active to 0)
     */
    public function removeProduct($productId, $userId) {
        try {
            // Verify ownership before deletion
            $stmt = $this->db->prepare(
                "UPDATE products SET is_active = 0 
                 WHERE id = ? AND user_id = ?"
            );
            
            return $stmt->execute([$productId, $userId]);
        } catch (PDOException $e) {
            error_log("Error removing product: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hard delete a product (permanently remove from database)
     * Use with caution - this will also delete all price history due to CASCADE
     */
    public function deleteProduct($productId, $userId) {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM products WHERE id = ? AND user_id = ?"
            );
            
            return $stmt->execute([$productId, $userId]);
        } catch (PDOException $e) {
            error_log("Error deleting product: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get product count for a user
     */
    public function getUserProductCount($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM products 
                 WHERE user_id = ? AND is_active = 1"
            );
            
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error getting product count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Extract ASIN from Amazon URL
     */
    public static function extractAsin($url) {
        // Match patterns like /dp/B0DKFMSMYK/ or /product/B0DKFMSMYK/
        if (preg_match('/\/(?:dp|product)\/([A-Z0-9]{10})/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}