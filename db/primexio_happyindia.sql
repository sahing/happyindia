-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 21, 2026 at 05:22 PM
-- Server version: 10.6.25-MariaDB
-- PHP Version: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `primexio_happyindia`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `permission_level` enum('super_admin','admin','moderator') DEFAULT 'admin',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `created_at`, `permission_level`, `updated_at`) VALUES
(1, 'admin', '$2y$10$n.zcCOIKca2hHS2snjZToOF40UqVaIzLilaDdWnhhYaY28SvQcoCi', '2025-09-24 06:52:37', 'admin', '2025-09-30 04:36:41'),
(4, 'admin1', '$2y$10$hFPVM8gfsq5TrBThws1RcOBgEvNvkyVLKsYc4WRWxIO1DoGMDA8Zi', '2025-09-24 12:22:17', 'moderator', '2025-09-24 12:22:17'),
(5, 'debkumar', '$2y$10$MiWjKWQBwEWg3OIQEnu2xe2/GnalIK4GwJYcMSFHtrlKntKO6ydnK', '2025-09-30 04:38:39', NULL, '2026-04-21 17:17:14'),
(6, '7063109133', '$2y$10$T09n1wfjEdco66uazmVgW.R/DuERnGdCtDQrvCyEieAMzQzsKiIti', '2025-09-30 05:45:03', NULL, '2026-04-21 17:17:17');

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `recipient_user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `level` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `commissions`
--

INSERT INTO `commissions` (`id`, `purchase_id`, `recipient_user_id`, `amount`, `level`, `created_at`) VALUES
(1, 3, 1, 100.00, 1, '2025-09-25 06:32:48');

-- --------------------------------------------------------

--
-- Table structure for table `commission_settings`
--

CREATE TABLE `commission_settings` (
  `id` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `commission_settings`
--

INSERT INTO `commission_settings` (`id`, `level`, `percentage`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 0.10, 'Direct referral commission', '2025-09-24 08:39:06', '2025-09-26 17:16:18'),
(2, 2, 0.05, 'Level 2 commission', '2025-09-24 08:39:06', '2025-09-26 17:16:33'),
(3, 3, 0.02, 'Level 3 commission', '2025-09-24 08:39:06', '2025-09-26 17:16:42');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `screenshot_path` varchar(255) DEFAULT NULL,
  `verified` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `utr_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `amount`, `screenshot_path`, `verified`, `verified_at`, `created_at`, `utr_number`) VALUES
(1, 1, 100.00, 'payment_1_1758697545.png', 'verified', '2025-09-24 07:15:16', '2025-09-24 07:05:45', NULL),
(2, 3, 100.00, 'payment_3_1758781798.png', 'verified', '2025-09-25 06:30:52', '2025-09-25 06:29:58', NULL),
(3, 4, 100.00, 'payment_4_1758903711.png', 'verified', '2025-09-26 17:14:54', '2025-09-26 16:21:51', '12324'),
(4, 6, 100.00, 'payment_6_1759209305.jpg', 'verified', '2025-09-30 05:20:38', '2025-09-30 05:15:05', '1234');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `image`, `created_at`, `updated_at`) VALUES
(1, 'Premium', 'Get access to exclusive features and higher commissions', 500.00, NULL, '2025-09-24 08:39:09', '2025-09-26 16:19:31'),
(2, 'Business Package', 'Complete business toolkit with marketing materials.', 1000.00, NULL, '2025-09-24 08:39:09', '2025-09-26 16:19:20'),
(3, 'Training Course', 'Comprehensive training program for network marketing', 750.00, NULL, '2025-09-24 08:39:09', '2025-09-24 08:39:09');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `purchase_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `user_id`, `product_id`, `amount`, `purchase_date`) VALUES
(1, 1, 1, 500.00, '2025-09-24 08:51:50'),
(2, 1, 1, 500.00, '2025-09-24 08:52:16'),
(3, 3, 2, 1000.00, '2025-09-25 06:32:48');

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `qr_codes`
--

INSERT INTO `qr_codes` (`id`, `filename`, `original_name`, `is_active`, `created_at`) VALUES
(1, 'qr_1758714856_68d3dbe86ee94.png', 'Screenshot 2025-09-24 172336.png', 0, '2025-09-24 11:54:16'),
(2, 'qr_1759211149_68db6e8d06505.png', 'GooglePay_QR.png', 0, '2025-09-30 05:45:49'),
(3, 'qr_1776791922_69e7b1726f140.jpg', 'logo.jpg', 1, '2026-04-21 17:18:42');

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referee_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`id`, `referrer_id`, `referee_id`, `created_at`) VALUES
(1, 1, 2, '2025-09-24 10:01:35'),
(2, 1, 3, '2025-09-25 06:29:47'),
(3, 1, 4, '2025-09-26 16:21:21'),
(4, 1, 6, '2025-09-30 05:11:41'),
(5, 6, 7, '2025-09-30 05:50:05'),
(6, 6, 8, '2025-09-30 08:57:56');

-- --------------------------------------------------------

--
-- Table structure for table `upi_ids`
--

CREATE TABLE `upi_ids` (
  `id` int(11) NOT NULL,
  `upi_id` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `upi_ids`
--

INSERT INTO `upi_ids` (`id`, `upi_id`, `is_active`, `created_at`) VALUES
(1, 'happyindia@upi', 1, '2025-09-24 07:41:38'),
(3, 'sahin@ybl', 1, '2025-09-24 07:42:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `education` varchar(100) DEFAULT NULL,
  `referral_id` varchar(20) DEFAULT NULL,
  `payment_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `coins` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `age`, `mobile`, `password`, `address`, `education`, `referral_id`, `payment_status`, `created_at`, `updated_at`, `coins`) VALUES
