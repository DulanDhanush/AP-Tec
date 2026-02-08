-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Feb 07, 2026 at 02:01 AM
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
-- Database: `aptec_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `approval_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `type` enum('Purchase Order','Leave Request','Contract Renewal') NOT NULL,
  `details` text DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approvals`
--

INSERT INTO `approvals` (`approval_id`, `requester_id`, `type`, `details`, `amount`, `status`, `reviewed_by`, `created_at`) VALUES
(1, 3, 'Leave Request', 'Sick leave for Feb 7', 0.00, 'Approved', 2, '2026-02-07 01:01:16'),
(2, 4, 'Purchase Order', 'Restock 50 HP Toners', 275000.00, 'Pending', NULL, '2026-02-07 01:01:16'),
(3, 5, 'Purchase Order', 'Tools upgrade kit', 15000.00, 'Approved', 2, '2026-02-07 01:01:16'),
(4, 6, 'Leave Request', 'Family function Feb 15', 0.00, 'Pending', NULL, '2026-02-07 01:01:16'),
(5, 1, 'Contract Renewal', 'Canon Wholesale Contract', 0.00, 'Pending', NULL, '2026-02-07 01:01:16'),
(6, 3, 'Purchase Order', 'Emergency Cables', 5000.00, 'Approved', 2, '2026-02-07 01:01:16'),
(7, 7, 'Leave Request', 'Medical appointment', 0.00, 'Rejected', 2, '2026-02-07 01:01:16'),
(8, 4, 'Contract Renewal', 'ISP Agreement', 45000.00, 'Approved', 2, '2026-02-07 01:01:16'),
(9, 5, 'Purchase Order', 'Cleaning Supplies', 2000.00, 'Pending', NULL, '2026-02-07 01:01:16'),
(10, 2, 'Purchase Order', 'New Office Furniture', 150000.00, 'Pending', NULL, '2026-02-07 01:01:16');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `faq_id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(50) DEFAULT 'General',
  `is_visible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`faq_id`, `question`, `answer`, `category`, `is_visible`) VALUES
(1, 'How to reset the printer?', 'Turn off the printer. Hold the Resume button for 5 seconds while turning it back on.', 'Hardware', 1),
(2, 'How to change toner?', 'Open the front cover, pull out the drum unit, and replace the black cartridge.', 'Supplies', 1),
(3, 'Wifi connection lost?', 'Check if the router is blinking red. If so, restart the modem.', 'Network', 1),
(4, 'How to request a new user?', 'Submit a support ticket with the Category \"Software\" and include the new user name.', 'Software', 1),
(5, 'Where do I view invoices?', 'Go to the \"Invoices\" tab in your Customer Dashboard.', 'Billing', 1),
(6, 'What is the urgent support number?', 'You can call +94 11 234 5678 for 24/7 support.', 'General', 1),
(7, 'My screen is blue?', 'This is a BSOD. Please take a photo of the error code and open a ticket immediately.', 'Hardware', 1),
(8, 'How to clear browser cache?', 'Press Ctrl+Shift+Delete and select \"Cached images and files\".', 'Software', 1),
(9, 'Printer printing weird symbols?', 'This is a driver issue. Reinstall the printer driver from the manufacturer site.', 'Hardware', 1),
(10, 'Can I order paper here?', 'Yes, go to \"My Orders\" and click \"New Request\" to order stationery.', 'Supplies', 1);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `alert_threshold` int(11) DEFAULT 5,
  `supplier_id` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`item_id`, `item_name`, `category`, `quantity`, `unit_price`, `alert_threshold`, `supplier_id`, `last_updated`) VALUES
(1, 'HP 85A Toner', 'Toner', 45, 5500.00, 10, 1, '2026-02-07 01:01:16'),
(2, 'Canon Drum Unit', 'Printer Part', 12, 12000.00, 5, 2, '2026-02-07 01:01:16'),
(3, 'A4 Paper Ream (80gsm)', 'Stationery', 200, 850.00, 50, 3, '2026-02-07 01:01:16'),
(4, 'Fuser Assembly (Model X)', 'Printer Part', 3, 25000.00, 2, 4, '2026-02-07 01:01:16'),
(5, 'Network Switch 24-Port', 'Network', 8, 45000.00, 3, 7, '2026-02-07 01:01:16'),
(6, 'Cat6 Ethernet Cable (100m)', 'Network', 15, 6500.00, 5, 8, '2026-02-07 01:01:16'),
(7, 'USB Keyboard', 'Accessory', 30, 1500.00, 10, 7, '2026-02-07 01:01:16'),
(8, 'USB Mouse', 'Accessory', 35, 800.00, 10, 7, '2026-02-07 01:01:16'),
(9, 'Samsung Toner D111S', 'Toner', 20, 6200.00, 5, 9, '2026-02-07 01:01:16'),
(10, 'Printer Roller Kit', 'Printer Part', 8, 3500.00, 3, 4, '2026-02-07 01:01:16'),
(11, 'HDMI Cable (5m)', 'Accessory', 50, 1200.00, 10, 7, '2026-02-07 01:01:16'),
(12, 'Power Supply Unit 500W', 'PC Part', 5, 8500.00, 2, 4, '2026-02-07 01:01:16'),
(13, 'Thermal Paste Tube', 'PC Part', 25, 450.00, 5, 4, '2026-02-07 01:01:16'),
(14, 'SSD 500GB', 'PC Part', 10, 14000.00, 3, 7, '2026-02-07 01:01:16'),
(15, 'RJ45 Connectors (Pack)', 'Network', 100, 1500.00, 10, 8, '2026-02-07 01:01:16');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL,
  `invoice_number` varchar(20) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `tax_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Unpaid','Paid','Overdue','Cancelled') DEFAULT 'Unpaid',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`invoice_id`, `invoice_number`, `order_id`, `customer_id`, `issue_date`, `due_date`, `subtotal`, `tax_amount`, `total_amount`, `status`, `created_by`, `created_at`) VALUES
