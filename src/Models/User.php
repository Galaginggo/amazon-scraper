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
                "SELECT id, username, email, password_hash, is_active 
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
                        'email' => $user['email']
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
}