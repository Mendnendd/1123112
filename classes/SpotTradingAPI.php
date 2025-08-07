<?php

class SpotTradingAPI {
    private $db;
    private $binance;
    private $settings;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->binance = new BinanceAPI();
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $this->settings = $this->db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
    }
    
    public function getSpotAccount() {
        if (!$this->binance->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        try {
            $account = $this->makeSpotRequest('/api/v3/account', [], 'GET', true);
            
            // Update spot balances in database
            $this->updateSpotBalances($account['balances']);
            
            return $account;
        } catch (Exception $e) {
            error_log("Spot API getAccount error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getSpotBalances() {
        try {
            $account = $this->getSpotAccount();
            return $account['balances'];
        } catch (Exception $e) {
            error_log("Error getting spot balances: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function placeSpotOrder($symbol, $side, $type, $quantity, $price = null, $timeInForce = 'GTC') {
        if (!$this->binance->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
            'quantity' => $quantity
        ];
        
        if ($price && in_array($type, ['LIMIT', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT_LIMIT'])) {
            $params['price'] = $price;
            $params['timeInForce'] = $timeInForce;
        }
        
        try {
            $order = $this->makeSpotRequest('/api/v3/order', $params, 'POST', true);
            
            // Log the trade
            $this->logSpotTrade($order, $symbol, $side, $type, $quantity, $price);
            
            return $order;
        } catch (Exception $e) {
            error_log("Spot order placement error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getSpotOpenOrders($symbol = null) {
        if (!$this->binance->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        
        return $this->makeSpotRequest('/api/v3/openOrders', $params, 'GET', true);
    }
    
    public function cancelSpotOrder($symbol, $orderId) {
        if (!$this->binance->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [
            'symbol' => $symbol,
            'orderId' => $orderId
        ];
        
        return $this->makeSpotRequest('/api/v3/order', $params, 'DELETE', true);
    }
    
    public function getSpotOrderHistory($symbol, $limit = 100) {
        if (!$this->binance->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [
            'symbol' => $symbol,
            'limit' => $limit
        ];
        
        return $this->makeSpotRequest('/api/v3/allOrders', $params, 'GET', true);
    }
    
    public function getSpotTicker($symbol = null) {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        
        try {
            $result = $this->makeSpotRequest('/api/v3/ticker/24hr', $params);
            
            // Ensure we always return an array
            if ($result === null || $result === false) {
                return $this->getMockSpotTickerData($symbol);
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
            // Check if it's an invalid symbol error
            if (strpos($e->getMessage(), 'Invalid symbol') !== false) {
                error_log("Invalid symbol for spot trading: {$symbol}");
                throw new Exception("Symbol {$symbol} is not valid for spot trading");
            }
            
            // If API credentials are not configured, return mock data for development
            if (!$this->binance->hasCredentials()) {
                return $this->getMockSpotTickerData($symbol);
            }
            throw $e;
        }
    }
    
    public function getSpotKlines($symbol, $interval = '1h', $limit = 100) {
        $params = [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit
        ];
        
        try {
            $result = $this->makeSpotRequest('/api/v3/klines', $params);
            
            // Ensure we return valid data
            if ($result === null || $result === false || !is_array($result)) {
                return $this->getMockSpotKlinesData($limit);
            }
            
            return $result;
        } catch (Exception $e) {
            // Check if it's an invalid symbol error
            if (strpos($e->getMessage(), 'Invalid symbol') !== false) {
                error_log("Invalid symbol for spot klines: {$symbol}");
                throw new Exception("Symbol {$symbol} is not valid for spot trading");
            }
            
            // If API credentials are not configured, return mock data for development
            if (!$this->binance->hasCredentials()) {
                return $this->getMockSpotKlinesData($limit);
            }
            throw $e;
        }
    }
    
    public function getSpotTrades($symbol, $limit = 100) {
        if (!$this->binance->hasCredentials()) {
            throw new Exception('API credentials not configured');
        }
        
        $params = [
            'symbol' => $symbol,
            'limit' => $limit
        ];
        
        return $this->makeSpotRequest('/api/v3/myTrades', $params, 'GET', true);
    }
    
    private function updateSpotBalances($balances) {
        try {
            // Check if spot_balances table exists
            $tableExists = $this->db->fetchOne("SHOW TABLES LIKE 'spot_balances'");
            if (!$tableExists) {
                // Create the table if it doesn't exist
                $this->db->query("
                    CREATE TABLE IF NOT EXISTS `spot_balances` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `asset` varchar(10) NOT NULL,
                      `free` decimal(20,8) NOT NULL DEFAULT 0.00000000,
                      `locked` decimal(20,8) NOT NULL DEFAULT 0.00000000,
                      `total` decimal(20,8) NOT NULL DEFAULT 0.00000000,
                      `btc_value` decimal(20,8) DEFAULT 0.00000000,
                      `usdt_value` decimal(20,8) DEFAULT 0.00000000,
                      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `asset` (`asset`),
                      KEY `updated_at` (`updated_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");
            }
            
            // Clear existing balances
            $this->db->query("DELETE FROM spot_balances");
            
            foreach ($balances as $balance) {
                $free = (float)$balance['free'];
                $locked = (float)$balance['locked'];
                $total = $free + $locked;
                
                if ($total > 0) {
                    // Get USDT value (simplified - in production you'd get real prices)
                    $usdtValue = $this->calculateUSDTValue($balance['asset'], $total);
                    
                    $this->db->insert('spot_balances', [
                        'asset' => $balance['asset'],
                        'free' => $free,
                        'locked' => $locked,
                        'total' => $total,
                        'usdt_value' => $usdtValue
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Error updating spot balances: " . $e->getMessage());
        }
    }
    
    private function calculateUSDTValue($asset, $amount) {
        if ($asset === 'USDT') {
            return $amount;
        }
        
        try {
            // Get price from ticker
            $ticker = $this->getSpotTicker($asset . 'USDT');
            if (!empty($ticker) && isset($ticker[0]['lastPrice'])) {
                return $amount * (float)$ticker[0]['lastPrice'];
            }
        } catch (Exception $e) {
            // Fallback to mock price
        }
        
        // Mock prices for common assets
        $mockPrices = [
            'BTC' => 50000,
            'ETH' => 3000,
            'BNB' => 400,
            'ADA' => 0.5,
            'DOT' => 20,
            'LINK' => 15,
            'LTC' => 100,
            'XRP' => 0.6
        ];
        
        return $amount * ($mockPrices[$asset] ?? 1);
    }
    
    private function roundToSpotPrecision($symbol, $quantity) {
        try {
            // Get symbol info from database
            $symbolInfo = $this->db->fetchOne("SELECT step_size FROM trading_pairs WHERE symbol = ?", [$symbol]);
            
            if ($symbolInfo && $symbolInfo['step_size'] > 0) {
                $stepSize = (float)$symbolInfo['step_size'];
                $precision = strlen(substr(strrchr($stepSize, "."), 1));
                return round($quantity, $precision);
            }
            
            // Safe defaults for spot trading (generally more restrictive than futures)
            $spotPrecisionMap = [
                'BTCUSDT' => 5,
                'ETHUSDT' => 4,
                'BNBUSDT' => 3,
                'ADAUSDT' => 0,
                'DOTUSDT' => 2,
                'LINKUSDT' => 2,
                'LTCUSDT' => 3,
                'XRPUSDT' => 0,
                'SOLUSDT' => 2,
                'AVAXUSDT' => 2,
                'OMUSDT' => 0  // OMUSDT requires whole numbers
            ];
            
            $precision = $spotPrecisionMap[$symbol] ?? 4;
            
            // Ensure minimum quantity requirements
            $roundedQuantity = round($quantity, $precision);
            
            // For symbols that require whole numbers, ensure we have at least 1
            if ($precision === 0 && $roundedQuantity < 1) {
                $roundedQuantity = 1;
            }
            
            return $roundedQuantity;
            
        } catch (Exception $e) {
            error_log("Error getting spot precision for {$symbol}: " . $e->getMessage());
            // Safe fallback
            return round($quantity, 4);
        }
    }
    
    private function logSpotTrade($order, $symbol, $side, $type, $quantity, $price) {
        try {
            $tradeData = [
                'symbol' => $symbol,
                'trading_type' => 'SPOT',
                'side' => $side,
                'type' => $type,
                'quantity' => $quantity,
                'price' => $price,
                'executed_price' => $order['price'] ?? $price ?? 0,
                'executed_qty' => $order['executedQty'] ?? $quantity,
                'status' => $order['status'] ?? 'FILLED',
                'order_id' => $order['orderId'] ?? null,
                'client_order_id' => $order['clientOrderId'] ?? null,
                'commission' => 0, // Will be updated from trade history
                'notes' => 'Spot Trade'
            ];
            
            $this->db->insert('trading_history', $tradeData);
        } catch (Exception $e) {
            error_log("Error logging spot trade: " . $e->getMessage());
        }
    }
    
    private function makeSpotRequest($endpoint, $params = [], $method = 'GET', $signed = false) {
        $baseUrl = $this->settings && $this->settings['testnet_mode'] 
            ? 'https://testnet.binance.vision' 
            : 'https://api.binance.com';
            
        
        // We need to implement our own request method for spot API
        if (!$this->binance->hasCredentials() && $signed) {
            throw new Exception('API credentials not configured');
        }
        
        $url = $baseUrl . $endpoint;
        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        if ($signed && $this->binance->hasCredentials()) {
            // Get API key from settings
            $settings = $this->db->fetchOne("SELECT binance_api_key FROM trading_settings WHERE id = 1");
            if ($settings && $settings['binance_api_key']) {
                $headers[] = 'X-MBX-APIKEY: ' . $settings['binance_api_key'];
            }
        }
        
        if ($signed) {
            $params['timestamp'] = (string)round(microtime(true) * 1000);
            $params['recvWindow'] = 5000;
        }
        
        $queryString = http_build_query($params);
        
        if ($signed && $this->binance->hasCredentials()) {
            // Get API secret and create signature
            $settings = $this->db->fetchOne("SELECT binance_api_secret FROM trading_settings WHERE id = 1");
            if ($settings && $settings['binance_api_secret']) {
                $apiSecret = $this->decryptApiSecret($settings['binance_api_secret']);
                if ($apiSecret) {
                    $signature = hash_hmac('sha256', $queryString, $apiSecret);
                    $queryString .= '&signature=' . $signature;
                }
            }
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $method === 'GET' ? $url . '?' . $queryString : $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
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
            // Handle timeout errors gracefully
            if (strpos($error, 'timeout') !== false || strpos($error, 'timed out') !== false) {
                error_log("Spot API timeout for {$endpoint}: {$error} (took {$totalTime}s)");
                throw new Exception('Spot API request timeout - please try again');
            }
            throw new Exception('Spot Network Error: ' . $error);
        }
        
        // Log slow requests
        if ($totalTime > 10) {
            error_log("Slow spot API request for {$endpoint}: {$totalTime}s");
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $errorMsg = isset($data['msg']) ? $data['msg'] : 'Unknown API error';
            
            // Handle precision errors specifically
            if ($httpCode === 400 && strpos($errorMsg, 'Precision') !== false) {
                $errorMsg = "Invalid quantity precision for spot trading. " . $errorMsg;
            }
            
            throw new Exception("Spot API Error ({$httpCode}): {$errorMsg}");
        }
        
        return $data;
    }
    
    private function decryptApiSecret($encryptedSecret) {
        if (empty($encryptedSecret)) return null;
        
        if (!defined('ENCRYPTION_KEY')) {
            return null;
        }
        
        try {
            $key = ENCRYPTION_KEY;
            $data = base64_decode($encryptedSecret);
            
            if (strlen($data) < 16) {
                return null;
            }
            
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            
            return $decrypted !== false ? $decrypted : null;
        } catch (Exception $e) {
            error_log('Failed to decrypt API secret: ' . $e->getMessage());
            return null;
        }
    }
    
    private function getMockSpotTickerData($symbol = null) {
        return [[
            'symbol' => $symbol ?: 'BTCUSDT',
            'lastPrice' => '50000.00',
            'priceChangePercent' => '2.50',
            'volume' => '1000.00',
            'highPrice' => '51000.00',
            'lowPrice' => '49000.00',
            'count' => 100
        ]];
    }
    
    private function getMockSpotKlinesData($limit) {
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
                rand(1000000, 5000000), // Quote asset volume
                rand(50, 200), // Number of trades
                rand(500, 800), // Taker buy base asset volume
                rand(500000, 2000000), // Taker buy quote asset volume
                '0' // Ignore
            ];
        }
        return array_reverse($mockData);
    }
}