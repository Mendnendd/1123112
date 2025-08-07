-- Enhanced Binance AI Trader Database Schema
-- Updated: 2025-01-27
-- Includes Spot Trading, Enhanced AI, and Improved Dashboard

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Admin Users Table (Enhanced)
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('ADMIN','TRADER','VIEWER') DEFAULT 'ADMIN',
  `permissions` text DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(32) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enhanced Trading Settings Table
CREATE TABLE IF NOT EXISTS `trading_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `binance_api_key` varchar(255) DEFAULT NULL,
  `binance_api_secret` text DEFAULT NULL,
  `testnet_mode` tinyint(1) DEFAULT 1,
  `trading_enabled` tinyint(1) DEFAULT 0,
  `ai_enabled` tinyint(1) DEFAULT 1,
  `spot_trading_enabled` tinyint(1) DEFAULT 1,
  `futures_trading_enabled` tinyint(1) DEFAULT 1,
  `max_position_size` decimal(15,8) DEFAULT 100.00000000,
  `max_spot_position_size` decimal(15,8) DEFAULT 50.00000000,
  `risk_percentage` decimal(5,2) DEFAULT 2.00,
  `stop_loss_percentage` decimal(5,2) DEFAULT 5.00,
  `take_profit_percentage` decimal(5,2) DEFAULT 10.00,
  `leverage` int(11) DEFAULT 10,
  `margin_type` enum('ISOLATED','CROSSED') DEFAULT 'ISOLATED',
  `ai_confidence_threshold` decimal(3,2) DEFAULT 0.75,
  `max_daily_trades` int(11) DEFAULT 20,
  `max_concurrent_positions` int(11) DEFAULT 5,
  `emergency_stop` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert enhanced default settings
INSERT IGNORE INTO `trading_settings` (`id`, `spot_trading_enabled`, `futures_trading_enabled`) VALUES (1, 1, 1);

-- Enhanced Trading Pairs Table
CREATE TABLE IF NOT EXISTS `trading_pairs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(20) NOT NULL,
  `base_asset` varchar(10) NOT NULL,
  `quote_asset` varchar(10) NOT NULL,
  `trading_type` enum('SPOT','FUTURES','BOTH') DEFAULT 'BOTH',
  `enabled` tinyint(1) DEFAULT 1,
  `leverage` int(11) DEFAULT 10,
  `margin_type` enum('ISOLATED','CROSSED') DEFAULT 'ISOLATED',
  `min_notional` decimal(15,8) DEFAULT 0.00000000,
  `tick_size` decimal(15,8) DEFAULT 0.00000000,
  `step_size` decimal(15,8) DEFAULT 0.00000000,
  `min_qty` decimal(15,8) DEFAULT 0.00000000,
  `max_qty` decimal(15,8) DEFAULT 0.00000000,
  `ai_priority` int(11) DEFAULT 1,
  `volatility_score` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol` (`symbol`),
  KEY `trading_type` (`trading_type`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert enhanced default trading pairs
INSERT IGNORE INTO `trading_pairs` (`symbol`, `base_asset`, `quote_asset`, `trading_type`, `enabled`) VALUES
('BTCUSDT', 'BTC', 'USDT', 'BOTH', 1),
('ETHUSDT', 'ETH', 'USDT', 'BOTH', 1),
('BNBUSDT', 'BNB', 'USDT', 'BOTH', 1),
('ADAUSDT', 'ADA', 'USDT', 'BOTH', 1),
('DOTUSDT', 'DOT', 'USDT', 'BOTH', 1),
('LINKUSDT', 'LINK', 'USDT', 'BOTH', 1),
('LTCUSDT', 'LTC', 'USDT', 'BOTH', 1),
('XRPUSDT', 'XRP', 'USDT', 'BOTH', 1),
('SOLUSDT', 'SOL', 'USDT', 'BOTH', 1),
('AVAXUSDT', 'AVAX', 'USDT', 'BOTH', 1);

-- Enhanced Trading History Table
CREATE TABLE IF NOT EXISTS `trading_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(20) NOT NULL,
  `trading_type` enum('SPOT','FUTURES') NOT NULL DEFAULT 'FUTURES',
  `side` enum('BUY','SELL') NOT NULL,
  `type` enum('MARKET','LIMIT','STOP','STOP_MARKET','OCO') NOT NULL,
  `quantity` decimal(15,8) NOT NULL,
  `price` decimal(15,8) DEFAULT NULL,
  `executed_price` decimal(15,8) DEFAULT NULL,
  `executed_qty` decimal(15,8) DEFAULT NULL,
  `status` enum('NEW','PARTIALLY_FILLED','FILLED','CANCELED','REJECTED','EXPIRED') NOT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `client_order_id` varchar(50) DEFAULT NULL,
  `commission` decimal(15,8) DEFAULT 0.00000000,
  `commission_asset` varchar(10) DEFAULT NULL,
  `profit_loss` decimal(15,8) DEFAULT 0.00000000,
  `profit_loss_percentage` decimal(8,4) DEFAULT 0.0000,
  `ai_signal_id` int(11) DEFAULT NULL,
  `strategy_used` varchar(50) DEFAULT NULL,
  `confidence_score` decimal(5,3) DEFAULT NULL,
  `market_conditions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `symbol` (`symbol`),
  KEY `trading_type` (`trading_type`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `ai_signal_id` (`ai_signal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enhanced AI Signals Table