(1, 'INV-001', 1, 8, '2026-02-01', '2026-02-15', 25000.00, 2500.00, 27500.00, 'Paid', 3, '2026-02-07 01:01:16'),
(2, 'INV-002', 2, 9, '2026-02-02', '2026-02-16', 10909.09, 1090.91, 12000.00, 'Unpaid', 3, '2026-02-07 01:01:16'),
(3, 'INV-003', 3, 10, '2026-02-03', '2026-02-17', 3863.64, 386.36, 4250.00, 'Unpaid', 4, '2026-02-07 01:01:16'),
(4, 'INV-004', 4, 11, '2026-02-04', '2026-02-18', 77272.73, 7727.27, 85000.00, 'Unpaid', 4, '2026-02-07 01:01:16'),
(5, 'INV-005', 5, 8, '2026-02-05', '2026-02-19', 5000.00, 500.00, 5500.00, 'Paid', 3, '2026-02-07 01:01:16'),
(6, 'INV-006', 11, 10, '2026-01-25', '2026-02-08', 2727.27, 272.73, 3000.00, 'Overdue', 5, '2026-02-07 01:01:16'),
(7, 'INV-007', 12, 11, '2026-01-28', '2026-02-11', 8636.36, 863.64, 9500.00, 'Paid', 5, '2026-02-07 01:01:16'),
(8, 'INV-008', 13, 12, '2026-01-30', '2026-02-13', 6363.64, 636.36, 7000.00, 'Paid', 6, '2026-02-07 01:01:16'),
(9, 'INV-009', 14, 13, '2026-02-01', '2026-02-15', 1363.64, 136.36, 1500.00, 'Unpaid', 6, '2026-02-07 01:01:16'),
(10, 'INV-010', 15, 14, '2026-02-02', '2026-02-16', 20000.00, 2000.00, 22000.00, 'Unpaid', 7, '2026-02-07 01:01:16'),
(11, 'INV-011', NULL, 8, '2026-01-15', '2026-01-30', 5000.00, 500.00, 5500.00, 'Paid', 3, '2026-02-07 01:01:16'),
(12, 'INV-012', NULL, 9, '2026-01-10', '2026-01-24', 10000.00, 1000.00, 11000.00, 'Paid', 4, '2026-02-07 01:01:16'),
(13, 'INV-013', NULL, 10, '2025-12-01', '2025-12-15', 8000.00, 800.00, 8800.00, 'Overdue', 5, '2026-02-07 01:01:16'),
(14, 'INV-014', NULL, 11, '2026-01-05', '2026-01-19', 45000.00, 4500.00, 49500.00, 'Paid', 6, '2026-02-07 01:01:16'),
(15, 'INV-015', NULL, 12, '2026-02-07', '2026-02-21', 12000.00, 1200.00, 13200.00, 'Unpaid', 7, '2026-02-07 01:01:16');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `message_text`, `is_read`, `sent_at`) VALUES
(1, 8, 3, 'Hello John, are you available tomorrow?', 1, '2026-02-07 01:01:16'),
(2, 3, 8, 'Hi! Yes, I have a slot at 10 AM.', 1, '2026-02-07 01:01:16'),
(3, 8, 3, 'Great, see you then.', 1, '2026-02-07 01:01:16'),
(4, 9, 4, 'Printer still not working.', 0, '2026-02-07 01:01:16'),
(5, 1, 2, 'Weekly report is ready, sir.', 1, '2026-02-07 01:01:16'),
(6, 2, 1, 'Thanks, please email it.', 1, '2026-02-07 01:01:16'),
(7, 3, 5, 'Can you cover my shift on Monday?', 0, '2026-02-07 01:01:16'),
(8, 5, 3, 'Sorry, I am on leave.', 1, '2026-02-07 01:01:16'),
(9, 10, 1, 'We need to reset our admin password.', 0, '2026-02-07 01:01:16'),
(10, 1, 10, 'Ticket created. Tech will call you.', 1, '2026-02-07 01:01:16'),
(11, 12, 6, 'Lab 2 computers are ready.', 0, '2026-02-07 01:01:16'),
(12, 6, 12, 'Noted, coming to install software.', 1, '2026-02-07 01:01:16'),
(13, 14, 7, 'POS frozen again!', 0, '2026-02-07 01:01:16'),
(14, 7, 14, 'On my way.', 1, '2026-02-07 01:01:16'),
(15, 2, 3, 'Good job on the Alpha Corp ticket.', 1, '2026-02-07 01:01:16');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('Alert','Success','System') DEFAULT 'System',
  `title` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notif_id`, `user_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'System', 'Backup Successful', 'Daily backup completed at 02:00 AM.', 0, '2026-02-07 01:01:16'),
