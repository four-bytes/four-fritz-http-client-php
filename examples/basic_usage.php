<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Four\FritzHttpClient\Services\NasService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Basic usage example for Four Fritz HTTP Client
 */

// Create logger
$logger = new Logger('fritzbox_example');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Configuration
$config = [
    'ip' => '192.168.178.1',           // Your FritzBox IP
    'username' => 'admin',              // Your FritzBox username
    'password' => 'your_password_here', // Your FritzBox password
    'timeout' => 30,
    'max_retries' => 3
];

echo "Four Fritz HTTP Client - Basic Example\n";
echo "====================================\n\n";

try {
    // Create NAS service
    $nas = new NasService($config, $logger);
    
    // Test connection
    echo "1. Testing connection...\n";
    if (!$nas->isReachable()) {
        throw new Exception("FritzBox not reachable!");
    }
    echo "✅ FritzBox is reachable\n\n";
    
    // Test authentication
    echo "2. Testing authentication...\n";
    if (!$nas->authenticate()) {
        throw new Exception("Authentication failed!");
    }
    echo "✅ Authentication successful\n\n";
    
    // List root directory
    echo "3. Listing root directory...\n";
    $result = $nas->getFileList('/');
    
    if ($result) {
        echo "Found " . count($result['items']) . " items:\n";
        foreach ($result['items'] as $item) {
            $icon = $item['type'] === 'folder' ? '📁' : '📄';
            $size = $item['type'] === 'file' ? ' (' . number_format($item['size']) . ' bytes)' : '';
            echo "  {$icon} {$item['name']}{$size}\n";
        }
        
        // Show disk info if available
        if (isset($result['diskInfo'])) {
            $disk = $result['diskInfo'];
            $totalGB = round($disk['total'] / 1e9, 2);
            $usedGB = round($disk['used'] / 1e9, 2);
            $freeGB = round($disk['free'] / 1e9, 2);
            echo "\n💿 Disk Usage: {$usedGB}GB used / {$totalGB}GB total ({$freeGB}GB free)\n";
        }
    } else {
        echo "❌ Failed to list directory\n";
    }
    
    echo "\n4. Checking for scan files...\n";
    $scanFiles = $nas->getScanFiles('/Scan');
    
    if (empty($scanFiles)) {
        echo "No scan files found in /Scan directory\n";
    } else {
        echo "Found " . count($scanFiles) . " scan file(s):\n";
        foreach ($scanFiles as $file) {
            $ext = strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION));
            echo "  📄 {$file['name']} ({$ext}) - " . number_format($file['size']) . " bytes\n";
        }
    }
    
    echo "\n✅ Example completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}