<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Models/UserSettings.php';

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$userModel = new User();
$settingsModel = new UserSettings();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_settings':
                $autoUpdate = isset($_POST['auto_update_enabled']);
                $interval = max(5, intval($_POST['update_interval_minutes'] ?? 60));
                $exchangeRate = max(1, floatval($_POST['exchange_rate'] ?? 59.0));
                
                $success = $settingsModel->updateSettings($userId, [
                    'auto_update_enabled' => $autoUpdate,
                    'update_interval_minutes' => $interval,
                    'exchange_rate' => $exchangeRate
                ]);
                
                if ($success) {
                    $message = 'Settings updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update settings.';
                    $messageType = 'error';
                }
                break;
                
            case 'update_email':
                $newEmail = trim($_POST['email'] ?? '');
                
                if (empty($newEmail)) {
                    $message = 'Please enter an email address.';
                    $messageType = 'error';
                } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    $message = 'Please enter a valid email address.';
                    $messageType = 'error';
                } else {
                    $result = $userModel->updateEmail($userId, $newEmail);
                    
                    if ($result['success']) {
                        $_SESSION['email'] = $newEmail;
                        $message = 'Email updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = $result['error'];
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $message = 'All password fields are required.';
                    $messageType = 'error';
                } elseif (strlen($newPassword) < 8) {
                    $message = 'New password must be at least 8 characters long.';
                    $messageType = 'error';
                } elseif ($newPassword !== $confirmPassword) {
                    $message = 'New passwords do not match.';
                    $messageType = 'error';
                } else {
                    // Verify current password
                    $result = $userModel->login($username, $currentPassword);
                    
                    if ($result['success']) {
                        if ($userModel->updatePassword($userId, $newPassword)) {
                            $message = 'Password changed successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to update password.';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Current password is incorrect.';
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Get current settings
$settings = $settingsModel->getUserSettings($userId);
$user = $userModel->getUserById($userId);

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings – Amazon Price Tracker</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(15,23,42,0.1);
            margin-bottom: 20px;
        }

        h1 {
            margin-top: 0;
            font-size: 28px;
            color: #111827;
        }

        h2 {
            font-size: 18px;
            color: #111827;
            margin-top: 0;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #6b7280;
            color: #ffffff;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }

        .message {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .message-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .info-text {
            font-size: 13px;
            color: #6b7280;
            margin-top: 6px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-row">
        <h1>⚙️ Settings</h1>
        <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="message message-<?= h($messageType) ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <!-- Account Information -->
    <div class="card">
        <h2>👤 Account Information</h2>
        <div class="form-group">
            <label>Username</label>
            <input type="text" value="<?= h($user['username']) ?>" disabled>
            <div class="info-text">Username cannot be changed</div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update_email">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= h($user['email']) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Update Email</button>
        </form>
    </div>

    <!-- Price Tracking Settings -->
    <div class="card">
        <h2>💰 Price Tracking Settings</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update_settings">
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input 
                        type="checkbox" 
                        id="auto_update_enabled" 
                        name="auto_update_enabled" 
                        <?= $settings['auto_update_enabled'] ? 'checked' : '' ?>
                    >
                    <label for="auto_update_enabled">Enable Automatic Price Updates</label>
                </div>
                <div class="info-text">Automatically check prices at regular intervals</div>
            </div>

            <div class="settings-grid">
                <div class="form-group">
                    <label for="update_interval_minutes">Update Interval (minutes)</label>
                    <input 
                        type="number" 
                        id="update_interval_minutes" 
                        name="update_interval_minutes" 
                        value="<?= h($settings['update_interval_minutes']) ?>" 
                        min="5" 
                        step="5"
                        required
                    >
                    <div class="info-text">Minimum: 5 minutes</div>
                </div>

                <div class="form-group">
                    <label for="exchange_rate">Exchange Rate (USD to PHP)</label>
                    <input 
                        type="number" 
                        id="exchange_rate" 
                        name="exchange_rate" 
                        value="<?= h($settings['exchange_rate']) ?>" 
                        min="1" 
                        step="0.01"
                        required
                    >
                    <div class="info-text">Used for price conversion</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>

    <!-- Change Password -->
    <div class="card">
        <h2>🔒 Change Password</h2>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>

            <div class="settings-grid">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" minlength="8" required>
                    <div class="info-text">At least 8 characters</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Change Password</button>
        </form>
    </div>

    <!-- Account Stats -->
    <div class="card">
        <h2>📊 Account Statistics</h2>
        <div class="settings-grid">
            <div>
                <strong>Account Created:</strong><br>
                <?php
                try {
                    $date = new DateTime($user['created_at'], new DateTimeZone('Asia/Manila'));
                    echo $date->format('F d, Y g:i A');
                } catch (Exception $e) {
                    echo h($user['created_at']);
                }
                ?>
            </div>
            <div>
                <strong>Account Status:</strong><br>
                <?= $user['is_active'] ? '<span style="color: #059669;">✓ Active</span>' : '<span style="color: #dc2626;">✗ Inactive</span>' ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>