-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 04, 2026 at 02:38 AM
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
-- Database: `malishop`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_audit_logs`
--

CREATE TABLE `admin_audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_audit_logs`
--

INSERT INTO `admin_audit_logs` (`id`, `admin_id`, `action`, `entity_type`, `entity_id`, `ip`, `user_agent`, `created_at`) VALUES
(1, 2, 'login_success', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-25 10:21:19'),
(2, 2, 'partner_created_product', 'product', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-25 12:57:10'),
(3, 1, 'owner_published_product', 'product', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:57:42'),
(4, 1, 'owner_updated_product', 'product', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 15:46:12'),
(5, 1, 'owner_updated_product', 'product', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 15:46:16'),
(6, 2, 'partner_created_product', 'product', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-25 16:03:53'),
(7, 1, 'owner_published_product', 'product', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 16:04:15'),
(8, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 19:10:44'),
(9, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-27 19:25:23'),
(10, 1, 'owner_updated_product', 'product', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 19:16:23'),
(11, 1, 'owner_updated_product', 'product', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 19:16:29'),
(12, 1, 'owner_updated_product', 'product', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 19:43:41'),
(13, 1, 'owner_updated_product', 'product', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 19:50:18'),
(14, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-02-28 21:59:00'),
(15, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 22:06:36'),
(16, 1, 'owner_changed_order_status', 'order', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 01:27:56'),
(17, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 01:29:08'),
(18, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 01:29:30'),
(19, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 02:00:28'),
(20, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 02:26:25'),
(21, 2, 'owner_requeued_notifications', 'order', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 12:49:38'),
(22, 2, 'owner_changed_order_status', 'order', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 12:49:46'),
(23, 2, 'owner_changed_order_status', 'order', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 12:54:54'),
(24, 1, 'owner_updated_settings', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:03:16'),
(25, 1, 'owner_updated_settings', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:16:09'),
(26, 1, 'owner_requeued_notifications', 'order', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 17:16:29'),
(27, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 18:31:42'),
(28, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 18:31:56'),
(29, 1, 'owner_updated_product', 'product', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 18:35:29'),
(30, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 18:43:53'),
(31, 2, 'partner_created_product', 'product', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 18:54:41'),
(32, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 18:55:10'),
(33, 1, 'owner_updated_product', 'product', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 18:55:36'),
(34, 1, 'owner_published_product', 'product', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-01 18:55:43'),
(35, 1, 'owner_export_orders_csv', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-02 13:04:56'),
(36, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 22:44:26'),
(37, 1, 'owner_changed_order_status', 'order', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:03:23'),
(38, 1, 'owner_changed_order_status', 'order', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:06:21'),
(39, 1, 'owner_requeued_notifications', 'order', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:06:50'),
(40, 1, 'owner_changed_order_status', 'order', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:12:37'),
(41, 1, 'owner_changed_order_status', 'order', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:15:33'),
(42, 1, 'owner_changed_order_status', 'order', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:22:08'),
(43, 1, 'owner_changed_order_status', 'order', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:22:15'),
(44, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 19:16:04'),
(45, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-04 19:16:38'),
(46, 1, 'owner_changed_order_status', 'order', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 19:43:11'),
(47, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 13:04:45'),
(48, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 20:06:46'),
(49, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 20:16:55'),
(50, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 09:52:08'),
(51, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 12:17:40'),
(52, 1, 'owner_changed_order_status', 'order', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 14:08:31'),
(53, 1, 'owner_changed_order_status', 'order', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 15:24:37'),
(54, 1, 'owner_changed_order_status', 'order', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 15:24:41'),
(55, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 21:30:50'),
(56, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-12 22:04:36'),
(57, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 23:30:53'),
(58, 1, 'owner_reset_partner_password', 'user', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 00:46:05'),
(59, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-15 17:25:59'),
(60, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 18:40:59'),
(61, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-28 13:16:26'),
(62, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-28 13:19:14'),
(63, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 13:19:41'),
(64, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 18:45:01'),
(65, 1, 'login_failed', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 17:09:51'),
(66, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 17:15:38'),
(67, 1, 'login_failed', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-24 17:16:04'),
(68, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-24 17:16:12'),
(69, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36 Edg/147.0.0.0', '2026-04-24 22:53:40'),
(70, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36 Edg/147.0.0.0', '2026-04-24 22:58:25'),
(71, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36 Edg/147.0.0.0', '2026-04-24 23:13:22'),
(72, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36 Edg/147.0.0.0', '2026-04-24 23:13:54'),
(73, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-25 00:30:43'),
(74, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-26 12:05:02'),
(75, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-26 12:16:29'),
(76, 1, 'product_created', 'product', 25, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-26 12:49:24'),
(77, 1, 'order_status_changed', 'order', 16, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-26 14:29:09'),
(78, 1, 'order_status_changed', 'order', 18, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-26 16:49:28'),
(79, 1, 'order_status_changed', 'order', 18, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-26 16:49:32'),
(80, 1, 'order_status_changed', 'order', 18, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-26 16:49:35'),
(81, 1, 'order_status_changed', 'order', 18, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-26 16:49:38'),
(82, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 00:40:11'),
(83, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 00:40:37'),
(84, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 00:41:42'),
(85, 1, 'inventory_adjusted', 'product', 24, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 01:44:41'),
(86, 1, 'inventory_adjusted', 'product', 24, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 01:44:57'),
(87, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 11:41:26'),
(88, 2, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 12:27:10'),
(89, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 12:28:26'),
(90, 1, 'product_updated', 'product', 25, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 18:44:36'),
(91, 1, 'product_updated', 'product', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-27 18:45:29'),
(92, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-28 10:18:29'),
(93, 1, 'owner_created_coupon', 'coupon', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-28 10:22:27'),
(94, 1, 'order_status_changed', 'order', 23, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-28 16:44:44'),
(95, 1, 'order_status_changed', 'order', 23, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-28 16:45:35'),
(96, 1, 'order_status_changed', 'order', 22, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-28 16:48:55'),
(97, 1, 'order_status_changed', 'order', 19, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-28 16:49:35'),
(98, 1, 'order_status_changed', 'order', 19, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-28 16:49:38'),
(99, 1, 'order_status_changed', 'order', 19, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-28 16:49:40'),
(100, 1, 'order_status_changed', 'order', 19, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-28 16:49:41'),
(101, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-29 11:06:22'),
(102, 1, 'login_success', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-03 13:55:29'),
(103, 1, 'order_status_changed', 'order', 24, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-03 20:16:29'),
(104, 1, 'order_status_changed', 'order', 24, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-03 20:16:32'),
(105, 1, 'order_status_changed', 'order', 24, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-05-03 20:16:35');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `description` text DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `seo_title` varchar(160) DEFAULT NULL,
  `seo_description` varchar(255) DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `subject` varchar(80) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) NOT NULL,
  `type` enum('percent','fixed') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `min_subtotal` decimal(10,2) DEFAULT NULL,
  `max_uses` int(11) DEFAULT NULL,
  `uses_count` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `type`, `value`, `starts_at`, `ends_at`, `min_subtotal`, `max_uses`, `uses_count`, `is_active`, `created_at`) VALUES
(1, 'PROM010', 'percent', 10.00, '2026-04-01 10:21:00', '2026-04-30 10:21:00', 5000.00, NULL, 1, 1, '2026-04-28 10:22:27');

-- --------------------------------------------------------

--
-- Table structure for table `coupon_categories`
--

CREATE TABLE `coupon_categories` (
  `coupon_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(190) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `city` varchar(64) NOT NULL,
  `district` varchar(128) DEFAULT NULL,
  `address_note` varchar(255) DEFAULT NULL,
  `is_blacklisted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `full_name`, `phone`, `email`, `city`, `district`, `address_note`, `is_blacklisted`, `created_at`, `updated_at`) VALUES
(1, 'Fedyna Vanessa Julien', '14388682925', 'vanessafedyna2002@gmail.com', 'Bamako', 'ACI 2000', 'pres du metro', 0, '2026-02-28 20:39:05', '2026-02-28 20:39:05'),
(2, 'VIVI', '+2234388682925', 'vanessafedyna2002@gmail.com', 'Bamako', NULL, NULL, 0, '2026-03-03 16:59:56', '2026-04-28 10:23:40'),
(3, 'Fedyna VJ', '+2234386302122', NULL, 'Tombouctou', NULL, 'pres du bus verte', 0, '2026-04-26 12:06:24', '2026-04-26 12:27:23'),
(4, 'Fedyna VJ', '+223438682925', NULL, 'Tombouctou', NULL, NULL, 0, '2026-04-26 12:29:09', '2026-04-26 12:29:09'),
(5, 'lolita', '+22370123456', 'vanessafedyna2002@gmail.com', 'Sikasso', NULL, NULL, 0, '2026-04-27 20:28:53', '2026-04-28 20:30:38'),
(6, 'QA Test', '+22370123457', 'qa@example.com', 'Bamako', NULL, 'Audit local', 0, '2026-04-27 20:29:08', '2026-04-27 20:29:08'),
(7, 'QA Test', '+22370123458', 'qa@example.com', 'Bamako', NULL, 'Audit local', 0, '2026-04-27 20:29:27', '2026-04-27 20:29:27'),
(8, 'QA Test', '+22370123459', 'qa@example.com', 'Bamako', NULL, 'Audit local', 0, '2026-04-27 20:29:43', '2026-04-27 20:29:43');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `fail_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `first_failed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_failed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_jobs`
--

