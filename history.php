<?php
// history.php - View detailed price history for a product

// Set timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

$historyFile = __DIR__ . '/price_history.csv';
$productUrl = $_GET['url'] ?? '';

if (empty($productUrl)) {
    header('Location: index.php');
    exit;
}

// Load all history entries for this product
$historyEntries = [];
if (file_exists($historyFile)) {
    if (($fh = fopen($historyFile, 'r')) !== false) {
        $header = fgetcsv($fh); // skip header
        
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 5) continue;
            
            // Handle both old and new format
            if (count($row) >= 6) {
                [$timestamp, $title, $price, $rawPrice, $imageUrl, $url] = $row;
            } else {
                [$timestamp, $title, $price, $rawPrice, $url] = $row;
                $imageUrl = null;
            }
            
            $url = trim($url);
            if ($url === $productUrl) {
                $historyEntries[] = [
                    'timestamp' => $timestamp,
                    'title' => $title,
                    'price' => (float)$price,
                    'raw_price' => $rawPrice,
                    'image_url' => $imageUrl,
                ];
            }
        }
        
        fclose($fh);
    }
}

// Sort by timestamp descending (newest first)
usort($historyEntries, function($a, $b) {
    return strcmp($b['timestamp'], $a['timestamp']);
});

// Calculate price changes
for ($i = 0; $i < count($historyEntries); $i++) {
    if ($i < count($historyEntries) - 1) {
        $current = $historyEntries[$i]['price'];
        $previous = $historyEntries[$i + 1]['price'];
        
        if ($previous > 0) {
            $change = $current - $previous;
            $percentChange = (($change / $previous) * 100);
            
            $historyEntries[$i]['price_change'] = $change;
            $historyEntries[$i]['percent_change'] = $percentChange;
        }
    }
}

function h($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$productTitle = !empty($historyEntries) ? $historyEntries[0]['title'] : 'Unknown Product';
$productImage = !empty($historyEntries) ? $historyEntries[0]['image_url'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Price History - <?= h($productTitle) ?></title>
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
        .back-link {
            display: inline-block;
            margin-bottom: 16px;
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .product-header {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
        }
        .product-image {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
        }
        .product-info {
            flex: 1;
        }
        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
        }
        .product-url {
            font-size: 13px;
            color: #6b7280;
            word-break: break-all;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 14px;
        }
        th, td {
            padding: 12px 8px;
            text-align: left;
        }
        th {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
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
            font-size: 15px;
        }
        .price-change {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .price-increase {
            background: #fee2e2;
            color: #991b1b;
        }
        .price-decrease {
            background: #d1fae5;
            color: #065f46;
        }
        .price-same {
            background: #f3f4f6;
            color: #6b7280;
        }
        .timestamp {
            font-size: 13px;
            color: #6b7280;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
            font-style: italic;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
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
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .stat-value {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
        }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← Back to Dashboard</a>
    
    <div class="product-header">
        <?php if ($productImage): ?>
            <img src="<?= h($productImage) ?>" alt="<?= h($productTitle) ?>" class="product-image">
        <?php endif; ?>
        <div class="product-info">
            <div class="product-title"><?= h($productTitle) ?></div>
            <div class="product-url"><?= h($productUrl) ?></div>
        </div>
    </div>
    
    <?php if (!empty($historyEntries)): ?>
        <?php
        // Calculate statistics
        $prices = array_map(function($e) { return $e['price']; }, $historyEntries);
        $currentPrice = $prices[0];
        $lowestPrice = min($prices);
        $highestPrice = max($prices);
        $avgPrice = array_sum($prices) / count($prices);
        ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Current Price</div>
                <div class="stat-value"><?= h($historyEntries[0]['raw_price']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Lowest Price</div>
                <div class="stat-value">PHP<?= number_format($lowestPrice, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Highest Price</div>
                <div class="stat-value">PHP<?= number_format($highestPrice, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Price</div>
                <div class="stat-value">PHP<?= number_format($avgPrice, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Checks</div>
                <div class="stat-value"><?= count($historyEntries) ?></div>
            </div>
        </div>
        
        <h2 style="font-size: 18px; margin-bottom: 12px;">Price History</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Price</th>
                    <th>Change</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historyEntries as $entry): ?>
                    <tr>
                        <td class="timestamp">
                            <?php
                            try {
                                $date = new DateTime($entry['timestamp'], new DateTimeZone('Asia/Manila'));
                                echo $date->format('M d, Y g:i A');
                            } catch (Exception $e) {
                                echo h($entry['timestamp']);
                            }
                            ?>
                        </td>
                        <td class="price"><?= h($entry['raw_price']) ?></td>
                        <td>
                            <?php if (isset($entry['price_change'])): ?>
                                <?php
                                $change = $entry['price_change'];
                                $percent = $entry['percent_change'];
                                
                                if ($change > 0) {
                                    $class = 'price-increase';
                                    $symbol = '↑';
                                    $sign = '+';
                                } elseif ($change < 0) {
                                    $class = 'price-decrease';
                                    $symbol = '↓';
                                    $sign = '';
                                } else {
                                    $class = 'price-same';
                                    $symbol = '→';
                                    $sign = '';
                                }
                                ?>
                                <span class="price-change <?= $class ?>">
                                    <?= $symbol ?> <?= $sign ?>PHP<?= number_format(abs($change), 2) ?>
                                    (<?= $sign ?><?= number_format($percent, 1) ?>%)
                                </span>
                            <?php else: ?>
                                <span class="price-change price-same">Initial price</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">
            No price history found for this product.
        </div>
    <?php endif; ?>
</div>
</body>
</html>