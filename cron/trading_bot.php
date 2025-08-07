#!/usr/bin/env php
<?php

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
    echo "[" . date('Y-m-d H:i:s') . "] Starting trading bot...\n";
    
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
    
    // Initialize the trading bot
    $bot = class_exists('EnhancedTradingBot') ? new EnhancedTradingBot() : new TradingBot();
    
    // Check if trading is enabled
    $settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
    
    if (!$settings) {
        echo "[" . date('Y-m-d H:i:s') . "] Trading settings not found. Please configure the system.\n";
        exit(1);
    }
    
    if (!$settings['trading_enabled']) {
        echo "[" . date('Y-m-d H:i:s') . "] Trading is disabled. Skipping execution.\n";
        exit(0);
    }
    
    if (!$settings['ai_enabled']) {
        echo "[" . date('Y-m-d H:i:s') . "] AI analysis is disabled. Skipping execution.\n";
        exit(0);
    }
    
    // Run the bot
    $bot->run();
    
    // Generate AI signals for active pairs if enabled
    if ($settings['ai_enabled']) {
        echo "[" . date('Y-m-d H:i:s') . "] Generating AI signals...\n";
        
        $ai = class_exists('EnhancedAIAnalyzer') ? new EnhancedAIAnalyzer() : new AIAnalyzer();
        $pairs = $db->fetchAll("SELECT symbol FROM trading_pairs WHERE enabled = 1");
        $processedPairs = 0;
        $maxPairs = 10; // Limit to prevent timeouts
        
        foreach ($pairs as $pair) {
            if ($processedPairs >= $maxPairs) {
                echo "[" . date('Y-m-d H:i:s') . "] Reached maximum pairs limit ({$maxPairs}) to prevent timeout\n";
                break;
            }
            
            try {
                $startTime = microtime(true);
                
                if (method_exists($ai, 'analyzeSymbolEnhanced')) {
                    $analysis = $ai->analyzeSymbolEnhanced($pair['symbol'], 'BOTH');
                    echo "[" . date('Y-m-d H:i:s') . "] Generated " . $analysis['signal'] . " signal for " . $pair['symbol'] . " (Confidence: " . ($analysis['confidence'] * 100) . "%, Strength: " . $analysis['strength'] . ")\n";
                } else {
                    $analysis = $ai->analyzeSymbol($pair['symbol']);
                    echo "[" . date('Y-m-d H:i:s') . "] Generated " . $analysis['signal'] . " signal for " . $pair['symbol'] . " (Confidence: " . ($analysis['confidence'] * 100) . "%)\n";
                }
                
                $analysisTime = microtime(true) - $startTime;
                if ($analysisTime > 30) {
                    echo "[" . date('Y-m-d H:i:s') . "] Warning: Analysis for " . $pair['symbol'] . " took {$analysisTime}s\n";
                }
                
            } catch (Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Error generating signal for " . $pair['symbol'] . ": " . $e->getMessage() . "\n";
                
                // Skip problematic symbols
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
    
    echo "[" . date('Y-m-d H:i:s') . "] Trading bot completed successfully.\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Trading bot error: " . $e->getMessage() . "\n";
    
    // Log the error
    error_log("Trading bot cron error: " . $e->getMessage());
    
    exit(1);
}