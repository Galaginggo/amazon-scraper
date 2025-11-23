<?php
/**
 * UserSettings Model
 * Handles user-specific settings and preferences
 */

require_once __DIR__ . '/../../config/database.php';

class UserSettings {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get user settings (creates default if not exists)
     */
    public function getUserSettings($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM user_settings WHERE user_id = ?"
            );
            
            $stmt->execute([$userId]);
            $settings = $stmt->fetch();
            
            // Create default settings if they don't exist
            if (!$settings) {
                $this->createDefaultSettings($userId);
                $stmt->execute([$userId]);
                $settings = $stmt->fetch();
            }
            
            return $settings;
        } catch (PDOException $e) {
            error_log("Error getting user settings: " . $e->getMessage());
            return $this->getDefaultSettings();
        }
    }
    
    /**
     * Create default settings for a user
     */
    public function createDefaultSettings($userId) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO user_settings (user_id, auto_update_enabled, update_interval_minutes, exchange_rate, timezone, email_notifications) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            
            return $stmt->execute([
                $userId,
                false,  // auto_update_enabled
                60,     // update_interval_minutes
                59.00,  // exchange_rate
                'Asia/Manila',  // timezone
                false   // email_notifications
            ]);
        } catch (PDOException $e) {
            error_log("Error creating default settings: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user settings
     */
    public function updateSettings($userId, $settings) {
        try {
            $updates = [];
            $params = [];
            
            if (isset($settings['auto_update_enabled'])) {
                $updates[] = "auto_update_enabled = ?";
                $params[] = (bool)$settings['auto_update_enabled'];
            }
            
            if (isset($settings['update_interval_minutes'])) {
                $updates[] = "update_interval_minutes = ?";
                $params[] = max(5, (int)$settings['update_interval_minutes']);
            }
            
            if (isset($settings['exchange_rate'])) {
                $updates[] = "exchange_rate = ?";
                $params[] = max(1, (float)$settings['exchange_rate']);
            }
            
            if (isset($settings['timezone'])) {
                $updates[] = "timezone = ?";
                $params[] = $settings['timezone'];
            }
            
            if (isset($settings['email_notifications'])) {
                $updates[] = "email_notifications = ?";
                $params[] = (bool)$settings['email_notifications'];
            }
            
            if (empty($updates)) {
                return true; // Nothing to update
            }
            
            $params[] = $userId;
            
            $sql = "UPDATE user_settings SET " . implode(", ", $updates) . 
                   " WHERE user_id = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error updating settings: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get default settings array
     */
    private function getDefaultSettings() {
        return [
            'auto_update_enabled' => false,
            'update_interval_minutes' => 60,
            'exchange_rate' => 59.00,
            'timezone' => 'Asia/Manila',
            'email_notifications' => false
        ];
    }
    
    /**
     * Update exchange rate only
     */
    public function updateExchangeRate($userId, $rate) {
        return $this->updateSettings($userId, ['exchange_rate' => $rate]);
    }
    
    /**
     * Update auto-update settings
     */
    public function updateAutoUpdate($userId, $enabled, $intervalMinutes) {
        return $this->updateSettings($userId, [
            'auto_update_enabled' => $enabled,
            'update_interval_minutes' => $intervalMinutes
        ]);
    }
    
    /**
     * Toggle email notifications
     */
    public function toggleEmailNotifications($userId, $enabled) {
        return $this->updateSettings($userId, ['email_notifications' => $enabled]);
    }
}