(1, 'SAHIN AHMED', 33, '9153463966', '$2y$10$5urP2HF6jwHXYu4pAFT4vuiF0ph9667/oxSf23HLPBBSu.bkSjiy6', 'VILL PO KANTANAGAR, PS BHAGWANGOLA', 'à¦®à¦¾à¦§à§à¦¯à¦®à¦¿à¦•', 'HWF8D50C', 'verified', '2025-09-24 07:05:16', '2025-09-25 06:32:48', 700.00),
(2, 'sahin2', 33, '7003089031', '$2y$10$fmx6KMvcdZmilR32Mf8aV.3c3.uV/Rb.wMd12V3Di/uJ1zDwlqtna', 'hhh', 'Post Graduate', 'HWD301EC', 'pending', '2025-09-24 10:01:35', '2025-09-24 12:59:00', 100.00),
(3, 'ABCD', 32, '9876543210', '$2y$10$tSgluUXErQlJmvsHNAT8VewqxbPfrOOKlLE0kb/9AEmhtI3VgTWu6', 'ADDRESS', 'Higher Secondary', 'HW2C88A1', 'verified', '2025-09-25 06:29:47', '2025-09-25 06:32:48', -1000.00),
(4, 'rrr', 32, '9876543211', '$2y$10$w4TxFv43oiABSVKqlgCDBOTXJ1Y.rIELHpRLNjhFyhQf.r7jhiUiO', 'otop', 'Secondary', 'HW8B0794', 'verified', '2025-09-26 16:21:21', '2025-09-26 17:14:54', 0.00),
(5, 'Deb Kumar saha', 42, '8597342174', '$2y$10$HdbkQ4I2rWF6Snu27xtNY.IIt4i9zpwaid1jKf1V08RCfqZHb0Yee', 'Metiabruz  kolkata 24', 'Higher Secondary', 'HWD16658', 'pending', '2025-09-28 14:03:24', '2025-09-28 14:03:24', 0.00),
(6, 'Sadhana das', 35, '7063109133', '$2y$10$q1fF6pZwYsAA9o73buvfSuCD9LepUNBucRarVsxU0L/YhRw.KAvKS', 'Kolkata', 'Secondary', 'HW396175', 'verified', '2025-09-30 05:11:41', '2025-09-30 05:20:38', 0.00),
(7, 'Ram', 20, '9999999999', '$2y$10$MLJqkzg6bTgAf/qHYHNjNOnN27T0utUIZMpgGaelJZNgPlpiJEwQa', 'Kolkata', 'Graduate', 'HWFAABF5', 'pending', '2025-09-30 05:50:05', '2025-09-30 05:50:05', 0.00),
(8, 'Anisha parveen', 18, '7478947906', '$2y$10$zTXJbj2XmjoBE0soU9svpOhADUj7fffi1Mh36DTl9JoNrst2DqVMy', 'sagar dighi sontosh pur majar', 'Secondary', 'HWE45C75', 'pending', '2025-09-30 08:57:56', '2025-09-30 08:57:56', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `wallet_deposits`
--

CREATE TABLE `wallet_deposits` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `screenshot_path` varchar(255) DEFAULT NULL,
  `qr_code_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `utr_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `wallet_deposits`
--

INSERT INTO `wallet_deposits` (`id`, `user_id`, `amount`, `description`, `screenshot_path`, `qr_code_id`, `status`, `admin_notes`, `requested_at`, `processed_at`, `processed_by`, `utr_number`) VALUES
(1, 1, 600.00, 'ddd', 'deposit_1758716483_68d3e243a8a0f.png', 1, 'approved', '', '2025-09-24 12:21:23', '2025-09-24 12:54:25', 1, NULL),
(2, 3, 1000.00, 'ddd', 'deposit_1758781931_68d4e1eb099cc.png', 1, 'approved', '', '2025-09-25 06:32:11', '2025-09-25 06:32:36', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('deposit','withdrawal','purchase','commission','transfer_sent','transfer_received') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `user_id`, `transaction_type`, `amount`, `description`, `reference_id`, `balance_after`, `created_at`) VALUES
(1, 1, 'purchase', 500.00, 'Premium Membership', 1, 500.00, '2025-09-24 08:51:50'),
(2, 1, 'purchase', 500.00, 'Premium Membership', 2, 0.00, '2025-09-24 08:52:16'),
(3, 1, 'deposit', 600.00, 'fgg', NULL, 600.00, '2025-09-24 12:02:52'),
(4, 1, 'deposit', 600.00, 'ddd', NULL, 1200.00, '2025-09-24 12:54:25'),
(5, 1, 'transfer_sent', 100.00, 'tr', NULL, 1100.00, '2025-09-24 12:59:00'),
(6, 2, 'transfer_received', 100.00, 'tr', NULL, 100.00, '2025-09-24 12:59:00'),
(7, 1, 'withdrawal', 500.00, 'Withdrawal request - UPI', 0, 600.00, '2025-09-25 06:10:11'),
(8, 1, 'withdrawal', 500.00, 'Withdrawal approved - UPI', 1, 600.00, '2025-09-25 06:14:42'),
(9, 3, 'deposit', 1000.00, 'ddd', NULL, 1000.00, '2025-09-25 06:32:36'),
(10, 3, 'purchase', 1000.00, 'Business Package', 3, -1000.00, '2025-09-25 06:32:48'),
(11, 1, 'commission', 100.00, 'Commission from Level 1 referral purchase', 1, 700.00, '2025-09-25 06:32:48');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `account_details` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `user_id`, `amount`, `status`, `payment_method`, `account_details`, `requested_at`, `approved_at`, `processed_at`) VALUES
(1, 1, 500.00, 'approved', 'UPI', 'sahin@ybl', '2025-09-25 06:10:11', NULL, '2025-09-25 06:14:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `recipient_user_id` (`recipient_user_id`);

--
-- Indexes for table `commission_settings`
--
ALTER TABLE `commission_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_referral` (`referrer_id`,`referee_id`),
  ADD KEY `referee_id` (`referee_id`),
  ADD KEY `idx_referrals_referrer` (`referrer_id`);

--
-- Indexes for table `upi_ids`
--
ALTER TABLE `upi_ids`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `upi_id` (`upi_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD UNIQUE KEY `referral_id` (`referral_id`),
  ADD KEY `idx_users_mobile` (`mobile`),
  ADD KEY `idx_users_referral_id` (`referral_id`);

--
-- Indexes for table `wallet_deposits`
--
ALTER TABLE `wallet_deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `qr_code_id` (`qr_code_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_withdrawals_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `commission_settings`
--
ALTER TABLE `commission_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `upi_ids`
--
ALTER TABLE `upi_ids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `wallet_deposits`
--
ALTER TABLE `wallet_deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `commissions`
--
ALTER TABLE `commissions`
  ADD CONSTRAINT `commissions_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`),
  ADD CONSTRAINT `commissions_ibfk_2` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`referee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_deposits`
--
ALTER TABLE `wallet_deposits`
  ADD CONSTRAINT `wallet_deposits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `wallet_deposits_ibfk_2` FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`),
  ADD CONSTRAINT `wallet_deposits_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `admins` (`id`);

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `wallet_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
