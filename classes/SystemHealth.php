<?php

class SystemHealth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getSystemStatus() {
        $status = [
            'overall' => 'healthy',
            'database' => $this->checkDatabase(),
            'api' => $this->checkBinanceAPI(),
            'files' => $this->checkFileSystem(),
            'memory' => $this->checkMemoryUsage(),
            'disk' => $this->checkDiskSpace(),
            'logs' => $this->checkLogFiles()
        ];
        
        // Determine overall status
        $issues = 0;
        foreach ($status as $key => $value) {
            if ($key !== 'overall' && is_array($value) && $value['status'] !== 'ok') {
                $issues++;
            }
        }
        
        if ($issues > 2) {
            $status['overall'] = 'critical';
        } elseif ($issues > 0) {
            $status['overall'] = 'warning';
        }
        
        return $status;
    }
    
    private function checkDatabase() {
        try {
            $this->db->query("SELECT 1");
            
            // Check table counts
            $tables = [
                'admin_users', 'trading_settings', 'trading_pairs', 
                'trading_history', 'ai_signals', 'positions', 'system_logs'
            ];
            
            $tableCounts = [];
            foreach ($tables as $table) {
                try {
                    $count = $this->db->fetchOne("SELECT COUNT(*) as count FROM {$table}")['count'];
                    $tableCounts[$table] = $count;
                } catch (Exception $e) {
                    return [
                        'status' => 'error',
                        'message' => "Table {$table} not accessible: " . $e->getMessage()
                    ];
                }
            }
            
            return [
                'status' => 'ok',
                'message' => 'Database connection healthy',
                'table_counts' => $tableCounts
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkBinanceAPI() {
        try {
            $binance = new BinanceAPI();
            $result = $binance->testConnection();
            
            if ($result['success']) {
                return [
                    'status' => 'ok',
                    'message' => 'Binance API connection successful'
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => 'Binance API connection failed: ' . $result['message']
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Binance API error: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkFileSystem() {
        $directories = [
            'config' => __DIR__ . '/../config',
            'logs' => __DIR__ . '/../logs',
            'classes' => __DIR__ . '/../classes',
            'admin' => __DIR__ . '/../admin',
            'assets' => __DIR__ . '/../assets'
        ];
        
        $issues = [];
        
        foreach ($directories as $name => $path) {
            if (!is_dir($path)) {
                $issues[] = "Directory missing: {$name}";
            } elseif (!is_readable($path)) {
                $issues[] = "Directory not readable: {$name}";
            }
        }
        
        // Check writable directories
        $writableDirs = ['logs', 'config'];
        foreach ($writableDirs as $dir) {
            $path = __DIR__ . "/../{$dir}";
            if (!is_writable($path)) {
                $issues[] = "Directory not writable: {$dir}";
            }
        }
        
        if (empty($issues)) {
            return [
                'status' => 'ok',
                'message' => 'File system healthy'
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'File system issues found',
                'issues' => $issues
            ];
        }
    }
    
    private function checkMemoryUsage() {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        // Convert memory limit to bytes
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        
        $usagePercent = ($memoryUsage / $memoryLimitBytes) * 100;
        
        $status = 'ok';
        if ($usagePercent > 80) {
            $status = 'critical';
        } elseif ($usagePercent > 60) {
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'message' => sprintf('Memory usage: %s / %s (%.1f%%)', 
                $this->formatBytes($memoryUsage), 
                $memoryLimit, 
                $usagePercent
            ),
            'usage_bytes' => $memoryUsage,
            'limit_bytes' => $memoryLimitBytes,
            'usage_percent' => $usagePercent
        ];
    }
    
    private function checkDiskSpace() {
        $path = __DIR__ . '/..';
        $freeBytes = disk_free_space($path);
        $totalBytes = disk_total_space($path);
        
        if ($freeBytes === false || $totalBytes === false) {
            return [
                'status' => 'error',
                'message' => 'Unable to check disk space'
            ];
        }
        
        $usedBytes = $totalBytes - $freeBytes;
        $usagePercent = ($usedBytes / $totalBytes) * 100;
        
        $status = 'ok';
        if ($usagePercent > 90) {
            $status = 'critical';
        } elseif ($usagePercent > 80) {
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'message' => sprintf('Disk usage: %s / %s (%.1f%%)', 
                $this->formatBytes($usedBytes), 
                $this->formatBytes($totalBytes), 
                $usagePercent
            ),
            'free_bytes' => $freeBytes,
            'total_bytes' => $totalBytes,
            'usage_percent' => $usagePercent
        ];
    }
    
    private function checkLogFiles() {
        $logDir = __DIR__ . '/../logs';
        $issues = [];
        
        if (!is_dir($logDir)) {
            return [
                'status' => 'error',
                'message' => 'Logs directory not found'
            ];
        }
        
        // Check error log
        $errorLog = $logDir . '/error.log';
        if (file_exists($errorLog)) {
            $size = filesize($errorLog);
            if ($size > 10 * 1024 * 1024) { // 10MB
                $issues[] = 'Error log file is large (' . $this->formatBytes($size) . ')';
            }
        }
        
        // Check log directory permissions
        if (!is_writable($logDir)) {
            $issues[] = 'Logs directory is not writable';
        }
        
        if (empty($issues)) {
            return [
                'status' => 'ok',
                'message' => 'Log files healthy'
            ];
        } else {
            return [
                'status' => 'warning',
                'message' => 'Log file issues found',
                'issues' => $issues
            ];
        }
    }
    
    private function convertToBytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    public function getRecentErrors($limit = 10) {
        return $this->db->fetchAll(
            "SELECT * FROM system_logs WHERE level IN ('ERROR', 'CRITICAL') ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }
    
    public function cleanupOldLogs($days = 30) {
        try {
            $deleted = $this->db->query(
                "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
            
            return [
                'success' => true,
                'message' => "Cleaned up logs older than {$days} days"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to cleanup logs: ' . $e->getMessage()
            ];
        }
    }
}