<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Set timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Models/Product.php';
require_once __DIR__ . '/../src/Models/PriceHistory.php';
require_once __DIR__ . '/../src/Services/PriceTracker.php';
require_once __DIR__ . '/../src/Models/UserSettings.php';

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$productModel = new Product();
$historyModel = new PriceHistory();
$settingsModel = new UserSettings();

$message = '';
$messageType = '';

// Get user settings for exchange rate
$userSettings = $settingsModel->getUserSettings($userId);
$exchangeRate = $userSettings['exchange_rate'] ?? 59.0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $newUrl = trim($_POST['url'] ?? '');
                
                // Validate URL
                if (empty($newUrl)) {
                    $message = 'Please enter a URL.';
                    $messageType = 'error';
                } elseif (!filter_var($newUrl, FILTER_VALIDATE_URL)) {
                    $message = 'Please enter a valid URL.';
                    $messageType = 'error';
                } elseif (strpos($newUrl, 'amazon.com') === false && strpos($newUrl, 'amazon.') === false) {
                    $message = 'Please enter a valid Amazon product URL.';
                    $messageType = 'error';
                } else {
                    // Check if product already exists
                    if ($productModel->productExists($userId, $newUrl)) {
                        $message = 'This product URL is already being tracked.';
                        $messageType = 'warning';
                    } else {
                        // Add and scrape product
                        $tracker = new PriceTracker($exchangeRate);
                        $result = $tracker->addAndScrapeProduct($userId, $newUrl);
                        
                        if ($result['success']) {
                            $message = 'Product added and scraped successfully! Product details are now available.';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to add product: ' . $result['error'];
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'remove':
                $productId = intval($_POST['product_id'] ?? 0);
                
                if ($productId > 0) {
                    if ($productModel->removeProduct($productId, $userId)) {
                        $message = 'Product removed successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to remove product.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Invalid product ID.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get all user products with price information
$tracker = new PriceTracker($exchangeRate);
$products = $tracker->getUserProductsWithPrices($userId);
$productCount = count($products);

// Helper for HTML escape
function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Price Tracker – Dashboard</title>
   <style>
    /* Base styles */
    body {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background: #f3f4f6;
        margin: 0;
        padding: 20px;
    }

    .container {
        max-width: 1100px;
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

    h2 {
        font-size: 20px;
        color: #111827;
        margin-top: 24px;
        margin-bottom: 12px;
    }

    p {
        color: #4b5563;
        margin-top: 4px;
    }

    /* Table styles */
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

    .price {
        font-weight: 600;
    }

    .no-data {
        color: #9ca3af;
        font-style: italic;
    }

    .timestamp {
        font-size: 12px;
        color: #6b7280;
    }

    a {
        color: #2563eb;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }

    .badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 999px;
        font-size: 11px;
        background: #e5e7eb;
        color: #4b5563;
    }

    .header-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* Form styles */
    .add-product-form {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin: 20px 0;
    }

    .form-group {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .form-group input[type="url"] {
        flex: 1;
        min-width: 250px;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        font-family: inherit;
    }

    .form-group input[type="url"]:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    /* Buttons */
    .btn {
        padding: 10px 16px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        font-family: inherit;
        min-width: 120px;
        text-align: center;
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

    .btn-history {
        background: #6366f1;
        color: #ffffff;
    }

    .btn-history:hover {
        background: #4f46e5;
    }

    /* Message styles */
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

    .message-warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
    }

    /* Action column */
    .action-cell {
        text-align: center;
    }

    .url-cell {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Product image */
    .product-image {
        width: 80px;
        height: 80px;
        object-fit: contain;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .product-image-cell {
        text-align: center;
        padding: 8px;
    }

    .no-image {
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f3f4f6;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        color: #9ca3af;
        font-size: 11px;
        text-align: center;
        margin: 0 auto;
    }

    .product-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .product-details {
        flex: 1;
    }

    /* Price change badges */
    .price-change {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        margin-left: 8px;
    }

    .price-increase {
        background: #fee2e2;
        color: #991b1b;
    }

    .price-decrease {
        background: #d1fae5;
        color: #065f46;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .form-group {
            flex-direction: column;
        }
        .btn {
            width: 100%;
        }
        .url-cell {
            max-width: 150px;
        }
    }
</style>

</head>
<body>
<div class="container">
   <div class="header-row" style="justify-content: space-between; align-items: center;">
    <h1>🛒 Amazon Price Tracker</h1>
    <div>
        <span class="badge">
            <?= $productCount ?> product<?= $productCount === 1 ? '' : 's' ?> tracked
        </span>
        <span style="margin-left:12px; color:#374151; font-size:14px;">
            Logged in as <?= h($username) ?>
        </span>
        <a href="settings.php" class="btn btn-primary" style="margin-left: 12px;">⚙️ Settings</a>
        <a href="logout.php" class="btn btn-danger" style="margin-left: 8px;">Logout</a>
    </div>
</div>

    <p>
        Monitor Amazon product prices and track price history over time. All your tracked products are private and only visible to you.
    </p>
    
    <?php if ($message): ?>
        <div class="message message-<?= h($messageType) ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>
    
    <!-- Add Product Form -->
    <div class="add-product-form">
        <h2>➕ Add New Product to Track</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <input
                    type="url"
                    name="url"
                    placeholder="Paste Amazon product URL here (e.g., https://www.amazon.com/...)"
                    required
                >
                <button type="submit" class="btn btn-primary">Add Product</button>
            </div>
        </form>
        <p style="margin-top: 8px; font-size: 13px;">
            💡 Tip: Product details will be automatically fetched when you add a new URL.
        </p>
    </div>
    
    <h2>📊 Your Tracked Products</h2>
    <p style="margin-top: 8px;">
        Showing your tracked products with the latest price information from the database.
    </p>

    <table>
        <thead>
        <tr>
            <th style="width: 35%;">Product</th>
            <th style="width: 15%;">Latest Price</th>
            <th style="width: 18%;">URL</th>
            <th style="width: 12%;">Last Checked</th>
            <th style="width: 20%;" class="action-cell">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($products)): ?>
            <tr>
                <td colspan="5" class="no-data">
                    No products found. Use the form above to add your first product URL.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <div class="product-info">
                            <div class="product-image-cell">
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="<?= h($product['image_url']) ?>" alt="<?= h($product['title']) ?>" class="product-image">
                                <?php else: ?>
                                    <div class="no-image">No Image</div>
                                <?php endif; ?>
                            </div>
                            <div class="product-details">
                                <?php if ($product['title']): ?>
                                    <?= h($product['title']) ?>
                                <?php else: ?>
                                    <span class="no-data">No title available</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="price">
                        <?php if ($product['latest_raw_price']): ?>
                            <?= h($product['latest_raw_price']) ?>
                            <?php if ($product['price_change']): ?>
                                <?php
                                $change = $product['price_change'];
                                
                                if ($change['direction'] === 'increase') {
                                    $class = 'price-increase';
                                    $symbol = '↑';
                                    $sign = '+';
                                } elseif ($change['direction'] === 'decrease') {
                                    $class = 'price-decrease';
                                    $symbol = '↓';
                                    $sign = '';
                                } else {
                                    $class = '';
                                    $symbol = '';
                                    $sign = '';
                                }
                                
                                if ($change['direction'] !== 'same'):
                                ?>
                                <span class="price-change <?= $class ?>">
                                    <?= $symbol ?> <?= $sign ?><?= number_format(abs($change['percent_change']), 1) ?>%
                                </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="no-data">No data</span>
                        <?php endif; ?>
                    </td>
                    <td class="url-cell">
                        <a href="<?= h($product['amazon_url']) ?>" target="_blank" title="<?= h($product['amazon_url']) ?>">
                            <?= h(substr($product['amazon_url'], 0, 50)) ?><?= strlen($product['amazon_url']) > 50 ? '...' : '' ?>
                        </a>
                    </td>
                    <td class="timestamp">
                        <?php if ($product['last_checked']): ?>
                            <?php
                            try {
                                $date = new DateTime($product['last_checked'], new DateTimeZone('Asia/Manila'));
                                echo $date->format('M d, Y g:i A');
                            } catch (Exception $e) {
                                echo h($product['last_checked']);
                            }
                            ?>
                        <?php else: ?>
                            <span class="no-data">Not checked yet</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-cell">
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <a href="history.php?id=<?= $product['id'] ?>" class="btn btn-history" title="View price history">
                                📊 History (<?= $product['check_count'] ?? 0 ?>)
                            </a>
                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this product from tracking?');">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <button type="submit" class="btn btn-danger">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <p style="margin-top:24px; padding-top: 16px; border-top: 1px solid #e5e7eb;" class="timestamp">
        <strong>💾 Database Storage:</strong> All your products and price history are now stored in the MySQL database for better performance and reliability.
    </p>
</div>

</body>
</html>