(2, 2, 'Alert', 'Low Stock Warning', 'HP 85A Toner is below threshold.', 0, '2026-02-07 01:01:16'),
(3, 3, 'Success', 'Task Assigned', 'New task TSK-9924 assigned to you.', 0, '2026-02-07 01:01:16'),
(4, 4, 'Alert', 'Overdue Task', 'TSK-9801 is overdue.', 1, '2026-02-07 01:01:16'),
(5, 8, 'System', 'Order Shipped', 'Your order #ORD-2026-001 has been shipped.', 0, '2026-02-07 01:01:16'),
(6, 2, 'Alert', 'Approval Needed', 'Purchase Order #PO-55 requires approval.', 0, '2026-02-07 01:01:16'),
(7, 1, 'System', 'New User Registered', 'Customer \"Tech University\" added.', 1, '2026-02-07 01:01:16'),
(8, 3, 'System', 'Meeting Reminder', 'Team meeting tomorrow at 08:30.', 0, '2026-02-07 01:01:16'),
(9, 9, 'System', 'Ticket Resolved', 'Ticket #TCK-1002 has been marked resolved.', 0, '2026-02-07 01:01:16'),
(10, 5, 'Alert', 'Leave Approved', 'Your leave for Feb 7 is approved.', 1, '2026-02-07 01:01:16'),
(11, 6, 'Success', 'Positive Feedback', 'Customer rated you 5 stars!', 0, '2026-02-07 01:01:16'),
(12, 2, 'System', 'Report Ready', 'Monthly financial report is ready.', 0, '2026-02-07 01:01:16'),
(13, 7, 'Alert', 'Urgent Ticket', 'New urgent ticket at ABC Bank.', 0, '2026-02-07 01:01:16'),
(14, 10, 'System', 'Invoice Due', 'Invoice #INV-006 is overdue.', 0, '2026-02-07 01:01:16'),
(15, 1, 'Alert', 'High CPU Usage', 'Server CPU at 95% for 10 mins.', 0, '2026-02-07 01:01:16');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `order_reference` varchar(20) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') DEFAULT 'Pending',
  `tracking_step` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_reference`, `customer_id`, `order_date`, `total_amount`, `status`, `tracking_step`) VALUES
(1, 'ORD-2026-001', 8, '2026-02-01 04:30:00', 27500.00, 'Delivered', 4),
(2, 'ORD-2026-002', 9, '2026-02-02 06:00:00', 12000.00, 'Shipped', 3),
(3, 'ORD-2026-003', 10, '2026-02-03 03:45:00', 4250.00, 'Processing', 2),
(4, 'ORD-2026-004', 11, '2026-02-04 08:30:00', 85000.00, 'Pending', 1),
(5, 'ORD-2026-005', 8, '2026-02-05 11:15:00', 5500.00, 'Delivered', 4),
(6, 'ORD-2026-006', 12, '2026-02-06 03:00:00', 13500.00, 'Cancelled', 1),
(7, 'ORD-2026-007', 13, '2026-02-06 04:30:00', 45000.00, 'Processing', 2),
(8, 'ORD-2026-008', 14, '2026-02-06 06:30:00', 2500.00, 'Pending', 1),
(9, 'ORD-2026-009', 8, '2026-02-07 03:30:00', 11000.00, 'Processing', 2),
(10, 'ORD-2026-010', 9, '2026-02-07 05:30:00', 60000.00, 'Shipped', 3),
(11, 'ORD-2026-011', 10, '2026-01-25 09:30:00', 3000.00, 'Delivered', 4),
(12, 'ORD-2026-012', 11, '2026-01-28 04:30:00', 9500.00, 'Delivered', 4),
(13, 'ORD-2026-013', 12, '2026-01-30 09:00:00', 7000.00, 'Delivered', 4),
(14, 'ORD-2026-014', 13, '2026-02-01 04:15:00', 1500.00, 'Delivered', 4),
(15, 'ORD-2026-015', 14, '2026-02-02 07:45:00', 22000.00, 'Delivered', 4);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `price_at_purchase` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `quantity`, `price_at_purchase`) VALUES
(1, 1, 1, 5, 5500.00),
(2, 2, 2, 1, 12000.00),
(3, 3, 3, 5, 850.00),
(4, 4, 5, 2, 42500.00),
(5, 5, 1, 1, 5500.00),
(6, 6, 6, 2, 6750.00),
(7, 7, 5, 1, 45000.00),
(8, 8, 7, 1, 1500.00),
(9, 8, 8, 1, 1000.00),
(10, 9, 1, 2, 5500.00),
(11, 10, 4, 2, 25000.00),
(12, 10, 10, 2, 5000.00),
(13, 11, 11, 2, 1500.00),
(14, 12, 12, 1, 9500.00),
(15, 13, 9, 1, 7000.00),
(16, 14, 15, 1, 1500.00),
(17, 15, 2, 1, 12000.00),
(18, 15, 1, 1, 5500.00),
(19, 15, 3, 5, 900.00),
(20, 1, 3, 2, 850.00);

-- --------------------------------------------------------

--
-- Table structure for table `schedule_events`
--

CREATE TABLE `schedule_events` (
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `type` enum('Routine','Urgent','Leave','Meeting') DEFAULT 'Routine',
  `start_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_events`
--

INSERT INTO `schedule_events` (`event_id`, `user_id`, `title`, `type`, `start_date`, `start_time`, `end_time`, `location`, `description`) VALUES
(1, 3, 'Server Maintenance', 'Urgent', '2026-02-07', '09:00:00', '11:00:00', 'Alpha Corp', NULL),
(2, 3, 'Routine Checkup', 'Routine', '2026-02-07', '14:00:00', '16:00:00', 'Beta Ind.', NULL),
(3, 4, 'Printer Setup', 'Routine', '2026-02-07', '10:00:00', '12:00:00', 'City Library', NULL),
(4, 5, 'Sick Leave', 'Leave', '2026-02-07', '00:00:00', '23:59:00', 'Home', NULL),
(5, 6, 'Software Install', 'Routine', '2026-02-07', '13:00:00', '15:00:00', 'Royal College', NULL),
(6, 7, 'ATM Repair', 'Urgent', '2026-02-07', '08:00:00', '10:00:00', 'ABC Bank', NULL),
(7, 3, 'Team Meeting', 'Meeting', '2026-02-08', '08:30:00', '09:30:00', 'AP Tec HQ', NULL),
(8, 4, 'CCTV Audit', 'Routine', '2026-02-08', '10:30:00', '13:00:00', 'Grand Hotel', NULL),
(9, 5, 'Network Cabling', 'Routine', '2026-02-08', '09:00:00', '17:00:00', 'Tech Uni', NULL),
(10, 6, 'Email Config', 'Routine', '2026-02-08', '11:00:00', '12:00:00', 'Alpha Corp', NULL),
(11, 7, 'POS Fix', 'Urgent', '2026-02-08', '15:00:00', '17:00:00', 'Liberty Plaza', NULL),
(12, 3, 'Day Off', 'Leave', '2026-02-09', '00:00:00', '23:59:00', '-', NULL),
(13, 4, 'Router Config', 'Routine', '2026-02-09', '09:00:00', '11:00:00', 'City Library', NULL),
(14, 5, 'Backup Verify', 'Routine', '2026-02-09', '14:00:00', '15:00:00', 'ABC Bank', NULL),
(15, 6, 'Virus Scan', 'Routine', '2026-02-09', '10:00:00', '12:00:00', 'Beta Ind.', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `service_type` enum('Hardware','Stationery','Maintenance','Logistics') DEFAULT 'Hardware',
  `contract_status` enum('Active','Pending','Expired') DEFAULT 'Active',
  `next_delivery_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `company_name`, `contact_person`, `email`, `phone`, `service_type`, `contract_status`, `next_delivery_date`) VALUES
