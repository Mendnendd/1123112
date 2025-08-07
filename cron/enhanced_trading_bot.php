#!/usr/bin/env php
<?php

// Enhanced Trading Bot Cron Job
// This script runs the enhanced trading bot with spot and futures support

// Ensure we're in the correct directory
$projectRoot = dirname(__DIR__);
if (!chdir($projectRoot)) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: Could not change to project directory: {$projectRoot}\n";
    exit(1);
}

// Verify required files exist
if (!file_exists('config/app.php')) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: config/app.php not found. Current directory: " . getcwd() . "\n";
    exit(1);
}

// Load the application
try {
    require_once 'config/app.php';
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error loading application: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if installation is complete
if (!file_exists('config/installed.flag')) {
    echo "Installation not complete. Please run install.php first.\n";
    exit(1);
}

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting enhanced trading bot...\n";
    
    // Set execution time limit to prevent timeouts
    set_time_limit(300); // 5 minutes max
    ini_set('memory_limit', '256M');
    
    // Test database connection
    try {
        $db = Database::getInstance();
        $db->query("SELECT 1");
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Database connection failed: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Initialize the enhanced trading bot
    try {
        $bot = new EnhancedTradingBot();
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Failed to initialize EnhancedTradingBot: " . $e->getMessage() . "\n";
        echo "[" . date('Y-m-d H:i:s') . "] Falling back to basic TradingBot...\n";
        $bot = new TradingBot();
    }
    
    // Check if trading is enabled
    $settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
    
    if (!$settings) {
        echo "[" . date('Y-m-d H:i:s') . "] Trading settings not found. Please configure the system.\n";
        exit(1);
    }
    
    if ($settings['emergency_stop']) {
        echo "[" . date('Y-m-d H:i:s') . "] Emergency stop is active. Skipping execution.\n";
        exit(0);
    }
    
    if (!$settings['trading_enabled']) {
        echo "[" . date('Y-m-d H:i:s') . "] Trading is disabled. Skipping execution.\n";
        exit(0);
    }
    
    if (!$settings['ai_enabled']) {
        echo "[" . date('Y-m-d H:i:s') . "] AI analysis is disabled. Skipping execution.\n";
        exit(0);
    }
    
    // Run the enhanced bot
    $bot->run();
    
    // Generate enhanced AI signals for active pairs if enabled
    if ($settings['ai_enabled']) {
        echo "[" . date('Y-m-d H:i:s') . "] Generating enhanced AI signals...\n";
        
        try {
            $enhancedAI = new EnhancedAIAnalyzer();
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Failed to initialize EnhancedAIAnalyzer: " . $e->getMessage() . "\n";
            echo "[" . date('Y-m-d H:i:s') . "] Falling back to basic AIAnalyzer...\n";
            $enhancedAI = new AIAnalyzer();
        }
        
        $pairs = $db->fetchAll("SELECT * FROM trading_pairs WHERE enabled = 1");
        $processedPairs = 0;
        $maxPairs = 10; // Limit to prevent timeouts
        
        foreach ($pairs as $pair) {
            if ($processedPairs >= $maxPairs) {
                echo "[" . date('Y-m-d H:i:s') . "] Reached maximum pairs limit ({$maxPairs}) to prevent timeout\n";
                break;
            }
            
            try {
                $tradingType = $pair['trading_type'];
                
                // Skip if trading type is disabled
                if ($tradingType === 'SPOT' && !$settings['spot_trading_enabled']) {
                    continue;
                }
                if ($tradingType === 'FUTURES' && !$settings['futures_trading_enabled']) {
                    continue;
                }
                
                // Add timeout protection for individual symbol analysis
                $startTime = microtime(true);
                
                if (method_exists($enhancedAI, 'analyzeSymbolEnhanced')) {
                    $analysis = $enhancedAI->analyzeSymbolEnhanced($pair['symbol'], $tradingType);
                } else {
                    $analysis = $enhancedAI->analyzeSymbol($pair['symbol']);
                    $analysis['strength'] = 'MODERATE';
                    $analysis['trading_type'] = $tradingType;
                }
                
                $analysisTime = microtime(true) - $startTime;
                if ($analysisTime > 30) {
                    echo "[" . date('Y-m-d H:i:s') . "] Warning: Analysis for " . $pair['symbol'] . " took {$analysisTime}s\n";
                }
                
                echo "[" . date('Y-m-d H:i:s') . "] Generated " . $analysis['signal'] . " signal for " . $pair['symbol'] . " ({$tradingType}) - Confidence: " . ($analysis['confidence'] * 100) . "%, Strength: " . $analysis['strength'] . "\n";
                
                // Send notification for high-confidence signals
                if ($analysis['confidence'] > 0.8) {
                    try {
                        $db->insert('notifications', [
                            'type' => 'SIGNAL',
                            'category' => 'AI',
                            'title' => "High Confidence Signal: " . $analysis['signal'] . " " . $pair['symbol'],
                            'message' => "AI generated a " . $analysis['strength'] . " " . $analysis['signal'] . " signal for " . $pair['symbol'] . " with " . ($analysis['confidence'] * 100) . "% confidence.",
                            'priority' => $analysis['confidence'] > 0.9 ? 'HIGH' : 'NORMAL',
                            'data' => json_encode([
                                'symbol' => $pair['symbol'],
                                'signal' => $analysis['signal'],
                                'confidence' => $analysis['confidence'],
                                'strength' => $analysis['strength'],
                                'trading_type' => $tradingType
                            ])
                        ]);
                    } catch (Exception $e) {
                        echo "[" . date('Y-m-d H:i:s') . "] Failed to create notification: " . $e->getMessage() . "\n";
                    }
                }
                
            } catch (Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Error generating enhanced signal for " . $pair['symbol'] . ": " . $e->getMessage() . "\n";
                
                // Skip problematic symbols to prevent blocking others
                if (strpos($e->getMessage(), 'timeout') !== false || 
                    strpos($e->getMessage(), 'Division by zero') !== false) {
                    echo "[" . date('Y-m-d H:i:s') . "] Skipping " . $pair['symbol'] . " due to critical error\n";
                    continue;
                }
            }
            
            $processedPairs++;
            
            // Rate limiting
            sleep(1);
        }
    }
    
    // Clean up old data
    try {
        echo "[" . date('Y-m-d H:i:s') . "] Cleaning up old data...\n";
        
        // Clean old market data cache (older than 1 hour)
        $db->query("DELETE FROM market_data_cache WHERE expires_at < NOW()");
        
        // Clean old notifications (older than 7 days and read)
        $db->query("DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        // Clean old system logs (older than 30 days, keep errors for 90 days)
        $db->query("DELETE FROM system_logs WHERE level NOT IN ('ERROR', 'CRITICAL') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $db->query("DELETE FROM system_logs WHERE level IN ('ERROR', 'CRITICAL') AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        echo "[" . date('Y-m-d H:i:s') . "] Data cleanup completed.\n";
        
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Data cleanup error: " . $e->getMessage() . "\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Enhanced trading bot completed successfully.\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Enhanced trading bot error: " . $e->getMessage() . "\n";
    
    // Log the error
    error_log("Enhanced trading bot cron error: " . $e->getMessage());
    
    // Create error notification
    try {
        $db = Database::getInstance();
        $db->insert('notifications', [
            'type' => 'ERROR',
            'category' => 'SYSTEM',
            'title' => 'Trading Bot Error',
            'message' => 'The enhanced trading bot encountered an error: ' . $e->getMessage(),
            'priority' => 'HIGH'
        ]);
    } catch (Exception $notifError) {
        echo "[" . date('Y-m-d H:i:s') . "] Failed to create error notification: " . $notifError->getMessage() . "\n";
    }
    
    exit(1);
}