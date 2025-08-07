-- Fix for Portfolio Database Tables
-- This SQL script ensures all portfolio-related tables have proper structure

-- Update balance_history table to ensure proper decimal precision
ALTER TABLE `balance_history` 
MODIFY COLUMN `total_wallet_balance` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
MODIFY COLUMN `total_unrealized_pnl` DECIMAL(20,8) DEFAULT 0.00000000,
MODIFY COLUMN `total_margin_balance` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
MODIFY COLUMN `total_position_initial_margin` DECIMAL(20,8) DEFAULT 0.00000000,
MODIFY COLUMN `total_open_order_initial_margin` DECIMAL(20,8) DEFAULT 0.00000000,
MODIFY COLUMN `available_balance` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
MODIFY COLUMN `max_withdraw_amount` DECIMAL(20,8) DEFAULT 0.00000000;

-- Update positions table for better precision
ALTER TABLE `positions`
MODIFY COLUMN `position_amt` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
MODIFY COLUMN `entry_price` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
MODIFY COLUMN `mark_price` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
MODIFY COLUMN `unrealized_pnl` DECIMAL(20,8) DEFAULT 0.00000000,
MODIFY COLUMN `isolated_margin` DECIMAL(20,8) DEFAULT 0.00000000,
MODIFY COLUMN `position_initial_margin` DECIMAL(20,8) DEFAULT 0.00000000,
MODIFY COLUMN `open_order_initial_margin` DECIMAL(20,8) DEFAULT 0.00000000;

-- Add index for better performance on balance history queries
CREATE INDEX IF NOT EXISTS `idx_balance_history_date` ON `balance_history` (`created_at` DESC);

-- Add index for positions symbol lookup
CREATE INDEX IF NOT EXISTS `idx_positions_symbol_updated` ON `positions` (`symbol`, `updated_at` DESC);

-- Create a view for latest portfolio summary
CREATE OR REPLACE VIEW `portfolio_summary` AS
SELECT 
    bh.total_wallet_balance,
    bh.total_unrealized_pnl,
    bh.total_margin_balance,
    bh.available_balance,
    bh.created_at as last_updated,
    COUNT(p.id) as active_positions,
    COALESCE(SUM(p.unrealized_pnl), 0) as total_position_pnl
FROM balance_history bh
LEFT JOIN positions p ON p.position_amt != 0
WHERE bh.id = (SELECT MAX(id) FROM balance_history)
GROUP BY bh.id;

-- Create portfolio performance tracking table
CREATE TABLE IF NOT EXISTS `portfolio_performance` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `date` DATE NOT NULL,
    `starting_balance` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    `ending_balance` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    `daily_pnl` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    `daily_return_pct` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    `total_trades` INT(11) DEFAULT 0,
    `winning_trades` INT(11) DEFAULT 0,
    `max_drawdown` DECIMAL(20,8) DEFAULT 0.00000000,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `date` (`date`),
    KEY `idx_date_desc` (`date` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;