(1, 'HP Supplies Inc.', 'Robert Fox', 'orders@hpsupplies.com', '0112334455', 'Hardware', 'Active', '2026-02-12'),
(2, 'Canon Wholesale', 'Jenny Wilson', 'sales@canonlk.com', '0112998800', 'Hardware', 'Pending', '2026-02-20'),
(3, 'Global Stationery', 'Guy Hawkins', 'info@globalpaper.lk', '0114567890', 'Stationery', 'Active', '2026-02-10'),
(4, 'Tech Parts Ltd', 'Alice Cooper', 'sales@techparts.lk', '0112223333', 'Maintenance', 'Active', '2026-02-15'),
(5, 'Logistics Pro', 'Mark Lee', 'dispatch@logipro.lk', '0771231234', 'Logistics', 'Active', '2026-02-08'),
(6, 'Office Mate', 'Nancy Green', 'orders@officemate.lk', '0115678901', 'Stationery', 'Expired', NULL),
(7, 'Dell Partners', 'Steve Jobs', 'partners@dell.lk', '0117890123', 'Hardware', 'Active', '2026-03-01'),
(8, 'Network Solutions', 'Bill Gates', 'net@solutions.lk', '0113456789', 'Maintenance', 'Active', '2026-02-25'),
(9, 'Ink Masters', 'Tom Cruise', 'refills@inkmasters.lk', '0712345678', 'Stationery', 'Active', '2026-02-14'),
(10, 'Fast Couriers', 'Usain Bolt', 'delivery@fast.lk', '0755555555', 'Logistics', 'Pending', '2026-02-18');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `ticket_id` int(11) NOT NULL,
  `ticket_reference` varchar(20) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `category` enum('Hardware','Software','Network','Supplies','Other') DEFAULT NULL,
  `priority` enum('Normal','High','Urgent') DEFAULT 'Normal',
  `description` text DEFAULT NULL,
  `status` enum('Open','In Progress','Resolved','Closed') DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`ticket_id`, `ticket_reference`, `customer_id`, `subject`, `category`, `priority`, `description`, `status`, `created_at`) VALUES
