-- ============================================
-- Aero's Store - Database Schema
-- MLBB Boosting Management System
-- ============================================

CREATE DATABASE IF NOT EXISTS `aeros_store` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `aeros_store`;

-- ============================================
-- 1. Admins Table
-- ============================================
CREATE TABLE `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin: admin / admin123
INSERT INTO `admins` (`username`, `password`, `name`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Aero Admin');

-- ============================================
-- 2. Customers Table
-- ============================================
CREATE TABLE `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `game_id` VARCHAR(50) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `total_orders` INT DEFAULT 0,
    `total_spent` DECIMAL(15,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- 3. Workers Table
-- ============================================
CREATE TABLE `workers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `specialization` TEXT DEFAULT NULL,
    `rank_info` VARCHAR(100) DEFAULT NULL,
    `roles` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- 4. Orders Table
-- ============================================
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `worker_id` INT DEFAULT NULL,
    `rank_from` VARCHAR(50) NOT NULL,
    `rank_to` VARCHAR(50) NOT NULL,
    `request_hero` VARCHAR(255) DEFAULT NULL,
    `request_role` VARCHAR(255) DEFAULT NULL,
    `special_request` TEXT DEFAULT NULL,
    `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `worker_commission` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('unpaid','dp','paid') NOT NULL DEFAULT 'unpaid',
    `status` ENUM('unassigned','in_progress','pending_verification','completed') NOT NULL DEFAULT 'unassigned',
    `deadline` DATE DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`worker_id`) REFERENCES `workers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 5. Order Logs Table
-- ============================================
CREATE TABLE `order_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 6. Worker Commissions Ledger
-- ============================================
CREATE TABLE `worker_commissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `worker_id` INT NOT NULL,
    `order_id` INT DEFAULT NULL,
    `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `type` ENUM('earned','paid') NOT NULL DEFAULT 'earned',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`worker_id`) REFERENCES `workers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;
