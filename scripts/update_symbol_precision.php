#!/usr/bin/env php
<?php
/**
 * Update Symbol Precision Script
 * Fetches symbol precision data from Binance and updates the database
 */

require_once '../config/app.php';

echo "=== Updating Symbol Precision Data ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance();
    $binance = new BinanceAPI();
    
    echo "1. Fetching exchange info from Binance...\n";
    
    // Get exchange info for futures
    $futuresExchangeInfo = $binance->makeRequest('/fapi/v1/exchangeInfo');
    
    if (!$futuresExchangeInfo || !isset($futuresExchangeInfo['symbols'])) {
        throw new Exception('Failed to get futures exchange info from Binance');
    }
    
    $futuresSymbols = $futuresExchangeInfo['symbols'];
    echo "   Found " . count($futuresSymbols) . " futures symbols\n";
    
    // Get exchange info for spot
    $spotAPI = new SpotTradingAPI();
    try {
        $spotExchangeInfo = $spotAPI->makeSpotRequest('/api/v3/exchangeInfo');
        $spotSymbols = $spotExchangeInfo['symbols'] ?? [];
        echo "   Found " . count($spotSymbols) . " spot symbols\n";
    } catch (Exception $e) {
        echo "   Warning: Could not get spot exchange info: " . $e->getMessage() . "\n";
        $spotSymbols = [];
    }
    
    echo "2. Updating symbol precision data...\n";
    
    $updated = 0;
    $errors = 0;
    
    // Update futures symbols
    foreach ($futuresSymbols as $symbol) {
        if (!isset($symbol['symbol']) || !isset($symbol['filters'])) {
            continue;
        }
        
        $symbolName = $symbol['symbol'];
        
        // Extract precision data from filters
        $minQty = 0;
        $maxQty = 0;
        $stepSize = 0;
        $tickSize = 0;
        $minNotional = 0;
        
        foreach ($symbol['filters'] as $filter) {
            switch ($filter['filterType']) {
                case 'LOT_SIZE':
                    $minQty = (float)($filter['minQty'] ?? 0);
                    $maxQty = (float)($filter['maxQty'] ?? 0);
                    $stepSize = (float)($filter['stepSize'] ?? 0);
                    break;
                case 'PRICE_FILTER':
                    $tickSize = (float)($filter['tickSize'] ?? 0);
                    break;
                case 'MIN_NOTIONAL':
                    $minNotional = (float)($filter['minNotional'] ?? 0);
                    break;
            }
        }
        
        // Update database
        try {
            // Check if columns exist first
            $columns = $db->fetchAll("SHOW COLUMNS FROM trading_pairs");
            $columnNames = array_column($columns, 'Field');
            
            $updateData = [];
            if (in_array('min_qty', $columnNames)) $updateData['min_qty'] = $minQty;
            if (in_array('max_qty', $columnNames)) $updateData['max_qty'] = $maxQty;
            if (in_array('step_size', $columnNames)) $updateData['step_size'] = $stepSize;
            if (in_array('tick_size', $columnNames)) $updateData['tick_size'] = $tickSize;
            if (in_array('min_notional', $columnNames)) $updateData['min_notional'] = $minNotional;
            
            if (!empty($updateData)) {
                $db->update('trading_pairs', $updateData, 'symbol = ?', [$symbolName]);
                $updated++;
            }
            
        } catch (Exception $e) {
            echo "   Error updating {$symbolName}: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    // Update spot symbols (if available)
    foreach ($spotSymbols as $symbol) {
        if (!isset($symbol['symbol']) || !isset($symbol['filters'])) {
            continue;
        }
        
        $symbolName = $symbol['symbol'];
        
        // Check if this symbol exists in our trading pairs
        $existingPair = $db->fetchOne("SELECT id FROM trading_pairs WHERE symbol = ?", [$symbolName]);
        if (!$existingPair) {
            continue; // Skip symbols not in our trading pairs
        }
        
        // Extract precision data from filters
        $minQty = 0;
        $maxQty = 0;
        $stepSize = 0;
        $tickSize = 0;
        $minNotional = 0;
        
        foreach ($symbol['filters'] as $filter) {
            switch ($filter['filterType']) {
                case 'LOT_SIZE':
                    $minQty = (float)($filter['minQty'] ?? 0);
                    $maxQty = (float)($filter['maxQty'] ?? 0);
                    $stepSize = (float)($filter['stepSize'] ?? 0);
                    break;
                case 'PRICE_FILTER':
                    $tickSize = (float)($filter['tickSize'] ?? 0);
                    break;
                case 'MIN_NOTIONAL':
                    $minNotional = (float)($filter['notional'] ?? $filter['minNotional'] ?? 0);
                    break;
            }
        }
        
        // Update database with spot-specific precision (usually more restrictive)
        try {
            $currentData = $db->fetchOne("SELECT min_qty, step_size, tick_size, min_notional FROM trading_pairs WHERE symbol = ?", [$symbolName]);
            
            $updateData = [];
            if ($currentData) {
                // Use more restrictive values between spot and futures
                if ($minQty > 0) $updateData['min_qty'] = max($minQty, (float)($currentData['min_qty'] ?? 0));
                if ($stepSize > 0) $updateData['step_size'] = $stepSize; // Use spot step size
                if ($tickSize > 0) $updateData['tick_size'] = min($tickSize, (float)($currentData['tick_size'] ?? $tickSize));
                if ($minNotional > 0) $updateData['min_notional'] = max($minNotional, (float)($currentData['min_notional'] ?? 0));
                
                if (!empty($updateData)) {
                    $db->update('trading_pairs', $updateData, 'symbol = ?', [$symbolName]);
                }
            }
            
        } catch (Exception $e) {
            echo "   Error updating spot precision for {$symbolName}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "3. Adding manual precision overrides for problematic symbols...\n";
    
    // Enhanced manual overrides for known problematic symbols
    $manualOverrides = [
        'OMUSDT' => ['step_size' => 1.0, 'min_qty' => 1.0, 'tick_size' => 0.0001],
        'SHIBUSDT' => ['step_size' => 1000000.0, 'min_qty' => 1000000.0, 'tick_size' => 0.00000001],
        'PEPEUSDT' => ['step_size' => 1000000.0, 'min_qty' => 1000000.0, 'tick_size' => 0.0000000001],
        'FLOKIUSDT' => ['step_size' => 100000.0, 'min_qty' => 100000.0, 'tick_size' => 0.00000001],
        'DOGEUSDT' => ['step_size' => 1.0, 'min_qty' => 1.0, 'tick_size' => 0.00001],
        '1000BONKUSDT' => ['step_size' => 1000.0, 'min_qty' => 1000.0, 'tick_size' => 0.0000001],
        '1000RATSUSDT' => ['step_size' => 1000.0, 'min_qty' => 1000.0, 'tick_size' => 0.0000001],
        'BONDUSDT' => ['step_size' => 0.01, 'min_qty' => 0.01, 'tick_size' => 0.0001],
        'BROCCOLIF3BUSDT' => ['step_size' => 1000.0, 'min_qty' => 1000.0, 'tick_size' => 0.000001],
        'JSTUSDT' => ['step_size' => 1.0, 'min_qty' => 1.0, 'tick_size' => 0.00001]
    ];
    
    foreach ($manualOverrides as $symbolName => $overrides) {
        $existingPair = $db->fetchOne("SELECT id FROM trading_pairs WHERE symbol = ?", [$symbolName]);
        if ($existingPair) {
            try {
                $db->update('trading_pairs', $overrides, 'symbol = ?', [$symbolName]);
                echo "   Applied manual override for {$symbolName}\n";
            } catch (Exception $e) {
                echo "   Error applying override for {$symbolName}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n=== Update Complete ===\n";
    echo "✅ Updated precision data for {$updated} symbols\n";
    if ($errors > 0) {
        echo "⚠️  {$errors} errors encountered\n";
    }
    echo "🔧 Manual overrides applied for problematic symbols\n";
    
    // Log the update
    $db->insert('system_logs', [
        'level' => 'INFO',
        'category' => 'SYSTEM',
        'message' => "[SYMBOL_PRECISION] Updated symbol precision data from Binance",
        'context' => json_encode([
            'futures_symbols_processed' => count($futuresSymbols),
            'spot_symbols_processed' => count($spotSymbols),
            'symbols_updated' => $updated,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ])
    ]);
    
} catch (Exception $e) {
    echo "❌ Update failed: " . $e->getMessage() . "\n";
    
    // Log the error
    try {
        $db = Database::getInstance();
        $db->insert('system_logs', [
            'level' => 'ERROR',
            'category' => 'SYSTEM',
            'message' => "[SYMBOL_PRECISION] Failed to update symbol precision: " . $e->getMessage(),
            'context' => json_encode([
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ])
        ]);
    } catch (Exception $logError) {
        echo "Failed to log error: " . $logError->getMessage() . "\n";
    }
    
    exit(1);
}
?>