(1, 'TCK-1001', 8, 'Server Room Overheating', 'Hardware', 'Urgent', 'Temperature warning on Rack A.', 'In Progress', '2026-02-07 01:01:16'),
(2, 'TCK-1002', 9, 'Printer Jammed', 'Hardware', 'Normal', 'Paper stuck in tray 2.', 'Resolved', '2026-02-07 01:01:16'),
(3, 'TCK-1003', 10, 'Cannot Login to ERP', 'Software', 'High', 'Error 503 on login screen.', 'Open', '2026-02-07 01:01:16'),
(4, 'TCK-1004', 11, 'Wi-Fi down in Lobby', 'Network', 'Urgent', 'Guests complaining.', 'In Progress', '2026-02-07 01:01:16'),
(5, 'TCK-1005', 12, 'Need 5 Mouse Pads', 'Supplies', '', 'Standard black pads.', 'Resolved', '2026-02-07 01:01:16'),
(6, 'TCK-1006', 13, 'VPN not connecting', 'Network', 'High', 'Remote staff cannot access.', 'Open', '2026-02-07 01:01:16'),
(7, 'TCK-1007', 14, 'Bill Printer Error', 'Hardware', 'High', 'Printing blank slips.', 'Resolved', '2026-02-07 01:01:16'),
(8, 'TCK-1008', 8, 'New User Account', 'Software', 'Normal', 'Create account for Jane Doe.', 'Resolved', '2026-02-07 01:01:16'),
(9, 'TCK-1009', 9, 'Firewall Alert', 'Network', 'Urgent', 'Suspicious activity detected.', 'Closed', '2026-02-07 01:01:16'),
(10, 'TCK-1010', 10, 'Scanner blurry', 'Hardware', '', 'Glass needs cleaning?', 'Open', '2026-02-07 01:01:16'),
(11, 'TCK-1011', 11, 'Upgrade Windows', 'Software', 'Normal', 'Update reception PC to Win 11.', '', '2026-02-07 01:01:16'),
(12, 'TCK-1012', 12, 'Projector dim', 'Hardware', 'Normal', 'Bulb replacement needed.', 'Open', '2026-02-07 01:01:16'),
(13, 'TCK-1013', 13, 'Request Audit Log', 'Other', 'Normal', 'Need logs for last week.', 'Resolved', '2026-02-07 01:01:16'),
(14, 'TCK-1014', 14, 'Music System Buzzing', 'Hardware', '', 'Audio cable issue?', 'Open', '2026-02-07 01:01:16'),
(15, 'TCK-1015', 8, 'Monitor flickering', 'Hardware', 'Normal', 'Marketing director screen.', 'Resolved', '2026-02-07 01:01:16');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL,
  `level` enum('INFO','WARNING','ERROR') NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`log_id`, `level`, `module`, `message`, `user_id`, `ip_address`, `created_at`) VALUES
