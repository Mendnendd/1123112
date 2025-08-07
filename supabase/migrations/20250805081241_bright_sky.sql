-- Binance AI Trader Database Schema
-- Created: 2025-01-27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Admin Users Table
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trading Settings Table
CREATE TABLE `trading_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `binance_api_key` varchar(255) DEFAULT NULL,
  `binance_api_secret` text DEFAULT NULL,
  `testnet_mode` tinyint(1) DEFAULT 1,
  `trading_enabled` tinyint(1) DEFAULT 0,
  `ai_enabled` tinyint(1) DEFAULT 1,
  `max_position_size` decimal(15,8) DEFAULT 100.00000000,
  `risk_percentage` decimal(5,2) DEFAULT 2.00,
  `stop_loss_percentage` decimal(5,2) DEFAULT 5.00,
  `take_profit_percentage` decimal(5,2) DEFAULT 10.00,
  `leverage` int(11) DEFAULT 10,
  `margin_type` enum('ISOLATED','CROSSED') DEFAULT 'ISOLATED',
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `trading_settings` (`id`) VALUES (1);

-- Trading Pairs Table
CREATE TABLE `trading_pairs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(20) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `leverage` int(11) DEFAULT 10,
  `margin_type` enum('ISOLATED','CROSSED') DEFAULT 'ISOLATED',
  `min_notional` decimal(15,8) DEFAULT 0.00000000,
  `tick_size` decimal(15,8) DEFAULT 0.00000000,
  `step_size` decimal(15,8) DEFAULT 0.00000000,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default trading pairs
INSERT INTO `trading_pairs` (`symbol`, `enabled`) VALUES
('BTCUSDT', 1),
('ETHUSDT', 1),
('BNBUSDT', 1),
('ADAUSDT', 1),
('DOTUSDT', 1),
('LINKUSDT', 1),
('LTCUSDT', 1),
('XRPUSDT', 1);

-- Trading History Table
CREATE TABLE `trading_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(20) NOT NULL,
  `side` enum('BUY','SELL') NOT NULL,
  `type` enum('MARKET','LIMIT','STOP','STOP_MARKET') NOT NULL,
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
  `ai_signal_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `symbol` (`symbol`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Signals Table
CREATE TABLE `ai_signals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(20) NOT NULL,
  `signal` enum('BUY','SELL','HOLD') NOT NULL,
  `confidence` decimal(5,3) NOT NULL,
  `price` decimal(15,8) NOT NULL,
  `rsi` decimal(5,2) DEFAULT NULL,
  `macd` decimal(15,8) DEFAULT NULL,
  `macd_signal` decimal(15,8) DEFAULT NULL,
  `macd_histogram` decimal(15,8) DEFAULT NULL,
  `bb_upper` decimal(15,8) DEFAULT NULL,
  `bb_middle` decimal(15,8) DEFAULT NULL,
  `bb_lower` decimal(15,8) DEFAULT NULL,
  `sma_20` decimal(15,8) DEFAULT NULL,
  `sma_50` decimal(15,8) DEFAULT NULL,
  `volume` decimal(15,8) DEFAULT NULL,
  `volume_avg` decimal(15,8) DEFAULT NULL,
  `price_change_24h` decimal(5,2) DEFAULT NULL,
  `analysis_score` int(11) DEFAULT NULL,
  `indicators_data` text DEFAULT NULL,
  `executed` tinyint(1) DEFAULT 0,
  `execution_price` decimal(15,8) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `symbol` (`symbol`),
  KEY `signal` (`signal`),
  KEY `confidence` (`confidence`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Account Balance History Table
CREATE TABLE `balance_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `total_wallet_balance` decimal(15,8) NOT NULL,
  `total_unrealized_pnl` decimal(15,8) DEFAULT 0.00000000,
  `total_margin_balance` decimal(15,8) NOT NULL,
  `total_position_initial_margin` decimal(15,8) DEFAULT 0.00000000,
  `total_open_order_initial_margin` decimal(15,8) DEFAULT 0.00000000,
  `available_balance` decimal(15,8) NOT NULL,
  `max_withdraw_amount` decimal(15,8) DEFAULT 0.00000000,
  `assets_data` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Positions Table
CREATE TABLE `positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(20) NOT NULL,
  `position_amt` decimal(15,8) NOT NULL,
  `entry_price` decimal(15,8) NOT NULL,
  `mark_price` decimal(15,8) NOT NULL,
  `unrealized_pnl` decimal(15,8) DEFAULT 0.00000000,
  `percentage` decimal(8,4) DEFAULT 0.0000,
  `side` enum('LONG','SHORT','BOTH') NOT NULL,
  `leverage` int(11) DEFAULT 1,
  `margin_type` enum('ISOLATED','CROSSED') DEFAULT 'ISOLATED',
  `isolated_margin` decimal(15,8) DEFAULT 0.00000000,
  `position_initial_margin` decimal(15,8) DEFAULT 0.00000000,
  `open_order_initial_margin` decimal(15,8) DEFAULT 0.00000000,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol` (`symbol`),
  KEY `side` (`side`),
  KEY `updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System Logs Table
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` enum('DEBUG','INFO','WARNING','ERROR','CRITICAL') NOT NULL,
  `message` text NOT NULL,
  `context` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `level` (`level`),
  KEY `created_at` (`created_at`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API Rate Limits Table
CREATE TABLE `api_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `endpoint` varchar(100) NOT NULL,
  `requests_count` int(11) DEFAULT 0,
  `window_start` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_request` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint` (`endpoint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Performance Metrics Table
CREATE TABLE `performance_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `total_trades` int(11) DEFAULT 0,
  `winning_trades` int(11) DEFAULT 0,
  `losing_trades` int(11) DEFAULT 0,
  `win_rate` decimal(5,2) DEFAULT 0.00,
  `total_pnl` decimal(15,8) DEFAULT 0.00000000,
  `max_drawdown` decimal(15,8) DEFAULT 0.00000000,
  `sharpe_ratio` decimal(8,4) DEFAULT 0.0000,
  `total_volume` decimal(15,8) DEFAULT 0.00000000,
  `avg_trade_duration` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;