CREATE TABLE IF NOT EXISTS `ai_signals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(20) NOT NULL,
  `trading_type` enum('SPOT','FUTURES','BOTH') DEFAULT 'BOTH',
  `signal` enum('BUY','SELL','HOLD','STRONG_BUY','STRONG_SELL') NOT NULL,
  `confidence` decimal(5,3) NOT NULL,
  `strength` enum('WEAK','MODERATE','STRONG','VERY_STRONG') DEFAULT 'MODERATE',
  `price` decimal(15,8) NOT NULL,
  `target_price` decimal(15,8) DEFAULT NULL,
  `stop_loss_price` decimal(15,8) DEFAULT NULL,
  `time_horizon` enum('SHORT','MEDIUM','LONG') DEFAULT 'SHORT',
  `rsi` decimal(5,2) DEFAULT NULL,
  `macd` decimal(15,8) DEFAULT NULL,
  `macd_signal` decimal(15,8) DEFAULT NULL,
  `macd_histogram` decimal(15,8) DEFAULT NULL,
  `bb_upper` decimal(15,8) DEFAULT NULL,
  `bb_middle` decimal(15,8) DEFAULT NULL,
  `bb_lower` decimal(15,8) DEFAULT NULL,
  `sma_20` decimal(15,8) DEFAULT NULL,
  `sma_50` decimal(15,8) DEFAULT NULL,
  `sma_200` decimal(15,8) DEFAULT NULL,
  `ema_12` decimal(15,8) DEFAULT NULL,
  `ema_26` decimal(15,8) DEFAULT NULL,
  `volume` decimal(15,8) DEFAULT NULL,
  `volume_avg` decimal(15,8) DEFAULT NULL,
  `volume_ratio` decimal(8,4) DEFAULT NULL,
  `price_change_24h` decimal(5,2) DEFAULT NULL,
  `volatility` decimal(8,4) DEFAULT NULL,
  `market_sentiment` enum('BEARISH','NEUTRAL','BULLISH') DEFAULT 'NEUTRAL',
  `analysis_score` int(11) DEFAULT NULL,
  `technical_score` int(11) DEFAULT NULL,
  `momentum_score` int(11) DEFAULT NULL,
  `trend_score` int(11) DEFAULT NULL,
  `volume_score` int(11) DEFAULT NULL,
  `indicators_data` text DEFAULT NULL,
  `market_analysis` text DEFAULT NULL,
  `risk_assessment` text DEFAULT NULL,
  `executed` tinyint(1) DEFAULT 0,
  `execution_price` decimal(15,8) DEFAULT NULL,
  `execution_time` timestamp NULL DEFAULT NULL,
  `performance_tracked` tinyint(1) DEFAULT 0,
  `actual_outcome` enum('PROFIT','LOSS','BREAKEVEN') DEFAULT NULL,
  `actual_return` decimal(8,4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `symbol` (`symbol`),
  KEY `trading_type` (`trading_type`),
  KEY `signal` (`signal`),
  KEY `confidence` (`confidence`),
  KEY `strength` (`strength`),
  KEY `created_at` (`created_at`),
  KEY `executed` (`executed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Spot Balances Table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enhanced Balance History Table
