<?php
/**
 * PriceHistory Model
 * Handles all price history database operations
 */

require_once __DIR__ . '/../../config/database.php';

class PriceHistory {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Add a new price entry for a product
     */
    public function addPriceEntry($productId, $price, $rawPrice, $currency = 'PHP') {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO price_history (product_id, price, raw_price, currency) 
                 VALUES (?, ?, ?, ?)"
            );
            
            return $stmt->execute([$productId, $price, $rawPrice, $currency]);
        } catch (PDOException $e) {
            error_log("Error adding price entry: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all price history for a product (with user ownership verification)
     */
    public function getProductHistory($productId, $userId) {
        try {
            // Verify user owns this product
            $stmt = $this->db->prepare(
                "SELECT ph.* 
                 FROM price_history ph
                 INNER JOIN products p ON ph.product_id = p.id
                 WHERE ph.product_id = ? AND p.user_id = ? AND p.is_active = 1
                 ORDER BY ph.checked_at DESC"
            );
            
            $stmt->execute([$productId, $userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting product history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get latest price for a product
     */
    public function getLatestPrice($productId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM price_history 
                 WHERE product_id = ? 
                 ORDER BY checked_at DESC LIMIT 1"
            );
            
            $stmt->execute([$productId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting latest price: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get previous price (second most recent)
     */
    public function getPreviousPrice($productId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM price_history 
                 WHERE product_id = ? 
                 ORDER BY checked_at DESC LIMIT 1 OFFSET 1"
            );
            
            $stmt->execute([$productId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting previous price: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get price statistics for a product
     */
    public function getPriceStats($productId, $userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    MIN(ph.price) as lowest_price,
                    MAX(ph.price) as highest_price,
                    AVG(ph.price) as average_price,
                    COUNT(*) as total_checks,
                    MIN(ph.checked_at) as first_check,
                    MAX(ph.checked_at) as last_check
                 FROM price_history ph
                 INNER JOIN products p ON ph.product_id = p.id
                 WHERE ph.product_id = ? AND p.user_id = ?"
            );
            
            $stmt->execute([$productId, $userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting price stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get price change between latest and previous
     */
    public function getPriceChange($productId) {
        try {
            $latest = $this->getLatestPrice($productId);
            $previous = $this->getPreviousPrice($productId);
            
            if (!$latest || !$previous) {
                return null;
            }
            
            $change = $latest['price'] - $previous['price'];
            $percentChange = ($previous['price'] > 0) 
                ? (($change / $previous['price']) * 100) 
                : 0;
            
            return [
                'current_price' => $latest['price'],
                'previous_price' => $previous['price'],
                'change' => $change,
                'percent_change' => $percentChange,
                'direction' => $change > 0 ? 'increase' : ($change < 0 ? 'decrease' : 'same')
            ];
        } catch (Exception $e) {
            error_log("Error calculating price change: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get price history for a date range
     */
    public function getHistoryByDateRange($productId, $userId, $startDate, $endDate) {
        try {
            $stmt = $this->db->prepare(
                "SELECT ph.* 
                 FROM price_history ph
                 INNER JOIN products p ON ph.product_id = p.id
                 WHERE ph.product_id = ? 
                   AND p.user_id = ? 
                   AND ph.checked_at BETWEEN ? AND ?
                 ORDER BY ph.checked_at DESC"
            );
            
            $stmt->execute([$productId, $userId, $startDate, $endDate]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting history by date range: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete old price history entries (for cleanup)
     */
    public function deleteOldEntries($productId, $daysToKeep = 90) {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
            
            $stmt = $this->db->prepare(
                "DELETE FROM price_history 
                 WHERE product_id = ? AND checked_at < ?"
            );
            
            return $stmt->execute([$productId, $cutoffDate]);
        } catch (PDOException $e) {
            error_log("Error deleting old entries: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get total number of price checks across all user's products
     */
    public function getUserTotalChecks($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total
                 FROM price_history ph
                 INNER JOIN products p ON ph.product_id = p.id
                 WHERE p.user_id = ? AND p.is_active = 1"
            );
            
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error getting user total checks: " . $e->getMessage());
            return 0;
        }
    }
}