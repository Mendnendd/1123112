-- Database Fix Script
-- Run this script to fix common database issues

-- First, make sure we're using the correct database
-- Replace 'your_database_name' with your actual database name
-- USE `your_database_name`;

-- Fix missing columns in existing tables
-- Add missing columns to trading_settings if they don't exist
ALTER TABLE `trading_settings` 
ADD COLUMN IF NOT EXISTS `spot_trading_enabled` tinyint(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS `futures_trading_enabled` tinyint(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS `max_spot_position_size` decimal(15,8) DEFAULT 50.00000000,
ADD COLUMN IF NOT EXISTS `ai_confidence_threshold` decimal(3,2) DEFAULT 0.75,
ADD COLUMN IF NOT EXISTS `max_daily_trades` int(11) DEFAULT 20,
ADD COLUMN IF NOT EXISTS `max_concurrent_positions` int(11) DEFAULT 5,
ADD COLUMN IF NOT EXISTS `emergency_stop` tinyint(1) DEFAULT 0;

-- Add missing columns to trading_pairs if they don't exist
ALTER TABLE `trading_pairs`
ADD COLUMN IF NOT EXISTS `base_asset` varchar(10) DEFAULT 'BTC',
ADD COLUMN IF NOT EXISTS `quote_asset` varchar(10) DEFAULT 'USDT',
ADD COLUMN IF NOT EXISTS `trading_type` enum('SPOT','FUTURES','BOTH') DEFAULT 'BOTH',
ADD COLUMN IF NOT EXISTS `ai_priority` int(11) DEFAULT 1,
ADD COLUMN IF NOT EXISTS `volatility_score` decimal(5,2) DEFAULT 0.00;

-- Add missing columns to trading_history if they don't exist
ALTER TABLE `trading_history`
ADD COLUMN IF NOT EXISTS `trading_type` enum('SPOT','FUTURES') DEFAULT 'FUTURES',
ADD COLUMN IF NOT EXISTS `profit_loss_percentage` decimal(8,4) DEFAULT 0.0000,
ADD COLUMN IF NOT EXISTS `strategy_used` varchar(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `confidence_score` decimal(5,3) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `market_conditions` text DEFAULT NULL;

-- Add missing columns to ai_signals if they don't exist
ALTER TABLE `ai_signals`
ADD COLUMN IF NOT EXISTS `trading_type` enum('SPOT','FUTURES','BOTH') DEFAULT 'BOTH',
ADD COLUMN IF NOT EXISTS `strength` enum('WEAK','MODERATE','STRONG','VERY_STRONG') DEFAULT 'MODERATE',
ADD COLUMN IF NOT EXISTS `target_price` decimal(15,8) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `stop_loss_price` decimal(15,8) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `time_horizon` enum('SHORT','MEDIUM','LONG') DEFAULT 'SHORT',
ADD COLUMN IF NOT EXISTS `market_sentiment` enum('BEARISH','NEUTRAL','BULLISH') DEFAULT 'NEUTRAL',
ADD COLUMN IF NOT EXISTS `execution_time` timestamp NULL DEFAULT NULL;

-- Add missing columns to system_logs if they don't exist
ALTER TABLE `system_logs`
ADD COLUMN IF NOT EXISTS `category` enum('SYSTEM','TRADING','AI','API','SECURITY','USER') DEFAULT 'SYSTEM';

-- Update base_asset and quote_asset for existing pairs
UPDATE `trading_pairs` SET 
    `base_asset` = SUBSTRING(`symbol`, 1, LENGTH(`symbol`) - 4),
    `quote_asset` = RIGHT(`symbol`, 4)
WHERE `base_asset` IS NULL OR `base_asset` = '';

-- Create spot_balances table if it doesn't exist
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

-- Create notifications table if it doesn't exist
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

-- Create trading_strategies table if it doesn't exist
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

-- Update balance_history table structure
ALTER TABLE `balance_history`
ADD COLUMN IF NOT EXISTS `account_type` enum('SPOT','FUTURES','COMBINED') DEFAULT 'COMBINED',
ADD COLUMN IF NOT EXISTS `spot_balance_usdt` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `futures_balance_usdt` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `total_portfolio_value` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `daily_pnl` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `daily_pnl_percentage` decimal(8,4) DEFAULT 0.0000;

-- Update positions table structure
ALTER TABLE `positions`
ADD COLUMN IF NOT EXISTS `trading_type` enum('SPOT','FUTURES') DEFAULT 'FUTURES',
ADD COLUMN IF NOT EXISTS `position_value` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `liquidation_price` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `risk_level` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'LOW',
ADD COLUMN IF NOT EXISTS `stop_loss_price` decimal(20,8) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `take_profit_price` decimal(20,8) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `entry_time` timestamp NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `ai_signal_id` int(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `strategy_used` varchar(50) DEFAULT NULL;

-- Update performance_metrics table structure
ALTER TABLE `performance_metrics`
ADD COLUMN IF NOT EXISTS `account_type` enum('SPOT','FUTURES','COMBINED') DEFAULT 'COMBINED',
ADD COLUMN IF NOT EXISTS `starting_balance` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `ending_balance` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `spot_trades` int(11) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `futures_trades` int(11) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `spot_pnl` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `futures_pnl` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `max_drawdown_percentage` decimal(8,4) DEFAULT 0.0000,
ADD COLUMN IF NOT EXISTS `best_trade` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `worst_trade` decimal(20,8) DEFAULT 0.00000000,
ADD COLUMN IF NOT EXISTS `ai_signals_generated` int(11) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `ai_signals_executed` int(11) DEFAULT 0,
ADD COLUMN IF NOT EXISTS `ai_success_rate` decimal(5,2) DEFAULT 0.00;

-- Create dashboard views (will replace if they exist)
CREATE OR REPLACE VIEW `dashboard_summary` AS
SELECT 
    COALESCE((SELECT COUNT(*) FROM positions WHERE position_amt != 0), 0) as active_positions,
    COALESCE((SELECT COUNT(*) FROM trading_history WHERE DATE(created_at) = CURDATE()), 0) as today_trades,
    COALESCE((SELECT SUM(profit_loss) FROM trading_history WHERE DATE(created_at) = CURDATE()), 0) as today_pnl,
    COALESCE((SELECT COUNT(*) FROM ai_signals WHERE DATE(created_at) = CURDATE()), 0) as today_signals,
    COALESCE((SELECT AVG(confidence) FROM ai_signals WHERE DATE(created_at) = CURDATE()), 0) as avg_confidence,
    COALESCE((SELECT total_portfolio_value FROM balance_history ORDER BY created_at DESC LIMIT 1), 0) as portfolio_value,
    COALESCE((SELECT daily_pnl FROM balance_history ORDER BY created_at DESC LIMIT 1), 0) as daily_pnl,
    COALESCE((SELECT COUNT(*) FROM system_logs WHERE level IN ('ERROR', 'CRITICAL') AND DATE(created_at) = CURDATE()), 0) as error_count;

CREATE OR REPLACE VIEW `portfolio_overview` AS
SELECT 
    COALESCE(bh.total_portfolio_value, 0) as total_portfolio_value,
    COALESCE(bh.spot_balance_usdt, 0) as spot_balance_usdt,
    COALESCE(bh.futures_balance_usdt, 0) as futures_balance_usdt,
    COALESCE(bh.total_unrealized_pnl, 0) as total_unrealized_pnl,
    COALESCE(bh.daily_pnl, 0) as daily_pnl,
    COALESCE(bh.daily_pnl_percentage, 0) as daily_pnl_percentage,
    COALESCE(COUNT(p.id), 0) as active_positions,
    COALESCE(SUM(CASE WHEN p.trading_type = 'SPOT' THEN p.position_value ELSE 0 END), 0) as spot_position_value,
    COALESCE(SUM(CASE WHEN p.trading_type = 'FUTURES' THEN p.position_value ELSE 0 END), 0) as futures_position_value,
    COALESCE(bh.created_at, NOW()) as last_updated
FROM (SELECT * FROM balance_history ORDER BY created_at DESC LIMIT 1) bh
LEFT JOIN positions p ON p.position_amt != 0
GROUP BY bh.id;