CREATE TABLE `notification_jobs` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(80) NOT NULL,
  `channel` varchar(16) NOT NULL DEFAULT 'email',
  `recipient` varchar(190) NOT NULL,
  `payload_json` text DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `last_error` text DEFAULT NULL,
  `next_retry_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `lock_token` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_jobs`
--

INSERT INTO `notification_jobs` (`id`, `order_id`, `type`, `channel`, `recipient`, `payload_json`, `status`, `attempts`, `max_attempts`, `last_error`, `next_retry_at`, `locked_at`, `lock_token`, `created_at`, `updated_at`) VALUES
(22, 15, 'admin_new_order', 'email', 'admin@malishop.com', '{\"order_number\":\"ML-2026-CE9101\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 12:27:23', '2026-04-26 12:27:23'),
(23, 15, 'admin_new_order', 'email', 'support@soracollectionmali.com', '{\"order_number\":\"ML-2026-CE9101\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 12:27:23', '2026-04-26 12:27:23'),
(24, 16, 'admin_new_order', 'email', 'admin@malishop.com', '{\"order_number\":\"ML-2026-DA6C36\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 12:29:09', '2026-04-26 12:29:09'),
(25, 16, 'admin_new_order', 'email', 'support@soracollectionmali.com', '{\"order_number\":\"ML-2026-DA6C36\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 12:29:09', '2026-04-26 12:29:09'),
(26, 17, 'admin_new_order', 'email', 'admin@malishop.com', '{\"order_number\":\"ML-2026-E42C8F\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 15:57:03', '2026-04-26 15:57:03'),
(27, 17, 'admin_new_order', 'email', 'support@soracollectionmali.com', '{\"order_number\":\"ML-2026-E42C8F\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 15:57:03', '2026-04-26 15:57:03'),
(28, 17, 'client_order_created', 'email', 'vanessafedyna2002@gmail.com', '{\"order_number\":\"ML-2026-E42C8F\",\"status\":\"nouveau\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 15:57:03', '2026-04-26 15:57:03'),
(29, 18, 'admin_new_order', 'email', 'admin@malishop.com', '{\"order_number\":\"ML-2026-88E2F8\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 16:06:30', '2026-04-26 16:06:30'),
(30, 18, 'admin_new_order', 'email', 'support@soracollectionmali.com', '{\"order_number\":\"ML-2026-88E2F8\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 16:06:30', '2026-04-26 16:06:30'),
(31, 18, 'client_order_created', 'email', 'vanessafedyna2002@gmail.com', '{\"order_number\":\"ML-2026-88E2F8\",\"status\":\"nouveau\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 16:06:30', '2026-04-26 16:06:30'),
(32, 18, 'client_status_update', 'email', 'vanessafedyna2002@gmail.com', '{\"order_number\":\"ML-2026-88E2F8\",\"status\":\"en_livraison\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-26 16:49:35', '2026-04-26 16:49:35'),
(33, 19, 'admin_new_order', 'email', 'admin@malishop.com', '{\"order_number\":\"ML-2026-BE6C19\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:28:53', '2026-04-27 20:28:53'),
(34, 19, 'admin_new_order', 'email', 'support@soracollectionmali.com', '{\"order_number\":\"ML-2026-BE6C19\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:28:53', '2026-04-27 20:28:53'),
(35, 19, 'client_order_created', 'email', 'qa@example.com', '{\"order_number\":\"ML-2026-BE6C19\",\"status\":\"nouveau\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:28:53', '2026-04-27 20:28:53'),
(36, 20, 'admin_new_order', 'email', 'admin@malishop.com', '{\"order_number\":\"ML-2026-07FF46\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:29:08', '2026-04-27 20:29:08'),
(37, 20, 'admin_new_order', 'email', 'support@soracollectionmali.com', '{\"order_number\":\"ML-2026-07FF46\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:29:08', '2026-04-27 20:29:08'),
(38, 20, 'client_order_created', 'email', 'qa@example.com', '{\"order_number\":\"ML-2026-07FF46\",\"status\":\"nouveau\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:29:08', '2026-04-27 20:29:08'),
(39, 21, 'admin_new_order', 'email', 'admin@malishop.com', '{\"order_number\":\"ML-2026-138820\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:29:27', '2026-04-27 20:29:27'),
(40, 21, 'admin_new_order', 'email', 'support@soracollectionmali.com', '{\"order_number\":\"ML-2026-138820\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:29:27', '2026-04-27 20:29:27'),
(41, 21, 'client_order_created', 'email', 'qa@example.com', '{\"order_number\":\"ML-2026-138820\",\"status\":\"nouveau\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:29:27', '2026-04-27 20:29:27'),
(42, 22, 'admin_new_order', 'email', 'admin@malishop.com', '{\"order_number\":\"ML-2026-ADFF5C\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:29:43', '2026-04-27 20:29:43'),
(43, 22, 'admin_new_order', 'email', 'support@soracollectionmali.com', '{\"order_number\":\"ML-2026-ADFF5C\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:29:43', '2026-04-27 20:29:43'),
(44, 22, 'client_order_created', 'email', 'qa@example.com', '{\"order_number\":\"ML-2026-ADFF5C\",\"status\":\"nouveau\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-27 20:29:43', '2026-04-27 20:29:43'),
(45, 23, 'admin_new_order', 'email', 'admin@malishop.com', '{\"order_number\":\"ML-2026-2073AF\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-28 10:23:40', '2026-04-28 10:23:40'),
(46, 23, 'admin_new_order', 'email', 'support@soracollectionmali.com', '{\"order_number\":\"ML-2026-2073AF\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-28 10:23:40', '2026-04-28 10:23:40'),
(47, 23, 'client_order_created', 'email', 'vanessafedyna2002@gmail.com', '{\"order_number\":\"ML-2026-2073AF\",\"status\":\"nouveau\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-28 10:23:40', '2026-04-28 10:23:40'),
(48, 19, 'client_status_update', 'email', 'qa@example.com', '{\"order_number\":\"ML-2026-BE6C19\",\"status\":\"en_livraison\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-28 16:49:40', '2026-04-28 16:49:40'),
(49, 24, 'admin_new_order', 'email', 'admin@malishop.com', '{\"order_number\":\"ML-2026-3FEE01\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-28 20:30:38', '2026-04-28 20:30:38'),
(50, 24, 'admin_new_order', 'email', 'support@soracollectionmali.com', '{\"order_number\":\"ML-2026-3FEE01\",\"items_count\":1}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-28 20:30:38', '2026-04-28 20:30:38'),
(51, 24, 'client_order_created', 'email', 'vanessafedyna2002@gmail.com', '{\"order_number\":\"ML-2026-3FEE01\",\"status\":\"nouveau\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-04-28 20:30:38', '2026-04-28 20:30:38'),
(52, 24, 'client_status_update', 'email', 'vanessafedyna2002@gmail.com', '{\"order_number\":\"ML-2026-3FEE01\",\"status\":\"en_livraison\"}', 'pending', 0, 5, NULL, NULL, NULL, NULL, '2026-05-03 20:16:36', '2026-05-03 20:16:36');

