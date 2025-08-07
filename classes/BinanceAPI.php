<?php

class BinanceAPI {
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $testnet;
    private $db;
    
    public function __construct($testnet = null) {
        $this->db = Database::getInstance();
        $this->loadCredentials();
        
        // Override testnet setting if provided
        if ($testnet !== null) {
            $this->testnet = $testnet;
        }
        
        $this->baseUrl = $this->testnet 
            ? 'https://testnet.binancefuture.com'
            : 'https://fapi.binance.com';
    }
    
    private function loadCredentials() {
        try {
            $settings = $this->db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
            
            if ($settings) {
                $this->apiKey = $settings['binance_api_key'];
                $this->apiSecret = !empty($settings['binance_api_secret']) ? $this->decrypt($settings['binance_api_secret']) : null;
                $this->testnet = (bool)$settings['testnet_mode'];
            } else {
                // Create default settings if none exist
                $this->db->insert('trading_settings', [
                    'id' => 1,
                    'testnet_mode' => 1,
                    'trading_enabled' => 0,
                    'ai_enabled' => 1
                ]);
                $this->testnet = true;
                $this->apiKey = null;
                $this->apiSecret = null;
            }
        } catch (Exception $e) {
            error_log("Failed to load API credentials: " . $e->getMessage());
            $this->testnet = true;
            $this->apiKey = null;
            $this->apiSecret = null;
        }
    }
    
    public function hasCredentials() {
        return !empty($this->apiKey) && !empty($this->apiSecret);
    }
    
    public function getCredentialsStatus() {
        return [
            'has_api_key' => !empty($this->apiKey),
            'has_api_secret' => !empty($this->apiSecret),
            'testnet_mode' => $this->testnet,
            'base_url' => $this->baseUrl
        ];
    }
        
