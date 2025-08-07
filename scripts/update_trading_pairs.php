<?php
/**
 * Update Trading Pairs Script
 * Fetches top 200 trading symbols from Binance and updates the database
 */

require_once '../config/app.php';

echo "=== Updating Trading Pairs ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance();
    $binance = new BinanceAPI();
    
    echo "1. Fetching exchange info from Binance...\n";
    
    // Get exchange info to get all available symbols
    $exchangeInfo = $binance->makeRequest('/fapi/v1/exchangeInfo');
    
    if (!$exchangeInfo || !isset($exchangeInfo['symbols'])) {
        throw new Exception('Failed to get exchange info from Binance');
    }
    
    $symbols = $exchangeInfo['symbols'];
    echo "   Found " . count($symbols) . " symbols from Binance\n";
    
    // Filter for USDT pairs and active symbols
    $usdtPairs = [];
    foreach ($symbols as $symbol) {
        if (isset($symbol['symbol']) && 
            isset($symbol['quoteAsset']) && 
            $symbol['quoteAsset'] === 'USDT' &&
            isset($symbol['status']) && 
            $symbol['status'] === 'TRADING') {
            
            $usdtPairs[] = [
                'symbol' => $symbol['symbol'],
                'baseAsset' => $symbol['baseAsset'],
                'quoteAsset' => $symbol['quoteAsset'],
                'status' => $symbol['status']
            ];
        }
    }
    
    echo "   Filtered to " . count($usdtPairs) . " USDT trading pairs\n";
    
    // Get 24hr ticker data to sort by volume
    echo "2. Getting 24hr ticker data for volume sorting...\n";
    $tickers = $binance->get24hrTicker();
    
    if (!$tickers) {
        throw new Exception('Failed to get ticker data from Binance');
    }
    
    // Create volume map
    $volumeMap = [];
    foreach ($tickers as $ticker) {
        if (isset($ticker['symbol']) && isset($ticker['volume'])) {
            $volumeMap[$ticker['symbol']] = (float)$ticker['volume'];
        }
    }
    
    // Add volume to pairs and sort
    foreach ($usdtPairs as &$pair) {
        $pair['volume'] = $volumeMap[$pair['symbol']] ?? 0;
    }
    
    // Sort by volume (highest first)
    usort($usdtPairs, function($a, $b) {
        return $b['volume'] <=> $a['volume'];
    });
    
    // Take top 200
    $top200Pairs = array_slice($usdtPairs, 0, 200);
    
    echo "   Selected top 200 pairs by volume\n";
    
    echo "3. Updating database...\n";
    
    // Get existing pairs
    $existingPairs = $db->fetchAll("SELECT symbol FROM trading_pairs");
    $existingSymbols = array_column($existingPairs, 'symbol');
    
    $added = 0;
    $updated = 0;
    
    foreach ($top200Pairs as $pair) {
        $symbol = $pair['symbol'];
        $baseAsset = $pair['baseAsset'];
        $quoteAsset = $pair['quoteAsset'];
        
        if (in_array($symbol, $existingSymbols)) {
            // Update existing pair
            $db->update('trading_pairs', [
                'base_asset' => $baseAsset,
                'quote_asset' => $quoteAsset,
                'trading_type' => 'BOTH',
                'volatility_score' => round($pair['volume'] / 1000000, 2) // Simple volatility score based on volume
            ], 'symbol = ?', [$symbol]);
            $updated++;
        } else {
            // Add new pair (disabled by default)
            $db->insert('trading_pairs', [
                'symbol' => $symbol,
                'base_asset' => $baseAsset,
                'quote_asset' => $quoteAsset,
                'trading_type' => 'BOTH',
                'enabled' => 0, // Disabled by default for safety
                'leverage' => 10,
                'margin_type' => 'ISOLATED',
                'ai_priority' => 1,
                'volatility_score' => round($pair['volume'] / 1000000, 2)
            ]);
            $added++;
        }
    }
    
    echo "   Added {$added} new trading pairs\n";
    echo "   Updated {$updated} existing trading pairs\n";
    
    // Log the update
    $db->insert('system_logs', [
        'level' => 'INFO',
        'category' => 'SYSTEM',
        'message' => "[TRADING_PAIRS] Updated trading pairs database with top 200 symbols from Binance",
        'context' => json_encode([
            'total_symbols_fetched' => count($symbols),
            'usdt_pairs_found' => count($usdtPairs),
            'top_pairs_selected' => count($top200Pairs),
            'pairs_added' => $added,
            'pairs_updated' => $updated,
            'timestamp' => date('Y-m-d H:i:s')
        ])
    ]);
    
    echo "\n4. Top 10 pairs by volume:\n";
    for ($i = 0; $i < min(10, count($top200Pairs)); $i++) {
        $pair = $top200Pairs[$i];
        echo "   " . ($i + 1) . ". {$pair['symbol']} - Volume: " . number_format($pair['volume']) . "\n";
    }
    
    echo "\n=== Update Complete ===\n";
    echo "✅ Successfully updated trading pairs database!\n";
    echo "📊 Total pairs in database: " . ($added + $updated) . "\n";
    echo "⚠️  New pairs are disabled by default for safety\n";
    echo "🔧 Enable pairs manually in the admin panel\n";
    
} catch (Exception $e) {
    echo "❌ Update failed: " . $e->getMessage() . "\n";
    
    // Log the error
    try {
        $db = Database::getInstance();
        $db->insert('system_logs', [
            'level' => 'ERROR',
            'category' => 'SYSTEM',
            'message' => "[TRADING_PAIRS] Failed to update trading pairs: " . $e->getMessage(),
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