-- --------------------------------------------------------

--
-- Table structure for table `notification_log`
--

CREATE TABLE `notification_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED DEFAULT NULL,
  `type` varchar(80) NOT NULL,
  `recipient` varchar(190) NOT NULL,
  `status` varchar(20) NOT NULL,
  `error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(32) NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_profile_id` int(10) UNSIGNED DEFAULT NULL,
  `coupon_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(32) NOT NULL,
  `customer_email` varchar(190) DEFAULT NULL,
  `city` varchar(64) NOT NULL,
  `district` varchar(128) NOT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'nouveau',
  `owner_seen_at` datetime DEFAULT NULL,
  `status_updated_at` datetime DEFAULT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'cod',
  `paid_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `subtotal_amount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `discount_amount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `shipping_fee_amount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `tax_amount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_amount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `otp_code` varchar(20) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `customer_id`, `customer_profile_id`, `coupon_id`, `customer_name`, `customer_phone`, `customer_email`, `city`, `district`, `landmark`, `status`, `owner_seen_at`, `status_updated_at`, `payment_method`, `paid_at`, `delivered_at`, `subtotal_amount`, `discount_amount`, `shipping_fee_amount`, `tax_amount`, `total_amount`, `otp_code`, `otp_expires_at`, `created_at`, `updated_at`) VALUES
(1, 'ML-2026-2D0A7B', 1, 1, NULL, 'Fedyna Vanessa Julien', '14388682925', NULL, 'Bamako', 'ACI 2000', 'pres du metro', 'en_preparation', NULL, '2026-03-04 19:43:11', 'cod', '2026-03-01 12:49:46', NULL, 4862, 0, 0, 0, 4862, NULL, NULL, '2026-02-28 20:39:05', '2026-03-04 19:43:11'),
(2, 'ML-2026-B60A82', NULL, 2, NULL, 'Fedyna Vanessa Julien', '+2234388682925', NULL, 'Bamako', 'ACI 2000', 'pres du metro', 'livre', NULL, '2026-03-03 21:22:15', 'cod', '2026-03-03 21:12:37', '2026-03-03 21:22:15', 2513, 0, 0, 0, 2513, NULL, NULL, '2026-03-03 16:59:56', '2026-03-03 21:22:15'),
(3, 'ML-2026-16B507', NULL, 2, NULL, 'lilia', '+2234388682925', NULL, 'Gao', 'ACI 2000', 'pres du l\'arret du bus 23', 'annulee', NULL, '2026-03-03 21:06:21', 'cod', '2026-03-03 21:03:23', NULL, 7375, 0, 0, 0, 7375, NULL, NULL, '2026-03-03 20:24:32', '2026-03-03 21:06:21'),
(4, 'ML-2026-3B4CEA', NULL, 2, NULL, 'Fedyna Vanessa Julien', '+2234388682925', NULL, 'Bamako', 'ACI 2000', 'pres du bus', 'confirme', NULL, '2026-03-12 14:08:31', 'cod', '2026-03-12 14:08:31', NULL, 5511, 0, 0, 0, 5511, NULL, NULL, '2026-03-04 20:03:43', '2026-03-12 14:08:31'),
(5, 'ML-2026-B7B522', 3, 2, NULL, 'Fedyna Vanessa Julien', '+2234388682925', NULL, 'Bamako', 'ACI 2000', 'pre dun', 'nouveau', NULL, '2026-03-04 20:38:14', 'cod', NULL, NULL, 8024, 0, 0, 0, 8024, NULL, NULL, '2026-03-04 20:38:14', '2026-03-04 20:38:14'),
(15, 'ML-2026-CE9101', NULL, 3, NULL, 'Fedyna VJ', '+2234386302122', NULL, 'Tombouctou', '', 'pres du bus verte', 'nouveau', NULL, '2026-04-26 12:27:23', 'cod', NULL, NULL, 2513, 0, 0, 0, 2513, NULL, NULL, '2026-04-26 12:27:23', '2026-04-26 12:27:23'),
(16, 'ML-2026-DA6C36', NULL, 4, NULL, 'Fedyna VJ', '+223438682925', NULL, 'Tombouctou', '', NULL, 'confirme', NULL, '2026-04-26 14:29:09', 'cod', '2026-04-26 14:29:09', NULL, 2513, 0, 0, 0, 2513, NULL, NULL, '2026-04-26 12:29:09', '2026-04-26 14:29:09'),
(17, 'ML-2026-E42C8F', NULL, 2, NULL, 'vivi', '+2234388682925', 'vanessafedyna2002@gmail.com', 'Bamako', '', NULL, 'nouveau', NULL, '2026-04-26 15:57:03', 'cod', NULL, NULL, 232324, 0, 0, 0, 232324, NULL, NULL, '2026-04-26 15:57:03', '2026-04-26 15:57:03'),
(18, 'ML-2026-88E2F8', NULL, 2, NULL, 'lea', '+2234388682925', 'vanessafedyna2002@gmail.com', 'Gao', '', NULL, 'livre', NULL, '2026-04-26 16:49:38', 'cod', '2026-04-26 16:49:28', '2026-04-26 16:49:38', 232324, 0, 0, 0, 232324, NULL, NULL, '2026-04-26 16:06:30', '2026-04-26 16:49:38'),
(19, 'ML-2026-BE6C19', NULL, 5, NULL, 'QA Test', '+22370123456', 'qa@example.com', 'Bamako', '', 'Audit local', 'livre', NULL, '2026-04-28 16:49:41', 'cod', '2026-04-28 16:49:35', '2026-04-28 16:49:41', 232324, 0, 0, 0, 232324, NULL, NULL, '2026-04-27 20:28:53', '2026-04-28 16:49:41'),
(20, 'ML-2026-07FF46', NULL, 6, NULL, 'QA Test', '+22370123457', 'qa@example.com', 'Bamako', '', 'Audit local', 'nouveau', NULL, '2026-04-27 20:29:08', 'cod', NULL, NULL, 232324, 0, 0, 0, 232324, NULL, NULL, '2026-04-27 20:29:08', '2026-04-27 20:29:08'),
(21, 'ML-2026-138820', NULL, 7, NULL, 'QA Test', '+22370123458', 'qa@example.com', 'Bamako', '', 'Audit local', 'nouveau', NULL, '2026-04-27 20:29:27', 'cod', NULL, NULL, 232324, 0, 0, 0, 232324, NULL, NULL, '2026-04-27 20:29:27', '2026-04-27 20:29:27'),
(22, 'ML-2026-ADFF5C', NULL, 8, NULL, 'QA Test', '+22370123459', 'qa@example.com', 'Bamako', '', 'Audit local', 'annulee', NULL, '2026-04-28 16:48:55', 'cod', NULL, NULL, 232324, 0, 0, 0, 232324, NULL, NULL, '2026-04-27 20:29:43', '2026-04-28 16:48:55'),
(23, 'ML-2026-2073AF', NULL, 2, 1, 'VIVI', '+2234388682925', 'vanessafedyna2002@gmail.com', 'Bamako', '', NULL, 'annulee', NULL, '2026-04-28 16:45:35', 'cod', '2026-04-28 16:44:44', NULL, 464648, 46464, 0, 0, 418184, NULL, NULL, '2026-04-28 10:23:40', '2026-04-28 16:45:35'),
(24, 'ML-2026-3FEE01', NULL, 5, NULL, 'lolita', '+22370123456', 'vanessafedyna2002@gmail.com', 'Sikasso', '', NULL, 'en_livraison', NULL, '2026-05-03 20:16:35', 'cod', '2026-05-03 20:16:29', NULL, 232324, 0, 0, 0, 232324, NULL, NULL, '2026-04-28 20:30:38', '2026-05-03 20:16:35');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `sku_snapshot` varchar(64) NOT NULL,
  `product_name_snapshot` varchar(255) NOT NULL,
  `unit_price_snapshot` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `qty` int(11) NOT NULL DEFAULT 1,
  `line_total` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `sku_snapshot`, `product_name_snapshot`, `unit_price_snapshot`, `qty`, `line_total`) VALUES
(1, 1, 3, 'ML-518CE3', 'Agbada', 2998, 1, 2998),
(2, 1, 2, 'ML-4E4DE1', 'Boubou', 1864, 1, 1864),
(3, 2, 4, 'ML-B547AC', 'veste', 2513, 1, 2513),
(4, 3, 4, 'ML-B547AC', 'veste', 2513, 1, 2513),
(5, 3, 3, 'ML-518CE3', 'Agbada', 2998, 1, 2998),
(6, 3, 2, 'ML-4E4DE1', 'Boubou', 1864, 1, 1864),
(7, 4, 4, 'ML-B547AC', 'veste', 2513, 1, 2513),
(8, 4, 3, 'ML-518CE3', 'Agbada', 2998, 1, 2998),
(9, 5, 4, 'ML-B547AC', 'veste', 2513, 2, 5026),
(10, 5, 3, 'ML-518CE3', 'Agbada', 2998, 1, 2998),
(29, 15, 4, 'ML-B547AC', 'veste', 2513, 1, 2513),
(30, 16, 4, 'ML-B547AC', 'veste', 2513, 1, 2513),
(31, 17, 25, 'ML-7A3699', 'Boubou', 232324, 1, 232324),
(32, 18, 25, 'ML-7A3699', 'Boubou', 232324, 1, 232324),
(33, 19, 25, 'ML-7A3699', 'Boubou', 232324, 1, 232324),
(34, 20, 25, 'ML-7A3699', 'Boubou', 232324, 1, 232324),
(35, 21, 25, 'ML-7A3699', 'Boubou', 232324, 1, 232324),
(36, 22, 25, 'ML-7A3699', 'Boubou', 232324, 1, 232324),
(37, 23, 25, 'ML-7A3699', 'Boubou', 232324, 2, 464648),
(38, 24, 25, 'ML-7A3699', 'Boubou', 232324, 1, 232324);

-- --------------------------------------------------------

--
-- Table structure for table `order_notes`
--

CREATE TABLE `order_notes` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `note` text DEFAULT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `old_status`, `new_status`, `note`, `changed_by`, `changed_at`) VALUES
(9, 15, NULL, 'nouveau', 'Commande créée', NULL, '2026-04-26 12:27:23'),
(10, 16, NULL, 'nouveau', 'Commande créée', NULL, '2026-04-26 12:29:09'),
(11, 16, 'nouveau', 'confirme', 'Mise a jour (admin)', 1, '2026-04-26 14:29:09'),
(12, 17, NULL, 'nouveau', 'Commande créée', NULL, '2026-04-26 15:57:03'),
(13, 18, NULL, 'nouveau', 'Commande créée', NULL, '2026-04-26 16:06:30'),
(14, 18, 'nouveau', 'confirme', 'Mise a jour (admin)', 1, '2026-04-26 16:49:28'),
(15, 18, 'confirme', 'en_preparation', 'Mise a jour (admin)', 1, '2026-04-26 16:49:32'),
(16, 18, 'en_preparation', 'en_livraison', 'Mise a jour (admin)', 1, '2026-04-26 16:49:35'),
(17, 18, 'en_livraison', 'livre', 'Mise a jour (admin)', 1, '2026-04-26 16:49:38'),
(18, 19, NULL, 'nouveau', 'Commande créée', NULL, '2026-04-27 20:28:53'),
(19, 20, NULL, 'nouveau', 'Commande créée', NULL, '2026-04-27 20:29:08'),
(20, 21, NULL, 'nouveau', 'Commande créée', NULL, '2026-04-27 20:29:27'),
(21, 22, NULL, 'nouveau', 'Commande créée', NULL, '2026-04-27 20:29:43'),
(22, 23, NULL, 'nouveau', 'Commande créée', NULL, '2026-04-28 10:23:40'),
(23, 23, 'nouveau', 'confirme', 'Mise a jour (admin)', 1, '2026-04-28 16:44:44'),
(24, 23, 'confirme', 'annulee', 'Mise a jour (admin)', 1, '2026-04-28 16:45:35'),
(25, 22, 'nouveau', 'annulee', 'Mise a jour (admin)', 1, '2026-04-28 16:48:55'),
(26, 19, 'nouveau', 'confirme', 'Mise a jour (admin)', 1, '2026-04-28 16:49:35'),
(27, 19, 'confirme', 'en_preparation', 'Mise a jour (admin)', 1, '2026-04-28 16:49:38'),
(28, 19, 'en_preparation', 'en_livraison', 'Mise a jour (admin)', 1, '2026-04-28 16:49:40'),
(29, 19, 'en_livraison', 'livre', 'Mise a jour (admin)', 1, '2026-04-28 16:49:41'),
(30, 24, NULL, 'nouveau', 'Commande créée', NULL, '2026-04-28 20:30:38'),
(31, 24, 'nouveau', 'confirme', 'Mise a jour (admin)', 1, '2026-05-03 20:16:29'),
(32, 24, 'confirme', 'en_preparation', 'Mise a jour (admin)', 1, '2026-05-03 20:16:32'),
(33, 24, 'en_preparation', 'en_livraison', 'Mise a jour (admin)', 1, '2026-05-03 20:16:35');

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(10) UNSIGNED NOT NULL,
  `key_name` varchar(60) NOT NULL,
  `title` varchar(160) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `content` longtext NOT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `seo_title` varchar(160) DEFAULT NULL,
  `seo_description` varchar(255) DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `key_name`, `title`, `slug`, `content`, `is_published`, `seo_title`, `seo_description`, `og_image`, `updated_at`) VALUES
(1, 'faq', 'FAQ', 'faq', '<p>Ajoutez votre contenu FAQ ici.</p>', 1, NULL, NULL, NULL, '2026-02-25 10:31:13'),
(2, 'livraison', 'Livraison', 'livraison', '<p>Ajoutez vos informations de livraison ici.</p>', 1, NULL, NULL, NULL, '2026-02-25 10:31:13'),
(3, 'retours', 'Retours', 'retours', '<p>Ajoutez votre politique de retours ici.</p>', 1, NULL, NULL, NULL, '2026-02-25 10:31:13'),
(4, 'about', '?? propos', 'a-propos', '<p>Ajoutez votre page ?? propos ici.</p>', 1, NULL, NULL, NULL, '2026-02-25 10:31:13');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets_otp`
--