    public function testConnection() {
        try {
            if (!$this->hasCredentials()) {
                return [
                    'success' => false, 
                    'message' => 'API credentials not configured. Please set your Binance API key and secret in Settings.'
                ];
            }
            
            // Test with a simple ping first (no auth required)
            try {
                $response = $this->makeRequest('/fapi/v1/ping');
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Cannot reach Binance API: ' . $e->getMessage()];
            }
            
            // Test authenticated endpoint
            try {
                $account = $this->makeRequest('/fapi/v2/account', [], 'GET', true);
                return ['success' => true, 'message' => 'API connection and authentication successful'];
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'API authentication failed: ' . $e->getMessage()];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getAccountInfo() {
        if (!$this->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        try {
            $account = $this->makeRequest('/fapi/v2/account', [], 'GET', true);
            
            // Normalize the account data structure
            if (isset($account['assets']) && is_array($account['assets'])) {
                // Find USDT asset and extract balance
                $usdtAsset = null;
                foreach ($account['assets'] as $asset) {
                    if (isset($asset['asset']) && $asset['asset'] === 'USDT') {
                        $usdtAsset = $asset;
                        break;
                    }
                }
                
                if ($usdtAsset) {
                    $account['availableBalance'] = (float)($usdtAsset['availableBalance'] ?? $usdtAsset['walletBalance'] ?? 0);
                    $account['walletBalance'] = (float)($usdtAsset['walletBalance'] ?? 0);
                } else {
                    $account['availableBalance'] = 0;
                    $account['walletBalance'] = 0;
                }
            } else {
                // Handle case where assets is not an array or missing
                $account['availableBalance'] = (float)($account['availableBalance'] ?? 0);
                $account['walletBalance'] = (float)($account['totalWalletBalance'] ?? 0);
                $account['assets'] = [];
            }
            
            // Ensure all numeric fields are properly typed
            $account['totalWalletBalance'] = (float)($account['totalWalletBalance'] ?? 0);
            $account['totalUnrealizedProfit'] = (float)($account['totalUnrealizedProfit'] ?? 0);
            $account['totalMarginBalance'] = (float)($account['totalMarginBalance'] ?? 0);
            $account['totalPositionInitialMargin'] = (float)($account['totalPositionInitialMargin'] ?? 0);
            $account['totalOpenOrderInitialMargin'] = (float)($account['totalOpenOrderInitialMargin'] ?? 0);
            $account['maxWithdrawAmount'] = (float)($account['maxWithdrawAmount'] ?? 0);
            
            return $account;
        } catch (Exception $e) {
            error_log("Binance API getAccountInfo error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getPositions() {
        if (!$this->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        try {
            $positions = $this->makeRequest('/fapi/v2/positionRisk', [], 'GET', true);
            
            // Filter and enhance position data
            $activePositions = [];
            foreach ($positions as $position) {
                $positionAmt = (float)$position['positionAmt'];
                if ($positionAmt != 0) {
                    // Calculate percentage
                    $entryPrice = (float)$position['entryPrice'];
                    $markPrice = (float)$position['markPrice'];
                    $percentage = 0;
                    
                    if ($entryPrice > 0) {
                        if ($positionAmt > 0) { // Long position
                            $percentage = (($markPrice - $entryPrice) / $entryPrice) * 100;
                        } else { // Short position
                            $percentage = (($entryPrice - $markPrice) / $entryPrice) * 100;
                        }
                    }
                    
                    $position['percentage'] = $percentage;
                    $activePositions[] = $position;
                }
            }
            
            return $activePositions;
        } catch (Exception $e) {
            error_log("Binance API getPositions error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getBalance() {
        if (!$this->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        try {
            $balance = $this->makeRequest('/fapi/v2/balance', [], 'GET', true);
            
            // Find USDT balance
            $usdtBalance = null;
            foreach ($balance as $asset) {
                if ($asset['asset'] === 'USDT') {
                    $usdtBalance = $asset;
                    break;
                }
            }
            
            return $usdtBalance ?: $balance;
        } catch (Exception $e) {
            error_log("Binance API getBalance error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function get24hrTicker($symbol = null) {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        
        try {
            $result = $this->makeRequest('/fapi/v1/ticker/24hr', $params);
            
            // Ensure we always return an array
            if ($result === null || $result === false) {
                return $this->getMockTickerData($symbol);
            }
            
            // If single symbol requested, ensure it's wrapped in array
            if ($symbol && !is_array($result)) {
                return [$result];
            }
            
            // If single symbol but result is not array, wrap it
            if ($symbol && is_array($result) && !isset($result[0])) {
                return [$result];
            }
            
            return $result;
        } catch (Exception $e) {
            // If API credentials are not configured, return mock data for development
            if (!$this->hasCredentials()) {
                return $this->getMockTickerData($symbol);
            }
            throw $e;
        }
    }
    
    private function getMockTickerData($symbol = null) {
        return [[
            'symbol' => $symbol ?: 'BTCUSDT',
            'lastPrice' => '50000.00',
            'priceChangePercent' => '2.50',
            'volume' => '1000.00',
            'highPrice' => '51000.00',
            'lowPrice' => '49000.00'
        ]];
    }
    
    public function getKlines($symbol, $interval = '1h', $limit = 100) {
        $params = [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit
        ];
        
        try {
            $result = $this->makeRequest('/fapi/v1/klines', $params);
            
            // Ensure we return valid data
            if ($result === null || $result === false || !is_array($result)) {
                return $this->getMockKlinesData($limit);
            }
            
            return $result;
        } catch (Exception $e) {
            // If API credentials are not configured, return mock data for development
            if (!$this->hasCredentials()) {
                return $this->getMockKlinesData($limit);
            }
            throw $e;
        }
    }
    
    private function getMockKlinesData($limit) {
        $mockData = [];
        $basePrice = 50000;
        for ($i = 0; $i < $limit; $i++) {
            $price = $basePrice + (rand(-1000, 1000));
            $mockData[] = [
                time() - ($i * 3600), // Open time
                $price, // Open
                $price + rand(-100, 100), // High
                $price - rand(-100, 100), // Low
                $price + rand(-50, 50), // Close
                rand(100, 1000), // Volume
                time() - ($i * 3600) + 3599, // Close time
            ];
        }
        return array_reverse($mockData);
    }
    
    public function placeOrder($symbol, $side, $type, $quantity, $price = null, $timeInForce = 'GTC') {
        if (!$this->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
            'quantity' => $quantity
        ];
        
        if ($price && in_array($type, ['LIMIT', 'STOP', 'TAKE_PROFIT'])) {
            $params['price'] = $price;
            $params['timeInForce'] = $timeInForce;
        }
        
        return $this->makeRequest('/fapi/v1/order', $params, 'POST', true);
    }
    
    public function placeStopOrder($symbol, $side, $type, $quantity, $stopPrice, $price = null) {
        if (!$this->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
            'quantity' => $quantity,
            'stopPrice' => $stopPrice
        ];
        
        // For STOP_MARKET orders, we only need stopPrice
        // For STOP orders, we need both stopPrice and price
        if ($price && $type === 'STOP') {
            $params['price'] = $price;
            $params['timeInForce'] = 'GTC';
        }
        
        return $this->makeRequest('/fapi/v1/order', $params, 'POST', true);
    }
    
    public function cancelOrder($symbol, $orderId) {
        if (!$this->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [
            'symbol' => $symbol,
            'orderId' => $orderId
        ];
        return $this->makeRequest('/fapi/v1/order', $params, 'DELETE', true);
    }
    
    public function getOpenOrders($symbol = null) {
        if (!$this->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        return $this->makeRequest('/fapi/v1/openOrders', $params, 'GET', true);
    }
    
    public function getOrderHistory($symbol, $limit = 100) {
        if (!$this->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [
            'symbol' => $symbol,
            'limit' => $limit
        ];
        return $this->makeRequest('/fapi/v1/allOrders', $params, 'GET', true);
    }
    
    public function makeRequest($endpoint, $params = [], $method = 'GET', $signed = false) {
        return $this->makeRequestPublic($endpoint, $params, $method, $signed);
    }
    
    public function changeMarginType($symbol, $marginType) {
        if (!$this->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [
            'symbol' => $symbol,
            'marginType' => $marginType
        ];
        return $this->makeRequest('/fapi/v1/marginType', $params, 'POST', true);
    }
    
    public function changeLeverage($symbol, $leverage) {
        if (!$this->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [
            'symbol' => $symbol,
            'leverage' => $leverage
        ];
        return $this->makeRequest('/fapi/v1/leverage', $params, 'POST', true);
    }
    
    private function makeRequestPublic($endpoint, $params = [], $method = 'GET', $signed = false) {
        if ($signed && (!$this->apiKey || !$this->apiSecret)) {
            throw new Exception('API credentials not configured. Please set your Binance API key and secret in the admin Settings page.');
        }
        
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        if ($this->apiKey) {
            $headers[] = 'X-MBX-APIKEY: ' . $this->apiKey;
        }
        
        if ($signed) {
            $params['timestamp'] = round(microtime(true) * 1000);
            $params['recvWindow'] = 5000;
        }
        
        $queryString = http_build_query($params);
        
        if ($signed && $this->apiSecret) {
            $signature = hash_hmac('sha256', $queryString, $this->apiSecret);
            $queryString .= '&signature=' . $signature;
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $method === 'GET' ? $url . '?' . $queryString : $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Binance AI Trader/2.0',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);
        
        if ($error) {
            // Log timeout errors differently
            if (strpos($error, 'timeout') !== false || strpos($error, 'timed out') !== false) {
                error_log("API timeout for {$endpoint}: {$error} (took {$totalTime}s)");
                throw new Exception('API request timeout - please try again');
            }
            throw new Exception('Network Error: ' . $error);
        }
        
        // Log slow requests
        if ($totalTime > 10) {
            error_log("Slow API request for {$endpoint}: {$totalTime}s");
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $errorMsg = isset($data['msg']) ? $data['msg'] : 'Unknown API error';
            
            // Provide more specific error messages
            if ($httpCode === 401) {
                $errorMsg = "Invalid API credentials. Please check your API key and secret in Settings. Error: " . $errorMsg;
            } elseif ($httpCode === 403) {
                $errorMsg = "API key doesn't have required permissions. Please enable Futures trading permissions. Error: " . $errorMsg;
            } elseif ($httpCode === 400 && strpos($errorMsg, 'Precision') !== false) {
                $errorMsg = "Invalid quantity precision for this symbol. " . $errorMsg;
            }
            
            throw new Exception("Binance API Error ({$httpCode}): {$errorMsg}");
        }
        
        // Log API usage for rate limiting
        $this->logAPIUsage($endpoint);
        
        return $data;
    }
    
    private function logAPIUsage($endpoint) {
        try {
            // Check if table exists first
            $tableExists = $this->db->fetchOne("SHOW TABLES LIKE 'api_rate_limits'");
            if ($tableExists) {
                $this->db->query(
                    "INSERT INTO api_rate_limits (endpoint, requests_count, window_start) 
                     VALUES (?, 1, NOW()) 
                     ON DUPLICATE KEY UPDATE 
                     requests_count = requests_count + 1, 
                     last_request = NOW()",
                    [$endpoint]
                );
            }
        } catch (Exception $e) {
            error_log("Failed to log API usage: " . $e->getMessage());
        }
    }
    
    private function encrypt($data) {
        if (empty($data)) return null;
        
        if (!defined('ENCRYPTION_KEY')) {
            throw new Exception('Encryption key not configured');
        }
        
        $key = ENCRYPTION_KEY;
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    private function decrypt($data) {
        if (empty($data)) return null;
        
        if (!defined('ENCRYPTION_KEY')) {
            throw new Exception('Encryption key not configured');
        }
        
        $key = ENCRYPTION_KEY;
        $data = base64_decode($data);
        
        if (strlen($data) < 16) {
            // Return null for invalid data instead of throwing exception
            error_log('Invalid encrypted data format');
            return null;
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
        if ($decrypted === false) {
            error_log('Failed to decrypt API secret');
            return null;
        }
        
        return $decrypted;
    }
    
    public function updateCredentials($apiKey, $apiSecret) {
        $encryptedSecret = $this->encrypt($apiSecret);
        
        $this->db->update(
            'trading_settings',
            [
                'binance_api_key' => $apiKey,
                'binance_api_secret' => $encryptedSecret
            ],
            'id = ?',
            [1]
        );
        
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        
        return true;
    }
}