<?php

namespace Four\FritzHttpClient\Client;

use Psr\Log\LoggerInterface;

/**
 * Abstract base class for FritzBox API clients
 * Handles authentication and common functionality
 */
abstract class AbstractFritzClient implements FritzClientInterface
{
    protected string $myfritzUrl;
    protected string $username;
    protected string $password;
    protected string $sessionId = '';
    protected int $sessionExpiry = 0;
    protected array $config;
    protected LoggerInterface $logger;
    
    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = array_merge([
            'myfritz_url' => '',
            'ip' => '192.168.178.1',
            'username' => '',
            'password' => '',
            'timeout' => 30,
            'max_retries' => 3
        ], $config);
        
        $this->logger = $logger;
        
        // Prefer MyFRITZ URL if provided, otherwise use IP with HTTPS
        if (!empty($this->config['myfritz_url'])) {
            $this->myfritzUrl = rtrim($this->config['myfritz_url'], '/');
        } else {
            // Handle IP with optional port
            $ip = $this->config['ip'];
            if (!str_contains($ip, '://')) {
                // Add protocol if not present
                $useHttps = $this->config['use_https'] ?? true;
                $protocol = $useHttps ? 'https://' : 'http://';
                $this->myfritzUrl = $protocol . $ip;
            } else {
                $this->myfritzUrl = $ip;
            }
        }
        
