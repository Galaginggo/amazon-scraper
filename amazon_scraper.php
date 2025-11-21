<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class AmazonScraper
{
    private Client $client;
    private float $usdToPhpRate = 59.0; // Default USD to PHP exchange rate

    public function __construct(float $usdToPhpRate = 59.0)
    {
        $this->usdToPhpRate = $usdToPhpRate;
        $this->client = new Client([
            'timeout' => 20,
            'headers' => [
                // Don't pretend to be a bot, just a generic browser-ish UA
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ]);
    }

    public function fetchHtml(string $url): string
    {
        $response = $this->client->get($url);

        echo "HTTP status: " . $response->getStatusCode() . PHP_EOL;

        $html = (string) $response->getBody();
        file_put_contents(__DIR__ . '/debug.html', $html); // for inspection

        return $html;
    }

    public function parseProduct(string $url): ?array
    {
        $html = $this->fetchHtml($url);
        $crawler = new Crawler($html);

        // ---------- TITLE ----------
        // Amazon usually uses #productTitle
        $titleNode = $crawler->filter('#productTitle');

        if ($titleNode->count() === 0) {
            // Fallbacks, just in case
            $titleNode = $crawler->filter('span#productTitle');
        }

        if ($titleNode->count() === 0) {
            echo "DEBUG: Could not find title using #productTitle\n";
            return null;
        }

        $title = trim($titleNode->text());

        // ---------- PRICE ----------
        // Try several known price locations on Amazon
        $priceSelectors = [
            '#corePrice_feature_div span.a-offscreen', // common new layout
            '#priceblock_ourprice',                    // older layout
            '#priceblock_dealprice',
            '#priceblock_saleprice',
            'span.a-price span.a-offscreen',           // generic price span
        ];

        $rawPrice = null;

        foreach ($priceSelectors as $selector) {
            $node = $crawler->filter($selector);
            if ($node->count() > 0) {
                $rawPrice = trim($node->first()->text());
                echo "DEBUG: Matched price with selector: {$selector}\n";
                break;
            }
        }

        if ($rawPrice === null) {
            echo "DEBUG: Could not find price with any selector\n";
            return null;
        }

        // Detect currency and convert to PHP
        $priceData = $this->convertToPHP($rawPrice);

        // ---------- IMAGE ----------
        // Try to extract product image
        $imageUrl = $this->extractImageUrl($crawler);

        return [
            'title'     => $title,
            'price'     => $priceData['price_php'],
            'raw_price' => $priceData['display_price'],
            'image_url' => $imageUrl,
            'url'       => $url,
        ];
    }

    private function extractImageUrl(Crawler $crawler): ?string
    {
        // Try multiple image selectors
        $imageSelectors = [
            '#landingImage',                           // Main product image
            '#imgBlkFront',                            // Alternative main image
            '#main-image',                             // Another common ID
            'img.a-dynamic-image',                     // Dynamic image class
            '#imageBlock img[data-old-hires]',         // High-res image
            '#altImages img.a-button-thumbnail',       // Thumbnail fallback
        ];

        foreach ($imageSelectors as $selector) {
            $node = $crawler->filter($selector);
            if ($node->count() > 0) {
                // Try to get the best quality image
                $img = $node->first();
                
                // Try data-old-hires first (high resolution)
                $imageUrl = $img->attr('data-old-hires');
                if ($imageUrl) {
                    echo "DEBUG: Found image (data-old-hires): {$selector}\n";
                    return $imageUrl;
                }
                
                // Try data-a-dynamic-image (contains JSON with multiple sizes)
                $dynamicImage = $img->attr('data-a-dynamic-image');
                if ($dynamicImage) {
                    $imageData = json_decode($dynamicImage, true);
                    if ($imageData && is_array($imageData)) {
                        // Get the first (usually largest) image URL
                        $imageUrl = array_key_first($imageData);
                        echo "DEBUG: Found image (data-a-dynamic-image): {$selector}\n";
                        return $imageUrl;
                    }
                }
                
                // Fallback to src attribute
                $imageUrl = $img->attr('src');
                if ($imageUrl && strpos($imageUrl, 'data:image') !== 0) {
                    echo "DEBUG: Found image (src): {$selector}\n";
                    return $imageUrl;
                }
            }
        }

        echo "DEBUG: Could not find product image\n";
        return null;
    }

    private function normalizePrice(string $raw): float
    {
        $clean = preg_replace('/[^\d.,]/', '', $raw);
        $clean = str_replace(',', '', $clean);
        return (float) $clean;
    }

    private function convertToPHP(string $rawPrice): array
    {
        // Detect currency symbol
        $isUSD = (strpos($rawPrice, '$') !== false);
        $isPHP = (strpos($rawPrice, 'PHP') !== false || strpos($rawPrice, '₱') !== false);

        // Extract numeric value
        $numericPrice = $this->normalizePrice($rawPrice);

        if ($isUSD) {
            // Convert USD to PHP
            $priceInPHP = $numericPrice * $this->usdToPhpRate;
            $displayPrice = 'PHP' . number_format($priceInPHP, 2);
            echo "DEBUG: Converted ${numericPrice} USD to PHP{$priceInPHP}\n";
        } elseif ($isPHP) {
            // Already in PHP
            $priceInPHP = $numericPrice;
            $displayPrice = 'PHP' . number_format($priceInPHP, 2);
        } else {
            // Unknown currency, assume USD and convert
            $priceInPHP = $numericPrice * $this->usdToPhpRate;
            $displayPrice = 'PHP' . number_format($priceInPHP, 2);
            echo "DEBUG: Unknown currency, assuming USD. Converted to PHP{$priceInPHP}\n";
        }

        return [
            'price_php' => $priceInPHP,
            'display_price' => $displayPrice,
        ];
    }
}

if (php_sapi_name() === 'cli') {
    $arg1 = $argv[1] ?? null;

    // Check for custom exchange rate
    $exchangeRate = 59.0; // Default
    foreach ($argv as $arg) {
        if (strpos($arg, '--rate=') === 0) {
            $exchangeRate = (float) substr($arg, 7);
            echo "Using custom exchange rate: 1 USD = {$exchangeRate} PHP\n";
        }
    }

    // MODE 1: daily tracking from products.txt
    if ($arg1 === '--track-daily') {
        $scraper = new AmazonScraper($exchangeRate);

        $productsFile = __DIR__ . '/products.txt';
        $historyFile  = __DIR__ . '/price_history.csv';

        if (!file_exists($productsFile)) {
            echo "products.txt not found. Create it in this folder with one URL per line.\n";
            exit(1);
        }

        // Read URLs
        $urls = file($productsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($urls)) {
            echo "products.txt is empty. Add at least one product URL.\n";
            exit(1);
        }

        // Open history CSV (create with header if not exists)
        $isNewFile = !file_exists($historyFile);
        $fh = fopen($historyFile, 'a');
        if ($fh === false) {
            echo "Could not open price_history.csv for writing.\n";
            exit(1);
        }

        if ($isNewFile) {
            fputcsv($fh, ['timestamp', 'title', 'price', 'raw_price', 'image_url', 'url']);
        }

        // Set timezone to Philippine Time
        date_default_timezone_set('Asia/Manila');
        $timestamp = date('Y-m-d H:i:s'); // Philippine time

        foreach ($urls as $url) {
            echo "Tracking: {$url}\n";

            $product = $scraper->parseProduct($url);

            if ($product === null) {
                echo "  -> Failed to scrape. (See debug.html / selectors / response)\n";
                fputcsv($fh, [$timestamp, null, null, null, null, $url]);
                continue;
            }

            echo "  -> {$product['title']} | {$product['raw_price']}\n";

            fputcsv($fh, [
                $timestamp,
                $product['title'],
                $product['price'],
                $product['raw_price'],
                $product['image_url'],
                $product['url'],
            ]);
        }

        fclose($fh);
        echo "Done. Logged to price_history.csv\n";
        exit(0);
    }

    // MODE 2: single URL test (like before)
    $url = $arg1;

    if (!$url) {
        echo "Usage:\n";
        echo "  php amazon_scraper.php \"PRODUCT_URL\" [--rate=59.0]\n";
        echo "  php amazon_scraper.php --track-daily [--rate=59.0]\n";
        echo "\nOptions:\n";
        echo "  --rate=X.X  Set custom USD to PHP exchange rate (default: 59.0)\n";
        exit(1);
    }

    $scraper = new AmazonScraper($exchangeRate);
    $product = $scraper->parseProduct($url);

    if (!$product) {
        echo "Could not extract product info. Check debug.html and selectors.\n";
        exit(1);
    }

    echo "Product Information:\n";
    echo "Title:  {$product['title']}\n";
    echo "Price:  {$product['price']}\n";
    echo "Raw:    {$product['raw_price']}\n";
    echo "URL:    {$product['url']}\n";
}

