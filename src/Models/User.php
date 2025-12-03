<?php
/**
 * User Model
 * Handles user authentication and management
 */

require_once __DIR__ . '/../../config/database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Register a new user
     */
    public function register($username, $email, $password) {
        try {
            // Check if username already exists
            if ($this->usernameExists($username)) {
                return ['success' => false, 'error' => 'Username already exists'];
            }
            
            // Check if email already exists
            if ($this->emailExists($email)) {
                return ['success' => false, 'error' => 'Email already exists'];
            }
            
            // Hash password
            $hash = password_hash($password, PASSWORD_ARGON2ID);
            
            // Insert user
            $stmt = $this->db->prepare(
                "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)"
            );
            
            if ($stmt->execute([$username, $email, $hash])) {
                $userId = $this->db->lastInsertId();
                
                // Create default settings for user
                require_once __DIR__ . '/UserSettings.php';
                $settingsModel = new UserSettings();
                $settingsModel->createDefaultSettings($userId);
                
                return [
                    'success' => true,
                    'user_id' => $userId,
                    'username' => $username
                ];
            }
            
            return ['success' => false, 'error' => 'Failed to create user'];
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, username, email, password_hash, is_active, is_admin
                 FROM users WHERE username = ?"
            );
            
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'error' => 'Invalid username or password'];
            }
            
            if (!$user['is_active']) {
                return ['success' => false, 'error' => 'Account is disabled'];
            }
            
            if (password_verify($password, $user['password_hash'])) {
                return [
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'is_admin' => (bool)$user['is_admin']
                    ]
                ];
            }
            
            return ['success' => false, 'error' => 'Invalid username or password'];
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, username, email, created_at, is_active 
                 FROM users WHERE id = ?"
            );
            
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting user: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user by username
     */
    public function getUserByUsername($username) {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, username, email, created_at, is_active 
                 FROM users WHERE username = ?"
            );
            
            $stmt->execute([$username]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting user: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if username exists
     */
    public function usernameExists($username) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM users WHERE username = ?"
            );
            
            $stmt->execute([$username]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Error checking username: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if email exists
     */
    public function emailExists($email) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM users WHERE email = ?"
            );
            
            $stmt->execute([$email]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Error checking email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user password
     */
    public function updatePassword($userId, $newPassword) {
        try {
            $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
            
            $stmt = $this->db->prepare(
                "UPDATE users SET password_hash = ? WHERE id = ?"
            );
            
            return $stmt->execute([$hash, $userId]);
        } catch (PDOException $e) {
            error_log("Error updating password: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user email
     */
    public function updateEmail($userId, $newEmail) {
        try {
            // Check if email already exists for another user
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?"
            );
            $stmt->execute([$newEmail, $userId]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                return ['success' => false, 'error' => 'Email already in use'];
            }
            
            $stmt = $this->db->prepare(
                "UPDATE users SET email = ? WHERE id = ?"
            );
            
            if ($stmt->execute([$newEmail, $userId])) {
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Failed to update email'];
        } catch (PDOException $e) {
            error_log("Error updating email: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    
    /**
     * Deactivate user account
     */
    public function deactivateUser($userId) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users SET is_active = 0 WHERE id = ?"
            );
            
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Error deactivating user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Activate user account
     */
    public function activateUser($userId) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users SET is_active = 1 WHERE id = ?"
            );
            
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Error activating user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin($userId) {
            try {
                $stmt = $this->db->prepare(
                    "SELECT is_admin FROM users WHERE id = ?"
                );
                
                $stmt->execute([$userId]);
                $result = $stmt->fetch();
                return $result ? (bool)$result['is_admin'] : false;
            } catch (PDOException $e) {
                error_log("Error checking admin status: " . $e->getMessage());
                return false;
        }
    }
    
    /**
     * Get all users (admin only)
     */
    public function getAllUsers() {
            try {
                $stmt = $this->db->prepare(
                    "SELECT u.id, u.username, u.email, u.created_at, u.is_active, u.is_admin,
                            COUNT(DISTINCT p.id) as product_count,
                            COUNT(DISTINCT ph.id) as history_count
                     FROM users u
                     LEFT JOIN products p ON u.id = p.user_id
                     LEFT JOIN price_history ph ON p.id = ph.product_id
                     GROUP BY u.id
                     ORDER BY u.created_at DESC"
                );
                
                $stmt->execute();
                return $stmt->fetchAll();
            } catch (PDOException $e) {
                error_log("Error getting all users: " . $e->getMessage());
                return [];
        }
    }
    
    /**
     * Delete user and all related data (admin only)
     */
    public function deleteUser($userId) {
            try {
                // The CASCADE DELETE in foreign keys will handle related records
                $stmt = $this->db->prepare(
                    "DELETE FROM users WHERE id = ?"
                );
                
                return $stmt->execute([$userId]);
            } catch (PDOException $e) {
                error_log("Error deleting user: " . $e->getMessage());
                return false;
        }
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats($userId) {
            try {
                $stmt = $this->db->prepare(
                    "SELECT
                        COUNT(DISTINCT p.id) as product_count,
                        COUNT(DISTINCT ph.id) as total_checks,
                        MIN(ph.checked_at) as first_check,
                        MAX(ph.checked_at) as last_check
                     FROM users u
                     LEFT JOIN products p ON u.id = p.user_id
                     LEFT JOIN price_history ph ON p.id = ph.product_id
                     WHERE u.id = ?
                     GROUP BY u.id"
                );
                
                $stmt->execute([$userId]);
                return $stmt->fetch();
            } catch (PDOException $e) {
                error_log("Error getting user stats: " . $e->getMessage());
                return null;
        }
    }
}