        $this->username = $this->config['username'];
        $this->password = $this->config['password'];
    }
    
    public function isReachable(): bool
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->myfritzUrl . '/login_sid.lua?version=2',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Four-Fritz-HttpClient/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200) {
            // Check for active BlockTime
            if (preg_match('/<BlockTime>(\d+)<\/BlockTime>/', $response, $matches)) {
                $blockTime = (int)$matches[1];
                if ($blockTime > 0) {
                    $this->logger->warning("FritzBox reachable but rate-limited: {$blockTime}s BlockTime");
                    return false;
                }
            }
            return true;
        }
        
        $this->logger->error("FritzBox not reachable: HTTP $httpCode - $error");
        return false;
    }
    
    public function authenticate(): bool
    {
        $this->logger->info("Starting authentication process", [
            'url' => $this->myfritzUrl,
            'username' => $this->username ?: '(password-only)'
        ]);
        
        if (!$this->isReachable()) {
            $this->logger->error("FritzBox not reachable, cannot authenticate");
            return false;
        }
        
        try {
            // Get login state using official AVM endpoint
            $this->logger->debug("Getting login state from FritzBox");
            $loginState = $this->getLoginState();
            
            $this->logger->debug("Login state received", [
                'challenge' => substr($loginState['challenge'], 0, 20) . '...',
                'blocktime' => $loginState['blocktime'],
                'sid' => $loginState['sid']
            ]);
            
            if ($loginState['blocktime'] > 0) {
                $this->logger->warning("Rate limited! BlockTime: {$loginState['blocktime']} seconds");
                return false;
            }
            
            // Calculate response based on challenge type
            if ($this->isPbkdf2($loginState['challenge'])) {
                $this->logger->info("Using PBKDF2 method (FritzOS 7.24+)");
                $challengeResponse = $this->calculatePbkdf2Response($loginState['challenge'], $this->password);
            } else {
                $this->logger->info("Using MD5 method (legacy)");
                $challengeResponse = $this->calculateMd5Response($loginState['challenge'], $this->password);
            }
            
            // Send response and get SID
            $sid = $this->sendAuthResponse($this->username, $challengeResponse);
            
            if ($sid === '0000000000000000') {
                $this->logger->error("Authentication failed - wrong credentials");
                return false;
            }
            
            $this->sessionId = $sid;
            $this->sessionExpiry = time() + (20 * 60);
            $this->logger->info("Authentication successful. SID: {$this->sessionId}");
            $this->saveSidToFile();
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Central method for making LUA API requests to FritzBox
     */
    protected function makeLuaRequest(string $endpoint, array $params, string $method = 'POST'): ?array
    {
        if (!$this->ensureValidSession()) {
            $this->logger->error("No valid session for LUA request", ['endpoint' => $endpoint]);
            return null;
        }
        
        // Add session ID to parameters
        $params['sid'] = $this->sessionId;
        
        $url = $this->myfritzUrl . $endpoint;
        
        $this->logger->debug("Making LUA API request", [
            'url' => $url,
            'method' => $method,
            'params' => array_merge($params, ['sid' => '***']) // Hide SID in logs
        ]);
        
        $ch = curl_init();
        if ($method === 'POST') {
            $postData = http_build_query($params);
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
            ]);
        } else {
            $url .= '?' . http_build_query($params);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_USERAGENT => 'Four-Fritz-HttpClient/1.0',
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $this->logger->debug("LUA API response", [
            'httpCode' => $httpCode,
            'curlError' => $curlError,
            'responseLength' => strlen($response),
            'endpoint' => $endpoint
        ]);
        
        if ($httpCode !== 200 || !$response) {
            $this->logger->error("LUA API request failed", [
                'httpCode' => $httpCode,
                'curlError' => $curlError,
                'endpoint' => $endpoint
            ]);
            return null;
        }
        
        // Try to decode as JSON
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning("Non-JSON response from LUA API", [
                'endpoint' => $endpoint,
                'jsonError' => json_last_error_msg(),
                'response' => substr($response, 0, 200)
            ]);
            // Return raw response for non-JSON endpoints (like file downloads)
            return ['_raw_response' => $response, '_http_code' => $httpCode];
        }
        
        return $data;
    }
    
    /**
     * Make a file download request (returns raw content)
     */
    protected function makeFileDownloadRequest(string $endpoint, array $params): ?string
    {
        if (!$this->ensureValidSession()) {
            return null;
        }
        
        $params['sid'] = $this->sessionId;
        $url = $this->myfritzUrl . $endpoint . '?' . http_build_query($params);
        
        $this->logger->debug("Making file download request", ['url' => $url]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_USERAGENT => 'Four-Fritz-HttpClient/1.0',
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200 || $response === false) {
            $this->logger->error("File download failed", [
                'httpCode' => $httpCode,
                'curlError' => $curlError,
                'endpoint' => $endpoint
            ]);
            return null;
        }
        
        return $response;
    }
    
    /**
     * Ensure we have a valid session ID
     */
    protected function ensureValidSession(): bool
    {
        // Check if we have a SID and it's not expired
        if (!empty($this->sessionId) && time() < $this->sessionExpiry) {
            if ($this->testSidValidity()) {
                return true;
            }
        }
        
        // Try to load SID from file
        if ($this->loadSidFromFile()) {
            if ($this->testSidValidity()) {
                return true;
            }
        }
        
        // Need to authenticate
        return $this->authenticate();
    }
    
    private function getLoginState(): array
    {
        $url = $this->myfritzUrl . '/login_sid.lua?version=2';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_USERAGENT => 'Four-Fritz-HttpClient/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            throw new \Exception("Failed to get login state (HTTP $httpCode)");
        }
        
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            throw new \Exception("Invalid XML response");
        }
        
        return [
            'challenge' => (string)$xml->Challenge,
            'blocktime' => (int)$xml->BlockTime,
            'sid' => (string)$xml->SID
        ];
    }
    
    private function isPbkdf2(string $challenge): bool
    {
        return str_starts_with($challenge, '2$');
    }
    
    private function calculatePbkdf2Response(string $challenge, string $password): string
    {
        $parts = explode('$', $challenge);
        
        if (count($parts) !== 5) {
            throw new \Exception("Invalid PBKDF2 challenge format");
        }
        
        $iter1 = (int)$parts[1];
        $salt1 = hex2bin($parts[2]);
        $iter2 = (int)$parts[3];
        $salt2 = hex2bin($parts[4]);
        
        // Hash twice as per AVM specification
        $hash1 = hash_pbkdf2('sha256', $password, $salt1, $iter1, 32, true);
        $hash2 = hash_pbkdf2('sha256', $hash1, $salt2, $iter2, 32, true);
        
        return $parts[4] . '$' . bin2hex($hash2);
    }
    
    private function calculateMd5Response(string $challenge, string $password): string
    {
        // Official AVM method: challenge-password
        $responseInput = $challenge . '-' . $password;
        
        // Convert to UTF-16LE as per AVM specification
        $utf16le = mb_convert_encoding($responseInput, 'UTF-16LE', 'UTF-8');
        
        // Calculate MD5 and return in correct format
        $md5Hash = md5($utf16le);
        return $challenge . '-' . $md5Hash;
    }
    
    private function sendAuthResponse(string $username, string $challengeResponse): string
    {
        $url = $this->myfritzUrl . '/login_sid.lua?version=2';
        
        $postData = http_build_query([
            'username' => $username,
            'response' => $challengeResponse
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            throw new \Exception("Failed to send auth response (HTTP $httpCode)");
        }
        
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            throw new \Exception("Invalid XML response");
        }
        
        return (string)$xml->SID;
    }
    
    private function testSidValidity(): bool
    {
        if (empty($this->sessionId)) {
            return false;
        }
        
        $testUrl = $this->myfritzUrl . '/login_sid.lua?version=2&sid=' . $this->sessionId;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Four-Fritz-HttpClient/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            if (preg_match('/<SID>(.*?)<\/SID>/', $response, $matches)) {
                $returnedSid = $matches[1];
                if ($returnedSid !== '0000000000000000' && $returnedSid === $this->sessionId) {
                    return true;
                }
            }
        }
        
        $this->sessionId = '';
        $this->sessionExpiry = 0;
        return false;
    }
    
    private function saveSidToFile(): void
    {
        $sidFile = sys_get_temp_dir() . '/fritzbox_sid_' . md5($this->myfritzUrl . $this->username) . '.json';
        $data = [
            'sid' => $this->sessionId,
            'expiry' => $this->sessionExpiry,
            'username' => $this->username,
            'url' => $this->myfritzUrl
        ];
        file_put_contents($sidFile, json_encode($data), LOCK_EX);
    }
    
    private function loadSidFromFile(): bool
    {
        $sidFile = sys_get_temp_dir() . '/fritzbox_sid_' . md5($this->myfritzUrl . $this->username) . '.json';
        
        if (!file_exists($sidFile)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($sidFile), true);
        if (!$data || !isset($data['sid'], $data['expiry'])) {
            return false;
        }
        
        // Check if SID is for the same user and URL
        if ($data['username'] !== $this->username || $data['url'] !== $this->myfritzUrl) {
            return false;
        }
        
        // Check if not expired
        if (time() >= $data['expiry']) {
            unlink($sidFile);
            return false;
        }
        
        $this->sessionId = $data['sid'];
        $this->sessionExpiry = $data['expiry'];
        $this->logger->info("Loaded valid SID from cache");
        return true;
    }
    
    public function getStatus(): array
    {
        return [
            'reachable' => $this->isReachable(),
            'authenticated' => !empty($this->sessionId),
            'session_id' => $this->sessionId,
            'config' => $this->config
        ];
    }
}