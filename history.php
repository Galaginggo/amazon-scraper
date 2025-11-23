<?php
session_start();

// Redirect to login if the user is not logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Set timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

$historyFile = __DIR__ . '/price_history.csv';
$productUrl  = $_GET['url'] ?? '';

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
                    'title'     => $title,
                    'price'     => (float)$price,
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

// Calculate price changes between rows
for ($i = 0; $i < count($historyEntries); $i++) {
    if ($i < count($historyEntries) - 1) {
        $current  = $historyEntries[$i]['price'];
        $previous = $historyEntries[$i + 1]['price'];

        if ($previous > 0) {
            $change        = $current - $previous;
            $percentChange = (($change / $previous) * 100);

            $historyEntries[$i]['price_change']   = $change;
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
    <title>Price History – <?= h($productTitle) ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            transition: all 0.2s ease;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f4f6;
            padding: 24px;
        }

        .page-wrapper {
            max-width: 1100px;
            margin: 0 auto;
        }

        /* Top bar like dashboard header */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .top-left {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            min-width: 120px;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.4);
        }

        .btn-ghost {
            background: transparent;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-ghost:hover {
            background: #e5e7eb;
        }

        .user-label {
            font-size: 14px;
            color: #4b5563;
        }

        .user-label span {
            font-weight: 600;
            color: #111827;
        }

        /* Main card (same feel as dashboard container) */
        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(15,23,42,0.1);
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
        }

        .subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 20px;
        }

        /* Product header section */
        .product-header {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .product-image {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 6px 14px rgba(0,0,0,0.05);
        }

        .product-info {
            flex: 1;
        }

        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 6px;
        }

 

        /* Stats cards – same style as dashboard cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px 16px;
        }

        .stat-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }

        .stat-note {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
        }

        /* Table */
        h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #111827;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 4px;
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
            background: #fafafa;
        }

        tr:hover td {
            background: #eef2ff;
        }

        td {
            border-bottom: 1px solid #e5e7eb;
            color: #111827;
        }

        .timestamp {
            font-size: 13px;
            color: #6b7280;
        }

        .price {
            font-weight: 600;
            font-size: 14px;
        }

        .price-change {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
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

        .no-data {
            padding: 40px 20px;
            text-align: center;
            color: #9ca3af;
            font-style: italic;
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }
            .product-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
  
    </style>
</head>
<body>
<div class="page-wrapper">

    <!-- Top bar -->
    <div class="top-bar">
        <div class="top-left">
            <a href="index.php" class="btn btn-ghost">← Back to Dashboard</a>
            <a href="logout.php" class="btn btn-primary">Logout</a>
        </div>
        <div class="user-label">
            Logged in as <span><?= h($_SESSION['user']) ?></span>
        </div>
    </div>

    <!-- Main card -->
    <div class="card">
        <h1>Price History</h1>
        <p class="subtitle">
            Detailed price timeline for this Amazon product based on entries in
            <code>price_history.csv</code>.
        </p>

        <!-- Product header -->
        <div class="product-header">
            <?php if ($productImage): ?>
                <img src="<?= h($productImage) ?>" alt="<?= h($productTitle) ?>" class="product-image">
            <?php endif; ?>

            <div class="product-info">
                <div class="product-title"><?= h($productTitle) ?></div>
               <div class="product-url">
    <a href="<?= h($productUrl) ?>" target="_blank">
        <?= h(strlen($productUrl) > 60 ? substr($productUrl, 0, 60) . '...' : $productUrl) ?>
    </a>
</div>

            </div>
        </div>

        <?php if (!empty($historyEntries)): ?>
            <?php
            $prices       = array_map(fn($e) => $e['price'], $historyEntries);
            $currentPrice = $prices[0];
            $lowestPrice  = min($prices);
            $highestPrice = max($prices);
            $avgPrice     = array_sum($prices) / count($prices);
            ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Current Price</div>
                    <div class="stat-value">
                        <?= h($historyEntries[0]['raw_price']) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Lowest Price</div>
                    <div class="stat-value">
                        PHP<?= number_format($lowestPrice, 2) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Highest Price</div>
                    <div class="stat-value">
                        PHP<?= number_format($highestPrice, 2) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Average Price</div>
                    <div class="stat-value">
                        PHP<?= number_format($avgPrice, 2) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Checks</div>
                    <div class="stat-value">
                        <?= count($historyEntries) ?>
                    </div>
                </div>
            </div>

            <h2>Timeline</h2>

            <table>
                <thead>
                    <tr>
                        <th style="width: 40%;">Date &amp; Time</th>
                        <th style="width: 25%;">Price</th>
                        <th style="width: 35%;">Change</th>
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
                                $change  = $entry['price_change'];
                                $percent = $entry['percent_change'];

                                if ($change > 0) {
                                    $class  = 'price-increase';
                                    $symbol = '↑';
                                    $sign   = '+';
                                } elseif ($change < 0) {
                                    $class  = 'price-decrease';
                                    $symbol = '↓';
                                    $sign   = '';
                                } else {
                                    $class  = 'price-same';
                                    $symbol = '→';
                                    $sign   = '';
                                }
                                ?>
                                <span class="price-change <?= $class ?>">
                                    <?= $symbol ?>
                                    <?= $sign ?>PHP<?= number_format(abs($change), 2) ?>
                                    (<?= $sign ?><?= number_format($percent, 1) ?>%)
                                </span>
                            <?php else: ?>
                                <span class="price-change price-same">
                                    Initial price
                                </span>
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
</div>
</body>
</html>