CREATE TABLE IF NOT EXISTS `balance_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_type` enum('SPOT','FUTURES','COMBINED') DEFAULT 'COMBINED',
  `total_wallet_balance` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `total_unrealized_pnl` decimal(20,8) DEFAULT 0.00000000,
  `total_margin_balance` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `total_position_initial_margin` decimal(20,8) DEFAULT 0.00000000,
  `total_open_order_initial_margin` decimal(20,8) DEFAULT 0.00000000,
  `available_balance` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `max_withdraw_amount` decimal(20,8) DEFAULT 0.00000000,
  `spot_balance_usdt` decimal(20,8) DEFAULT 0.00000000,
  `futures_balance_usdt` decimal(20,8) DEFAULT 0.00000000,
  `total_portfolio_value` decimal(20,8) DEFAULT 0.00000000,
  `daily_pnl` decimal(20,8) DEFAULT 0.00000000,
  `daily_pnl_percentage` decimal(8,4) DEFAULT 0.0000,
  `assets_data` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `account_type` (`account_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enhanced Positions Table
CREATE TABLE IF NOT EXISTS `positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(20) NOT NULL,
  `trading_type` enum('SPOT','FUTURES') NOT NULL DEFAULT 'FUTURES',
  `position_amt` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `entry_price` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `mark_price` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `unrealized_pnl` decimal(20,8) DEFAULT 0.00000000,
  `percentage` decimal(8,4) DEFAULT 0.0000,
  `side` enum('LONG','SHORT','BOTH') NOT NULL,
  `leverage` int(11) DEFAULT 1,
  `margin_type` enum('ISOLATED','CROSSED') DEFAULT 'ISOLATED',
  `isolated_margin` decimal(20,8) DEFAULT 0.00000000,
  `position_initial_margin` decimal(20,8) DEFAULT 0.00000000,
  `open_order_initial_margin` decimal(20,8) DEFAULT 0.00000000,
  `position_value` decimal(20,8) DEFAULT 0.00000000,
  `liquidation_price` decimal(20,8) DEFAULT 0.00000000,
  `risk_level` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'LOW',
  `stop_loss_price` decimal(20,8) DEFAULT NULL,
  `take_profit_price` decimal(20,8) DEFAULT NULL,
  `entry_time` timestamp NULL DEFAULT NULL,
  `ai_signal_id` int(11) DEFAULT NULL,
  `strategy_used` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol_type` (`symbol`, `trading_type`),
  KEY `trading_type` (`trading_type`),
  KEY `side` (`side`),
  KEY `risk_level` (`risk_level`),
  KEY `updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- AI Strategy Performance Table
CREATE TABLE IF NOT EXISTS `ai_strategy_performance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `strategy_name` varchar(50) NOT NULL,
  `symbol` varchar(20) DEFAULT NULL,
  `trading_type` enum('SPOT','FUTURES','BOTH') DEFAULT 'BOTH',
  `total_signals` int(11) DEFAULT 0,
  `executed_signals` int(11) DEFAULT 0,
  `profitable_signals` int(11) DEFAULT 0,
  `win_rate` decimal(5,2) DEFAULT 0.00,
  `total_return` decimal(15,8) DEFAULT 0.00000000,
  `total_return_percentage` decimal(8,4) DEFAULT 0.0000,
  `avg_return_per_trade` decimal(8,4) DEFAULT 0.0000,
  `max_drawdown` decimal(8,4) DEFAULT 0.0000,
  `sharpe_ratio` decimal(8,4) DEFAULT 0.0000,
  `avg_confidence` decimal(5,3) DEFAULT 0.000,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `strategy_symbol_type` (`strategy_name`, `symbol`, `trading_type`),
  KEY `strategy_name` (`strategy_name`),
  KEY `trading_type` (`trading_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Market Data Cache Table
CREATE TABLE IF NOT EXISTS `market_data_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(20) NOT NULL,
  `data_type` enum('KLINES','TICKER','DEPTH','TRADES','ENHANCED_TICKER') NOT NULL,
  `timeframe` varchar(10) DEFAULT NULL,
  `data` longtext NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol_type_timeframe` (`symbol`, `data_type`, `timeframe`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enhanced System Logs Table
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` enum('DEBUG','INFO','WARNING','ERROR','CRITICAL') NOT NULL,
  `category` enum('SYSTEM','TRADING','AI','API','SECURITY','USER') DEFAULT 'SYSTEM',
  `message` text NOT NULL,
  `context` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_id` varchar(36) DEFAULT NULL,
  `execution_time` decimal(8,4) DEFAULT NULL,
  `memory_usage` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `level` (`level`),
  KEY `category` (`category`),
  KEY `created_at` (`created_at`),
  KEY `user_id` (`user_id`),
  KEY `request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- API Rate Limits Table (Enhanced)
CREATE TABLE IF NOT EXISTS `api_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `endpoint` varchar(100) NOT NULL,
  `api_type` enum('SPOT','FUTURES','GENERAL') DEFAULT 'GENERAL',
  `requests_count` int(11) DEFAULT 0,
  `weight_used` int(11) DEFAULT 0,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_request` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rate_limit_exceeded` tinyint(1) DEFAULT 0,
  `reset_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint_type` (`endpoint`, `api_type`),
  KEY `window_start` (`window_start`),
  KEY `rate_limit_exceeded` (`rate_limit_exceeded`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enhanced Performance Metrics Table
CREATE TABLE IF NOT EXISTS `performance_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `account_type` enum('SPOT','FUTURES','COMBINED') DEFAULT 'COMBINED',
  `starting_balance` decimal(20,8) DEFAULT 0.00000000,
  `ending_balance` decimal(20,8) DEFAULT 0.00000000,
  `total_trades` int(11) DEFAULT 0,
  `spot_trades` int(11) DEFAULT 0,
  `futures_trades` int(11) DEFAULT 0,
  `winning_trades` int(11) DEFAULT 0,
  `losing_trades` int(11) DEFAULT 0,
  `win_rate` decimal(5,2) DEFAULT 0.00,
  `total_pnl` decimal(20,8) DEFAULT 0.00000000,
  `spot_pnl` decimal(20,8) DEFAULT 0.00000000,
  `futures_pnl` decimal(20,8) DEFAULT 0.00000000,
  `max_drawdown` decimal(20,8) DEFAULT 0.00000000,
  `max_drawdown_percentage` decimal(8,4) DEFAULT 0.0000,
  `sharpe_ratio` decimal(8,4) DEFAULT 0.0000,
  `total_volume` decimal(20,8) DEFAULT 0.00000000,
  `avg_trade_duration` int(11) DEFAULT 0,
  `best_trade` decimal(20,8) DEFAULT 0.00000000,
  `worst_trade` decimal(20,8) DEFAULT 0.00000000,
  `ai_signals_generated` int(11) DEFAULT 0,
  `ai_signals_executed` int(11) DEFAULT 0,
  `ai_success_rate` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `date_type` (`date`, `account_type`),
  KEY `account_type` (`account_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('INFO','SUCCESS','WARNING','ERROR','TRADE','SIGNAL') NOT NULL DEFAULT 'INFO',
  `category` enum('SYSTEM','TRADING','AI','SECURITY','PERFORMANCE') DEFAULT 'SYSTEM',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` text DEFAULT NULL,
  `priority` enum('LOW','NORMAL','HIGH','URGENT') DEFAULT 'NORMAL',
  `read_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `category` (`category`),
  KEY `priority` (`priority`),
  KEY `created_at` (`created_at`),
  KEY `read_at` (`read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Trading Strategies Table
CREATE TABLE IF NOT EXISTS `trading_strategies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `strategy_type` enum('SCALPING','DAY_TRADING','SWING','POSITION') DEFAULT 'DAY_TRADING',
  `trading_type` enum('SPOT','FUTURES','BOTH') DEFAULT 'BOTH',
  `enabled` tinyint(1) DEFAULT 1,
  `risk_level` enum('LOW','MEDIUM','HIGH') DEFAULT 'MEDIUM',
  `min_confidence` decimal(3,2) DEFAULT 0.70,
  `max_position_size` decimal(15,8) DEFAULT 100.00000000,
  `stop_loss_percentage` decimal(5,2) DEFAULT 5.00,
  `take_profit_percentage` decimal(5,2) DEFAULT 10.00,
  `indicators_config` text DEFAULT NULL,
  `entry_conditions` text DEFAULT NULL,
  `exit_conditions` text DEFAULT NULL,
  `backtest_results` text DEFAULT NULL,
  `performance_metrics` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `strategy_type` (`strategy_type`),
  KEY `trading_type` (`trading_type`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default trading strategies
INSERT IGNORE INTO `trading_strategies` (`name`, `description`, `strategy_type`, `trading_type`, `min_confidence`) VALUES
('AI Momentum', 'AI-driven momentum trading strategy', 'DAY_TRADING', 'BOTH', 0.75),
('Mean Reversion', 'Mean reversion strategy using Bollinger Bands', 'SWING', 'BOTH', 0.70),
('Trend Following', 'Long-term trend following strategy', 'POSITION', 'BOTH', 0.80),
('Scalping Pro', 'High-frequency scalping strategy', 'SCALPING', 'FUTURES', 0.85);

-- User Sessions Table
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `last_activity` (`last_activity`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Backup Logs Table
CREATE TABLE IF NOT EXISTS `backup_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_type` enum('DATABASE','FILES','FULL') NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `status` enum('STARTED','COMPLETED','FAILED') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backup_type` (`backup_type`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_trading_history_symbol_type` ON `trading_history` (`symbol`, `trading_type`, `created_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_ai_signals_symbol_confidence` ON `ai_signals` (`symbol`, `confidence` DESC, `created_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_positions_type_side` ON `positions` (`trading_type`, `side`, `updated_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_balance_history_date` ON `balance_history` (`created_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_system_logs_category_level` ON `system_logs` (`category`, `level`, `created_at` DESC);

-- Create views for dashboard
CREATE OR REPLACE VIEW `dashboard_summary` AS
SELECT 
    (SELECT COUNT(*) FROM positions WHERE position_amt != 0) as active_positions,
    (SELECT COUNT(*) FROM trading_history WHERE DATE(created_at) = CURDATE()) as today_trades,
    (SELECT SUM(profit_loss) FROM trading_history WHERE DATE(created_at) = CURDATE()) as today_pnl,
    (SELECT COUNT(*) FROM ai_signals WHERE DATE(created_at) = CURDATE()) as today_signals,
    (SELECT AVG(confidence) FROM ai_signals WHERE DATE(created_at) = CURDATE()) as avg_confidence,
    (SELECT total_portfolio_value FROM balance_history ORDER BY created_at DESC LIMIT 1) as portfolio_value,
    (SELECT daily_pnl FROM balance_history ORDER BY created_at DESC LIMIT 1) as daily_pnl,
    (SELECT COUNT(*) FROM system_logs WHERE level IN ('ERROR', 'CRITICAL') AND DATE(created_at) = CURDATE()) as error_count;

CREATE OR REPLACE VIEW `portfolio_overview` AS
SELECT 
    bh.total_portfolio_value,
    bh.spot_balance_usdt,
    bh.futures_balance_usdt,
    bh.total_unrealized_pnl,
    bh.daily_pnl,
    bh.daily_pnl_percentage,
    COUNT(p.id) as active_positions,
    COALESCE(SUM(CASE WHEN p.trading_type = 'SPOT' THEN p.position_value ELSE 0 END), 0) as spot_position_value,
    COALESCE(SUM(CASE WHEN p.trading_type = 'FUTURES' THEN p.position_value ELSE 0 END), 0) as futures_position_value,
    bh.created_at as last_updated
FROM balance_history bh
LEFT JOIN positions p ON p.position_amt != 0
WHERE bh.id = (SELECT MAX(id) FROM balance_history)
GROUP BY bh.id;