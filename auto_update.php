<?php
// auto_update.php - Automated price update scheduler

$configFile = __DIR__ . '/update_config.json';
$lockFile = __DIR__ . '/update.lock';

// Default configuration
$defaultConfig = [
    'enabled' => false,
    'interval_minutes' => 60,
    'last_run' => null,
    'exchange_rate' => 59.0,
];

// Load configuration
function loadConfig($configFile, $defaultConfig) {
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        return array_merge($defaultConfig, $config);
    }
    return $defaultConfig;
}

// Save configuration
function saveConfig($configFile, $config) {
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
}

// Check if update is needed
function shouldUpdate($config) {
    if (!$config['enabled']) {
        return false;
    }
    
    if ($config['last_run'] === null) {
        return true;
    }
    
    $lastRun = strtotime($config['last_run']);
    $now = time();
    $intervalSeconds = $config['interval_minutes'] * 60;
    
    return ($now - $lastRun) >= $intervalSeconds;
}

// Run the scraper
function runScraper($exchangeRate) {
    $command = sprintf(
        'php %s --track-daily --rate=%s 2>&1',
        escapeshellarg(__DIR__ . '/amazon_scraper.php'),
        escapeshellarg($exchangeRate)
    );
    
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'return_code' => $returnCode,
    ];
}

// Main execution
$config = loadConfig($configFile, $defaultConfig);

// Check if this is being called via web or CLI
if (php_sapi_name() === 'cli') {
    // CLI mode - run the update
    if (shouldUpdate($config)) {
        // Check for lock file to prevent concurrent runs
        if (file_exists($lockFile)) {
            $lockAge = time() - filemtime($lockFile);
            if ($lockAge < 300) { // 5 minutes
                echo "Update already in progress (lock file exists)\n";
                exit(0);
            }
            // Lock file is stale, remove it
            unlink($lockFile);
        }
        
        // Create lock file
        touch($lockFile);
        
        echo "Starting automated price update...\n";
        $result = runScraper($config['exchange_rate']);
        
        // Update last run time
        $config['last_run'] = date('c');
        saveConfig($configFile, $config);
        
        // Remove lock file
        unlink($lockFile);
        
        echo $result['output'] . "\n";
        echo "Update completed at " . $config['last_run'] . "\n";
        
        exit($result['return_code']);
    } else {
        $nextRun = strtotime($config['last_run']) + ($config['interval_minutes'] * 60);
        echo "No update needed. Next update at: " . date('Y-m-d H:i:s', $nextRun) . "\n";
        exit(0);
    }
} else {
    // Web mode - handle AJAX requests
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'get_config':
                echo json_encode($config);
                break;
                
            case 'update_config':
                $config['enabled'] = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
                $config['interval_minutes'] = max(5, (int)($_POST['interval_minutes'] ?? 60));
                $config['exchange_rate'] = max(1, (float)($_POST['exchange_rate'] ?? 59.0));
                saveConfig($configFile, $config);
                echo json_encode(['success' => true, 'config' => $config]);
                break;
                
            case 'run_now':
                $result = runScraper($config['exchange_rate']);
                $config['last_run'] = date('c');
                saveConfig($configFile, $config);
                echo json_encode([
                    'success' => $result['success'],
                    'output' => $result['output'],
                    'last_run' => $config['last_run'],
                ]);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
    } else {
        echo json_encode(['error' => 'POST request required']);
    }
}