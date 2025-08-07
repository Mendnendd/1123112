<?php

class EnhancedTradingBot extends TradingBot {
    protected $db;
    protected $binance;
    private $spotAPI;
    private $enhancedAI;
    private $strategies;
    private $riskManager;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->binance = new BinanceAPI();
        $this->spotAPI = new SpotTradingAPI();
        
        // Load settings first
        $this->loadSettings();
        
        // Initialize enhanced AI safely
        try {
            $this->enhancedAI = new EnhancedAIAnalyzer();
        } catch (Exception $e) {
            error_log("Failed to initialize EnhancedAIAnalyzer in EnhancedTradingBot: " . $e->getMessage());
            $this->enhancedAI = null;
        }
        
        try {
            $this->loadStrategies();
        } catch (Exception $e) {
            error_log("Failed to load strategies in EnhancedTradingBot: " . $e->getMessage());
            $this->strategies = [];
        }
        
        try {
            $this->riskManager = new RiskManager();
        } catch (Exception $e) {
            error_log("Failed to initialize RiskManager: " . $e->getMessage());
            $this->riskManager = null;
        }
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
                'spot_trading_enabled' => 1,
                'futures_trading_enabled' => 1,
                'max_position_size' => 100,
                'max_spot_position_size' => 50,
                'risk_percentage' => 2,
                'stop_loss_percentage' => 5,
                'take_profit_percentage' => 10,
                'leverage' => 10,
                'margin_type' => 'ISOLATED',
                'ai_confidence_threshold' => 0.75,
                'max_daily_trades' => 20,
                'max_concurrent_positions' => 5,
                'emergency_stop' => 0
            ]);
            $this->settings = $this->db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
        }
    }
    
    private function loadStrategies() {
        try {
            // Check if trading_strategies table exists
            $tableExists = $this->db->fetchOne("SHOW TABLES LIKE 'trading_strategies'");
            if ($tableExists) {
                $this->strategies = $this->db->fetchAll("SELECT * FROM trading_strategies WHERE enabled = 1");
            } else {
                // Table doesn't exist yet, use empty array
                $this->strategies = [];
            }
        } catch (Exception $e) {
            error_log("Error loading strategies: " . $e->getMessage());
            $this->strategies = [];
        }
    }
    
    public function run() {
        try {
            $this->log('INFO', 'Enhanced trading bot started');
            
            // Check emergency stop
            if ($this->settings['emergency_stop']) {
                $this->log('WARNING', 'Emergency stop is active, skipping execution');
                return;
            }
            
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
            
            // Check daily trade limits
            $todayTrades = $this->db->fetchOne("SELECT COUNT(*) as count FROM trading_history WHERE DATE(created_at) = CURDATE()")['count'];
            if ($todayTrades >= $this->settings['max_daily_trades']) {
                $this->log('WARNING', 'Daily trade limit reached: ' . $todayTrades);
                return;
            }
            
            // Get active trading pairs
            $pairs = $this->db->fetchAll("SELECT * FROM trading_pairs WHERE enabled = 1 ORDER BY ai_priority DESC");
            
            if (empty($pairs)) {
                $this->log('WARNING', 'No active trading pairs found');
                return;
            }
            
            $this->log('INFO', 'Found ' . count($pairs) . ' active trading pairs');
            
            // Analyze and trade each pair
            foreach ($pairs as $pair) {
                try {
                    $this->analyzeAndTradePair($pair);
                    sleep(2); // Rate limiting
                } catch (Exception $e) {
                    $this->log('ERROR', "Error processing pair {$pair['symbol']}: " . $e->getMessage());
                }
            }
            
            // Update positions and balance after trading
            $this->updatePositions();
            $this->updateSpotBalances();
            $this->updateBalance();
            $this->updatePerformanceMetrics();
            
            $this->log('INFO', 'Enhanced trading bot completed successfully');
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Enhanced trading bot error: ' . $e->getMessage());
        }
    }
    
    private function analyzeAndTradePair($pair) {
        $symbol = $pair['symbol'];
        $tradingType = $pair['trading_type'];
        
        $this->log('INFO', "Analyzing {$symbol} for {$tradingType} trading");
        
        // Run analysis for each enabled strategy
        foreach ($this->strategies as $strategy) {
            if ($strategy['trading_type'] === 'BOTH' || $strategy['trading_type'] === $tradingType) {
                try {
                    $this->analyzeWithStrategy($pair, $strategy);
                } catch (Exception $e) {
                    // Check if it's a spot trading symbol issue
                    if (strpos($e->getMessage(), 'not available for spot trading') !== false) {
                        $this->log('WARNING', "Strategy {$strategy['name']} skipped for {$symbol}: Symbol not available for spot trading");
                        // Update the pair to only support futures if spot fails
                        try {
                            $this->db->update('trading_pairs', ['trading_type' => 'FUTURES'], 'symbol = ?', [$symbol]);
                        } catch (Exception $updateError) {
                            $this->log('ERROR', "Failed to update trading type for {$symbol}: " . $updateError->getMessage());
                        }
                    } else {
                        $this->log('ERROR', "Strategy {$strategy['name']} failed for {$symbol}: " . $e->getMessage());
                    }
                }
            }
        }
    }
    
    private function analyzeWithStrategy($pair, $strategy) {
        $symbol = $pair['symbol'];
        $tradingType = $pair['trading_type'];
        
        // Get enhanced AI analysis
        if ($this->enhancedAI) {
            $analysis = $this->enhancedAI->analyzeSymbolEnhanced($symbol, $tradingType, $strategy['name']);
        } else {
            // Fallback to basic AI analyzer
            $basicAI = new AIAnalyzer();
            $basicAnalysis = $basicAI->analyzeSymbol($symbol);
            $analysis = array_merge($basicAnalysis, [
                'trading_type' => $tradingType,
                'strength' => 'MODERATE',
                'market_sentiment' => 'NEUTRAL'
            ]);
        }
        
        $this->log('INFO', "Strategy {$strategy['name']} analysis for {$symbol}: {$analysis['signal']} (Confidence: " . ($analysis['confidence'] * 100) . "%, Strength: {$analysis['strength']})");
        
        // Check if we should execute trade based on strategy
        if ($this->shouldExecuteTradeWithStrategy($analysis, $pair, $strategy)) {
            $this->executeEnhancedTrade($analysis, $pair, $strategy);
        } else {
            $strength = $analysis['strength'] ?? 'MODERATE';
            $this->log('INFO', "Skipping trade for {$symbol} with strategy {$strategy['name']} - conditions not met (Strength: {$strength})");
        }
    }
    
    private function shouldExecuteTradeWithStrategy($analysis, $pair, $strategy) {
        // Check confidence threshold
        if ($analysis['confidence'] < $strategy['min_confidence']) {
            $this->log('INFO', "Low confidence for {$pair['symbol']}: " . ($analysis['confidence'] * 100) . "% < " . ($strategy['min_confidence'] * 100) . "%");
            return false;
        }
        
        // Don't trade HOLD signals
        if ($analysis['signal'] === 'HOLD') {
            return false;
        }
        
        // Check recent signals to avoid overtrading
        $recentSignals = $this->db->fetchAll(
            "SELECT * FROM ai_signals WHERE symbol = ? AND executed = 1 AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)",
            [$pair['symbol']]
        );
        
        if (count($recentSignals) > 0) {
            $this->log('INFO', "Skipping {$pair['symbol']} - recent trade executed");
            return false;
        }
        
        // Check maximum concurrent positions
        $activePositions = $this->db->fetchOne("SELECT COUNT(*) as count FROM positions WHERE position_amt != 0")['count'];
        if ($activePositions >= $this->settings['max_concurrent_positions']) {
            $this->log('WARNING', "Maximum concurrent positions reached: {$activePositions}");
            return false;
        }
        
        // Risk management checks
        if ($this->riskManager && !$this->riskManager->canOpenPosition($pair['symbol'], $analysis, $strategy)) {
            $this->log('WARNING', "Risk manager rejected trade for {$pair['symbol']}");
            return false;
        }
        
        return true;
    }
    
    private function executeEnhancedTrade($analysis, $pair, $strategy) {
        $symbol = $pair['symbol'];
        $tradingType = $analysis['trading_type'];
        $signal = $analysis['signal'];
        
        try {
            // Determine trade side
            $side = in_array($signal, ['BUY', 'STRONG_BUY']) ? 'BUY' : 'SELL';
            
            // Calculate position size based on strategy and risk management
            $quantity = $this->calculateEnhancedPositionSize($pair, $analysis, $strategy);
            
            if ($quantity <= 0) {
                $this->log('WARNING', "Invalid quantity calculated for {$symbol}");
                return;
            }
            
            // Validate quantity precision before executing
            if (!$this->validateQuantityPrecision($symbol, $quantity, $tradingType)) {
                $this->log('WARNING', "Quantity precision validation failed for {$symbol}: {$quantity}");
                return;
            }
            
            $this->log('INFO', "Executing {$side} order for {$symbol} ({$tradingType}): {$quantity}");
            
            $order = null;
            
            // Execute trade based on trading type
            if ($tradingType === 'SPOT' || ($tradingType === 'BOTH' && $this->settings['spot_trading_enabled'])) {
                $order = $this->executeSpotTrade($symbol, $side, $quantity, $analysis, $strategy);
            }
            
            if ($tradingType === 'FUTURES' || ($tradingType === 'BOTH' && $this->settings['futures_trading_enabled'])) {
                $order = $this->executeFuturesTrade($symbol, $side, $quantity, $analysis, $strategy, $pair);
            }
            
            if ($order) {
                // Mark signal as executed
                if (isset($analysis['signal_id'])) {
                    $this->db->update(
                        'ai_signals',
                        [
                            'executed' => 1,
                            'execution_price' => $order['avgPrice'] ?? $order['price'] ?? 0,
                            'execution_time' => date('Y-m-d H:i:s')
                        ],
                        'id = ?',
                        [$analysis['signal_id']]
                    );
                }
                
                // Set stop loss and take profit if futures
                if ($tradingType === 'FUTURES') {
                    $this->setEnhancedStopLossAndTakeProfit($symbol, $side, $order['avgPrice'] ?? $analysis['price'], $quantity, $analysis, $strategy);
                }
                
                // Send notification
                $this->sendTradeNotification($symbol, $side, $quantity, $order, $analysis, $strategy);
                
                $this->log('INFO', "Enhanced trade executed successfully: {$side} {$quantity} {$symbol} @ " . ($order['avgPrice'] ?? 'Market'));
            }
            
        } catch (Exception $e) {
            $this->log('ERROR', "Enhanced trade execution error for {$symbol}: " . $e->getMessage());
        }
    }
    
    private function executeSpotTrade($symbol, $side, $quantity, $analysis, $strategy) {
        try {
            // Round quantity to appropriate precision for spot trading
            $quantity = $this->roundToSpotPrecision($symbol, $quantity);
            
            if ($quantity <= 0) {
                $this->log('WARNING', "Invalid quantity after precision rounding for spot {$symbol}: {$quantity}");
                return null;
            }
            
            $order = $this->spotAPI->placeSpotOrder($symbol, $side, 'MARKET', $quantity);
            
            // Log the trade with enhanced data
            $tradeData = [
                'symbol' => $symbol,
                'trading_type' => 'SPOT',
                'side' => $side,
                'type' => 'MARKET',
                'quantity' => $quantity,
                'price' => $analysis['price'],
                'executed_price' => $order['price'] ?? $analysis['price'],
                'executed_qty' => $order['executedQty'] ?? $quantity,
                'status' => $order['status'] ?? 'FILLED',
                'order_id' => $order['orderId'] ?? null,
                'client_order_id' => $order['clientOrderId'] ?? null,
                'ai_signal_id' => $analysis['signal_id'] ?? null,
                'strategy_used' => $strategy['name'],
                'confidence_score' => $analysis['confidence'],
                'market_conditions' => json_encode([
                    'sentiment' => $analysis['market_sentiment'],
                    'volatility' => $analysis['indicators']['volatility'] ?? 0,
                    'trend_strength' => $analysis['indicators']['trend_strength'] ?? 0
                ]),
                'notes' => "Enhanced AI Spot Trade - Strategy: {$strategy['name']}, Confidence: " . ($analysis['confidence'] * 100) . "%"
            ];
            
            $this->db->insert('trading_history', $tradeData);
            
            return $order;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Spot trade execution failed for {$symbol}: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function executeFuturesTrade($symbol, $side, $quantity, $analysis, $strategy, $pair) {
        try {
            // Round quantity to appropriate precision for futures trading
            $quantity = $this->roundToSymbolPrecision($symbol, $quantity);
            
            if ($quantity <= 0) {
                $this->log('WARNING', "Invalid quantity after precision rounding for {$symbol}: {$quantity}");
                return null;
            }
            
            // Set leverage and margin type before placing order
            try {
                // Only change leverage if it's different from current
                try {
                    $this->binance->changeLeverage($symbol, $pair['leverage']);
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'No need to change leverage') === false) {
                        $this->log('WARNING', "Failed to set leverage for {$symbol}: " . $e->getMessage());
                    }
                }
                
                // Only change margin type if it's different from current
                try {
                    $this->binance->changeMarginType($symbol, $pair['margin_type']);
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'No need to change margin type') === false) {
                        $this->log('WARNING', "Failed to set margin type for {$symbol}: " . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                $this->log('WARNING', "Failed to configure leverage/margin for {$symbol}: " . $e->getMessage());
            }
            
            $order = $this->binance->placeOrder($symbol, $side, 'MARKET', $quantity);
            
            // Log the trade with enhanced data
            $tradeData = [
                'symbol' => $symbol,
                'trading_type' => 'FUTURES',
                'side' => $side,
                'type' => 'MARKET',
                'quantity' => $quantity,
                'price' => $analysis['price'],
                'executed_price' => $order['avgPrice'] ?? $order['price'] ?? 0,
                'executed_qty' => $order['executedQty'] ?? $quantity,
                'status' => $order['status'] ?? 'FILLED',
                'order_id' => $order['orderId'] ?? null,
                'client_order_id' => $order['clientOrderId'] ?? null,
                'ai_signal_id' => $analysis['signal_id'] ?? null,
                'strategy_used' => $strategy['name'],
                'confidence_score' => $analysis['confidence'],
                'market_conditions' => json_encode([
                    'sentiment' => $analysis['market_sentiment'],
                    'volatility' => $analysis['indicators']['volatility'] ?? 0,
                    'trend_strength' => $analysis['indicators']['trend_strength'] ?? 0,
                    'leverage' => $pair['leverage']
                ]),
                'notes' => "Enhanced AI Futures Trade - Strategy: {$strategy['name']}, Confidence: " . ($analysis['confidence'] * 100) . "%, Leverage: {$pair['leverage']}x"
            ];
            
            $this->db->insert('trading_history', $tradeData);
            
            return $order;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Futures trade execution failed for {$symbol}: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function calculateEnhancedPositionSize($pair, $analysis, $strategy) {
        try {
            // Get account balance
            $account = $this->binance->getAccountInfo();
            $availableBalance = (float)($account['availableBalance'] ?? 0);
            
            if ($availableBalance <= 0) {
                return 0;
            }
            
            // Use strategy-specific position size if available
            $maxPositionSize = min(
                $strategy['max_position_size'],
                $this->settings['max_position_size']
            );
            
            // Calculate risk-adjusted position size
            $riskAmount = $availableBalance * ($this->settings['risk_percentage'] / 100);
            $basePositionSize = min($riskAmount, $maxPositionSize);
            
            // Adjust for confidence level
            $confidenceMultiplier = $analysis['confidence'];
            
            // Adjust for signal strength
            $strengthMultipliers = [
                'WEAK' => 0.5,
                'MODERATE' => 0.75,
                'STRONG' => 1.0,
                'VERY_STRONG' => 1.25
            ];
            $strengthMultiplier = $strengthMultipliers[$analysis['strength']] ?? 1.0;
            
            // Adjust for volatility (reduce size for high volatility)
            $volatility = $analysis['indicators']['volatility'] ?? 0.02;
            $volatilityMultiplier = max(0.5, 1 - ($volatility * 10));
            
            // Calculate final position size
            $adjustedSize = $basePositionSize * $confidenceMultiplier * $strengthMultiplier * $volatilityMultiplier;
            
            // Convert to quantity
            $price = $analysis['price'];
            if ($price <= 0) {
                $this->log('ERROR', "Invalid price for position size calculation: {$price}");
                return 0;
            }
            
            $quantity = $adjustedSize / $price;
            
            // Apply appropriate precision rounding based on trading type
            if ($analysis['trading_type'] === 'SPOT') {
                $quantity = $this->roundToSpotPrecision($pair['symbol'], $quantity);
            } else {
                $quantity = $this->roundToSymbolPrecision($pair['symbol'], $quantity);
            }
            
            // Final validation
            if ($quantity <= 0) {
                $this->log('WARNING', "Position size calculation resulted in zero quantity for {$pair['symbol']}");
                return 0;
            }
            
            return $quantity;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Enhanced position size calculation error: " . $e->getMessage());
            return 0;
        }
    }
    
    private function setEnhancedStopLossAndTakeProfit($symbol, $side, $entryPrice, $quantity, $analysis, $strategy) {
        try {
            // Use analysis-provided levels if available, otherwise use strategy defaults
            $stopLossPrice = $analysis['stop_loss_price'];
            $takeProfitPrice = $analysis['target_price'];
            
            if (!$stopLossPrice || !$takeProfitPrice) {
                $stopLossPercent = $strategy['stop_loss_percentage'] / 100;
                $takeProfitPercent = $strategy['take_profit_percentage'] / 100;
                
                if ($side === 'BUY') {
                    $stopLossPrice = $entryPrice * (1 - $stopLossPercent);
                    $takeProfitPrice = $entryPrice * (1 + $takeProfitPercent);
                } else {
                    $stopLossPrice = $entryPrice * (1 + $stopLossPercent);
                    $takeProfitPrice = $entryPrice * (1 - $takeProfitPercent);
                }
            }
            
            // Round prices to appropriate precision
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
    
    private function updateSpotBalances() {
        try {
            if (!$this->binance->hasCredentials()) {
                return;
            }
            
            $this->spotAPI->getSpotAccount(); // This updates balances internally
            $this->log('INFO', 'Spot balances updated successfully');
            
        } catch (Exception $e) {
            $this->log('ERROR', "Error updating spot balances: " . $e->getMessage());
        }
    }
    
    private function updatePerformanceMetrics() {
        try {
            $today = date('Y-m-d');
            
            // Get today's trading statistics
            $todayStats = $this->db->fetchOne("
                SELECT 
                    COUNT(*) as total_trades,
                    SUM(CASE WHEN trading_type = 'SPOT' THEN 1 ELSE 0 END) as spot_trades,
                    SUM(CASE WHEN trading_type = 'FUTURES' THEN 1 ELSE 0 END) as futures_trades,
                    SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
                    SUM(CASE WHEN profit_loss < 0 THEN 1 ELSE 0 END) as losing_trades,
                    SUM(profit_loss) as total_pnl,
                    SUM(CASE WHEN trading_type = 'SPOT' THEN profit_loss ELSE 0 END) as spot_pnl,
                    SUM(CASE WHEN trading_type = 'FUTURES' THEN profit_loss ELSE 0 END) as futures_pnl,
                    MAX(profit_loss) as best_trade,
                    MIN(profit_loss) as worst_trade
                FROM trading_history 
                WHERE DATE(created_at) = ?
            ", [$today]);
            
            // Get AI signals statistics
            $aiStats = $this->db->fetchOne("
                SELECT 
                    COUNT(*) as signals_generated,
                    SUM(executed) as signals_executed,
                    AVG(confidence) as avg_confidence
                FROM ai_signals 
                WHERE DATE(created_at) = ?
            ", [$today]);
            
            // Get balance information
            $latestBalance = $this->db->fetchOne("
                SELECT * FROM balance_history 
                ORDER BY created_at DESC LIMIT 1
            ");
            
            $startBalance = $this->db->fetchOne("
                SELECT * FROM balance_history 
                WHERE DATE(created_at) = ? 
                ORDER BY created_at ASC LIMIT 1
            ", [$today]);
            
            // Calculate metrics
            $winRate = $todayStats['total_trades'] > 0 ? 
                ($todayStats['winning_trades'] / $todayStats['total_trades']) * 100 : 0;
            
            $aiSuccessRate = $aiStats['signals_executed'] > 0 ? 
                ($todayStats['winning_trades'] / $aiStats['signals_executed']) * 100 : 0;
            
            // Insert or update performance metrics
            $metricsData = [
                'date' => $today,
                'account_type' => 'COMBINED',
                'starting_balance' => $startBalance['total_portfolio_value'] ?? $latestBalance['total_portfolio_value'] ?? 0,
                'ending_balance' => $latestBalance['total_portfolio_value'] ?? 0,
                'total_trades' => $todayStats['total_trades'],
                'spot_trades' => $todayStats['spot_trades'],
                'futures_trades' => $todayStats['futures_trades'],
                'winning_trades' => $todayStats['winning_trades'],
                'losing_trades' => $todayStats['losing_trades'],
                'win_rate' => $winRate,
                'total_pnl' => $todayStats['total_pnl'] ?? 0,
                'spot_pnl' => $todayStats['spot_pnl'] ?? 0,
                'futures_pnl' => $todayStats['futures_pnl'] ?? 0,
                'best_trade' => $todayStats['best_trade'] ?? 0,
                'worst_trade' => $todayStats['worst_trade'] ?? 0,
                'ai_signals_generated' => $aiStats['signals_generated'] ?? 0,
                'ai_signals_executed' => $aiStats['signals_executed'] ?? 0,
                'ai_success_rate' => $aiSuccessRate
            ];
            
            // Use INSERT ... ON DUPLICATE KEY UPDATE
            $this->db->query("
                INSERT INTO performance_metrics 
                (date, account_type, starting_balance, ending_balance, total_trades, spot_trades, futures_trades, 
                 winning_trades, losing_trades, win_rate, total_pnl, spot_pnl, futures_pnl, best_trade, worst_trade, 
                 ai_signals_generated, ai_signals_executed, ai_success_rate) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                ending_balance = VALUES(ending_balance),
                total_trades = VALUES(total_trades),
                spot_trades = VALUES(spot_trades),
                futures_trades = VALUES(futures_trades),
                winning_trades = VALUES(winning_trades),
                losing_trades = VALUES(losing_trades),
                win_rate = VALUES(win_rate),
                total_pnl = VALUES(total_pnl),
                spot_pnl = VALUES(spot_pnl),
                futures_pnl = VALUES(futures_pnl),
                best_trade = VALUES(best_trade),
                worst_trade = VALUES(worst_trade),
                ai_signals_generated = VALUES(ai_signals_generated),
                ai_signals_executed = VALUES(ai_signals_executed),
                ai_success_rate = VALUES(ai_success_rate)
            ", array_values($metricsData));
            
            $this->log('INFO', 'Performance metrics updated successfully');
            
        } catch (Exception $e) {
            $this->log('ERROR', "Error updating performance metrics: " . $e->getMessage());
        }
    }
    
    private function sendTradeNotification($symbol, $side, $quantity, $order, $analysis, $strategy) {
        try {
            $this->db->insert('notifications', [
                'type' => 'TRADE',
                'category' => 'TRADING',
                'title' => "Trade Executed: {$side} {$symbol}",
                'message' => "Successfully executed {$side} order for {$quantity} {$symbol} using strategy '{$strategy['name']}' with {$analysis['confidence']}% confidence.",
                'data' => json_encode([
                    'symbol' => $symbol,
                    'side' => $side,
                    'quantity' => $quantity,
                    'price' => $order['avgPrice'] ?? $order['price'] ?? 0,
                    'strategy' => $strategy['name'],
                    'confidence' => $analysis['confidence'],
                    'signal_strength' => $analysis['strength']
                ]),
                'priority' => 'NORMAL'
            ]);
        } catch (Exception $e) {
            $this->log('ERROR', "Failed to send trade notification: " . $e->getMessage());
        }
    }
    
    protected function roundToSpotPrecision($symbol, $quantity) {
        try {
            // Validate input
            if ($quantity <= 0) {
                return 0;
            }
            
            // Get symbol info from database
            $symbolInfo = $this->db->fetchOne("SELECT step_size FROM trading_pairs WHERE symbol = ?", [$symbol]);
            
            if ($symbolInfo && $symbolInfo['step_size'] > 0) {
                $stepSize = (float)$symbolInfo['step_size'];
                
                // Calculate precision from step size
                if ($stepSize >= 1) {
                    // For step sizes >= 1, round to nearest step
                    return floor($quantity / $stepSize) * $stepSize;
                } else {
                    $precision = strlen(substr(strrchr($stepSize, "."), 1));
                    return round($quantity, $precision);
                }
            }
            
            // Enhanced precision map with step-based rounding
            $spotPrecisionMap = [
                'BTCUSDT' => ['precision' => 5, 'step' => 0.00001],
                'ETHUSDT' => ['precision' => 4, 'step' => 0.0001],
                'BNBUSDT' => ['precision' => 3, 'step' => 0.001],
                'ADAUSDT' => ['precision' => 0, 'step' => 1],
                'DOTUSDT' => ['precision' => 2, 'step' => 0.01],
                'LINKUSDT' => ['precision' => 2, 'step' => 0.01],
                'LTCUSDT' => ['precision' => 3, 'step' => 0.001],
                'XRPUSDT' => ['precision' => 0, 'step' => 1],
                'SOLUSDT' => ['precision' => 2, 'step' => 0.01],
                'AVAXUSDT' => ['precision' => 2, 'step' => 0.01],
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
            
            $symbolConfig = $spotPrecisionMap[$symbol] ?? ['precision' => 4, 'step' => 0.0001];
            $precision = $symbolConfig['precision'];
            $stepSize = $symbolConfig['step'];
            
            // Apply step-based rounding
            if ($stepSize >= 1) {
                $roundedQuantity = floor($quantity / $stepSize) * $stepSize;
            } else {
                $roundedQuantity = round($quantity, $precision);
            }
            
            // Ensure minimum quantity requirements
            if ($precision === 0) {
                $roundedQuantity = max($stepSize, floor($roundedQuantity));
            }
            
            // Final validation
            if ($roundedQuantity <= 0) {
                return 0;
            }
            
            return $roundedQuantity;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Error getting spot precision for {$symbol}: " . $e->getMessage());
            // Safe fallback - return 0 to prevent invalid trades
            return 0;
        }
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
                
                // Apply step-based rounding for futures
                if ($stepSize >= 1) {
                    return floor($quantity / $stepSize) * $stepSize;
                } else {
                    $precision = strlen(substr(strrchr($stepSize, "."), 1));
                    return round($quantity, $precision);
                }
            }
            
            // Enhanced precision map with step sizes
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
    
    private function validateQuantityPrecision($symbol, $quantity, $tradingType = 'FUTURES') {
        try {
            if ($quantity <= 0) {
                return false;
            }
            
            // Use local precision map for validation to avoid API calls
            $precisionMap = [
                'BTCUSDT' => ['min_qty' => 0.001, 'step' => 0.001],
                'ETHUSDT' => ['min_qty' => 0.001, 'step' => 0.001],
                'BNBUSDT' => ['min_qty' => 0.01, 'step' => 0.01],
                'ADAUSDT' => ['min_qty' => 1, 'step' => 1],
                'DOTUSDT' => ['min_qty' => 0.1, 'step' => 0.1],
                'LINKUSDT' => ['min_qty' => 0.1, 'step' => 0.1],
                'LTCUSDT' => ['min_qty' => 0.01, 'step' => 0.01],
                'XRPUSDT' => ['min_qty' => 1, 'step' => 1],
                'SOLUSDT' => ['min_qty' => 0.1, 'step' => 0.1],
                'AVAXUSDT' => ['min_qty' => 0.1, 'step' => 0.1],
                'OMUSDT' => ['min_qty' => 1, 'step' => 1],
                'SHIBUSDT' => ['min_qty' => 1000000, 'step' => 1000000],
                'PEPEUSDT' => ['min_qty' => 1000000, 'step' => 1000000],
                'FLOKIUSDT' => ['min_qty' => 100000, 'step' => 100000],
                'BONDUSDT' => ['min_qty' => 0.01, 'step' => 0.01],
                'BROCCOLIF3BUSDT' => ['min_qty' => 1000, 'step' => 1000],
                'JSTUSDT' => ['min_qty' => 1, 'step' => 1],
                '1000BONKUSDT' => ['min_qty' => 1000, 'step' => 1000],
                '1000RATSUSDT' => ['min_qty' => 1000, 'step' => 1000],
                'DOGEUSDT' => ['min_qty' => 1, 'step' => 1]
            ];
            
            $symbolConfig = $precisionMap[$symbol] ?? ['min_qty' => 0.001, 'step' => 0.001];
            
            // Check minimum quantity
            if ($quantity < $symbolConfig['min_qty']) {
                $this->log('WARNING', "Quantity {$quantity} below minimum {$symbolConfig['min_qty']} for {$symbol}");
                return false;
            }
            
            // Check step size compliance
            $stepSize = $symbolConfig['step'];
            if ($stepSize > 0) {
                $remainder = fmod($quantity, $stepSize);
                if ($remainder > 0.0000001) { // Allow for floating point precision
                    $this->log('WARNING', "Quantity {$quantity} not compliant with step size {$stepSize} for {$symbol}");
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error validating quantity precision: " . $e->getMessage());
            return true; // Allow on error to prevent blocking trades
        }
    }
}

// Risk Manager Class
class RiskManager {
    private $db;
    private $settings;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $this->settings = $this->db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
    }
    
    public function canOpenPosition($symbol, $analysis, $strategy) {
        // Check if we already have a position in this symbol
        $existingPosition = $this->db->fetchOne(
            "SELECT * FROM positions WHERE symbol = ? AND position_amt != 0",
            [$symbol]
        );
        
        if ($existingPosition) {
            return false; // Don't open conflicting positions
        }
        
        // Check portfolio heat (total risk exposure)
        $totalRisk = $this->calculatePortfolioRisk();
        if ($totalRisk > 0.2) { // Max 20% portfolio risk
            return false;
        }
        
        // Check volatility limits
        $volatility = $analysis['indicators']['volatility'] ?? 0;
        if ($volatility > 0.1) { // Max 10% volatility
            return false;
        }
        
        // Check correlation with existing positions
        if ($this->hasHighCorrelation($symbol)) {
            return false;
        }
        
        return true;
    }
    
    private function calculatePortfolioRisk() {
        // Simplified portfolio risk calculation
        $positions = $this->db->fetchAll("SELECT * FROM positions WHERE position_amt != 0");
        $totalRisk = 0;
        
        foreach ($positions as $position) {
            $positionRisk = abs($position['position_value']) * 0.05; // Assume 5% risk per position
            $totalRisk += $positionRisk;
        }
        
        $totalBalance = $this->db->fetchOne("SELECT total_portfolio_value FROM balance_history ORDER BY created_at DESC LIMIT 1")['total_portfolio_value'] ?? 1000;
        
        return $totalRisk / $totalBalance;
    }
    
    private function hasHighCorrelation($symbol) {
        // Simplified correlation check
        $activeSymbols = $this->db->fetchAll("SELECT DISTINCT symbol FROM positions WHERE position_amt != 0");
        
        // Check if we have too many crypto positions (simplified)
        $cryptoCount = 0;
        foreach ($activeSymbols as $pos) {
            if (strpos($pos['symbol'], 'USDT') !== false) {
                $cryptoCount++;
            }
        }
        
        return $cryptoCount >= 3; // Max 3 crypto positions
    }
}