<?php
// index.php – Price tracker with product management

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$productsFile = __DIR__ . '/products.txt';
$historyFile  = __DIR__ . '/price_history.csv';

$products = [];
$latestByUrl = [];
$message = '';
$messageType = '';

// Function to scrape a single product immediately
function scrapeSingleProduct($url, $historyFile) {
    require_once __DIR__ . '/amazon_scraper.php';
    
    // Set timezone to Philippine Time
    date_default_timezone_set('Asia/Manila');
    
    // Load exchange rate from config
    $configFile = __DIR__ . '/update_config.json';
    $exchangeRate = 59.0;
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        $exchangeRate = $config['exchange_rate'] ?? 59.0;
    }
    
    $scraper = new AmazonScraper($exchangeRate);
    $product = $scraper->parseProduct($url);
    
    if ($product) {
        // Open history CSV (create with header if not exists)
        $isNewFile = !file_exists($historyFile);
        $fh = fopen($historyFile, 'a');
        
        if ($fh !== false) {
            if ($isNewFile) {
                fputcsv($fh, ['timestamp', 'title', 'price', 'raw_price', 'image_url', 'url']);
            }
            
            $timestamp = date('Y-m-d H:i:s'); // Philippine time
            fputcsv($fh, [
                $timestamp,
                $product['title'],
                $product['price'],
                $product['raw_price'],
                $product['image_url'],
                $product['url'],
            ]);
            
            fclose($fh);
            return true;
        }
    }
    
    return false;
}

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
                    // Load existing products
                    $existingProducts = [];
                    if (file_exists($productsFile)) {
                        $existingProducts = file($productsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    }
                    
                    // Check if URL already exists
                    if (in_array($newUrl, $existingProducts)) {
                        $message = 'This product URL is already being tracked.';
                        $messageType = 'warning';
                    } else {
                        // Add new URL
                        file_put_contents($productsFile, $newUrl . PHP_EOL, FILE_APPEND);
                        
                        // Immediately scrape the product
                        if (scrapeSingleProduct($newUrl, $historyFile)) {
                            $message = 'Product added and scraped successfully! Product details are now available.';
                            $messageType = 'success';
                        } else {
                            $message = 'Product URL added, but scraping failed. Try running the update manually.';
                            $messageType = 'warning';
                        }
                    }
                }
                break;
                
            case 'remove':
                $urlToRemove = $_POST['url'] ?? '';
                
                if (file_exists($productsFile)) {
                    $existingProducts = file($productsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $filteredProducts = array_filter($existingProducts, function($url) use ($urlToRemove) {
                        return trim($url) !== $urlToRemove;
                    });
                    
                    if (count($filteredProducts) < count($existingProducts)) {
                        file_put_contents($productsFile, implode(PHP_EOL, $filteredProducts) . PHP_EOL);
                        $message = 'Product URL removed successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Product URL not found.';
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}


// Load products.txt (list of URLs to track)
if (file_exists($productsFile)) {
    $lines = file($productsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $url = trim($line);
        if ($url !== '') {
            $products[] = $url;
        }
    }
}

// Load price_history.csv - keep latest and previous for each URL
$allHistoryByUrl = [];
if (file_exists($historyFile)) {
    if (($fh = fopen($historyFile, 'r')) !== false) {
        $header = fgetcsv($fh); // skip header

        while (($row = fgetcsv($fh)) !== false) {
            // Expecting: [timestamp, title, price, raw_price, image_url, url]
            // Handle both old format (5 columns) and new format (6 columns)
            if (count($row) < 5) {
                continue;
            }

            // Check if we have the new format with image_url
            if (count($row) >= 6) {
                [$timestamp, $title, $price, $rawPrice, $imageUrl, $url] = $row;
            } else {
                // Old format without image_url
                [$timestamp, $title, $price, $rawPrice, $url] = $row;
                $imageUrl = null;
            }

            $url = trim($url);
            if ($url === '') {
                continue;
            }

            // Store all entries for this URL
            if (!isset($allHistoryByUrl[$url])) {
                $allHistoryByUrl[$url] = [];
            }
            
            $allHistoryByUrl[$url][] = [
                'timestamp' => $timestamp,
                'title'     => $title,
                'price'     => (float)$price,
                'raw_price' => $rawPrice,
                'image_url' => $imageUrl,
                'url'       => $url,
            ];
        }

        fclose($fh);
    }
}

// Get latest and previous prices for each URL
foreach ($allHistoryByUrl as $url => $entries) {
    // Sort by timestamp descending
    usort($entries, function($a, $b) {
        return strcmp($b['timestamp'], $a['timestamp']);
    });
    
    $latest = $entries[0];
    $previous = isset($entries[1]) ? $entries[1] : null;
    
    // Calculate price change
    if ($previous && $previous['price'] > 0) {
        $change = $latest['price'] - $previous['price'];
        $percentChange = (($change / $previous['price']) * 100);
        
        $latest['price_change'] = $change;
        $latest['percent_change'] = $percentChange;
        $latest['previous_price'] = $previous['price'];
    }
    
    $latest['check_count'] = count($entries);
    $latestByUrl[$url] = $latest;
}

// Helper for HTML escape
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Price Tracker – Latest Prices</title>
    <style>
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
            padding: 20px 24px 24px;
            box-shadow: 0 10px 25px rgba(15,23,42,0.1);
        }
        h1 {
            margin-top: 0;
            font-size: 24px;
            color: #111827;
        }
        h2 {
            font-size: 18px;
            color: #111827;
            margin-top: 24px;
            margin-bottom: 12px;
        }
        p {
            color: #4b5563;
            margin-top: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 14px;
        }
        th, td {
            padding: 10px 8px;
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
            min-width: 300px;
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
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-danger:hover {
            background: #b91c1c;
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
        .message-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
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
        
        /* Product image styles */
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
        
        /* Price change styles */
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
        .btn-history {
            background: #6366f1;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-history:hover {
            background: #4f46e5;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header-row">
        <h1>🛒 Amazon Price Tracker</h1>
        <span class="badge">
            <?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?> tracked
        </span>
    </div>
    <p>
        Monitor Amazon product prices and track price history over time.
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
    
    <!-- Auto-Update Settings -->
    <div class="add-product-form" style="margin-top: 24px;">
        <h2>⚙️ Automatic Price Updates</h2>
        <div id="auto-update-settings">
            <p style="margin-bottom: 12px;">Configure automatic price updates to run in the background.</p>
            
            <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 500; font-size: 13px;">
                        <input type="checkbox" id="auto-update-enabled" style="margin-right: 6px;">
                        Enable Automatic Updates
                    </label>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 500; font-size: 13px;">
                        Update Interval (minutes)
                    </label>
                    <input type="number" id="update-interval" min="5" value="60"
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 500; font-size: 13px;">
                        Exchange Rate (USD to PHP)
                    </label>
                    <input type="number" id="exchange-rate" min="1" step="0.01" value="59.0"
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div>
                    <button onclick="saveAutoUpdateSettings()" class="btn btn-primary">Save Settings</button>
                </div>
            </div>
            
            <div style="margin-top: 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <button onclick="runUpdateNow()" class="btn" style="background: #059669; color: white;">
                    ▶️ Run Update Now
                </button>
                <span id="last-update-time" style="font-size: 13px; color: #6b7280;"></span>
            </div>
            
            <div id="update-message" style="margin-top: 12px; display: none;"></div>
        </div>
        
        <details style="margin-top: 16px;">
            <summary style="cursor: pointer; font-weight: 500; font-size: 13px; color: #4b5563;">
                📋 Setup Instructions for Automatic Updates
            </summary>
            <div style="margin-top: 12px; padding: 12px; background: #f9fafb; border-radius: 6px; font-size: 13px; line-height: 1.6;">
                <p><strong>Windows (Task Scheduler):</strong></p>
                <ol style="margin: 8px 0; padding-left: 20px;">
                    <li>Open Task Scheduler</li>
                    <li>Create Basic Task → Name it "Amazon Price Tracker"</li>
                    <li>Trigger: Daily, repeat every 5 minutes (or your preferred interval)</li>
                    <li>Action: Start a program</li>
                    <li>Program: <code>php</code></li>
                    <li>Arguments: <code><?= h(__DIR__ . '/auto_update.php') ?></code></li>
                    <li>Start in: <code><?= h(__DIR__) ?></code></li>
                </ol>
                
                <p style="margin-top: 12px;"><strong>Alternative: Manual Cron (if available):</strong></p>
                <pre style="background: #1f2937; color: #f3f4f6; padding: 8px; border-radius: 4px; overflow-x: auto;">*/5 * * * * cd <?= h(__DIR__) ?> && php auto_update.php</pre>
                
                <p style="margin-top: 12px; color: #6b7280;">
                    💡 The auto-update script will only run when enabled and respects the interval you set above.
                </p>
            </div>
        </details>
    </div>
    
    <h2>📊 Tracked Products</h2>
    <p style="margin-top: 8px;">
        Showing the most recent price entry per product URL from <code>price_history.csv</code>.
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
            <?php foreach ($products as $url): ?>
                <?php
                $row = $latestByUrl[$url] ?? null;
                ?>
                <tr>
                    <td>
                        <div class="product-info">
                            <div class="product-image-cell">
                                <?php if ($row && !empty($row['image_url'])): ?>
                                    <img src="<?= h($row['image_url']) ?>" alt="<?= h($row['title'] ?? 'Product') ?>" class="product-image">
                                <?php else: ?>
                                    <div class="no-image">No Image</div>
                                <?php endif; ?>
                            </div>
                            <div class="product-details">
                                <?php if ($row && $row['title']): ?>
                                    <?= h($row['title']) ?>
                                <?php else: ?>
                                    <span class="no-data">No title scraped yet</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="price">
                        <?php if ($row && $row['raw_price']): ?>
                            <?= h($row['raw_price']) ?>
                            <?php if (isset($row['price_change'])): ?>
                                <?php
                                $change = $row['price_change'];
                                $percent = $row['percent_change'];
                                
                                if ($change > 0) {
                                    $class = 'price-increase';
                                    $symbol = '↑';
                                    $sign = '+';
                                } elseif ($change < 0) {
                                    $class = 'price-decrease';
                                    $symbol = '↓';
                                    $sign = '';
                                } else {
                                    continue;
                                }
                                ?>
                                <span class="price-change <?= $class ?>">
                                    <?= $symbol ?> <?= $sign ?><?= number_format(abs($percent), 1) ?>%
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="no-data">No data</span>
                        <?php endif; ?>
                    </td>
                    <td class="url-cell">
                        <a href="<?= h($url) ?>" target="_blank" title="<?= h($url) ?>">
                            <?= h(substr($url, 0, 50)) ?><?= strlen($url) > 50 ? '...' : '' ?>
                        </a>
                    </td>
                    <td class="timestamp">
                        <?php if ($row && $row['timestamp']): ?>
                            <?= h($row['timestamp']) ?>
                        <?php else: ?>
                            <span class="no-data">Not checked yet</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-cell">
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <a href="history.php?url=<?= urlencode($url) ?>" class="btn btn-history" title="View price history">
                                📊 History (<?= $row['check_count'] ?? 1 ?>)
                            </a>
                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this product from tracking?');">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="url" value="<?= h($url) ?>">
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
        <strong>📝 Manual Update:</strong><br>
        Run <code>php amazon_scraper.php --track-daily</code> in your terminal to manually fetch the latest prices.
    </p>
</div>

<script>
// Auto-update settings management
let autoUpdateConfig = null;

// Load settings on page load
document.addEventListener('DOMContentLoaded', function() {
    loadAutoUpdateSettings();
});

function loadAutoUpdateSettings() {
    fetch('auto_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_config'
    })
    .then(response => response.json())
    .then(config => {
        autoUpdateConfig = config;
        document.getElementById('auto-update-enabled').checked = config.enabled;
        document.getElementById('update-interval').value = config.interval_minutes;
        document.getElementById('exchange-rate').value = config.exchange_rate;
        
        if (config.last_run) {
            const lastRun = new Date(config.last_run);
            document.getElementById('last-update-time').textContent =
                'Last update: ' + lastRun.toLocaleString();
        }
    })
    .catch(error => {
        console.error('Error loading settings:', error);
    });
}

function saveAutoUpdateSettings() {
    const enabled = document.getElementById('auto-update-enabled').checked;
    const interval = document.getElementById('update-interval').value;
    const rate = document.getElementById('exchange-rate').value;
    
    fetch('auto_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_config&enabled=${enabled}&interval_minutes=${interval}&exchange_rate=${rate}`
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showUpdateMessage('Settings saved successfully!', 'success');
            autoUpdateConfig = result.config;
        } else {
            showUpdateMessage('Failed to save settings.', 'error');
        }
    })
    .catch(error => {
        showUpdateMessage('Error: ' + error.message, 'error');
    });
}

function runUpdateNow() {
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '⏳ Updating...';
    
    showUpdateMessage('Running price update... This may take a few moments.', 'info');
    
    fetch('auto_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=run_now'
    })
    .then(response => response.json())
    .then(result => {
        btn.disabled = false;
        btn.textContent = '▶️ Run Update Now';
        
        if (result.success) {
            showUpdateMessage('✅ Update completed successfully! Refresh the page to see new prices.', 'success');
            if (result.last_run) {
                const lastRun = new Date(result.last_run);
                document.getElementById('last-update-time').textContent =
                    'Last update: ' + lastRun.toLocaleString();
            }
            
            // Auto-refresh after 2 seconds
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showUpdateMessage('❌ Update failed. Check console for details.', 'error');
            console.error('Update output:', result.output);
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.textContent = '▶️ Run Update Now';
        showUpdateMessage('Error: ' + error.message, 'error');
    });
}

function showUpdateMessage(message, type) {
    const msgDiv = document.getElementById('update-message');
    msgDiv.textContent = message;
    msgDiv.className = 'message message-' + type;
    msgDiv.style.display = 'block';
    
    // Auto-hide after 5 seconds for success messages
    if (type === 'success') {
        setTimeout(() => {
            msgDiv.style.display = 'none';
        }, 5000);
    }
}
</script>
</body>
</html>