CREATE TABLE `password_resets_otp` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `phone` varchar(32) NOT NULL,
  `otp_code` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `sku` varchar(64) NOT NULL,
  `slug` varchar(140) DEFAULT NULL,
  `price` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `seo_title` varchar(160) DEFAULT NULL,
  `seo_description` varchar(255) DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `category` varchar(64) DEFAULT NULL,
  `gender` enum('homme','femme','unisex') NOT NULL DEFAULT 'unisex',
  `stock` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10,
  `image_main` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image1` varchar(255) DEFAULT NULL,
  `image2` varchar(255) DEFAULT NULL,
  `image3` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `featured_rank` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','published') NOT NULL DEFAULT 'pending',
  `material` varchar(255) DEFAULT NULL,
  `style` varchar(255) DEFAULT NULL,
  `occasion` varchar(255) DEFAULT NULL,
  `cut` varchar(255) DEFAULT NULL,
  `finishes` varchar(255) DEFAULT NULL,
  `inspiration` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `sku`, `slug`, `price`, `description`, `seo_title`, `seo_description`, `og_image`, `category`, `gender`, `stock`, `low_stock_threshold`, `image_main`, `image_path`, `image1`, `image2`, `image3`, `is_featured`, `featured_rank`, `is_active`, `created_at`, `status`, `material`, `style`, `occasion`, `cut`, `finishes`, `inspiration`) VALUES
(2, 'Boubou', 'ML-4E4DE1', NULL, 1864, 'Robe traditionnelle d???exception aux finitions luxueuses, confectionn??e dans un tissu riche aux motifs dor??s ??clatants. Sa coupe ample et fluide offre une silhouette majestueuse, sublim??e par des d??tails brod??s et un design raffin??. Id??ale pour les grandes occasions, c??r??monies et ??v??nements prestigieux.', NULL, NULL, NULL, 'robes', 'femme', 12, 3, NULL, 'uploads/products/product_ML-4E4DE1_1772042230_1_1129b1ada4d6.png', 'uploads/products/product_ML-4E4DE1_1772042230_1_1129b1ada4d6.png', 'uploads/products/product_ML-4E4DE1_1772052372_2_777ab746fe0d.png', 'uploads/products/product_ML-4E4DE1_1772052372_3_a90eca145b40.png', 1, NULL, 1, '2026-02-25 12:57:10', 'published', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Agbada', 'ML-518CE3', NULL, 2998, 'robe bleu', NULL, NULL, NULL, 'chemises', 'homme', 15, 3, NULL, 'uploads/products/product_ML-518CE3_1772053433_1_c889626d9fb0.png', 'uploads/products/product_ML-518CE3_1772053433_1_c889626d9fb0.png', 'uploads/products/product_ML-518CE3_1772053433_2_bf7e13ffe62c.png', 'uploads/products/product_ML-518CE3_1772053433_3_bda241bcc411.png', 1, NULL, 1, '2026-02-25 16:03:53', 'published', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'veste', 'ML-B547AC', NULL, 2513, 'Veste marron à manches longues ornée de motifs graphiques blancs d’inspiration ethnique. Coupe droite et structurée, avec col classique et finition soignée pour un style élégant et moderne.', NULL, NULL, NULL, 'vestes', 'homme', 14, 3, NULL, 'uploads/products/product_ML-B547AC_1772409281_1_d555b0b73eaa.png', 'uploads/products/product_ML-B547AC_1772409281_1_d555b0b73eaa.png', 'uploads/products/product_ML-B547AC_1772409281_2_537026936d75.png', 'uploads/products/product_ML-B547AC_1772409281_3_a73578fb8204.png', 1, NULL, 1, '2026-03-01 18:54:41', 'published', NULL, NULL, NULL, NULL, NULL, NULL),
(25, 'Boubou', 'ML-7A3699', NULL, 232324, '2qe22ed2', NULL, NULL, NULL, 'vestes', 'unisex', 17, 3, NULL, 'uploads/products/product_ML-7A3699_1777222164_1_d0d15e630b84.png', 'uploads/products/product_ML-7A3699_1777222164_1_d0d15e630b84.png', NULL, NULL, 1, NULL, 1, '2026-04-26 12:49:24', 'published', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_city` varchar(100) DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scope_key` varchar(190) NOT NULL,
  `window_start` int(11) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `scope_key`, `window_start`, `count`, `updated_at`) VALUES
(1, 'login|::1', 1777432980, 13, '2026-04-28 23:23:10'),
(7, 'register|::1', 1777137660, 3, '2026-04-25 13:21:44'),
(8, 'my_orders|::1', 1777432980, 9, '2026-04-28 23:23:21'),
(13, 'logout|::1', 1777432980, 3, '2026-04-28 23:23:32'),
(17, 'order_track|::1', 1777336200, 6, '2026-04-27 20:30:16'),
(26, 'reviews_create|::1', 1777333200, 4, '2026-04-27 19:40:07'),
(27, 'product_reviews_create|::1', 1777259880, 1, '2026-04-26 23:18:07');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `name`, `city`, `rating`, `message`, `is_approved`, `created_at`) VALUES
(2, 'vanessa', 'Montréal', 4, 'tres facile des commander', 1, '2026-04-27 19:38:02'),
(3, 'lili', 'saint-laurent', 4, 'vestment de quality', 1, '2026-04-27 19:39:17'),
(4, 'fefe', 'laval', 5, 'livraison rapide', 1, '2026-04-27 19:40:07');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key_name` varchar(80) NOT NULL,
  `value` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key_name`, `value`, `updated_at`) VALUES
('admin_whatsapp_number', '', '2026-03-15 21:28:23'),
('free_shipping_threshold', '0', '2026-02-25 15:03:34'),
('maintenance_message', 'Maintenance en cours. Merci de revenir plus tard.', '2026-02-25 15:03:34'),
('maintenance_mode', '0', '2026-02-25 15:03:34'),
('notify_admin_email', 'admin@malishop.com', '2026-02-25 15:03:34'),
('shop_email', 'support@soracollectionmali.com', '2026-02-25 15:03:34'),
('shop_name', 'SORA Collection', '2026-02-25 15:03:34'),
('shop_whatsapp_number', '+22392828271', '2026-05-02 23:28:08'),
('smtp_from_email', '', '2026-02-25 15:03:34'),
('smtp_from_name', '', '2026-02-25 15:03:34'),
('smtp_host', '', '2026-02-25 15:03:34'),
('smtp_pass', '', '2026-02-25 15:03:34'),
('smtp_port', '587', '2026-02-25 15:03:34'),
('smtp_user', '', '2026-02-25 15:03:34'),
('tax_rate_percent', '0', '2026-02-25 15:03:34'),
('whatsapp_access_token', '', '2026-03-15 21:28:23'),
('whatsapp_business_account_id', '', '2026-03-15 21:28:23'),
('whatsapp_enabled', '0', '2026-03-15 21:28:23'),
('whatsapp_phone_number_id', '', '2026-03-15 21:28:23'),
('whatsapp_provider', '', '2026-03-15 21:28:23'),
('whatsapp_template_admin_new_order', '', '2026-03-15 21:28:23'),
('whatsapp_template_order_created', '', '2026-03-15 21:28:23'),
('whatsapp_template_status_update', '', '2026-03-15 21:28:23'),
('whatsapp_webhook_verify_token', '', '2026-03-15 21:28:23');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_zones`
--

