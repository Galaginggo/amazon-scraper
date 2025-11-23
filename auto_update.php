<?php

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Models/User.php';
require_once __DIR__ . '/src/Models/Product.php';
require_once __DIR__ . '/src/Models/UserSettings.php';
require_once __DIR__ . '/src/Services/PriceTracker.php';

$lockFile = __DIR__ . '/update.lock';

function shouldUpdateUser($settings) {
    if (!$settings['auto_update_enabled']) {
        return false;
    }
    
    $lastUpdate = $settings['updated_at'] ?? null;
    
    if ($lastUpdate === null) {
        return true;
    }
    
    $lastUpdateTime = strtotime($lastUpdate);
    $now = time();
    $intervalSeconds = $settings['update_interval_minutes'] * 60;
    
    return ($now - $lastUpdateTime) >= $intervalSeconds;
}

function updateUserProducts($userId, $exchangeRate) {
    try {
        $tracker = new PriceTracker($exchangeRate);
        $results = $tracker->trackUserProducts($userId);
        
        return [
            'success' => true,
            'user_id' => $userId,
            'total' => $results['total'],
            'successful' => $results['successful'],
            'failed' => $results['failed']
        ];
    } catch (Exception $e) {
        error_log("Error updating user {$userId}: " . $e->getMessage());
        return [
            'success' => false,
            'user_id' => $userId,
            'error' => $e->getMessage()
        ];
    }
}

function updateAllUsers() {
    $db = Database::getInstance()->getConnection();
    $results = [
        'total_users' => 0,
        'updated_users' => 0,
        'total_products' => 0,
        'successful_products' => 0,
        'failed_products' => 0,
        'details' => []
    ];
    
    try {
        $stmt = $db->prepare(
            "SELECT u.id, u.username, us.* 
             FROM users u
             INNER JOIN user_settings us ON u.id = us.user_id
             WHERE u.is_active = 1 AND us.auto_update_enabled = 1"
        );
        
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        $results['total_users'] = count($users);
        
        foreach ($users as $user) {
            if (!shouldUpdateUser($user)) {
                echo "Skipping user {$user['username']} (interval not reached)\n";
                continue;
            }
            
            echo "Updating products for user: {$user['username']}\n";
            
            $userResult = updateUserProducts($user['id'], $user['exchange_rate']);
            
            if ($userResult['success']) {
                $results['updated_users']++;
                $results['total_products'] += $userResult['total'];
                $results['successful_products'] += $userResult['successful'];
                $results['failed_products'] += $userResult['failed'];
                
                echo "  ✓ Updated {$userResult['successful']}/{$userResult['total']} products\n";
            } else {
                echo "  ✗ Failed: {$userResult['error']}\n";
            }
            
            $results['details'][] = $userResult;
        }
        
        return $results;
    } catch (PDOException $e) {
        error_log("Database error in updateAllUsers: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function updateSingleUser($userId) {
    $settingsModel = new UserSettings();
    $settings = $settingsModel->getUserSettings($userId);
    
    $result = updateUserProducts($userId, $settings['exchange_rate']);
    
    return $result;
}

if (php_sapi_name() === 'cli') {
    echo "=== Amazon Price Tracker - Auto Update ===\n";
    echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
    
    if (file_exists($lockFile)) {
        $lockAge = time() - filemtime($lockFile);
        if ($lockAge < 300) {
            echo "Update already in progress (lock file exists)\n";
            exit(0);
        }
        unlink($lockFile);
    }
    
    touch($lockFile);
    
    try {
        $results = updateAllUsers();
        
        echo "\n=== Update Summary ===\n";
        echo "Total users with auto-update: {$results['total_users']}\n";
        echo "Users updated: {$results['updated_users']}\n";
        echo "Total products checked: {$results['total_products']}\n";
        echo "Successful: {$results['successful_products']}\n";
        echo "Failed: {$results['failed_products']}\n";
        echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
        
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
        
        exit(0);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
        
        exit(1);
    }
    
} else {
    session_start();
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'POST request required']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        $settingsModel = new UserSettings();
        
        switch ($action) {
            case 'get_config':
                $settings = $settingsModel->getUserSettings($userId);
                echo json_encode([
                    'enabled' => (bool)$settings['auto_update_enabled'],
                    'interval_minutes' => (int)$settings['update_interval_minutes'],
                    'exchange_rate' => (float)$settings['exchange_rate'],
                    'last_run' => $settings['updated_at']
                ]);
                break;
                
            case 'update_config':
                $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
                $interval = max(5, (int)($_POST['interval_minutes'] ?? 60));
                $rate = max(1, (float)($_POST['exchange_rate'] ?? 59.0));
                
                $success = $settingsModel->updateSettings($userId, [
                    'auto_update_enabled' => $enabled,
                    'update_interval_minutes' => $interval,
                    'exchange_rate' => $rate
                ]);
                
                if ($success) {
                    $settings = $settingsModel->getUserSettings($userId);
                    echo json_encode([
                        'success' => true,
                        'config' => [
                            'enabled' => (bool)$settings['auto_update_enabled'],
                            'interval_minutes' => (int)$settings['update_interval_minutes'],
                            'exchange_rate' => (float)$settings['exchange_rate'],
                            'last_run' => $settings['updated_at']
                        ]
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to update settings'
                    ]);
                }
                break;
                
            case 'run_now':
                $result = updateSingleUser($userId);
                
                if ($result['success']) {
                    echo json_encode([
                        'success' => true,
                        'total' => $result['total'],
                        'successful' => $result['successful'],
                        'failed' => $result['failed'],
                        'last_run' => date('c')
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => $result['error'] ?? 'Update failed'
                    ]);
                }
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Auto-update error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'An error occurred: ' . $e->getMessage()
        ]);
    }
}