<?php

class EnhancedAIAnalyzer extends AIAnalyzer {
    protected $db;
    private $spotAPI;
    private $strategies;
    
    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->spotAPI = new SpotTradingAPI();
        try {
            $this->loadStrategies();
        } catch (Exception $e) {
            error_log("Failed to load strategies in EnhancedAIAnalyzer: " . $e->getMessage());
            $this->strategies = [];
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
    
    public function analyzeSymbolEnhanced($symbol, $tradingType = 'BOTH', $strategy = null) {
        try {
            // Add timeout protection
            $startTime = microtime(true);
            set_time_limit(60); // 1 minute max per symbol
            
            $binance = new BinanceAPI();
            
            // Check if API credentials are available
            if (!$binance->hasCredentials()) {
                throw new Exception("Cannot analyze {$symbol}: API credentials not configured. Please set your Binance API key and secret in Settings.");
            }
            
            // Get market data for both spot and futures if needed
            $analysis = [];
            
            if ($tradingType === 'SPOT' || $tradingType === 'BOTH') {
                try {
                    $spotStartTime = microtime(true);
                    $spotAnalysis = $this->analyzeSpotMarket($symbol, $strategy);
                    $spotTime = microtime(true) - $spotStartTime;
                    if ($spotTime > 30) {
                        $this->log('WARNING', "Spot analysis for {$symbol} took {$spotTime}s");
                    }
                    $analysis['spot'] = $spotAnalysis;
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'not available for spot trading') !== false) {
                        // Skip spot analysis for this symbol
                        $this->log('WARNING', "Skipping spot analysis for {$symbol}: " . $e->getMessage());
                        if ($tradingType === 'SPOT') {
                            throw $e; // If only spot was requested, fail
                        }
                        $tradingType = 'FUTURES'; // Fall back to futures only
                    } else {
                        throw $e;
                    }
                }
            }
            
            if ($tradingType === 'FUTURES' || $tradingType === 'BOTH') {
                $futuresStartTime = microtime(true);
                $futuresAnalysis = $this->analyzeFuturesMarket($symbol, $strategy);
                $futuresTime = microtime(true) - $futuresStartTime;
                if ($futuresTime > 30) {
                    $this->log('WARNING', "Futures analysis for {$symbol} took {$futuresTime}s");
                }
                $analysis['futures'] = $futuresAnalysis;
            }
            
            $totalTime = microtime(true) - $startTime;
            if ($totalTime > 45) {
                $this->log('WARNING', "Total analysis for {$symbol} took {$totalTime}s");
            }
            
            // Combine analysis if both markets
            if ($tradingType === 'BOTH' && isset($analysis['spot']) && isset($analysis['futures'])) {
                $analysis['combined'] = $this->combineAnalysis($analysis['spot'], $analysis['futures']);
                $finalAnalysis = $analysis['combined'];
            } elseif ($tradingType === 'BOTH' && isset($analysis['futures'])) {
                // Only futures analysis available
                $finalAnalysis = $analysis['futures'];
                $finalAnalysis['trading_type'] = 'FUTURES';
            } elseif ($tradingType === 'BOTH' && isset($analysis['spot'])) {
                // Only spot analysis available
                $finalAnalysis = $analysis['spot'];
                $finalAnalysis['trading_type'] = 'SPOT';
            } else {
                $finalAnalysis = $analysis[$tradingType === 'SPOT' ? 'spot' : 'futures'];
            }
            
            // Save enhanced signal to database
            $signalId = $this->saveEnhancedSignal($symbol, $finalAnalysis, $tradingType, $strategy);
            
            // Log the analysis
            $this->logEnhancedAnalysis($symbol, $finalAnalysis, $tradingType);
            
            return array_merge($finalAnalysis, ['signal_id' => $signalId, 'analysis_breakdown' => $analysis]);
            
        } catch (Exception $e) {
            // Add more context to error messages
            $errorContext = [
                'symbol' => $symbol,
                'trading_type' => $tradingType,
                'strategy' => $strategy,
                'execution_time' => microtime(true) - ($startTime ?? microtime(true))
            ];
            
            error_log("Enhanced AI Analysis error for {$symbol}: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function analyzeSpotMarket($symbol, $strategy = null) {
        try {
            // Get spot market data
            $klines = $this->spotAPI->getSpotKlines($symbol, '1h', 200);
            $ticker = $this->spotAPI->getSpotTicker($symbol);
            
            if (empty($klines) || empty($ticker)) {
                throw new Exception("Failed to get spot market data for {$symbol}");
            }
            
            return $this->performEnhancedAnalysis($klines, $ticker, 'SPOT', $strategy);
        } catch (Exception $e) {
            // If symbol is invalid for spot trading, skip it
            if (strpos($e->getMessage(), 'not valid for spot trading') !== false) {
                throw new Exception("Symbol {$symbol} is not available for spot trading");
            }
            throw $e;
        }
    }
    
    private function analyzeFuturesMarket($symbol, $strategy = null) {
        $binance = new BinanceAPI();
        
        // Get futures market data
        $klines = $binance->getKlines($symbol, '1h', 200);
        $ticker = $binance->get24hrTicker($symbol);
        
        if (empty($klines) || empty($ticker)) {
            throw new Exception("Failed to get futures market data for {$symbol}");
        }
        
        return $this->performEnhancedAnalysis($klines, $ticker, 'FUTURES', $strategy);
    }
    
    private function performEnhancedAnalysis($klines, $ticker, $marketType, $strategy = null) {
        // Extract price and volume data
        $prices = array_map(function($kline) {
            return (float)$kline[4]; // Close price
        }, $klines);
        
        $volumes = array_map(function($kline) {
            return (float)$kline[5]; // Volume
        }, $klines);
        
        $highs = array_map(function($kline) {
            return (float)$kline[2]; // High price
        }, $klines);
        
        $lows = array_map(function($kline) {
            return (float)$kline[3]; // Low price
        }, $klines);
        
        $currentPrice = (float)$ticker[0]['lastPrice'];
        $priceChange24h = (float)$ticker[0]['priceChangePercent'];
        $volume24h = (float)$ticker[0]['volume'];
        
        // Calculate enhanced technical indicators
        $indicators = $this->calculateEnhancedIndicators($prices, $volumes, $highs, $lows);
        
        // Perform multi-layered AI analysis
        $analysis = $this->performMultiLayerAnalysis($indicators, $currentPrice, $priceChange24h, $volume24h, $marketType, $strategy);
        
        return $analysis;
    }
    
    private function calculateEnhancedIndicators($prices, $volumes, $highs, $lows) {
        $indicators = [];
        
        // Moving Averages
        $indicators['sma_20'] = $this->calculateSMA($prices, 20);
        $indicators['sma_50'] = $this->calculateSMA($prices, 50);
        $indicators['sma_200'] = $this->calculateSMA($prices, 200);
        $indicators['ema_12'] = $this->calculateEMA($prices, 12);
        $indicators['ema_26'] = $this->calculateEMA($prices, 26);
        
        // Momentum Indicators
        $indicators['rsi'] = $this->calculateRSI($prices, 14);
        $indicators['rsi_fast'] = $this->calculateRSI($prices, 7);
        $indicators['stoch_k'] = $this->calculateStochastic($highs, $lows, $prices, 14);
        
        // Trend Indicators
        $macd = $this->calculateMACD($prices);
        $indicators['macd'] = $macd['macd'];
        $indicators['macd_signal'] = $macd['signal'];
        $indicators['macd_histogram'] = $macd['histogram'];
        
        // Volatility Indicators
        $bb = $this->calculateBollingerBands($prices, 20, 2);
        $indicators['bb_upper'] = $bb['upper'];
        $indicators['bb_middle'] = $bb['middle'];
        $indicators['bb_lower'] = $bb['lower'];
        $indicators['bb_width'] = ($bb['upper'] - $bb['lower']) / $bb['middle'];
        
        // Volume Indicators
        $indicators['volume_avg'] = array_sum(array_slice($volumes, -20)) / 20;
        $indicators['volume_current'] = end($volumes);
        $indicators['volume_ratio'] = $indicators['volume_avg'] > 0 ? 
            $indicators['volume_current'] / $indicators['volume_avg'] : 1.0;
        $indicators['obv'] = $this->calculateOBV($prices, $volumes);
        
        // Advanced Indicators
        $indicators['atr'] = $this->calculateATR($highs, $lows, $prices, 14);
        $indicators['adx'] = $this->calculateADX($highs, $lows, $prices, 14);
        $indicators['cci'] = $this->calculateCCI($highs, $lows, $prices, 20);
        $indicators['williams_r'] = $this->calculateWilliamsR($highs, $lows, $prices, 14);
        
        // Support and Resistance
        $sr = $this->calculateSupportResistance($highs, $lows, $prices);
        $indicators['support_level'] = $sr['support'];
        $indicators['resistance_level'] = $sr['resistance'];
        
        // Market Structure
        $indicators['trend_strength'] = $this->calculateTrendStrength($prices);
        $indicators['volatility'] = $this->calculateVolatility($prices);
        $indicators['momentum'] = $this->calculateMomentum($prices);
        
        return $indicators;
    }
    
    private function performMultiLayerAnalysis($indicators, $currentPrice, $priceChange24h, $volume24h, $marketType, $strategy) {
        $analysis = [
            'signal' => 'HOLD',
            'strength' => 'MODERATE',
            'confidence' => 0.5,
            'trading_type' => $marketType,
            'strategy_used' => $strategy,
            'price' => $currentPrice,
            'target_price' => null,
            'stop_loss_price' => null,
            'time_horizon' => 'SHORT',
            'market_sentiment' => 'NEUTRAL',
            'risk_level' => 'MEDIUM'
        ];
        
        // Layer 1: Trend Analysis
        $trendScore = $this->analyzeTrend($indicators, $currentPrice);
        
        // Layer 2: Momentum Analysis
        $momentumScore = $this->analyzeMomentum($indicators);
        
        // Layer 3: Volume Analysis
        $volumeScore = $this->analyzeVolume($indicators, $volume24h);
        
        // Layer 4: Volatility Analysis
        $volatilityScore = $this->analyzeVolatility($indicators);
        
        // Layer 5: Support/Resistance Analysis
        $srScore = $this->analyzeSupportResistance($indicators, $currentPrice);
        
        // Layer 6: Market Structure Analysis
        $structureScore = $this->analyzeMarketStructure($indicators, $priceChange24h);
        
        // Combine all scores with weights
        $weights = [
            'trend' => 0.25,
            'momentum' => 0.20,
            'volume' => 0.15,
            'volatility' => 0.10,
            'support_resistance' => 0.15,
            'structure' => 0.15
        ];
        
        $totalScore = 
            ($trendScore * $weights['trend']) +
            ($momentumScore * $weights['momentum']) +
            ($volumeScore * $weights['volume']) +
            ($volatilityScore * $weights['volatility']) +
            ($srScore * $weights['support_resistance']) +
            ($structureScore * $weights['structure']);
        
        // Determine signal based on total score
        if ($totalScore >= 7) {
            $analysis['signal'] = 'STRONG_BUY';
            $analysis['strength'] = 'VERY_STRONG';
            $analysis['confidence'] = min(0.95, 0.7 + ($totalScore - 7) * 0.05);
        } elseif ($totalScore >= 5) {
            $analysis['signal'] = 'BUY';
            $analysis['strength'] = 'STRONG';
            $analysis['confidence'] = 0.7 + ($totalScore - 5) * 0.1;
        } elseif ($totalScore >= 3) {
            $analysis['signal'] = 'HOLD';
            $analysis['strength'] = 'MODERATE';
            $analysis['confidence'] = 0.5 + ($totalScore - 3) * 0.1;
        } elseif ($totalScore >= 1) {
            $analysis['signal'] = 'SELL';
            $analysis['strength'] = 'STRONG';
            $analysis['confidence'] = 0.7 + (1 - $totalScore) * 0.1;
        } else {
            $analysis['signal'] = 'STRONG_SELL';
            $analysis['strength'] = 'VERY_STRONG';
            $analysis['confidence'] = min(0.95, 0.7 + (1 - $totalScore) * 0.05);
        }
        
        // Set target and stop loss prices
        $atr = $indicators['atr'];
        if ($analysis['signal'] === 'BUY' || $analysis['signal'] === 'STRONG_BUY') {
            $analysis['target_price'] = $currentPrice + ($atr * 2);
            $analysis['stop_loss_price'] = $currentPrice - ($atr * 1.5);
        } elseif ($analysis['signal'] === 'SELL' || $analysis['signal'] === 'STRONG_SELL') {
            $analysis['target_price'] = $currentPrice - ($atr * 2);
            $analysis['stop_loss_price'] = $currentPrice + ($atr * 1.5);
        }
        
        // Determine market sentiment
        if ($totalScore >= 6) {
            $analysis['market_sentiment'] = 'BULLISH';
        } elseif ($totalScore <= 2) {
            $analysis['market_sentiment'] = 'BEARISH';
        }
        
        // Set time horizon based on volatility and trend strength
        if ($indicators['volatility'] > 0.05 && $indicators['trend_strength'] > 0.7) {
            $analysis['time_horizon'] = 'SHORT';
        } elseif ($indicators['trend_strength'] > 0.5) {
            $analysis['time_horizon'] = 'MEDIUM';
        } else {
            $analysis['time_horizon'] = 'LONG';
        }
        
        // Add detailed scores and indicators
        $analysis['scores'] = [
            'trend' => $trendScore,
            'momentum' => $momentumScore,
            'volume' => $volumeScore,
            'volatility' => $volatilityScore,
            'support_resistance' => $srScore,
            'structure' => $structureScore,
            'total' => $totalScore
        ];
        
        $analysis['indicators'] = $indicators;
        $analysis['price_change_24h'] = $priceChange24h;
        $analysis['volume_24h'] = $volume24h;
        
        return $analysis;
    }
    
    private function analyzeTrend($indicators, $currentPrice) {
        $score = 0;
        
        // SMA trend analysis
        if ($indicators['sma_20'] > $indicators['sma_50']) $score += 2;
        if ($indicators['sma_50'] > $indicators['sma_200']) $score += 2;
        if ($currentPrice > $indicators['sma_20']) $score += 1;
        if ($currentPrice > $indicators['sma_50']) $score += 1;
        
        // EMA trend analysis
        if ($indicators['ema_12'] > $indicators['ema_26']) $score += 1;
        
        // ADX trend strength
        if ($indicators['adx'] > 25) $score += 1;
        if ($indicators['adx'] > 40) $score += 1;
        
        return min(10, max(0, $score));
    }
    
    private function analyzeMomentum($indicators) {
        $score = 5; // Start neutral
        
        // RSI analysis
        if ($indicators['rsi'] < 30) $score += 3; // Oversold
        elseif ($indicators['rsi'] < 40) $score += 1;
        elseif ($indicators['rsi'] > 70) $score -= 3; // Overbought
        elseif ($indicators['rsi'] > 60) $score -= 1;
        
        // MACD analysis
        if ($indicators['macd'] > $indicators['macd_signal']) $score += 2;
        else $score -= 2;
        
        if ($indicators['macd_histogram'] > 0) $score += 1;
        else $score -= 1;
        
        // Stochastic analysis
        if ($indicators['stoch_k'] < 20) $score += 2;
        elseif ($indicators['stoch_k'] > 80) $score -= 2;
        
        return min(10, max(0, $score));
    }
    
    private function analyzeVolume($indicators, $volume24h) {
        $score = 5; // Start neutral
        
        $volumeRatio = $indicators['volume_ratio'];
        
        if ($volumeRatio > 2.0) $score += 3; // Very high volume
        elseif ($volumeRatio > 1.5) $score += 2; // High volume
        elseif ($volumeRatio > 1.2) $score += 1; // Above average volume
        elseif ($volumeRatio < 0.5) $score -= 2; // Low volume
        
        // OBV analysis
        if ($indicators['obv'] > 0) $score += 1;
        else $score -= 1;
        
        return min(10, max(0, $score));
    }
    
    private function analyzeVolatility($indicators) {
        $score = 5; // Start neutral
        
        // Bollinger Bands width
        if ($indicators['bb_width'] > 0.1) $score += 2; // High volatility
        elseif ($indicators['bb_width'] < 0.02) $score -= 1; // Low volatility
        
        // ATR analysis
        if ($indicators['atr'] > $indicators['sma_20'] * 0.05) $score += 1;
        
        return min(10, max(0, $score));
    }
    
    private function analyzeSupportResistance($indicators, $currentPrice) {
        $score = 5; // Start neutral
        
        // Prevent division by zero
        if ($currentPrice <= 0) {
            return $score;
        }
        
        $support = $indicators['support_level'];
        $resistance = $indicators['resistance_level'];
        
        // Validate support and resistance levels
        if ($support <= 0 || $resistance <= 0 || $support >= $resistance) {
            return $score;
        }
        
        // Distance from support/resistance
        $supportDistance = ($currentPrice - $support) / $support;
        $resistanceDistance = ($resistance - $currentPrice) / $currentPrice;
        
        if ($supportDistance < 0.02) $score += 3; // Near support
        elseif ($resistanceDistance < 0.02) $score -= 3; // Near resistance
        
        // Bollinger Bands position
        if ($currentPrice <= $indicators['bb_lower']) $score += 2;
        elseif ($currentPrice >= $indicators['bb_upper']) $score -= 2;
        
        return min(10, max(0, $score));
    }
    
    private function analyzeMarketStructure($indicators, $priceChange24h) {
        $score = 5; // Start neutral
        
        // Price momentum
        if ($priceChange24h > 5) $score += 3;
        elseif ($priceChange24h > 2) $score += 1;
        elseif ($priceChange24h < -5) $score -= 3;
        elseif ($priceChange24h < -2) $score -= 1;
        
        // CCI analysis
        if ($indicators['cci'] > 100) $score += 1;
        elseif ($indicators['cci'] < -100) $score -= 1;
        
        // Williams %R
        if ($indicators['williams_r'] < -80) $score += 2;
        elseif ($indicators['williams_r'] > -20) $score -= 2;
        
        return min(10, max(0, $score));
    }
    
    private function combineAnalysis($spotAnalysis, $futuresAnalysis) {
        // Weight futures analysis slightly higher due to leverage
        $spotWeight = 0.4;
        $futuresWeight = 0.6;
        
        $combinedScore = 
            ($spotAnalysis['scores']['total'] * $spotWeight) + 
            ($futuresAnalysis['scores']['total'] * $futuresWeight);
        
        $combinedConfidence = 
            ($spotAnalysis['confidence'] * $spotWeight) + 
            ($futuresAnalysis['confidence'] * $futuresWeight);
        
        // Use the stronger signal if both agree, otherwise be more conservative
        $signal = 'HOLD';
        $strength = 'MODERATE';
        
        if ($spotAnalysis['signal'] === $futuresAnalysis['signal']) {
            $signal = $spotAnalysis['signal'];
            $strength = $spotAnalysis['strength'] ?? 'MODERATE';
        } elseif (
            ($spotAnalysis['signal'] === 'BUY' && $futuresAnalysis['signal'] === 'STRONG_BUY') ||
            ($spotAnalysis['signal'] === 'STRONG_BUY' && $futuresAnalysis['signal'] === 'BUY')
        ) {
            $signal = 'BUY';
            $strength = 'STRONG';
        } elseif (
            ($spotAnalysis['signal'] === 'SELL' && $futuresAnalysis['signal'] === 'STRONG_SELL') ||
            ($spotAnalysis['signal'] === 'STRONG_SELL' && $futuresAnalysis['signal'] === 'SELL')
        ) {
            $signal = 'SELL';
            $strength = 'STRONG';
        }
        
        return [
            'signal' => $signal,
            'strength' => $strength,
            'confidence' => $combinedConfidence,
            'trading_type' => 'BOTH',
            'price' => $futuresAnalysis['price'], // Use futures price as reference
            'target_price' => ($spotAnalysis['target_price'] + $futuresAnalysis['target_price']) / 2,
            'stop_loss_price' => ($spotAnalysis['stop_loss_price'] + $futuresAnalysis['stop_loss_price']) / 2,
            'market_sentiment' => $futuresAnalysis['market_sentiment'] ?? 'NEUTRAL',
            'time_horizon' => $futuresAnalysis['time_horizon'] ?? 'SHORT',
            'risk_level' => $futuresAnalysis['risk_level'] ?? 'MEDIUM',
            'scores' => [
                'total' => $combinedScore,
                'spot_total' => $spotAnalysis['scores']['total'],
                'futures_total' => $futuresAnalysis['scores']['total']
            ],
            'spot_analysis' => $spotAnalysis,
            'futures_analysis' => $futuresAnalysis
        ];
    }
    
    private function saveEnhancedSignal($symbol, $analysis, $tradingType, $strategy) {
        $data = [
            'symbol' => $symbol,
            'trading_type' => $tradingType,
            '`signal`' => $analysis['signal'],
            'confidence' => $analysis['confidence'],
            'strength' => $analysis['strength'] ?? 'MODERATE',
            'price' => $analysis['price'],
            'target_price' => $analysis['target_price'],
            'stop_loss_price' => $analysis['stop_loss_price'],
            'time_horizon' => $analysis['time_horizon'] ?? 'SHORT',
            'market_sentiment' => $analysis['market_sentiment'] ?? 'NEUTRAL',
            'analysis_score' => $analysis['scores']['total'] ?? 0,
            'technical_score' => $analysis['scores']['trend'] ?? 0,
            'momentum_score' => $analysis['scores']['momentum'] ?? 0,
            'trend_score' => $analysis['scores']['trend'] ?? 0,
            'volume_score' => $analysis['scores']['volume'] ?? 0,
            'volatility' => $analysis['indicators']['volatility'] ?? 0,
            'rsi' => $analysis['indicators']['rsi'] ?? null,
            'macd' => $analysis['indicators']['macd'] ?? null,
            'macd_signal' => $analysis['indicators']['macd_signal'] ?? null,
            'macd_histogram' => $analysis['indicators']['macd_histogram'] ?? null,
            'bb_upper' => $analysis['indicators']['bb_upper'] ?? null,
            'bb_middle' => $analysis['indicators']['bb_middle'] ?? null,
            'bb_lower' => $analysis['indicators']['bb_lower'] ?? null,
            'sma_20' => $analysis['indicators']['sma_20'] ?? null,
            'sma_50' => $analysis['indicators']['sma_50'] ?? null,
            'sma_200' => $analysis['indicators']['sma_200'] ?? null,
            'ema_12' => $analysis['indicators']['ema_12'] ?? null,
            'ema_26' => $analysis['indicators']['ema_26'] ?? null,
            'volume' => $analysis['indicators']['volume_current'] ?? null,
            'volume_avg' => $analysis['indicators']['volume_avg'] ?? null,
            'volume_ratio' => $analysis['indicators']['volume_ratio'] ?? null,
            'price_change_24h' => $analysis['price_change_24h'] ?? null,
            'indicators_data' => json_encode($analysis['indicators'] ?? []),
            'market_analysis' => json_encode($analysis['scores'] ?? []),
            'risk_assessment' => json_encode([
                'risk_level' => $analysis['risk_level'] ?? 'MEDIUM',
                'volatility' => $analysis['indicators']['volatility'] ?? 0,
                'atr' => $analysis['indicators']['atr'] ?? 0
            ])
        ];
        
        try {
            return $this->db->insert('ai_signals', $data);
        } catch (Exception $e) {
            error_log("Failed to save enhanced signal: " . $e->getMessage());
            // Try with basic signal data
            $basicData = [
                'symbol' => $symbol,
                '`signal`' => $analysis['signal'],
                'confidence' => $analysis['confidence'],
                'price' => $analysis['price'],
                'analysis_score' => $analysis['scores']['total'] ?? 0
            ];
            return $this->db->insert('ai_signals', $basicData);
        }
    }
    
    private function logEnhancedAnalysis($symbol, $analysis, $tradingType) {
        try {
            $this->db->insert('system_logs', [
                'level' => 'INFO',
                'category' => 'AI',
                'message' => "[ENHANCED_AI] Generated {$analysis['signal']} signal for {$symbol} ({$tradingType}) with {$analysis['confidence']}% confidence",
                'context' => json_encode([
                    'symbol' => $symbol,
                    'trading_type' => $tradingType,
                    'signal' => $analysis['signal'],
                    'confidence' => $analysis['confidence'],
                    'strength' => $analysis['strength'] ?? 'MODERATE',
                    'total_score' => $analysis['scores']['total'] ?? 0,
                    'market_sentiment' => $analysis['market_sentiment'],
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ]);
        } catch (Exception $e) {
            error_log("Failed to log enhanced AI analysis: " . $e->getMessage());
        }
    }
    
    private function log($level, $message) {
        try {
            $this->db->insert('system_logs', [
                'level' => $level,
                'category' => 'AI',
                'message' => "[ENHANCED_AI] {$message}",
                'context' => json_encode([
                    'timestamp' => date('Y-m-d H:i:s'),
                    'memory_usage' => memory_get_usage(true)
                ])
            ]);
        } catch (Exception $e) {
            error_log("Failed to log enhanced AI message: " . $e->getMessage());
        }
    }
    
    // Additional technical indicator calculations
    
    private function calculateStochastic($highs, $lows, $closes, $period = 14) {
        if (count($highs) < $period) return 50;
        
        $recentHighs = array_slice($highs, -$period);
        $recentLows = array_slice($lows, -$period);
        $currentClose = end($closes);
        
        $highestHigh = max($recentHighs);
        $lowestLow = min($recentLows);
        
        if ($highestHigh == $lowestLow) return 50;
        
        return (($currentClose - $lowestLow) / ($highestHigh - $lowestLow)) * 100;
    }
    
    private function calculateOBV($prices, $volumes) {
        $obv = 0;
        for ($i = 1; $i < count($prices); $i++) {
            if ($prices[$i] > $prices[$i-1]) {
                $obv += $volumes[$i];
            } elseif ($prices[$i] < $prices[$i-1]) {
                $obv -= $volumes[$i];
            }
        }
        return $obv;
    }
    
    private function calculateATR($highs, $lows, $closes, $period = 14) {
        if (count($highs) < $period + 1) return 0;
        
        $trueRanges = [];
        for ($i = 1; $i < count($highs); $i++) {
            $tr1 = $highs[$i] - $lows[$i];
            $tr2 = abs($highs[$i] - $closes[$i-1]);
            $tr3 = abs($lows[$i] - $closes[$i-1]);
            $trueRanges[] = max($tr1, $tr2, $tr3);
        }
        
        $recentTR = array_slice($trueRanges, -$period);
        return array_sum($recentTR) / count($recentTR);
    }
    
    private function calculateADX($highs, $lows, $closes, $period = 14) {
        // Simplified ADX calculation
        if (count($highs) < $period + 1) return 0;
        
        $plusDM = [];
        $minusDM = [];
        
        for ($i = 1; $i < count($highs); $i++) {
            $highDiff = $highs[$i] - $highs[$i-1];
            $lowDiff = $lows[$i-1] - $lows[$i];
            
            $plusDM[] = ($highDiff > $lowDiff && $highDiff > 0) ? $highDiff : 0;
            $minusDM[] = ($lowDiff > $highDiff && $lowDiff > 0) ? $lowDiff : 0;
        }
        
        $avgPlusDM = array_sum(array_slice($plusDM, -$period)) / $period;
        $avgMinusDM = array_sum(array_slice($minusDM, -$period)) / $period;
        
        $atr = $this->calculateATR($highs, $lows, $closes, $period);
        if ($atr == 0) return 0;
        
        $plusDI = ($avgPlusDM / $atr) * 100;
        $minusDI = ($avgMinusDM / $atr) * 100;
        
        if ($plusDI + $minusDI == 0) return 0;
        
        return abs($plusDI - $minusDI) / ($plusDI + $minusDI) * 100;
    }
    
    private function calculateCCI($highs, $lows, $closes, $period = 20) {
        if (count($highs) < $period) return 0;
        
        $typicalPrices = [];
        for ($i = 0; $i < count($highs); $i++) {
            $typicalPrices[] = ($highs[$i] + $lows[$i] + $closes[$i]) / 3;
        }
        
        $recentTP = array_slice($typicalPrices, -$period);
        $smaTP = array_sum($recentTP) / count($recentTP);
        
        $meanDeviation = 0;
        foreach ($recentTP as $tp) {
            $meanDeviation += abs($tp - $smaTP);
        }
        $meanDeviation /= count($recentTP);
        
        if ($meanDeviation == 0) return 0;
        
        $currentTP = end($typicalPrices);
        return ($currentTP - $smaTP) / (0.015 * $meanDeviation);
    }
    
    private function calculateWilliamsR($highs, $lows, $closes, $period = 14) {
        if (count($highs) < $period) return -50;
        
        $recentHighs = array_slice($highs, -$period);
        $recentLows = array_slice($lows, -$period);
        $currentClose = end($closes);
        
        $highestHigh = max($recentHighs);
        $lowestLow = min($recentLows);
        
        if ($highestHigh == $lowestLow) return -50;
        
        return (($highestHigh - $currentClose) / ($highestHigh - $lowestLow)) * -100;
    }
    
    private function calculateSupportResistance($highs, $lows, $closes) {
        // Simplified support/resistance calculation
        if (empty($highs) || empty($lows) || empty($closes)) {
            return ['support' => 0, 'resistance' => 0];
        }
        
        $recentHighs = array_slice($highs, -50);
        $recentLows = array_slice($lows, -50);
        
        if (empty($recentHighs) || empty($recentLows)) {
            return ['support' => 0, 'resistance' => 0];
        }
        
        // Find pivot points
        $resistance = max($recentHighs);
        $support = min($recentLows);
        
        // Ensure valid levels
        if ($support <= 0 || $resistance <= 0 || $support >= $resistance) {
            $avgPrice = count($closes) > 0 ? array_sum($closes) / count($closes) : 1000;
            return [
                'support' => $avgPrice * 0.95,
                'resistance' => $avgPrice * 1.05
            ];
        }
        
        return [
            'support' => $support,
            'resistance' => $resistance
        ];
    }
    
    private function calculateTrendStrength($prices) {
        if (count($prices) < 20) return 0.5;
        
        $recent = array_slice($prices, -20);
        if (empty($recent) || count($recent) < 2) return 0.5;
        
        $slope = ($recent[19] - $recent[0]) / 19;
        $avgPrice = array_sum($recent) / count($recent);
        
        if ($avgPrice <= 0) return 0.5;
        
        return min(1, abs($slope / $avgPrice));
    }
    
    private function calculateVolatility($prices) {
        if (count($prices) < 20) return 0;
        
        if (empty($prices)) return 0;
        
        $returns = [];
        for ($i = 1; $i < count($prices); $i++) {
            if ($prices[$i-1] <= 0) continue;
            $returns[] = ($prices[$i] - $prices[$i-1]) / $prices[$i-1];
        }
        
        if (empty($returns)) return 0;
        
        $avgReturn = array_sum($returns) / count($returns);
        $variance = 0;
        
        foreach ($returns as $return) {
            $variance += pow($return - $avgReturn, 2);
        }
        
        if (count($returns) <= 1) return 0;
        
        return sqrt($variance / (count($returns) - 1));
    }
    
    private function calculateMomentum($prices) {
        if (count($prices) < 10) return 0;
        
        if (empty($prices)) return 0;
        
        $recent = array_slice($prices, -10);
        if (count($recent) < 2 || $recent[0] <= 0) return 0;
        
        return ($recent[9] - $recent[0]) / $recent[0];
    }
}