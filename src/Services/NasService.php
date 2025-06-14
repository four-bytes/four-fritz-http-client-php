<?php

namespace Four\FritzHttpClient\Services;

use Four\FritzHttpClient\Client\AbstractFritzClient;

/**
 * FritzBox NAS service for file access operations
 * Extends the base client with NAS-specific functionality
 */
class NasService extends AbstractFritzClient
{
    /**
     * Get file list using NAS API (updated to match actual API)
     */
    public function getFileList(string $path = '', int $index = 1, int $limit = 100, string $sorting = '+filename'): ?array
    {
        $this->logger->info("Getting file list for path: '$path'", [
            'index' => $index,
            'limit' => $limit,
            'sorting' => $sorting
        ]);
        
        $params = [
            'path' => $path,
            'index' => $index,
            'limit' => $limit,
            'sorting' => $sorting,
            'c' => 'files',
            'a' => 'browse'
        ];
        
        $data = $this->makeLuaRequest('/nas/api/data.lua', $params);
        
        if (!$data) {
            $this->logger->error("Failed to get file list for path: '$path'");
            return null;
        }
        
        $this->logger->info("Successfully parsed NAS response", [
            'directories' => count($data['directories'] ?? []),
            'files' => count($data['files'] ?? []),
            'browse' => $data['browse'] ?? null
        ]);
        
        return $this->parseNasApiResponse($data);
    }
    
    /**
     * Download a file from the NAS
     */
    public function downloadFile(string $filePath, string $localPath): bool
    {
        $this->logger->info("Downloading file", ['filePath' => $filePath, 'localPath' => $localPath]);
        
        $params = [
            'script' => '/api/data.lua',
            'c' => 'files',
            'a' => 'get',
            'path' => $filePath
        ];
        
        $retries = 0;
        while ($retries < $this->config['max_retries']) {
            $fileContent = $this->makeFileDownloadRequest('/nas/cgi-bin/luacgi_notimeout', $params);
            
            if ($fileContent !== null) {
                $result = file_put_contents($localPath, $fileContent);
                if ($result !== false) {
                    $this->logger->info("Downloaded " . strlen($fileContent) . " bytes to $localPath");
                    return true;
                }
            }
            
            $retries++;
            $this->logger->warning("Download attempt $retries failed for $filePath");
            if ($retries < $this->config['max_retries']) {
                sleep(2);
            }
        }
        
        $this->logger->error("Failed to download after {$this->config['max_retries']} attempts: $filePath");
        return false;
    }
    
    /**
     * Delete a file using NAS API (updated to match actual API)
     */
    public function deleteFile(string $filePath): bool
    {
        $this->logger->info("Deleting file: $filePath");
        
        $params = [
            'paths[1]' => $filePath,
            'c' => 'files',
            'a' => 'delete'
        ];
        
        $result = $this->makeLuaRequest('/nas/api/data.lua', $params);
        
        if ($result && isset($result['deleteCount']) && $result['deleteCount'] > 0) {
            $this->logger->info("File deleted: $filePath");
            return true;
        }
        
        $this->logger->error("Failed to delete file: $filePath");
        return false;
    }
    
    /**
     * Get files in scan directory that match scan patterns
     */
    public function getScanFiles(string $scanPath = '/Scan'): array
    {
        $this->logger->info("Getting scan files from path: '$scanPath'");
        
        $result = $this->getFileList($scanPath);
        if (!$result) {
            $this->logger->warning("Failed to get file list for scan path: '$scanPath'");
            return [];
        }
        
        if (!isset($result['items'])) {
            $this->logger->warning("No 'items' key in API response", ['result' => $result]);
            return [];
        }
        
        $this->logger->info("Found items in scan directory", [
            'totalItems' => count($result['items']),
            'scanPath' => $scanPath
        ]);
        
        // Log all items found
        foreach ($result['items'] as $item) {
            $this->logger->debug("Found item", [
                'name' => $item['name'],
                'type' => $item['type'],
                'size' => $item['size'] ?? 0,
                'path' => $item['path'] ?? ''
            ]);
        }
        
        // Filter for scan files (PDF, JPG, JPEG)
        $scanFiles = [];
        $supportedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        
        foreach ($result['items'] as $file) {
            if ($file['type'] === 'file') {
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $this->logger->debug("Checking file for scan compatibility", [
                    'filename' => $file['name'],
                    'extension' => $extension,
                    'isSupported' => in_array($extension, $supportedExtensions)
                ]);
                
                if (in_array($extension, $supportedExtensions)) {
                    $scanFiles[] = $file;
                    $this->logger->info("Added scan file", [
                        'filename' => $file['name'],
                        'extension' => $extension,
                        'size' => $file['size']
                    ]);
                }
            } else {
                $this->logger->debug("Skipping non-file item", [
                    'name' => $file['name'],
                    'type' => $file['type']
                ]);
            }
        }
        
        $this->logger->info("Scan file filtering complete", [
            'totalItems' => count($result['items']),
            'scanFiles' => count($scanFiles),
            'supportedExtensions' => $supportedExtensions
        ]);
        
        return $scanFiles;
    }
    
