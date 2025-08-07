<?php

class TradingBot {
    protected $db;
    protected $binance;
    private $ai;
    protected $settings;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->binance = new BinanceAPI();
        $this->ai = new AIAnalyzer();
        $this->loadSettings();
    }
    
    protected function loadSettings() {
        $this->settings = $this->db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
        
        // Create default settings if none exist
        if (!$this->settings) {
            $this->db->insert('trading_settings', [
                'id' => 1,
                'testnet_mode' => 1,
                'trading_enabled' => 0,
                'ai_enabled' => 1,
                'max_position_size' => 100,
                'risk_percentage' => 2,
                'stop_loss_percentage' => 5,
                'take_profit_percentage' => 10,
                'leverage' => 10,
                'margin_type' => 'ISOLATED'
            ]);
            $this->settings = $this->db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
        }
    }
    
    public function run() {
        try {
            $this->log('INFO', 'Trading bot started');
            
            if (!$this->settings['trading_enabled']) {
                $this->log('INFO', 'Trading is disabled, skipping execution');
                return;
            }
            
            if (!$this->settings['ai_enabled']) {
                $this->log('INFO', 'AI analysis is disabled, skipping execution');
                return;
            }
            
            // Check if API credentials are configured
            if (!$this->binance->hasCredentials()) {
                $this->log('WARNING', 'Cannot run trading bot: API credentials not configured. Please set your Binance API key and secret in the admin Settings page.');
                return;
            }
            
            // Test API connection before proceeding
            try {
                $testResult = $this->binance->testConnection();
                if (!$testResult['success']) {
                    $this->log('WARNING', 'API connection test failed: ' . $testResult['message']);
                    return;
                }
            } catch (Exception $e) {
                $this->log('WARNING', 'API connection test failed: ' . $e->getMessage());
                return;
            }
            
            // Get active trading pairs
            $pairs = $this->db->fetchAll("SELECT * FROM trading_pairs WHERE enabled = 1");
            
            if (empty($pairs)) {
                $this->log('WARNING', 'No active trading pairs found');
                return;
            }
            
            $this->log('INFO', 'Found ' . count($pairs) . ' active trading pairs');
            
            foreach ($pairs as $pair) {
                $this->analyzePair($pair);
                sleep(1); // Rate limiting
            }
            
            // Update positions and balance after trading
            $this->updatePositions();
            $this->updateBalance();
            
            $this->log('INFO', 'Trading bot completed successfully');
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Trading bot error: ' . $e->getMessage());
        }
    }
    
    private function analyzePair($pair) {
        try {
            $symbol = $pair['symbol'];
            $this->log('INFO', "Analyzing {$symbol}");
            
            // Get AI analysis
            $analysis = $this->ai->analyzeSymbol($symbol);
            
            $this->log('INFO', "AI Analysis for {$symbol}: {$analysis['signal']} (Confidence: " . ($analysis['confidence'] * 100) . "%, Score: {$analysis['score']})");
            
            // Check if we should execute trade
            if ($this->shouldExecuteTrade($analysis, $pair)) {
                $this->executeTrade($analysis, $pair);
            } else {
                $this->log('INFO', "Skipping trade for {$symbol} - conditions not met");
            }
            
        } catch (Exception $e) {
            $this->log('ERROR', "Error analyzing {$pair['symbol']}: " . $e->getMessage());
        }
    }
    
    private function shouldExecuteTrade($analysis, $pair) {
        // Only trade high confidence signals
        if ($analysis['confidence'] < 0.75) {
            $this->log('INFO', "Low confidence signal for {$pair['symbol']}: " . ($analysis['confidence'] * 100) . "%");
            return false;
        }
        
        // Don't trade HOLD signals
        if ($analysis['signal'] === 'HOLD') {
            $this->log('INFO', "HOLD signal for {$pair['symbol']} - no action needed");
            return false;
        }
        
        // Check if we have recent signals for this symbol (avoid overtrading)
        $recentSignals = $this->db->fetchAll(
            "SELECT * FROM ai_signals WHERE symbol = ? AND executed = 1 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$pair['symbol']]
        );
        
        if (count($recentSignals) > 0) {
            $this->log('INFO', "Skipping {$pair['symbol']} - recent trade executed");
            return false;
        }
        
        // Check current position
        $currentPosition = $this->getCurrentPosition($pair['symbol']);
        
        // Don't open opposite position if we already have one
        if ($currentPosition) {
            if (($currentPosition['side'] === 'LONG' && $analysis['signal'] === 'SELL') ||
                ($currentPosition['side'] === 'SHORT' && $analysis['signal'] === 'BUY')) {
                $this->log('INFO', "Skipping {$pair['symbol']} - opposite position exists");
                return false;
            }
        }
        
        // Check account balance
        try {
            $account = $this->binance->getAccountInfo();
            
            // Extract balance information (already normalized by getAccountInfo)
            $availableBalance = (float)($account['availableBalance'] ?? 0);
            
            if ($availableBalance < $this->settings['max_position_size']) {
                $this->log('WARNING', "Insufficient balance for {$pair['symbol']}: Available: {$availableBalance}, Required: {$this->settings['max_position_size']}");
                return false;
            }
        } catch (Exception $e) {
            $this->log('ERROR', "Error checking balance for {$pair['symbol']}: " . $e->getMessage());
            return false;
        }
        
        return true;
    }
    
    private function executeTrade($analysis, $pair) {
        try {
            $symbol = $pair['symbol'];
            $side = $analysis['signal'] === 'BUY' ? 'BUY' : 'SELL';
            
            // Calculate position size
            $quantity = $this->calculatePositionSize($pair, $analysis);
            
            if ($quantity <= 0) {
                $this->log('WARNING', "Invalid quantity calculated for {$symbol}");
                return;
            }
            
            $this->log('INFO', "Executing {$side} order for {$symbol}: {$quantity}");
            
            // Set leverage and margin type before placing order
            try {
                // Only change leverage if needed
                try {
                    $this->binance->changeLeverage($symbol, $pair['leverage']);
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'No need to change leverage') === false) {
                        $this->log('WARNING', "Failed to set leverage for {$symbol}: " . $e->getMessage());
                    }
                }
                
                // Only change margin type if needed
                try {
                    $this->binance->changeMarginType($symbol, $pair['margin_type']);
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'No need to change margin type') === false) {
                        $this->log('WARNING', "Failed to set margin type for {$symbol}: " . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                $this->log('WARNING', "Failed to configure leverage/margin for {$symbol}: " . $e->getMessage());
                // Continue with trade as these might already be set
            }
            
            // Place market order
            $order = $this->binance->placeOrder($symbol, $side, 'MARKET', $quantity);
            
            if ($order) {
                // Save trade to database
                $tradeData = [
                    'symbol' => $symbol,
                    'side' => $side,
                    'type' => 'MARKET',
                    'quantity' => $quantity,
                    'price' => $analysis['price'] ?? 0,
                    'executed_price' => $order['avgPrice'] ?? $order['price'] ?? 0,
                    'executed_qty' => $order['executedQty'] ?? $quantity,
                    'status' => $order['status'] ?? 'FILLED',
                    'order_id' => $order['orderId'] ?? null,
                    'client_order_id' => $order['clientOrderId'] ?? null,
                    'ai_signal_id' => $analysis['signal_id'] ?? null,
                    'notes' => 'AI Bot Trade - Confidence: ' . ($analysis['confidence'] * 100) . '%'
                ];
                
                $tradeId = $this->db->insert('trading_history', $tradeData);
                
                // Mark signal as executed
                if (isset($analysis['signal_id'])) {
                    $this->db->update(
                        'ai_signals',
                        [
                            'executed' => 1,
                            'execution_price' => $order['avgPrice'] ?? $order['price'] ?? 0
                        ],
                        'id = ?',
                        [$analysis['signal_id']]
                    );
                }
                
                $this->log('INFO', "Trade executed successfully: {$side} {$quantity} {$symbol} @ " . ($order['avgPrice'] ?? 'Market'));
                
                // Set stop loss and take profit
                $this->setStopLossAndTakeProfit($symbol, $side, $order['avgPrice'] ?? $analysis['price'], $quantity);
                
            } else {
                $this->log('ERROR', "Failed to execute trade for {$symbol}");
            }
            
        } catch (Exception $e) {
            $this->log('ERROR', "Trade execution error for {$pair['symbol']}: " . $e->getMessage());
        }
    }
    
    private function calculatePositionSize($pair, $analysis) {
        try {
            // Get account balance
            $account = $this->binance->getAccountInfo();
            
            // Extract balance information safely
            if (is_array($account)) {
                $availableBalance = (float)($account['availableBalance'] ?? 0);
            } else {
                $availableBalance = 0;
                error_log("Account info is not an array in TradingBot: " . print_r($account, true));
            }
            
            if ($availableBalance <= 0) {
                return 0;
            }
            
            // Calculate position size based on risk percentage
            $riskAmount = $availableBalance * ($this->settings['risk_percentage'] / 100);
            $maxPositionSize = min($riskAmount, $this->settings['max_position_size']);
            
            // Adjust for confidence level
            $adjustedSize = $maxPositionSize * $analysis['confidence'];
            
            // Get symbol info for minimum quantity
            $ticker = $this->binance->get24hrTicker($pair['symbol']);
            $price = (float)$ticker[0]['lastPrice'];
            
            $quantity = $adjustedSize / $price;
            
            // Round to appropriate decimal places based on symbol
            $quantity = $this->roundToSymbolPrecision($pair['symbol'], $quantity);
            
            return $quantity;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Position size calculation error: " . $e->getMessage());
            return 0;
        }
    }
    
    private function setStopLossAndTakeProfit($symbol, $side, $entryPrice, $quantity) {
        try {
            $stopLossPercent = $this->settings['stop_loss_percentage'] / 100;
            $takeProfitPercent = $this->settings['take_profit_percentage'] / 100;
            
            // Round prices to appropriate precision
            $stopLossPrice = 0;
            $takeProfitPrice = 0;
            
            if ($side === 'BUY') {
                $stopLossPrice = $entryPrice * (1 - $stopLossPercent);
                $takeProfitPrice = $entryPrice * (1 + $takeProfitPercent);
            } else { // SELL
                $stopLossPrice = $entryPrice * (1 + $stopLossPercent);
                $takeProfitPrice = $entryPrice * (1 - $takeProfitPercent);
            }
            
            // Round prices to tick size
            $stopLossPrice = $this->roundPriceToTickSize($symbol, $stopLossPrice);
            $takeProfitPrice = $this->roundPriceToTickSize($symbol, $takeProfitPrice);
            
            // Validate prices
            if ($stopLossPrice <= 0 || $takeProfitPrice <= 0) {
                $this->log('WARNING', "Invalid stop loss or take profit prices for {$symbol}");
                return;
            }
            
            // Place stop loss order
            try {
                $stopSide = $side === 'BUY' ? 'SELL' : 'BUY';
                $this->binance->placeStopOrder($symbol, $stopSide, 'STOP_MARKET', $quantity, $stopLossPrice);
                $this->log('INFO', "Stop loss set for {$symbol} at {$stopLossPrice}");
            } catch (Exception $e) {
                $this->log('WARNING', "Failed to set stop loss for {$symbol}: " . $e->getMessage());
            }
            
            // Place take profit order
            try {
                $profitSide = $side === 'BUY' ? 'SELL' : 'BUY';
                // Round quantity for take profit order
                $takeProfitQuantity = $this->roundToSymbolPrecision($symbol, $quantity);
                $this->binance->placeOrder($symbol, $profitSide, 'LIMIT', $takeProfitQuantity, $takeProfitPrice, 'GTC');
                $this->log('INFO', "Take profit set for {$symbol} at {$takeProfitPrice}");
            } catch (Exception $e) {
                $this->log('WARNING', "Failed to set take profit for {$symbol}: " . $e->getMessage());
            }
            
        } catch (Exception $e) {
            $this->log('WARNING', "Failed to set stop loss/take profit for {$symbol}: " . $e->getMessage());
        }
    }
    
    private function roundPriceToTickSize($symbol, $price) {
        try {
            // Get symbol info from database
            $symbolInfo = $this->db->fetchOne("SELECT tick_size FROM trading_pairs WHERE symbol = ?", [$symbol]);
            
            if ($symbolInfo && $symbolInfo['tick_size'] > 0) {
                $tickSize = (float)$symbolInfo['tick_size'];
                return round($price / $tickSize) * $tickSize;
            }
            
            // Default tick size map for common symbols
            $tickSizeMap = [
                'BTCUSDT' => 0.01,
                'ETHUSDT' => 0.01,
                'BNBUSDT' => 0.01,
                'ADAUSDT' => 0.0001,
                'DOTUSDT' => 0.001,
                'LINKUSDT' => 0.001,
                'LTCUSDT' => 0.01,
                'XRPUSDT' => 0.0001,
                'SOLUSDT' => 0.001,
                'AVAXUSDT' => 0.001,
                'OMUSDT' => 0.0001,
                'SHIBUSDT' => 0.00000001,
                'PEPEUSDT' => 0.0000000001,
                'FLOKIUSDT' => 0.00000001,
                'BONDUSDT' => 0.0001,
                'DOGEUSDT' => 0.00001
            ];
            
            $tickSize = $tickSizeMap[$symbol] ?? 0.01;
            return round($price / $tickSize) * $tickSize;
            
        } catch (Exception $e) {
            $this->log('WARNING', "Error getting tick size for {$symbol}: " . $e->getMessage());
            return round($price, 2); // Safe fallback
        }
    }
    
    private function getCurrentPosition($symbol) {
        try {
            $positions = $this->binance->getPositions();
            
            foreach ($positions as $position) {
                if ($position['symbol'] === $symbol && (float)$position['positionAmt'] != 0) {
                    return [
                        'symbol' => $position['symbol'],
                        'side' => (float)$position['positionAmt'] > 0 ? 'LONG' : 'SHORT',
                        'size' => abs((float)$position['positionAmt']),
                        'entry_price' => (float)$position['entryPrice'],
                        'unrealized_pnl' => (float)$position['unRealizedProfit']
                    ];
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Error getting position for {$symbol}: " . $e->getMessage());
            return null;
        }
    }
    
    public function updatePositions() {
        try {
            // Check if API credentials are configured and valid
            if (!$this->binance->hasCredentials()) {
                $this->log('WARNING', 'Cannot update positions: API credentials not configured. Please set your Binance API key and secret in the admin Settings page.');
                return;
            }
            
            // Test API connection first
            try {
                $testResult = $this->binance->testConnection();
                if (!$testResult['success']) {
                    $this->log('WARNING', 'Cannot update positions: API connection failed - ' . $testResult['message']);
                    return;
                }
            } catch (Exception $e) {
                $this->log('WARNING', 'Cannot update positions: API connection test failed - ' . $e->getMessage());
                return;
            }
            
            $positions = $this->binance->getPositions();
            
            // Validate positions data
            if (!is_array($positions)) {
                $this->log('ERROR', 'Invalid positions data received from API');
                return;
            }
            
            // Check if positions table exists and clear existing positions
            try {
                $this->db->query("DELETE FROM positions");
            } catch (Exception $e) {
                $this->log('WARNING', 'Positions table may not exist: ' . $e->getMessage());
                return;
            }
            
            foreach ($positions as $position) {
                if ((float)($position['positionAmt'] ?? 0) != 0) {
                    // Calculate percentage if not provided
                    $percentage = 0;
                    if (isset($position['percentage'])) {
                        $percentage = (float)$position['percentage'];
                    } else {
                        $entryPrice = (float)$position['entryPrice'];
                        $markPrice = (float)$position['markPrice'];
                        $positionAmt = (float)$position['positionAmt'];
                        
                        if ($entryPrice > 0) {
                            if ($positionAmt > 0) { // Long position
                                $percentage = (($markPrice - $entryPrice) / $entryPrice) * 100;
                            } else { // Short position
                                $percentage = (($entryPrice - $markPrice) / $entryPrice) * 100;
                            }
                        }
                    }
                    
                    $positionData = [
                        'symbol' => $position['symbol'],
                        'trading_type' => 'FUTURES',
                        'position_amt' => (float)$position['positionAmt'],
                        'entry_price' => (float)$position['entryPrice'],
                        'mark_price' => (float)$position['markPrice'],
                        'unrealized_pnl' => (float)$position['unRealizedProfit'],
                        'percentage' => $percentage,
                        'side' => (float)$position['positionAmt'] > 0 ? 'LONG' : 'SHORT',
                        'leverage' => isset($position['leverage']) ? (int)$position['leverage'] : 1,
                        'margin_type' => $position['marginType'] ?? 'ISOLATED',
                        'isolated_margin' => (float)($position['isolatedMargin'] ?? 0),
                        'position_initial_margin' => (float)($position['positionInitialMargin'] ?? 0),
                        'open_order_initial_margin' => (float)($position['openOrderInitialMargin'] ?? 0),
                        'position_value' => abs((float)$position['positionAmt']) * (float)$position['markPrice']
                    ];
                    
                    try {
                        $this->db->insert('positions', $positionData);
                    } catch (Exception $e) {
                        $this->log('ERROR', "Failed to insert position for {$position['symbol']}: " . $e->getMessage());
                    }
                }
            }
            
            $activeCount = count(array_filter($positions, function($pos) {
                return (float)($pos['positionAmt'] ?? 0) != 0;
            }));
            $this->log('INFO', "Positions updated successfully. Active positions: {$activeCount}");
            
        } catch (Exception $e) {
            $this->log('ERROR', "Error updating positions: " . $e->getMessage());
        }
    }
    
    public function updateBalance() {
        try {
            // Check if API credentials are configured and valid
            if (!$this->binance->hasCredentials()) {
                $this->log('WARNING', 'Cannot update balance: API credentials not configured. Please set your Binance API key and secret in the admin Settings page.');
                return;
            }
            
            // Test API connection first
            try {
                $testResult = $this->binance->testConnection();
                if (!$testResult['success']) {
                    $this->log('WARNING', 'Cannot update balance: API connection failed - ' . $testResult['message']);
                    return;
                }
            } catch (Exception $e) {
                $this->log('WARNING', 'Cannot update balance: API connection test failed - ' . $e->getMessage());
                return;
            }
            
            $account = $this->binance->getAccountInfo();
            
            // Extract balance information (already normalized by getAccountInfo)
            $totalWalletBalance = (float)($account['totalWalletBalance'] ?? 0);
            $totalUnrealizedPnl = (float)($account['totalUnrealizedProfit'] ?? 0);
            $totalMarginBalance = (float)($account['totalMarginBalance'] ?? 0);
            $totalPositionInitialMargin = (float)($account['totalPositionInitialMargin'] ?? 0);
            $totalOpenOrderInitialMargin = (float)($account['totalOpenOrderInitialMargin'] ?? 0);
            $availableBalance = (float)($account['availableBalance'] ?? 0);
            $maxWithdrawAmount = (float)($account['maxWithdrawAmount'] ?? 0);
            
            $balanceData = [
                'total_wallet_balance' => $totalWalletBalance,
                'total_unrealized_pnl' => $totalUnrealizedPnl,
                'total_margin_balance' => $totalMarginBalance,
                'total_position_initial_margin' => $totalPositionInitialMargin,
                'total_open_order_initial_margin' => $totalOpenOrderInitialMargin,
                'available_balance' => $availableBalance,
                'max_withdraw_amount' => $maxWithdrawAmount,
                'spot_balance_usdt' => 0, // Will be updated separately by spot API
                'futures_balance_usdt' => $availableBalance,
                'total_portfolio_value' => $totalWalletBalance,
                'daily_pnl' => $totalUnrealizedPnl,
                'daily_pnl_percentage' => $totalWalletBalance > 0 ? ($totalUnrealizedPnl / $totalWalletBalance) * 100 : 0,
                'assets_data' => json_encode($account['assets'] ?? [])
            ];
            
            $this->db->insert('balance_history', $balanceData);
            
            $this->log('INFO', "Balance updated successfully. Available: {$availableBalance} USDT, P&L: {$totalUnrealizedPnl} USDT");
            
        } catch (Exception $e) {
            $this->log('ERROR', "Error updating balance: " . $e->getMessage());
        }
    }
    
    protected function log($level, $message) {
        try {
            $this->db->insert('system_logs', [
                'level' => $level,
                'message' => "[TRADING_BOT] {$message}",
                'context' => json_encode([
                    'timestamp' => date('Y-m-d H:i:s'),
                    'memory_usage' => memory_get_usage(true)
                ])
            ]);
            
            // Also log to file
            error_log("[" . date('Y-m-d H:i:s') . "] [{$level}] {$message}");
            
        } catch (Exception $e) {
            error_log("Failed to log message: " . $e->getMessage());
        }
    }
    
    public function getStatus() {
        return [
            'trading_enabled' => (bool)$this->settings['trading_enabled'],
            'ai_enabled' => (bool)$this->settings['ai_enabled'],
            'testnet_mode' => (bool)$this->settings['testnet_mode'],
            'last_run' => $this->getLastRunTime(),
            'active_pairs' => $this->db->fetchOne("SELECT COUNT(*) as count FROM trading_pairs WHERE enabled = 1")['count'],
            'recent_signals' => $this->db->fetchOne("SELECT COUNT(*) as count FROM ai_signals WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)")['count']
        ];
    }
    
    private function getLastRunTime() {
        $lastLog = $this->db->fetchOne(
            "SELECT created_at FROM system_logs WHERE message LIKE '%Trading bot completed%' ORDER BY created_at DESC LIMIT 1"
        );
        
        return $lastLog ? $lastLog['created_at'] : null;
    }
    
    protected function roundToSymbolPrecision($symbol, $quantity) {
        try {
            // Validate input
            if ($quantity <= 0) {
                return 0;
            }
            
            // Get symbol info from database or use safe defaults
            $symbolInfo = $this->db->fetchOne("SELECT step_size FROM trading_pairs WHERE symbol = ?", [$symbol]);
            
            if ($symbolInfo && $symbolInfo['step_size'] > 0) {
                $stepSize = (float)$symbolInfo['step_size'];
                
                // Apply step-based rounding
                if ($stepSize >= 1) {
                    return floor($quantity / $stepSize) * $stepSize;
                } else {
                    $precision = strlen(substr(strrchr($stepSize, "."), 1));
                    return round($quantity, $precision);
                }
            }
            
            // Enhanced precision map
            $precisionMap = [
                'BTCUSDT' => ['precision' => 3, 'step' => 0.001],
                'ETHUSDT' => ['precision' => 3, 'step' => 0.001],
                'BNBUSDT' => ['precision' => 2, 'step' => 0.01],
                'ADAUSDT' => ['precision' => 0, 'step' => 1],
                'DOTUSDT' => ['precision' => 1, 'step' => 0.1],
                'LINKUSDT' => ['precision' => 1, 'step' => 0.1],
                'LTCUSDT' => ['precision' => 2, 'step' => 0.01],
                'XRPUSDT' => ['precision' => 0, 'step' => 1],
                'SOLUSDT' => ['precision' => 1, 'step' => 0.1],
                'AVAXUSDT' => ['precision' => 1, 'step' => 0.1],
                'OMUSDT' => ['precision' => 0, 'step' => 1],
                'SHIBUSDT' => ['precision' => 0, 'step' => 1000000],
                'PEPEUSDT' => ['precision' => 0, 'step' => 1000000],
                'FLOKIUSDT' => ['precision' => 0, 'step' => 100000],
                'BONDUSDT' => ['precision' => 2, 'step' => 0.01],
                'BROCCOLIF3BUSDT' => ['precision' => 0, 'step' => 1000],
                'JSTUSDT' => ['precision' => 0, 'step' => 1],
                '1000BONKUSDT' => ['precision' => 0, 'step' => 1000],
                '1000RATSUSDT' => ['precision' => 0, 'step' => 1000],
                'DOGEUSDT' => ['precision' => 0, 'step' => 1]
            ];
            
            $symbolConfig = $precisionMap[$symbol] ?? ['precision' => 3, 'step' => 0.001];
            $precision = $symbolConfig['precision'];
            $stepSize = $symbolConfig['step'];
            
            // Apply step-based rounding
            if ($stepSize >= 1) {
                $roundedQuantity = floor($quantity / $stepSize) * $stepSize;
            } else {
                $roundedQuantity = round($quantity, $precision);
            }
            
            // Ensure minimum quantity
            if ($precision === 0) {
                $roundedQuantity = max($stepSize, floor($roundedQuantity));
            }
            
            // Final validation
            if ($roundedQuantity <= 0) {
                return 0;
            }
            
            return $roundedQuantity;
            
        } catch (Exception $e) {
            $this->log('WARNING', "Error getting symbol precision for {$symbol}: " . $e->getMessage());
            // Safe fallback - return 0 to prevent invalid trades
            return 0;
        }
    }
}