(1, 'INFO', 'Auth', 'User admin logged in', 1, '192.168.1.10', '2026-02-07 01:01:16'),
(2, 'INFO', 'Inventory', 'Stock updated for HP Toner', 2, '192.168.1.12', '2026-02-07 01:01:16'),
(3, 'WARNING', 'Database', 'Slow query on orders table', NULL, 'Server-Local', '2026-02-07 01:01:16'),
(4, 'ERROR', 'Auth', 'Failed login attempt for user root', NULL, '10.0.0.5', '2026-02-07 01:01:16'),
(5, 'INFO', 'Tasks', 'Task TSK-9923 marked completed', 3, '192.168.1.20', '2026-02-07 01:01:16'),
(6, 'INFO', 'Auth', 'User tech_mike logged out', 4, '192.168.1.21', '2026-02-07 01:01:16'),
(7, 'WARNING', 'Inventory', 'Low stock alert: Fuser Assembly', NULL, 'System', '2026-02-07 01:01:16'),
(8, 'INFO', 'Billing', 'Invoice INV-001 generated', 3, '192.168.1.20', '2026-02-07 01:01:16'),
(9, 'ERROR', 'Email', 'SMTP Connection Timeout', NULL, 'System', '2026-02-07 01:01:16'),
(10, 'INFO', 'Auth', 'User cust_alpha logged in', 8, '203.115.10.5', '2026-02-07 01:01:16'),
(11, 'INFO', 'Orders', 'Order ORD-2026-009 placed', 8, '203.115.10.5', '2026-02-07 01:01:16'),
(12, 'INFO', 'Support', 'Ticket TCK-1001 created', 8, '203.115.10.5', '2026-02-07 01:01:16'),
(13, 'WARNING', 'Security', 'Multiple failed logins from 192.168.1.50', NULL, 'System', '2026-02-07 01:01:16'),
(14, 'INFO', 'Backup', 'Daily DB Backup completed', 1, 'Server-Local', '2026-02-07 01:01:16'),
(15, 'INFO', 'Auth', 'User owner logged in', 2, '192.168.1.12', '2026-02-07 01:01:16');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `task_reference` varchar(20) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` enum('Pending','In Progress','Waiting for Parts','Completed','Cancelled') DEFAULT 'Pending',
  `priority` enum('Low','Normal','High','Urgent') DEFAULT 'Normal',
  `due_date` date DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `technician_notes` text DEFAULT NULL,
  `proof_image_path` varchar(255) DEFAULT NULL,
  `customer_rating` int(11) DEFAULT NULL CHECK (`customer_rating` between 1 and 5),
  `customer_feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`task_id`, `task_reference`, `title`, `description`, `customer_id`, `assigned_to`, `status`, `priority`, `due_date`, `location`, `technician_notes`, `proof_image_path`, `customer_rating`, `customer_feedback`, `created_at`) VALUES