    /**
     * Parse NAS API response to standardized format (updated for actual API response)
     */
    private function parseNasApiResponse(array $data): array
    {
        $result = [
            'items' => [],
            'browse' => $data['browse'] ?? null,
            'diskInfo' => $data['diskInfo'] ?? null
        ];
        
        if (isset($data['directories'])) {
            foreach ($data['directories'] as $dir) {
                $result['items'][] = [
                    'type' => 'folder',
                    'name' => $dir['filename'],
                    'path' => $dir['path'] ?? '',
                    'size' => 0,
                    'modified' => isset($dir['timestamp']) ? (int)$dir['timestamp'] : 0,
                    'shared' => $dir['shared'] ?? false,
                    'storageType' => $dir['storageType'] ?? 'unknown'
                ];
            }
        }
        
        if (isset($data['files'])) {
            foreach ($data['files'] as $file) {
                $result['items'][] = [
                    'type' => 'file',
                    'name' => $file['filename'],
                    'path' => $file['path'] ?? '',
                    'size' => $file['size'] ?? 0,
                    'modified' => isset($file['timestamp']) ? (int)$file['timestamp'] : 0,
                    'shared' => $file['shared'] ?? false,
                    'storageType' => $file['storageType'] ?? 'unknown',
                    'fileType' => $file['type'] ?? 'unknown'
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Delete multiple files at once
     */
    public function deleteMultipleFiles(array $filePaths): int
    {
        $this->logger->info("Deleting multiple files", ['count' => count($filePaths)]);
        
        $params = [
            'c' => 'files',
            'a' => 'delete'
        ];
        
        foreach ($filePaths as $index => $path) {
            $params["paths[" . ($index + 1) . "]"] = $path;
        }
        
        $result = $this->makeLuaRequest('/nas/api/data.lua', $params);
        
        if ($result && isset($result['deleteCount'])) {
            $this->logger->info("Deleted {$result['deleteCount']} files");
            return (int)$result['deleteCount'];
        }
        
        $this->logger->error("Failed to delete multiple files");
        return 0;
    }
    
    /**
     * Create a new folder
     */
    public function createFolder(string $parentPath, string $name): bool
    {
        $this->logger->info("Creating folder", ['parentPath' => $parentPath, 'name' => $name]);
        
        $params = [
            'c' => 'files',
            'a' => 'create_dir',
            'path' => $parentPath,
            'name' => $name,
            'parents' => 'false'
        ];
        
        $result = $this->makeLuaRequest('/nas/api/data.lua', $params);
        
        if ($result !== null) {
            $this->logger->info("Created folder: $parentPath/$name");
            return true;
        }
        
        $this->logger->error("Failed to create folder: $parentPath/$name");
        return false;
    }
    
    /**
     * Rename a file or folder
     */
    public function renameFile(string $filePath, string $newName): bool
    {
        $this->logger->info("Renaming file", ['filePath' => $filePath, 'newName' => $newName]);
        
        $params = [
            'c' => 'files',
            'a' => 'rename',
            'paths[1][path]' => $filePath,
            'paths[1][newName]' => $newName
        ];
        
        $result = $this->makeLuaRequest('/nas/api/data.lua', $params);
        
        if ($result !== null) {
            $this->logger->info("Renamed file: $filePath -> $newName");
            return true;
        }
        
        $this->logger->error("Failed to rename file: $filePath");
        return false;
    }
    
    /**
     * Check if a file exists
     */
    public function fileExists(string $path, string $filename): bool
    {
        $this->logger->debug("Checking if file exists", ['path' => $path, 'filename' => $filename]);
        
        $params = [
            'c' => 'files',
            'a' => 'exist',
            'path' => $path,
            'keywords[1]' => $filename,
            'mode' => 'directory'
        ];
        
        $result = $this->makeLuaRequest('/nas/api/data.lua', $params);
        
        return $result && isset($result['exist']) && $result['exist'] === true;
    }
    
    /**
     * Get disk information
     */
    public function getDiskInfo(): ?array
    {
        $result = $this->getFileList('/');
        if ($result && isset($result['diskInfo'])) {
            return $result['diskInfo'];
        }
        return null;
    }
}