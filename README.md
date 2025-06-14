# Four Fritz HTTP Client

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://packagist.org/packages/four-bytes/four-fritz-http-client-php)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/four-bytes/four-fritz-http-client-php)](https://packagist.org/packages/four-bytes/four-fritz-http-client-php)

A modern, comprehensive PHP HTTP client for FritzBox API access. This library provides a clean, well-documented interface for interacting with AVM FritzBox devices via their HTTP/LUA APIs.

## 🚀 Features

- **Modern PHP 8.1+** with full type declarations
- **Comprehensive Authentication** supporting both PBKDF2 (FritzOS 7.24+) and MD5 (legacy)
- **Session Management** with automatic SID caching and renewal
- **NAS Operations** for file management (browse, upload, download, delete)
- **MyFRITZ Support** for remote access
- **Extensive Logging** with PSR-3 compliance
- **Well Tested** with unit and integration tests
- **Zero Dependencies** except PSR-3 logger interface

## 📦 Installation

```bash
composer require four-bytes/four-fritz-http-client-php
```

## 🔧 Quick Start

```php
<?php
use Four\FritzHttpClient\Services\NasService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create logger
$logger = new Logger('fritzbox');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Configure client
$config = [
    'ip' => '192.168.178.1',          // or use 'myfritz_url' for remote access
    'username' => 'admin',             // FritzBox username
    'password' => 'your_password',     // FritzBox password
    'timeout' => 30,                   // Request timeout in seconds
    'max_retries' => 3                 // Max retry attempts
];

// Create NAS service
$nas = new NasService($config, $logger);

// List files in root directory
$result = $nas->getFileList('/');
if ($result) {
    foreach ($result['items'] as $item) {
        echo "{$item['type']}: {$item['name']} ({$item['size']} bytes)\n";
    }
}

// Download a file
$nas->downloadFile('/path/to/file.pdf', './local/file.pdf');

// Upload a file (coming soon)
// $nas->uploadFile('./local/scan.jpg', '/Scan/scan.jpg');

// Delete a file
$nas->deleteFile('/path/to/old_file.pdf');
```

## 🏗️ Architecture

The library follows a clean, service-oriented architecture:

```
Four\FritzHttpClient\
├── Client\
│   ├── FritzClientInterface      # Base interface
│   └── AbstractFritzClient       # Authentication & HTTP handling
└── Services\
    └── NasService               # NAS file operations
```

## 📖 Documentation

### Authentication

The client supports both modern and legacy FritzBox authentication:

- **PBKDF2** (FritzOS 7.24+): Secure hash-based authentication
- **MD5** (Legacy): For older FritzBox models
- **Session Caching**: Automatic SID persistence and renewal

### NAS Service

Complete file management operations:

```php
// Browse directories with pagination
$files = $nas->getFileList('/Scan', $index = 1, $limit = 100, $sorting = '+filename');

// Check disk usage
$diskInfo = $nas->getDiskInfo();
echo "Used: " . round($diskInfo['used'] / 1e9, 2) . "GB\n";

// Create folders
$nas->createFolder('/Documents', 'NewFolder');

// Rename files
$nas->renameFile('/old_name.pdf', 'new_name.pdf');

// Check if file exists
if ($nas->fileExists('/Scan', 'document.pdf')) {
    echo "File exists!\n";
}

// Delete multiple files
$count = $nas->deleteMultipleFiles(['/file1.pdf', '/file2.jpg']);
echo "Deleted $count files\n";
```

### Configuration Options

```php
$config = [
    // Connection settings
    'ip' => '192.168.178.1',              // Local IP address
    'myfritz_url' => 'https://xxx.myfritz.net', // Remote MyFRITZ URL (alternative to IP)
    
    // Authentication
    'username' => 'admin',                 // FritzBox username (empty for password-only)
    'password' => 'your_password',         // FritzBox password
    
    // HTTP settings
    'timeout' => 30,                       // Request timeout in seconds
    'max_retries' => 3,                    // Maximum retry attempts
    
    // SSL settings (for HTTPS connections)
    'verify_ssl' => false,                 // Set to true for production with valid certs
];
```

## 🧪 Testing

```bash
# Run unit tests
composer test

# Run static analysis
composer analyse

# Check code style
composer style

# Fix code style
composer style-fix
```

## 🔒 Security Considerations

- **Never commit credentials** to version control
- **Use environment variables** for sensitive configuration
- **Enable SSL verification** in production environments
- **Limit session lifetime** for security-critical applications

## 🛠️ Advanced Usage

### Custom Logging

```php
use Psr\Log\LoggerInterface;

class CustomLogger implements LoggerInterface {
    // Your custom logging implementation
}

$nas = new NasService($config, new CustomLogger());
```

### Error Handling

```php
try {
    $files = $nas->getFileList('/NonExistentPath');
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check authentication status
$status = $nas->getStatus();
if (!$status['authenticated']) {
    echo "Authentication failed\n";
}
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## 📋 Requirements

- PHP 8.1 or higher
- cURL extension
- JSON extension
- mbstring extension
- PSR-3 compatible logger

## 🔮 Roadmap

- [ ] **File Upload Support** - Complete NAS upload functionality
- [ ] **WLAN Service** - WiFi configuration management
- [ ] **Call Service** - Call history and phone management
- [ ] **Smart Home Service** - DECT device control
- [ ] **System Service** - Device information and monitoring
- [ ] **TR-064 Support** - SOAP-based services integration

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- AVM for creating the FritzBox platform and APIs
- The PHP community for excellent tooling and standards
- All contributors and users of this library

## 📞 Support

- 🐛 **Issues**: [GitHub Issues](https://github.com/four-bytes/four-fritz-http-client-php/issues)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/four-bytes/four-fritz-http-client-php/discussions)
- 📖 **Documentation**: [Wiki](https://github.com/four-bytes/four-fritz-http-client-php/wiki)

---

**Made with ❤️ by [4 Bytes](https://github.com/four-bytes)**