<?php

class AIAnalyzer {
    protected $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function analyzeSymbol($symbol) {
        try {
            $binance = new BinanceAPI();
            
            // Check if API credentials are available
            if (!$binance->hasCredentials()) {
                throw new Exception("Cannot analyze {$symbol}: API credentials not configured. Please set your Binance API key and secret in Settings.");
            }
            
            // Get market data
            $klines = $binance->getKlines($symbol, '1h', 100);
            $ticker = $binance->get24hrTicker($symbol);
            
            if (empty($klines) || empty($ticker)) {
                throw new Exception("Failed to get market data for {$symbol}");
            }
            
            // Extract price data
            $prices = array_map(function($kline) {
                return (float)$kline[4]; // Close price
            }, $klines);
            
            $volumes = array_map(function($kline) {
                return (float)$kline[5]; // Volume
            }, $klines);
            
            $currentPrice = (float)$ticker[0]['lastPrice'];
            $priceChange24h = (float)$ticker[0]['priceChangePercent'];
            $volume24h = (float)$ticker[0]['volume'];
            
            // Calculate technical indicators
            $indicators = $this->calculateIndicators($prices, $volumes);
            
            // AI Analysis
            $analysis = $this->performAIAnalysis($indicators, $currentPrice, $priceChange24h, $volume24h);
            
            // Save signal to database
            $signalId = $this->saveSignal($symbol, $analysis, $indicators, $currentPrice);
            
            // Log the analysis
            $this->logAnalysis($symbol, $analysis);
            
            return array_merge($analysis, ['signal_id' => $signalId]);
            
        } catch (Exception $e) {
            error_log("AI Analysis error for {$symbol}: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function logAnalysis($symbol, $analysis) {
        try {
            $this->db->insert('system_logs', [
                'level' => 'INFO',
                'message' => "[AI_ANALYZER] Generated {$analysis['signal']} signal for {$symbol} with {$analysis['confidence']}% confidence",
                'context' => json_encode([
                    'symbol' => $symbol,
                    'signal' => $analysis['signal'],
                    'confidence' => $analysis['confidence'],
                    'score' => $analysis['score'],
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ]);
        } catch (Exception $e) {
            error_log("Failed to log AI analysis: " . $e->getMessage());
        }
    }
    
    private function calculateIndicators($prices, $volumes) {
        $indicators = [];
        
        // Simple Moving Averages
        $indicators['sma_20'] = $this->calculateSMA($prices, 20);
        $indicators['sma_50'] = $this->calculateSMA($prices, 50);
        
        // RSI
        $indicators['rsi'] = $this->calculateRSI($prices, 14);
        
        // MACD
        $macd = $this->calculateMACD($prices);
        $indicators['macd'] = $macd['macd'];
        $indicators['macd_signal'] = $macd['signal'];
        $indicators['macd_histogram'] = $macd['histogram'];
        
        // Bollinger Bands
        $bb = $this->calculateBollingerBands($prices, 20, 2);
        $indicators['bb_upper'] = $bb['upper'];
        $indicators['bb_middle'] = $bb['middle'];
        $indicators['bb_lower'] = $bb['lower'];
        
        // Volume indicators
        $indicators['volume_avg'] = array_sum(array_slice($volumes, -20)) / 20;
        $indicators['volume_current'] = end($volumes);
        
        return $indicators;
    }
    
    private function performAIAnalysis($indicators, $currentPrice, $priceChange24h, $volume24h) {
        $score = 0;
        $confidence = 0;
        $signal = 'HOLD';
        $reasons = [];
        
        // Trend Analysis (SMA)
        if ($indicators['sma_20'] > $indicators['sma_50']) {
            $score += 2; // Uptrend
            $reasons[] = 'SMA20 > SMA50 (Uptrend)';
        } else {
            $score -= 2; // Downtrend
            $reasons[] = 'SMA20 < SMA50 (Downtrend)';
        }
        
        // Price vs SMA
        if ($currentPrice > $indicators['sma_20']) {
            $score += 1;
            $reasons[] = 'Price above SMA20';
        } else {
            $score -= 1;
            $reasons[] = 'Price below SMA20';
        }
        
        // RSI Analysis
        if ($indicators['rsi'] < 30) {
            $score += 3; // Oversold - Strong buy signal
            $reasons[] = 'RSI Oversold (<30)';
        } elseif ($indicators['rsi'] < 40) {
            $score += 1; // Slightly oversold
            $reasons[] = 'RSI Low (<40)';
        } elseif ($indicators['rsi'] > 70) {
            $score -= 3; // Overbought - Strong sell signal
            $reasons[] = 'RSI Overbought (>70)';
        } elseif ($indicators['rsi'] > 60) {
            $score -= 1; // Slightly overbought
            $reasons[] = 'RSI High (>60)';
        }
        
        // MACD Analysis
        if ($indicators['macd'] > $indicators['macd_signal']) {
            $score += 2; // Bullish crossover
            $reasons[] = 'MACD Bullish';
        } else {
            $score -= 2; // Bearish crossover
            $reasons[] = 'MACD Bearish';
        }
        
        if ($indicators['macd_histogram'] > 0) {
            $score += 1; // Positive momentum
            $reasons[] = 'MACD Histogram Positive';
        } else {
            $score -= 1; // Negative momentum
            $reasons[] = 'MACD Histogram Negative';
        }
        
        // Bollinger Bands Analysis
        if ($currentPrice <= $indicators['bb_lower']) {
            $score += 2; // Near lower band - potential buy
            $reasons[] = 'Price at Lower Bollinger Band';
        } elseif ($currentPrice >= $indicators['bb_upper']) {
            $score -= 2; // Near upper band - potential sell
            $reasons[] = 'Price at Upper Bollinger Band';
        }
        
        // Volume Analysis
        $volumeRatio = $indicators['volume_current'] / $indicators['volume_avg'];
        if ($volumeRatio > 1.5) {
            if ($priceChange24h > 0) {
                $score += 2; // High volume with positive price change
                $reasons[] = 'High Volume + Positive Price Change';
            } else {
                $score -= 2; // High volume with negative price change
                $reasons[] = 'High Volume + Negative Price Change';
            }
        }
        
        // Price momentum
        if ($priceChange24h > 5) {
            $score += 2; // Strong positive momentum
            $reasons[] = 'Strong Positive Momentum (>5%)';
        } elseif ($priceChange24h > 2) {
            $score += 1; // Moderate positive momentum
            $reasons[] = 'Moderate Positive Momentum (>2%)';
        } elseif ($priceChange24h < -5) {
            $score -= 2; // Strong negative momentum
            $reasons[] = 'Strong Negative Momentum (<-5%)';
        } elseif ($priceChange24h < -2) {
            $score -= 1; // Moderate negative momentum
            $reasons[] = 'Moderate Negative Momentum (<-2%)';
        }
        
        // Determine signal and confidence
        if ($score >= 5) {
            $signal = 'BUY';
            $confidence = min($score / 10, 1.0);
        } elseif ($score <= -5) {
            $signal = 'SELL';
            $confidence = min(abs($score) / 10, 1.0);
        } else {
            $signal = 'HOLD';
            $confidence = 0.3;
        }
        
        // Apply additional filters for high confidence signals
        if ($confidence > 0.7) {
            // Check for conflicting signals
            if (($signal === 'BUY' && $indicators['rsi'] > 70) || 
                ($signal === 'SELL' && $indicators['rsi'] < 30)) {
                $confidence *= 0.7; // Reduce confidence for conflicting RSI
                $reasons[] = 'Confidence reduced due to conflicting RSI';
            }
            
            // Volume confirmation
            if ($volumeRatio < 1.2) {
                $confidence *= 0.8; // Reduce confidence for low volume
                $reasons[] = 'Confidence reduced due to low volume';
            }
        }
        
        return [
            'signal' => $signal,
            'confidence' => round($confidence, 3),
            'score' => $score,
            'price_change_24h' => $priceChange24h,
            'volume_ratio' => round($volumeRatio, 2),
            'reasons' => $reasons
        ];
    }
    
    private function saveSignal($symbol, $analysis, $indicators, $currentPrice) {
        $data = [
            'symbol' => $symbol,
            '`signal`' => $analysis['signal'],
            'confidence' => $analysis['confidence'],
            'price' => $currentPrice,
            'rsi' => $indicators['rsi'],
            'macd' => $indicators['macd'],
            'macd_signal' => $indicators['macd_signal'],
            'macd_histogram' => $indicators['macd_histogram'],
            'bb_upper' => $indicators['bb_upper'],
            'bb_middle' => $indicators['bb_middle'],
            'bb_lower' => $indicators['bb_lower'],
            'sma_20' => $indicators['sma_20'],
            'sma_50' => $indicators['sma_50'],
            'volume' => $indicators['volume_current'],
            'volume_avg' => $indicators['volume_avg'],
            'price_change_24h' => $analysis['price_change_24h'],
            'analysis_score' => $analysis['score'],
            'indicators_data' => json_encode($indicators)
        ];
        
        return $this->db->insert('ai_signals', $data);
    }
    
    // Technical Indicator Calculations
    
    protected function calculateSMA($prices, $period) {
        if (count($prices) < $period) {
            return 0;
        }
        
        if (empty($prices)) {
            return 0;
        }
        
        $slice = array_slice($prices, -$period);
        if (empty($slice)) {
            return 0;
        }
        
        $count = count($slice);
        return $count > 0 ? array_sum($slice) / $count : 0;
    }
    
    protected function calculateRSI($prices, $period = 14) {
        if (count($prices) < $period + 1) {
            return 50;
        }
        
        if (empty($prices)) {
            return 50;
        }
        
        $gains = [];
        $losses = [];
        
        for ($i = 1; $i < count($prices); $i++) {
            if (!isset($prices[$i]) || !isset($prices[$i-1])) {
                continue;
            }
            $change = $prices[$i] - $prices[$i - 1];
            if ($change > 0) {
                $gains[] = $change;
                $losses[] = 0;
            } else {
                $gains[] = 0;
                $losses[] = abs($change);
            }
        }
        
        if (empty($gains) || empty($losses)) {
            return 50;
        }
        
        $avgGain = array_sum(array_slice($gains, -$period)) / $period;
        $avgLoss = array_sum(array_slice($losses, -$period)) / $period;
        
        if ($avgLoss == 0) {
            return 100;
        }
        
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }
    
    protected function calculateMACD($prices, $fastPeriod = 12, $slowPeriod = 26, $signalPeriod = 9) {
        if (count($prices) < $slowPeriod) {
            return ['macd' => 0, 'signal' => 0, 'histogram' => 0];
        }
        
        $emaFast = $this->calculateEMA($prices, $fastPeriod);
        $emaSlow = $this->calculateEMA($prices, $slowPeriod);
        $macd = $emaFast - $emaSlow;
        
        // For simplicity, using SMA for signal line instead of EMA
        $signal = $macd; // Simplified
        $histogram = $macd - $signal;
        
        return [
            'macd' => $macd,
            'signal' => $signal,
            'histogram' => $histogram
        ];
    }
    
    protected function calculateEMA($prices, $period) {
        if (count($prices) < $period) {
            return $this->calculateSMA($prices, count($prices));
        }
        
        if (empty($prices)) {
            return 0;
        }
        
        $multiplier = 2 / ($period + 1);
        $ema = $prices[0];
        
        for ($i = 1; $i < count($prices); $i++) {
            if (!isset($prices[$i])) {
                continue;
            }
            $ema = ($prices[$i] * $multiplier) + ($ema * (1 - $multiplier));
        }
        
        return $ema;
    }
    
    protected function calculateBollingerBands($prices, $period = 20, $stdDev = 2) {
        if (count($prices) < $period) {
            if (empty($prices)) {
                return ['upper' => 0, 'middle' => 0, 'lower' => 0];
            }
            $count = count($prices);
            $middle = $count > 0 ? array_sum($prices) / $count : 0;
            return ['upper' => $middle, 'middle' => $middle, 'lower' => $middle];
        }
        
        $slice = array_slice($prices, -$period);
        if (empty($slice)) {
            return ['upper' => 0, 'middle' => 0, 'lower' => 0];
        }
        
        $count = count($slice);
        $middle = $count > 0 ? array_sum($slice) / $count : 0;
        
        // Calculate standard deviation
        $variance = 0;
        foreach ($slice as $price) {
            $variance += pow($price - $middle, 2);
        }
        
        if ($count <= 0) {
            return ['upper' => $middle, 'middle' => $middle, 'lower' => $middle];
        }
        
        $variance /= $count;
        $standardDeviation = sqrt($variance);
        
        return [
            'upper' => $middle + ($standardDeviation * $stdDev),
            'middle' => $middle,
            'lower' => $middle - ($standardDeviation * $stdDev)
        ];
    }
    
    public function getRecentSignals($limit = 20) {
        return $this->db->fetchAll(
            "SELECT * FROM ai_signals ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }
    
    public function getSignalsBySymbol($symbol, $limit = 10) {
        return $this->db->fetchAll(
            "SELECT * FROM ai_signals WHERE symbol = ? ORDER BY created_at DESC LIMIT ?",
            [$symbol, $limit]
        );
    }
    
    public function getSignalStats() {
        $stats = $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_signals,
                SUM(CASE WHEN `signal` = 'BUY' THEN 1 ELSE 0 END) as buy_signals,
                SUM(CASE WHEN `signal` = 'SELL' THEN 1 ELSE 0 END) as sell_signals,
                SUM(CASE WHEN `signal` = 'HOLD' THEN 1 ELSE 0 END) as hold_signals,
                AVG(confidence) as avg_confidence,
                SUM(CASE WHEN confidence > 0.7 THEN 1 ELSE 0 END) as high_confidence_signals
            FROM ai_signals 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        return $stats ?: [
            'total_signals' => 0,
            'buy_signals' => 0,
            'sell_signals' => 0,
            'hold_signals' => 0,
            'avg_confidence' => 0,
            'high_confidence_signals' => 0
        ];
    }
}