<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Models/User.php';

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$userModel = new User();

$message = '';
$messageType = '';

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_user') {
        $deleteUserId = intval($_POST['user_id'] ?? 0);
        
        // Prevent admin from deleting themselves
        if ($deleteUserId === $userId) {
            $message = 'You cannot delete your own account.';
            $messageType = 'error';
        } elseif ($deleteUserId > 0) {
            if ($userModel->deleteUser($deleteUserId)) {
                $message = 'User and all related data deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete user.';
                $messageType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'toggle_active') {
        $toggleUserId = intval($_POST['user_id'] ?? 0);
        $currentStatus = intval($_POST['current_status'] ?? 0);
        
        if ($toggleUserId === $userId) {
            $message = 'You cannot deactivate your own account.';
            $messageType = 'error';
        } elseif ($toggleUserId > 0) {
            if ($currentStatus) {
                $success = $userModel->deactivateUser($toggleUserId);
                $action = 'deactivated';
            } else {
                $success = $userModel->activateUser($toggleUserId);
                $action = 'activated';
            }
            
            if ($success) {
                $message = "User {$action} successfully.";
                $messageType = 'success';
            } else {
                $message = "Failed to {$action} user.";
                $messageType = 'error';
            }
        }
    }
}

// Get all users
$users = $userModel->getAllUsers();

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel – User Management</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(15,23,42,0.1);
        }

        h1 {
            margin-top: 0;
            font-size: 28px;
            color: #111827;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 500;
            background: #e5e7eb;
            color: #4b5563;
        }

        .badge-admin {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 14px;
        }

        th, td {
            padding: 12px 10px;
            text-align: left;
        }

        th {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
        }

        tr:nth-child(even) td {
            background: #f9fafb;
        }

        tr:hover td {
            background: #eef2ff;
        }

        td {
            border-bottom: 1px solid #e5e7eb;
            color: #111827;
        }

        .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-danger {
            background: #dc2626;
            color: #ffffff;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-warning {
            background: #f59e0b;
            color: #ffffff;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-secondary {
            background: #6b7280;
            color: #ffffff;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .message {
            padding: 12px 16px;
            border-radius: 6px;
            margin: 16px 0;
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

        .action-cell {
            text-align: center;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 24px 0;
        }

        .stat-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
        }

        .stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header-row">
        <h1>🛡️ Admin Panel – User Management</h1>
        <div>
            <span style="margin-right: 12px; color:#374151; font-size:14px;">
                Logged in as <?= h($username) ?> <span class="badge badge-admin">ADMIN</span>
            </span>
            <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
            <a href="logout.php" class="btn btn-danger" style="margin-left: 8px;">Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message message-<?= h($messageType) ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= count($users) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Users</div>
            <div class="stat-value"><?= count(array_filter($users, fn($u) => $u['is_active'])) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Products Tracked</div>
            <div class="stat-value"><?= array_sum(array_column($users, 'product_count')) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Price Checks</div>
            <div class="stat-value"><?= array_sum(array_column($users, 'history_count')) ?></div>
        </div>
    </div>

    <h2 style="margin-top: 32px; font-size: 20px; color: #111827;">All Users</h2>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Status</th>
            <th>Products</th>
            <th>Price Checks</th>
            <th>Registered</th>
            <th class="action-cell">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= h($user['id']) ?></td>
                <td>
                    <?= h($user['username']) ?>
                    <?php if ($user['is_admin']): ?>
                        <span class="badge badge-admin">ADMIN</span>
                    <?php endif; ?>
                </td>
                <td><?= h($user['email']) ?></td>
                <td>
                    <?php if ($user['is_active']): ?>
                        <span class="badge badge-active">Active</span>
                    <?php else: ?>
                        <span class="badge badge-inactive">Inactive</span>
                    <?php endif; ?>
                </td>
                <td><?= h($user['product_count']) ?></td>
                <td><?= h($user['history_count']) ?></td>
                <td style="font-size: 12px; color: #6b7280;">
                    <?php
                    try {
                        $date = new DateTime($user['created_at'], new DateTimeZone('Asia/Manila'));
                        echo $date->format('M d, Y');
                    } catch (Exception $e) {
                        echo h($user['created_at']);
                    }
                    ?>
                </td>
                <td class="action-cell">
                    <div class="action-buttons">
                        <?php if ($user['id'] !== $userId): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle user active status?');">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $user['is_active'] ?>">
                                <button type="submit" class="btn btn-warning">
                                    <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to DELETE this user and ALL their data? This action cannot be undone!');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        <?php else: ?>
                            <span style="color: #9ca3af; font-size: 12px;">You</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">
        <strong>⚠️ Warning:</strong> Deleting a user will permanently remove all their products and price history. This action cannot be undone.
    </p>
</div>
</body>
</html>