(1, 'TSK-9923', 'Server Room Overheating', 'AC failure causing high temps in Rack 1.', 8, 3, 'In Progress', 'Urgent', '2026-02-08', 'Alpha Corp HQ', NULL, NULL, NULL, NULL, '2026-02-07 01:01:16'),
(2, 'TSK-9801', 'Printer Not Connecting', 'Main lobby printer offline.', 9, 4, '', 'Normal', '2026-01-15', 'Beta Ind. Lobby', NULL, NULL, 5, 'Quick fix, thanks!', '2026-02-07 01:01:16'),
(3, 'TSK-9924', 'Network Slowdown', 'Internet speed drops at noon.', 10, 3, 'Pending', 'High', '2026-02-09', 'City Lib Admin', NULL, NULL, NULL, NULL, '2026-02-07 01:01:16'),
(4, 'TSK-9925', 'CCTV Maintenance', 'Routine check of 10 cameras.', 11, 5, 'Completed', 'Normal', '2026-02-01', 'Grand Hotel', NULL, NULL, 4, 'Good job but arrived late.', '2026-02-07 01:01:16'),
(5, 'TSK-9926', 'Lab PC Setup', 'Install software on 20 new PCs.', 12, 6, 'In Progress', 'High', '2026-02-10', 'Royal College Lab 2', NULL, NULL, NULL, NULL, '2026-02-07 01:01:16'),
(6, 'TSK-9927', 'ATM Network Check', 'Connection unstable at branch.', 13, 7, 'Pending', 'Urgent', '2026-02-07', 'ABC Bank Fort', NULL, NULL, NULL, NULL, '2026-02-07 01:01:16'),
(7, 'TSK-9928', 'POS System Repair', 'Cash register frozen.', 14, 4, 'Completed', 'Urgent', '2026-02-03', 'Liberty Food Court', NULL, NULL, 5, 'Lifesaver!', '2026-02-07 01:01:16'),
(8, 'TSK-9929', 'Toner Replacement', 'Replace toner in HR dept.', 8, 5, 'Completed', 'Low', '2026-02-05', 'Alpha Corp HR', NULL, NULL, 5, 'Efficient.', '2026-02-07 01:01:16'),
(9, 'TSK-9930', 'Wi-Fi Upgrade', 'Install new APs on 2nd floor.', 9, 6, 'Waiting for Parts', 'Normal', '2026-02-12', 'Beta Ind. Office', NULL, NULL, NULL, NULL, '2026-02-07 01:01:16'),
(10, 'TSK-9931', 'Scanner Driver Issue', 'Cannot scan to PC.', 10, 7, 'Completed', 'Low', '2026-01-20', 'City Lib Reception', NULL, NULL, 3, 'Took too long.', '2026-02-07 01:01:16'),
(11, 'TSK-9932', 'Projector Fix', 'Bulb replacement.', 12, 3, 'Pending', 'Normal', '2026-02-11', 'Royal College Hall', NULL, NULL, NULL, NULL, '2026-02-07 01:01:16'),
(12, 'TSK-9933', 'Server Backup', 'Monthly manual backup.', 13, 4, 'Completed', 'High', '2026-01-30', 'ABC Bank Server Room', NULL, NULL, 5, 'Excellent.', '2026-02-07 01:01:16'),
(13, 'TSK-9934', 'Kiosk Repair', 'Touchscreen not responding.', 14, 5, 'In Progress', 'Normal', '2026-02-07', 'Liberty Entrance', NULL, NULL, NULL, NULL, '2026-02-07 01:01:16'),
(14, 'TSK-9935', 'Email Config', 'Setup Outlook for new hires.', 8, 6, 'Completed', 'Low', '2026-01-25', 'Alpha Corp Sales', NULL, NULL, 4, 'Good.', '2026-02-07 01:01:16'),
(15, 'TSK-9936', 'Virus Removal', 'Admin PC infected.', 9, 7, 'Completed', 'Urgent', '2026-01-28', 'Beta Ind. Accounts', NULL, NULL, 5, 'Very professional.', '2026-02-07 01:01:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('Admin','Owner','Employee','Customer') NOT NULL,
  `avatar_initials` varchar(5) DEFAULT 'U',
  `avatar_color` varchar(20) DEFAULT '#0d2c4d',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('Active','Inactive','Banned') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `full_name`, `email`, `role`, `avatar_initials`, `avatar_color`, `phone`, `address`, `status`, `created_at`) VALUES