CREATE TABLE `shipping_zones` (
  `id` int(10) UNSIGNED NOT NULL,
  `city` varchar(80) NOT NULL,
  `zone` varchar(80) DEFAULT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `related_order_id` int(10) UNSIGNED DEFAULT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `type` enum('add','remove','adjust') NOT NULL,
  `reason` enum('manual_adjust','order','restock','correction') DEFAULT NULL,
  `qty` int(11) NOT NULL,
  `change_qty` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `user_id`, `related_order_id`, `admin_id`, `ip`, `type`, `reason`, `qty`, `change_qty`, `note`, `created_at`) VALUES
(1, 3, NULL, 1, NULL, '::1', 'remove', 'order', 1, -1, 'DÃ©crÃ©ment stock (commande)', '2026-02-28 20:39:05'),
(2, 2, NULL, 1, NULL, '::1', 'remove', 'order', 1, -1, 'DÃ©crÃ©ment stock (commande)', '2026-02-28 20:39:05'),
(3, 4, NULL, 2, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-03-03 16:59:56'),
(4, 4, NULL, 3, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-03-03 20:24:32'),
(5, 3, NULL, 3, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-03-03 20:24:32'),
(6, 2, NULL, 3, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-03-03 20:24:32'),
(7, 4, NULL, 4, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-03-04 20:03:43'),
(8, 3, NULL, 4, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-03-04 20:03:43'),
(9, 4, NULL, 5, NULL, '::1', 'remove', 'order', 2, -2, 'Décrément stock (commande)', '2026-03-04 20:38:14'),
(10, 3, NULL, 5, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-03-04 20:38:14'),
(29, 4, NULL, 15, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-04-26 12:27:23'),
(30, 4, NULL, 16, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-04-26 12:29:09'),
(31, 25, NULL, 17, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-04-26 15:57:03'),
(32, 25, NULL, 18, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-04-26 16:06:30'),
(35, 25, NULL, 19, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-04-27 20:28:53'),
(36, 25, NULL, 20, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-04-27 20:29:08'),
(37, 25, NULL, 21, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-04-27 20:29:27'),
(38, 25, NULL, 22, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-04-27 20:29:43'),
(39, 25, NULL, 23, NULL, '::1', 'remove', 'order', 2, -2, 'Décrément stock (commande)', '2026-04-28 10:23:40'),
(40, 25, NULL, 24, NULL, '::1', 'remove', 'order', 1, -1, 'Décrément stock (commande)', '2026-04-28 20:30:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `name` varchar(190) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','partner','customer') NOT NULL DEFAULT 'customer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `phone`, `name`, `last_name`, `password_hash`, `role`, `is_active`, `created_at`, `deleted_at`) VALUES
(1, 'admin@malishop.com', NULL, 'Admin', '', '$2y$12$lasEojOEW8s5hGqURcgyq.lh9gmxaEiQfbAn/izEYPqJY8btasiLG', 'admin', 1, '2026-02-25 09:55:25', NULL),
(2, 'partner@malishop.com', NULL, 'Partenaire', '', '$2y$10$P/93Cd1zCe2aXycagDeLHu/L3eJWcqQGPYKayXwrb9AqszWow0tAm', 'partner', 1, '2026-02-25 10:18:49', NULL),
(3, NULL, '+14388682925', 'Vanessa', 'julien', '$2y$10$vjoJMIFYD9KuHvB6NFW0cuStNbXqoA/8tlpnXH/vq7kVgh8AZtia.', 'customer', 1, '2026-03-15 20:54:32', NULL),
(4, NULL, '+14386302122', 'Fedeline', 'Germinal', '$2y$10$UOkQvzrycxUQOg/B5yqvvujotmOxjzwF7.Z6DcZ7/xabM0OVW3gPS', 'customer', 1, '2026-04-25 13:21:44', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_audit_logs`
--
ALTER TABLE `admin_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_admin_audit_logs_admin` (`admin_id`),
  ADD KEY `ix_admin_audit_logs_action` (`action`),
  ADD KEY `ix_admin_audit_logs_created` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_categories_slug` (`slug`),
  ADD KEY `ix_categories_active_sort` (`is_active`,`sort_order`,`id`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_contacts_created` (`created_at`),
  ADD KEY `ix_contacts_email` (`email`),
  ADD KEY `ix_contacts_phone` (`phone`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_coupons_code` (`code`),
  ADD KEY `ix_coupons_active_dates` (`is_active`,`starts_at`,`ends_at`),
  ADD KEY `ix_coupons_uses` (`uses_count`,`max_uses`);

--
-- Indexes for table `coupon_categories`
--
ALTER TABLE `coupon_categories`
  ADD PRIMARY KEY (`coupon_id`,`category_id`),
  ADD KEY `ix_cc_category` (`category_id`,`coupon_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_customers_phone` (`phone`),
  ADD KEY `ix_customers_blacklisted` (`is_blacklisted`),
  ADD KEY `ix_customers_created` (`created_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_login_attempts_email_ip` (`email`,`ip`),
  ADD KEY `ix_login_attempts_blocked` (`blocked_until`);

--
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_newsletter_email` (`email`),
  ADD KEY `ix_newsletter_created` (`created_at`);

--
-- Indexes for table `notification_jobs`
--
ALTER TABLE `notification_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_notification_jobs_order` (`order_id`),
  ADD KEY `ix_notification_jobs_status` (`status`,`next_retry_at`),
  ADD KEY `ix_notification_jobs_type` (`type`),
  ADD KEY `ix_notification_jobs_created` (`created_at`),
  ADD KEY `idx_notification_due` (`status`,`next_retry_at`),
  ADD KEY `idx_notification_lock` (`lock_token`);

--
-- Indexes for table `notification_log`
--
ALTER TABLE `notification_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_notification_log_order` (`order_id`),
  ADD KEY `ix_notification_log_type` (`type`),
  ADD KEY `ix_notification_log_created` (`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_orders_order_number` (`order_number`),
  ADD KEY `ix_orders_customer` (`customer_id`),
  ADD KEY `ix_orders_status` (`status`),
  ADD KEY `ix_orders_phone` (`customer_phone`),
  ADD KEY `ix_orders_created` (`created_at`),
  ADD KEY `ix_orders_coupon` (`coupon_id`),
  ADD KEY `ix_orders_customer_profile` (`customer_profile_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_order_items_order` (`order_id`),
  ADD KEY `ix_order_items_product` (`product_id`);

--
-- Indexes for table `order_notes`
--
ALTER TABLE `order_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_order_notes_order` (`order_id`,`created_at`),
  ADD KEY `ix_order_notes_admin` (`admin_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_osh_order_id` (`order_id`),
  ADD KEY `ix_osh_changed_by` (`changed_by`),
  ADD KEY `ix_osh_changed_at` (`changed_at`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_pages_key` (`key_name`),
  ADD UNIQUE KEY `ux_pages_slug` (`slug`),
  ADD KEY `ix_pages_published` (`is_published`,`updated_at`);

--
-- Indexes for table `password_resets_otp`
--
ALTER TABLE `password_resets_otp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_pro_user` (`user_id`),
  ADD KEY `ix_pro_phone` (`phone`),
  ADD KEY `ix_pro_expires` (`expires_at`),
  ADD KEY `ix_pro_used` (`used`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_products_sku` (`sku`),
  ADD UNIQUE KEY `ux_products_slug` (`slug`),
  ADD KEY `ix_products_category` (`category`),
  ADD KEY `ix_products_is_active` (`is_active`),
  ADD KEY `ix_products_featured` (`is_featured`,`featured_rank`),
  ADD KEY `ix_products_low_stock` (`low_stock_threshold`),
  ADD KEY `ix_products_status` (`status`),
  ADD KEY `ix_products_created` (`created_at`),
  ADD KEY `ix_products_gender` (`gender`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`product_id`,`category_id`),
  ADD KEY `ix_pc_category` (`category_id`,`product_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_product_reviews_product_approved_created` (`product_id`,`is_approved`,`created_at`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rate_limits_scope_key` (`scope_key`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_reviews_approved_created` (`is_approved`,`created_at`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key_name`);

--
-- Indexes for table `shipping_zones`
--
ALTER TABLE `shipping_zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_shipping_city_active` (`city`,`is_active`,`sort_order`,`id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_stock_movements_product` (`product_id`),
  ADD KEY `ix_stock_movements_user` (`user_id`),
  ADD KEY `ix_stock_movements_created` (`created_at`),
  ADD KEY `ix_stock_movements_reason` (`reason`),
  ADD KEY `ix_stock_movements_related_order` (`related_order_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_users_email` (`email`),
  ADD UNIQUE KEY `ux_users_phone` (`phone`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_audit_logs`
--
ALTER TABLE `admin_audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_jobs`
--
ALTER TABLE `notification_jobs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `notification_log`
--
ALTER TABLE `notification_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `order_notes`
--
ALTER TABLE `order_notes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_resets_otp`
--
ALTER TABLE `password_resets_otp`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shipping_zones`
--
ALTER TABLE `shipping_zones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `coupon_categories`
--
ALTER TABLE `coupon_categories`
  ADD CONSTRAINT `fk_cc_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cc_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_notes`
--
ALTER TABLE `order_notes`
  ADD CONSTRAINT `fk_order_notes_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_notes_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_osh_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `password_resets_otp`
--
ALTER TABLE `password_resets_otp`
  ADD CONSTRAINT `fk_pro_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `fk_product_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_movements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
