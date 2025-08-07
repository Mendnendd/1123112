-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 05, 2025 at 12:22 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `binance_trader`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_signals`
--

CREATE TABLE `ai_signals` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_rate_limits`
--

CREATE TABLE `api_rate_limits` (
  `id` int(11) NOT NULL,
  `endpoint` varchar(100) NOT NULL,
  `requests_count` int(11) DEFAULT 0,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_request` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `balance_history`
--

CREATE TABLE `balance_history` (
  `id` int(11) NOT NULL,
  `total_wallet_balance` decimal(15,8) NOT NULL,
  `total_unrealized_pnl` decimal(15,8) DEFAULT 0.00000000,
  `total_margin_balance` decimal(15,8) NOT NULL,
  `total_position_initial_margin` decimal(15,8) DEFAULT 0.00000000,
  `total_open_order_initial_margin` decimal(15,8) DEFAULT 0.00000000,
  `available_balance` decimal(15,8) NOT NULL,
  `max_withdraw_amount` decimal(15,8) DEFAULT 0.00000000,
  `assets_data` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_metrics`
--

CREATE TABLE `performance_metrics` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `level` enum('DEBUG','INFO','WARNING','ERROR','CRITICAL') NOT NULL,
  `message` text NOT NULL,
  `context` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trading_history`
--

CREATE TABLE `trading_history` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trading_pairs`
--

CREATE TABLE `trading_pairs` (
  `id` int(11) NOT NULL,
  `symbol` varchar(20) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `leverage` int(11) DEFAULT 10,
  `margin_type` enum('ISOLATED','CROSSED') DEFAULT 'ISOLATED',
  `min_notional` decimal(15,8) DEFAULT 0.00000000,
  `tick_size` decimal(15,8) DEFAULT 0.00000000,
  `step_size` decimal(15,8) DEFAULT 0.00000000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trading_pairs`
--

INSERT INTO `trading_pairs` (`id`, `symbol`, `enabled`, `leverage`, `margin_type`, `min_notional`, `tick_size`, `step_size`, `created_at`, `updated_at`) VALUES
(1, 'BTCUSDT', 1, 10, 'ISOLATED', 0.00000000, 0.00000000, 0.00000000, '2025-08-05 10:21:03', '2025-08-05 10:21:03'),
(2, 'ETHUSDT', 1, 10, 'ISOLATED', 0.00000000, 0.00000000, 0.00000000, '2025-08-05 10:21:03', '2025-08-05 10:21:03'),
(3, 'BNBUSDT', 1, 10, 'ISOLATED', 0.00000000, 0.00000000, 0.00000000, '2025-08-05 10:21:03', '2025-08-05 10:21:03'),
(4, 'ADAUSDT', 1, 10, 'ISOLATED', 0.00000000, 0.00000000, 0.00000000, '2025-08-05 10:21:03', '2025-08-05 10:21:03'),
(5, 'DOTUSDT', 1, 10, 'ISOLATED', 0.00000000, 0.00000000, 0.00000000, '2025-08-05 10:21:03', '2025-08-05 10:21:03'),
(6, 'LINKUSDT', 1, 10, 'ISOLATED', 0.00000000, 0.00000000, 0.00000000, '2025-08-05 10:21:03', '2025-08-05 10:21:03'),
(7, 'LTCUSDT', 1, 10, 'ISOLATED', 0.00000000, 0.00000000, 0.00000000, '2025-08-05 10:21:03', '2025-08-05 10:21:03'),
(8, 'XRPUSDT', 1, 10, 'ISOLATED', 0.00000000, 0.00000000, 0.00000000, '2025-08-05 10:21:03', '2025-08-05 10:21:03');

-- --------------------------------------------------------

--
-- Table structure for table `trading_settings`
--

CREATE TABLE `trading_settings` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trading_settings`
--

INSERT INTO `trading_settings` (`id`, `binance_api_key`, `binance_api_secret`, `testnet_mode`, `trading_enabled`, `ai_enabled`, `max_position_size`, `risk_percentage`, `stop_loss_percentage`, `take_profit_percentage`, `leverage`, `margin_type`, `updated_at`) VALUES
(1, NULL, NULL, 1, 0, 1, 100.00000000, 2.00, 5.00, 10.00, 10, 'ISOLATED', '2025-08-05 10:21:03');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `ai_signals`
--
ALTER TABLE `ai_signals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `symbol` (`symbol`),
  ADD KEY `signal` (`signal`),
  ADD KEY `confidence` (`confidence`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `endpoint` (`endpoint`);

--
-- Indexes for table `balance_history`
--
ALTER TABLE `balance_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `symbol` (`symbol`),
  ADD KEY `side` (`side`),
  ADD KEY `updated_at` (`updated_at`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `level` (`level`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `trading_history`
--
ALTER TABLE `trading_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `symbol` (`symbol`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `trading_pairs`
--
ALTER TABLE `trading_pairs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `symbol` (`symbol`);

--
-- Indexes for table `trading_settings`
--
ALTER TABLE `trading_settings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_signals`
--
ALTER TABLE `ai_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `balance_history`
--
ALTER TABLE `balance_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trading_history`
--
ALTER TABLE `trading_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trading_pairs`
--
ALTER TABLE `trading_pairs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `trading_settings`
--
ALTER TABLE `trading_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