(1, 'admin', '123', 'System Administrator', 'admin@aptec.com', 'Admin', 'AD', '#0d2c4d', '0112345678', 'HQ Office, Colombo', 'Active', '2026-02-07 01:01:16'),
(2, 'owner', '123', 'Mr. Director', 'director@aptec.com', 'Owner', 'MD', '#f1c40f', '0771112233', 'Executive Suite, Colombo', 'Active', '2026-02-07 01:01:16'),
(3, 'tech_john', '123', 'John Smith', 'john@aptec.com', 'Employee', 'JS', '#2ecc71', '0714445566', 'Kandy Road, Kiribathgoda', 'Active', '2026-02-07 01:01:16'),
(4, 'tech_mike', '123', 'Mike Williams', 'mike@aptec.com', 'Employee', 'MW', '#3498db', '0765556677', 'Galle Road, Dehiwala', 'Active', '2026-02-07 01:01:16'),
(5, 'tech_sarah', '123', 'Sarah Davis', 'sarah@aptec.com', 'Employee', 'SD', '#9b59b6', '0701234567', 'High Level Rd, Nugegoda', 'Active', '2026-02-07 01:01:16'),
(6, 'tech_david', '123', 'David Miller', 'david@aptec.com', 'Employee', 'DM', '#e67e22', '0759876543', 'Negombo Rd, Wattala', 'Active', '2026-02-07 01:01:16'),
(7, 'tech_emma', '123', 'Emma Wilson', 'emma@aptec.com', 'Employee', 'EW', '#1abc9c', '0785554433', 'Marine Drive, Bambalapitiya', 'Active', '2026-02-07 01:01:16'),
(8, 'cust_alpha', '123', 'Alpha Corp HQ', 'contact@alphacorp.com', 'Customer', 'AC', '#8e44ad', '0112998877', 'World Trade Center, Colombo', 'Active', '2026-02-07 01:01:16'),
(9, 'cust_beta', '123', 'Beta Industries', 'ops@betaind.com', 'Customer', 'BI', '#2c3e50', '0114443322', 'Industrial Zone, Biyagama', 'Active', '2026-02-07 01:01:16'),
(10, 'cust_city', '123', 'City Library', 'admin@citylib.lk', 'Customer', 'CL', '#16a085', '0112223344', 'Public Library, Colombo 07', 'Active', '2026-02-07 01:01:16'),
(11, 'cust_hotel', '123', 'Grand Hotel', 'it@grandhotel.lk', 'Customer', 'GH', '#c0392b', '0115556666', 'Galle Face, Colombo', 'Active', '2026-02-07 01:01:16'),
(12, 'cust_school', '123', 'Royal College', 'it@royal.lk', 'Customer', 'RC', '#2980b9', '0112691234', 'Reid Avenue, Colombo 07', 'Active', '2026-02-07 01:01:16'),
(13, 'cust_bank', '123', 'ABC Bank', 'support@abcbank.lk', 'Customer', 'AB', '#27ae60', '0114777888', 'York Street, Colombo 01', 'Active', '2026-02-07 01:01:16'),
(14, 'cust_mall', '123', 'Liberty Plaza', 'manager@liberty.lk', 'Customer', 'LP', '#d35400', '0112573456', 'Kollupitiya, Colombo 03', 'Active', '2026-02-07 01:01:16'),
(15, 'cust_uni', '123', 'Tech University', 'admin@techuni.ac.lk', 'Customer', 'TU', '#7f8c8d', '0112903903', 'Moratuwa', 'Inactive', '2026-02-07 01:01:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`approval_id`),
  ADD KEY `requester_id` (`requester_id`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`faq_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `order_reference` (`order_reference`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `schedule_events`
--
ALTER TABLE `schedule_events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD UNIQUE KEY `ticket_reference` (`ticket_reference`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD UNIQUE KEY `task_reference` (`task_reference`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `faq_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `schedule_events`
--
ALTER TABLE `schedule_events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approvals`
--
ALTER TABLE `approvals`
  ADD CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`item_id`);

--
-- Constraints for table `schedule_events`
--
ALTER TABLE `schedule_events`
  ADD CONSTRAINT `schedule_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
