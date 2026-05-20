-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 02, 2026 at 12:14 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u977501250_ucc_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `assignment_history`
--

CREATE TABLE `assignment_history` (
  `id` int(11) NOT NULL,
  `computer_id` int(11) DEFAULT NULL,
  `equipment_type` varchar(50) DEFAULT NULL,
  `equipment_table` varchar(50) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `returned_date` timestamp NULL DEFAULT NULL,
  `assigned_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `maintenance_reason` text DEFAULT NULL,
  `maintenance_resolved_date` timestamp NULL DEFAULT NULL,
  `maintenance_resolved_by` int(11) DEFAULT NULL,
  `maintenance_fix_details` text DEFAULT NULL,
  `status` enum('active','returned','transferred') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignment_history`
--

INSERT INTO `assignment_history` (`id`, `computer_id`, `equipment_type`, `equipment_table`, `location_id`, `user_id`, `assigned_date`, `returned_date`, `assigned_by`, `notes`, `maintenance_reason`, `maintenance_resolved_date`, `maintenance_resolved_by`, `maintenance_fix_details`, `status`) VALUES
(17, 199, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(18, 200, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(19, 201, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(20, 202, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(21, 203, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(22, 204, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(23, 205, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(24, 206, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(25, 207, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(26, 208, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(27, 209, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(28, 210, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(29, 211, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(30, 212, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(31, 213, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(32, 214, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(33, 215, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(34, 216, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(35, 217, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(36, 218, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 20:33:52', NULL, 16, 'Bulk assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(37, 199, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 21:08:36', NULL, 16, 'Equipment sent for maintenance', 'Maintenance required', NULL, NULL, NULL, ''),
(38, 199, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 21:09:06', '2026-02-27 21:09:06', 16, 'Bulk action: Set to available', NULL, NULL, NULL, NULL, 'returned'),
(39, 199, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 21:10:25', NULL, 16, 'Equipment sent for maintenance', 'Maintenance required', NULL, NULL, NULL, ''),
(40, 199, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 21:11:01', '2026-02-27 21:11:01', 16, 'Equipment set to available', NULL, NULL, NULL, NULL, 'returned'),
(41, 199, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 21:11:41', NULL, 16, 'Equipment sent for maintenance', 'Maintenance required', NULL, NULL, NULL, ''),
(42, 199, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 21:18:28', '2026-02-27 21:18:28', 16, 'Equipment set to available', NULL, NULL, NULL, NULL, 'returned'),
(43, 199, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 21:18:41', NULL, 16, 'Equipment sent for maintenance', 'Maintenance required', NULL, NULL, NULL, ''),
(44, 199, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 21:20:35', '2026-02-27 21:20:35', 16, 'Equipment set to available', NULL, NULL, NULL, NULL, 'returned'),
(45, 199, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:20:58', NULL, 16, 'Assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(46, 199, 'computer_inventory', 'computer_inventory', 2, 16, '2026-02-27 21:21:25', NULL, 16, 'Assigned to location via All Equipment management - ASPIRE TC 1770', NULL, NULL, NULL, NULL, 'active'),
(47, 223, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(48, 224, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(49, 225, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(50, 226, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(51, 227, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(52, 228, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(53, 229, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(54, 230, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(55, 231, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(56, 232, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(57, 233, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(58, 234, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(59, 235, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(60, 236, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(61, 237, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:29', NULL, 16, 'Bulk assigned to location via All Equipment management - Lenovo All in One', NULL, NULL, NULL, NULL, 'active'),
(62, 222, 'computer_inventory', 'computer_inventory', 15, 16, '2026-02-27 21:22:54', NULL, 16, 'Assigned to location via All Equipment management - AIO Computer', NULL, NULL, NULL, NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `computer_inventory`
--

CREATE TABLE `computer_inventory` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) DEFAULT NULL,
  `property_no` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `article` varchar(100) DEFAULT NULL,
  `computer_set_description` varchar(200) NOT NULL,
  `processor` varchar(100) NOT NULL,
  `ram` varchar(50) NOT NULL,
  `storage` varchar(100) NOT NULL,
  `device_type` enum('Desktop','Laptop','All-in-One') NOT NULL,
  `keyboard_status` enum('OK','Missing','Damaged','Needs Repair') DEFAULT 'OK',
  `mouse_status` enum('OK','Missing','Damaged','Needs Repair') DEFAULT 'OK',
  `power_cord_status` enum('OK','Missing','Damaged','Needs Repair') DEFAULT 'OK',
  `hdmi_status` enum('OK','Missing','Damaged','Needs Repair') DEFAULT 'OK',
  `operating_system` varchar(100) DEFAULT NULL,
  `serial_number` varchar(200) NOT NULL,
  `serial_number_monitor` varchar(200) DEFAULT NULL,
  `serial_number_system` varchar(200) DEFAULT NULL,
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location_id` int(11) DEFAULT NULL,
  `campus` varchar(100) DEFAULT 'Main Campus',
  `remarks` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('available','assigned','maintenance','damaged','retired') DEFAULT 'available',
  `is_condemned` tinyint(1) DEFAULT 0,
  `condemned_date` timestamp NULL DEFAULT NULL,
  `condemned_reason` text DEFAULT NULL,
  `condemned_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cost` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `computer_inventory`
--

INSERT INTO `computer_inventory` (`id`, `item_number`, `property_no`, `purchase_date`, `article`, `computer_set_description`, `processor`, `ram`, `storage`, `device_type`, `keyboard_status`, `mouse_status`, `power_cord_status`, `hdmi_status`, `operating_system`, `serial_number`, `serial_number_monitor`, `serial_number_system`, `condition_status`, `location_id`, `campus`, `remarks`, `image_path`, `status`, `is_condemned`, `condemned_date`, `condemned_reason`, `condemned_by`, `assigned_to`, `assigned_date`, `created_at`, `updated_at`, `cost`) VALUES
(175, 'COM-001', '2023-05-03-0153-089A', NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTKSS003320101143W01', 'DTBK7SP001319021059800', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-30 08:24:37', '2026-02-24 14:49:26', 61500.00),
(176, 'COM-002', '2023-05-03-0154-089A', NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTKSSP003320101CD3W01', 'DTBK7SP001319021A59800', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-30 08:28:30', '2026-02-24 14:49:29', 61500.00),
(177, 'COM-003', '2023-05-03-0155-089A', NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTKSSP003320100C63W01', 'DTBK7SP001319021429800', 'Good', NULL, 'South Campus', 'Mariano, Monica', NULL, 'available', 0, '2026-02-02 05:41:54', 'OS', NULL, NULL, NULL, '2026-01-30 08:59:04', '2026-02-27 20:27:35', 61500.00),
(178, 'COM-004', '2023-05-03-0156-089A', NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTKSSP003320100CD3W01', 'DTBK7SP001319021289800', 'Excellent', NULL, 'South Campus', 'Mariano, Monica', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-30 09:00:09', '2026-02-24 14:49:11', 61500.00),
(179, 'COM-005', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00334601A862X00', 'SN- DTBK7SP00241200E249600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:39:22', '2026-02-27 20:27:38', 61500.00),
(180, 'COM-006', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00341606DB92X00', 'SN- DTBK7SP00241200D619600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:39:51', '2026-02-27 20:28:49', 61500.00),
(181, 'COM-007', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00334601A7C2X00', 'SN- DTBK7SP00241200CD19600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:40:28', '2026-02-27 20:28:49', 61500.00),
(182, 'COM-008', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00341606B2D2X00', 'SN- DTBK7SP00241200D099600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:40:58', '2026-02-27 20:28:49', 61500.00),
(183, 'COM-009', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00334601AB52X00', 'SN- DTBK7SP00241200E1C9600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:41:27', '2026-02-27 20:28:49', 61500.00),
(184, 'COM-010', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00341606CD82X00', 'SN- DTBK7SP00241200D6F9600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:41:54', '2026-02-27 20:28:49', 61500.00),
(185, 'COM-011', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00341606B142X00', 'SN- DTBK7SP00241200E329600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:42:28', '2026-02-27 20:28:49', 61500.00),
(186, 'COM-012', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00341606CDD2X00', 'SN- DTBK7SP00241200DDC9600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:42:54', '2026-02-27 20:28:49', 61500.00),
(187, 'COM-013', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00341606B272X00', 'SN- DTBK7SP00241200E869600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:43:20', '2026-02-27 20:28:49', 61500.00),
(188, 'COM-014', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00341606D9C2X00', 'SN- DTBK7SP00241200E289600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:43:57', '2026-02-27 20:28:49', 61500.00),
(189, 'COM-015', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP003351019832X00', 'SN- DTBK7SP00241200F1B9600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:44:27', '2026-02-27 20:28:49', 61500.00),
(190, 'COM-016', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00334601AF22X00', 'SN- DTBK7SP00241200F119600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:44:52', '2026-02-27 20:28:49', 61500.00),
(191, 'COM-017', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00341606C732X00', 'SN- DTBK7SP00241200DD79600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:45:17', '2026-02-27 20:28:49', 61500.00),
(192, 'COM-018', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTKSSP003320100CB3W01', 'SN- DTBK7SP00241200E889600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:45:43', '2026-02-27 20:28:49', 61500.00),
(193, 'COM-019', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00341606CF72X00', 'SN- DTBK7SP00241200E119600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 03:46:08', '2026-02-27 20:28:49', 61500.00),
(196, 'COM-020', '2023-05-03-0156-089A', NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTKSSP003320100CD3W01', 'DTBK7SP001319021289800', 'Excellent', NULL, 'South Campus', 'Mariano, Monica', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 04:09:34', '2026-02-24 14:48:43', 61500.00),
(197, 'COM-021', '2023-05-03-0162-089A', NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTKSSP003320100FB3W01', 'DTBK7SP0013190210F9800', 'Excellent', NULL, 'South Campus', 'Mariano, Monica', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-02 04:10:39', '2026-02-24 14:48:40', 61500.00),
(199, 'COM-022', '2025-05-08-0348-089A', '2025-05-08', 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600DB72X00', 'DTBLNSP00553901F0E9600', 'Excellent', 2, 'South Campus', 'Victoria, Efren P.', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:00:55', '2026-02-27 21:21:25', 69949.00),
(200, 'COM-023', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600DE42X00', 'DTBLNSP00553901E149600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:06:28', '2026-02-27 20:33:52', 69949.00),
(201, 'COM-024', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600DE52X00', 'DTBLNSP005539010069600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:09:33', '2026-02-27 20:33:52', 69949.00),
(202, 'COM-025', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600AAD2X00', 'DTBLNSP0055390102A9600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:11:19', '2026-02-27 20:33:52', 69949.00),
(203, 'COM-026', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600DB52X00', 'DTBLNSP00553901E849600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:13:33', '2026-02-27 20:33:52', 69949.00),
(204, 'COM-027', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600DD42X00', 'DTBLNSP005539010509600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:14:51', '2026-02-27 20:33:52', 69949.00),
(205, 'COM-028', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600D792X00', 'DTBLNSP00553901F029600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:15:56', '2026-02-27 20:33:52', 69949.00),
(206, 'COM-029', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600D782X00', 'DTBLNSP00553901E769600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:17:56', '2026-02-27 20:33:52', 69949.00),
(207, 'COM-030', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP003546006B12X00', 'DTBLNSP00553901E919600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:20:53', '2026-02-27 20:33:52', 69949.00),
(208, 'COM-031', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600D7E2X00', 'DTBLNSP00553901F239600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:22:10', '2026-02-27 20:33:52', 69949.00),
(209, 'COM-032', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600DD22X00', 'DTBLNSP00553901ED09600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:24:13', '2026-02-27 20:33:52', 69949.00),
(210, 'COM-033', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600A932X00', 'DTBLNSP00553901E199600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:25:59', '2026-02-27 20:33:52', 69949.00),
(211, 'COM-034', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600AAE2X00', 'DTBLNSP00553901E029600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:26:42', '2026-02-27 20:33:52', 69949.00),
(212, 'COM-035', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600DAF2X00', 'DTBLNSP0055390102E9600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:28:29', '2026-02-27 20:33:52', 69949.00),
(213, 'COM-036', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600AA32X00', 'DTBLNSP005539010099600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:29:22', '2026-02-27 20:33:52', 69949.00),
(214, 'COM-037', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600D972X00', 'DTBLNSP00553901F0F9600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:31:07', '2026-02-27 20:33:52', 69949.00),
(215, 'COM-038', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600DB82X00', 'DTBLNSP00553901E7F9600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:32:11', '2026-02-27 20:33:52', 69949.00),
(216, 'COM-039', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600D8D2X00', 'DTBLNSP00553901E729600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:33:21', '2026-02-27 20:33:52', 69949.00),
(217, 'COM-040', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600D9B2X00', 'DTBLNSP00553901E749600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:34:21', '2026-02-27 20:33:52', 69949.00),
(218, 'COM-041', NULL, NULL, 'Computer Package', 'ASPIRE TC 1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00354600A9C2X00', 'DTBLNSP00553901F089600', 'Excellent', 2, 'South Campus', 'Efren P. Victoria', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-18 07:35:08', '2026-02-27 20:33:52', 69949.00),
(220, 'COM-042', '2023-05-03-0162-089A', NULL, 'Computer Package', 'ASPIRE TC1770', 'core i3-13100', '8GB DDR4 3200MHz UDIMM', '256GB M.2 2280 SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTKSSP003320100FB3W01', 'DTBK7SP0013190210F9800', 'Excellent', NULL, 'South Campus', 'Mariano, Monica', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 04:52:46', '2026-02-24 14:48:33', 61500.00),
(221, 'LAP-001', '2025-05-03-0300-089A', NULL, 'Laptop', 'Laptop', 'AMD 5900HX', '16GB', '512GB SSD', 'Laptop', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'NANOCX02M786424', NULL, NULL, 'Excellent', NULL, 'South Campus', 'Mariano, Monica', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 04:54:44', '2026-02-24 14:48:29', 106000.00),
(222, 'ALL-001', '2022-05-03-0124-089A', NULL, 'All-in-One', 'AIO Computer', 'i3-10100', '8GB', '256GB SSD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '20221210', NULL, NULL, 'Excellent', 15, 'Congressional Campus', 'Reyes, Dionie', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 09:56:15', '2026-02-27 21:22:54', 57480.00),
(223, 'ALL-002', '2016-05-03-0047-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P9010RNR', NULL, NULL, 'Excellent', 15, 'Congressional Campus', 'Reyes, Dionie', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 09:57:31', '2026-02-27 21:22:29', 50790.00),
(224, 'ALL-003', '2016-05-03-0019-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '8GB', '500GB HDD', 'Laptop', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P900TVQK', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 09:58:27', '2026-02-27 21:22:29', 50790.00),
(225, 'ALL-004', 'N/A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P9005TVL2', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:00:31', '2026-02-27 21:22:29', 50790.00),
(226, 'ALL-005', '2016-05-03-0064-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P900TVR3', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:02:09', '2026-02-27 21:22:29', 50790.00),
(227, 'ALL-006', 'N/A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P900TVSL', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:02:39', '2026-02-27 21:22:29', 50790.00),
(228, 'ALL-007', '2016-05-03-0050-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P9010RNX', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:03:31', '2026-02-27 21:22:29', 50790.00),
(229, 'ALL-008', '2016-05-03-0039-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P900TVLL', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:04:05', '2026-02-27 21:22:29', 50790.00),
(230, 'ALL-009', '2016-05-03-0034-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P900TVM4', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:04:36', '2026-02-27 21:22:29', 50790.00),
(231, 'ALL-010', 'N/A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P900TVR7', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:05:03', '2026-02-27 21:22:29', 50790.00),
(232, 'ALL-011', '2016-05-03-0030-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P9010RPL', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:05:31', '2026-02-27 21:22:29', 50790.00),
(233, 'ALL-012', '2016-05-03-0016-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', NULL, 'P900TVQQ', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:06:00', '2026-02-27 21:22:29', 50790.00),
(234, 'ALL-013', '2016-05-03-0033-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P900TVNN', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:06:30', '2026-02-27 21:22:29', 50790.00),
(235, 'ALL-014', '2016-05-03-0038-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P9010RPM', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:07:00', '2026-02-27 21:22:29', 50790.00),
(236, 'ALL-015', 'N/A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P900TVS5', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:07:39', '2026-02-27 21:22:29', 50790.00),
(237, 'ALL-016', '2016-05-03-0031-089A', NULL, 'All-in-One', 'Lenovo All in One', 'i3-5005U', '4GB', '500GB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 10', 'P9010RMA', NULL, NULL, 'Excellent', 15, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:08:11', '2026-02-27 21:22:29', 50790.00),
(238, 'COM-043', 'N/A', NULL, 'Computer Package', 'ASPIRE TC 1770', 'i3-13100', '8GB', '256GB SSD + 1TB HDD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'N/A', 'MMTX5SP00334601A862X00', 'DTBK7SP00241200E249600', 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-20 10:50:10', '2026-02-24 14:50:47', 61500.00),
(239, 'ALL-017', '2022-05-03-0118-089A', '2022-05-03', 'All-in-One', 'Computer Package Personal Computer', 'i3-13100', '8GB', '256GB SSD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '20221235', NULL, NULL, 'Excellent', NULL, 'Bagong Silang Campus', 'Gutierez, Raul', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-22 14:28:30', '2026-02-24 19:16:57', 57480.00),
(240, 'ALL-018', '2022-05-03-0119-089A', NULL, 'All-in-One', 'Computer Package Personal Computer', 'i3-10100', '8GB', '256GB SSD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '20221236', NULL, NULL, 'Excellent', NULL, 'Bagong Silang Campus', 'Gutierez, Raul', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-22 14:31:05', '2026-02-24 19:16:57', 0.00),
(241, 'ALL-019', '2022-05-03-0121-089A', NULL, 'All-in-One', 'Computer Package Personal Computer', 'i3-13100', '8GB', '256GB SSD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '20221202', NULL, NULL, 'Excellent', NULL, 'Bagong Silang Campus', 'Gutierez, Raul', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-22 14:35:26', '2026-02-24 19:16:57', 57480.00),
(242, 'ALL-020', '2022-05-03-0125-089A', '2022-05-03', 'All-in-One', 'Computer Package Personal Computer', 'i3-10100', '8GB', '256GB SSD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '20221211', NULL, NULL, 'Excellent', NULL, 'South Campus', 'Macaraeg Jr., Teodoro', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-22 14:45:03', '2026-02-24 14:50:51', 57480.00),
(243, 'LAP-002', '2017-05-03-0091-089A', NULL, 'Laptop', 'Laptop', 'i7-7500', 'N/A', 'N/A', 'Laptop', 'OK', 'OK', 'OK', 'OK', 'N/A', '133967178', NULL, NULL, 'Excellent', NULL, 'South Campus', 'Enriquez, Bernadette', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-22 14:50:55', '2026-02-24 14:52:02', 75800.00),
(244, 'ALL-021', '2024-05-03-0257-089A', '2024-05-03', 'All-in-One', 'HP Pavillion | All-in-One Desktop', 'core i5-12400T', '16GB', '512GB SSD + 1TB HDD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'N/A', '8CC3312N5B', NULL, NULL, 'Excellent', NULL, 'South Campus', 'Mariano, Monica', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-02-24 14:47:29', '2026-02-24 14:47:29', 102171.00),
(245, 'LAP-003', 'N/A', '2022-09-14', 'Laptop', 'Acer Aspire 3 A135-58', 'Core(TM) i3-1115G4', '12GB', '512GB SSD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'NXADDSP00K2190B23A3400', NULL, NULL, 'Excellent', NULL, 'South Campus', 'Mateo, Ryan B.', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-03-01 08:26:33', '2026-03-02 06:31:19', 28000.00);

-- --------------------------------------------------------

--
-- Stand-in structure for view `computer_inventory_detailed`
-- (See below for the actual view)
--
CREATE TABLE `computer_inventory_detailed` (
`id` int(11)
,`item_number` varchar(50)
,`computer_set_description` varchar(200)
,`processor` varchar(100)
,`ram` varchar(50)
,`storage` varchar(100)
,`device_type` enum('Desktop','Laptop','All-in-One')
,`keyboard_status` enum('OK','Missing','Damaged','Needs Repair')
,`mouse_status` enum('OK','Missing','Damaged','Needs Repair')
,`power_cord_status` enum('OK','Missing','Damaged','Needs Repair')
,`hdmi_status` enum('OK','Missing','Damaged','Needs Repair')
,`operating_system` varchar(100)
,`serial_number` varchar(200)
,`condition_status` enum('Excellent','Good','Fair','Poor','Damaged')
,`location_id` int(11)
,`remarks` text
,`status` enum('available','assigned','maintenance','damaged','retired')
,`assigned_to` int(11)
,`assigned_date` timestamp
,`created_at` timestamp
,`updated_at` timestamp
,`location_name` varchar(100)
,`assigned_user` varchar(100)
,`peripheral_summary` varchar(4)
);

-- --------------------------------------------------------

--
-- Table structure for table `condemned_equipment`
--

CREATE TABLE `condemned_equipment` (
  `id` int(11) NOT NULL,
  `model` varchar(100) NOT NULL,
  `category` enum('System Unit','Monitor','All in one','Keyboard','AVR','Other') NOT NULL,
  `serial_number` varchar(200) DEFAULT NULL,
  `equipment_type` enum('monitor_system','keyboard') NOT NULL COMMENT 'Indicates if from monitor/system section or keyboard section',
  `reason_condemned` text DEFAULT NULL,
  `condemned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `condemned_by` int(11) DEFAULT NULL,
  `disposal_status` varchar(50) DEFAULT 'pending',
  `disposal_date` timestamp NULL DEFAULT NULL,
  `disposal_notes` text DEFAULT NULL,
  `estimated_value` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `condemned_equipment`
--

INSERT INTO `condemned_equipment` (`id`, `model`, `category`, `serial_number`, `equipment_type`, `reason_condemned`, `condemned_date`, `condemned_by`, `disposal_status`, `disposal_date`, `disposal_notes`, `estimated_value`, `created_at`, `updated_at`) VALUES
(2, 'Chair', 'Other', NULL, '', 'Broken', '2026-01-27 02:35:15', NULL, 'Complete Condemned', NULL, NULL, 0.00, '2026-01-27 02:55:31', '2026-01-27 03:00:16'),
(3, 'LAPTOP-01', 'Other', 'SN-LAPTOP-001', '', 'Defective Display', '2026-01-27 02:59:29', NULL, 'Complete Condemned', NULL, NULL, 0.00, '2026-01-27 02:59:43', '2026-01-27 03:00:33'),
(4, 'LENOVO', 'All in one', 'PQ00TVMN', 'monitor_system', 'System does not power on / Hardware failure', '2026-02-02 04:47:14', 16, 'pending', NULL, NULL, 0.00, '2026-02-02 04:47:14', '2026-02-02 04:47:14');

-- --------------------------------------------------------

--
-- Table structure for table `consumables`
--

CREATE TABLE `consumables` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('Available','Low','Out of Stock') DEFAULT 'Available',
  `unit` varchar(20) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `brand` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `identification` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consumables`
--

INSERT INTO `consumables` (`id`, `item_name`, `category`, `quantity`, `status`, `unit`, `supplier`, `received_date`, `expiry_date`, `created_at`, `brand`, `serial_number`, `identification`) VALUES
(86, 'Ballpen', 'Office Supplies', 48, 'Available', 'unit', NULL, NULL, NULL, '2026-02-20 11:38:39', 'Panda', NULL, 'BAL-ZMXORQ4R'),
(87, 'A4 Bond Paper', 'Office Supplies', 0, '', 'ream', NULL, NULL, NULL, '2026-02-20 11:38:39', 'Hard Copy', NULL, 'ABO-U2FQQY38'),
(88, 'Letter Bond Paper', 'Office Supplies', 42, 'Available', 'ream', NULL, NULL, NULL, '2026-02-20 11:38:39', 'Hard Copy', NULL, 'LET-AWRGV01G'),
(89, 'HDMI Cable', 'Technical', 20, 'Available', 'pcs', NULL, NULL, NULL, '2026-02-20 11:38:39', 'Ugreen', NULL, 'HDM-F6BHJAA9'),
(90, 'Extension', 'Technical', 14, 'Available', 'pcs', NULL, NULL, NULL, '2026-02-20 11:38:39', 'N/A', NULL, 'EXT-KM0K2I9G'),
(91, 'USB Flash Drive', 'Technical', 11, 'Available', 'pcs', NULL, NULL, NULL, '2026-02-20 11:38:39', 'Sandisk', NULL, 'USB-ALGUY03U'),
(92, 'Printer', 'Technical', 19, 'Available', 'unit', NULL, NULL, NULL, '2026-02-20 11:38:39', 'EPSON', NULL, 'PRI-YWFWMN88'),
(93, 'Legal Bond Paper', 'Office Supplies', 18, 'Available', 'ream', NULL, NULL, NULL, '2026-02-23 09:02:10', 'Hard Copy', NULL, 'ITM-AS7XMGEB'),
(94, 'Computer Package (Monitor)', 'Desktop Computer', 100, 'Available', 'unit', NULL, NULL, NULL, '2026-02-24 17:35:46', 'Acer', NULL, 'COM-BKJ1XOJ8'),
(95, 'All-in-One Computer', 'Desktop Computer', 100, 'Available', 'unit', NULL, NULL, NULL, '2026-02-24 17:35:46', 'HP', NULL, 'ALL-S9Y91Z79'),
(96, 'Light Bulb', 'Furniture', 100, 'Available', 'box', NULL, NULL, NULL, '2026-02-24 17:35:46', 'GE', NULL, 'LIG-7CTY2UAU'),
(97, 'VGA Cable', 'Technical', 100, 'Available', 'pcs', NULL, NULL, NULL, '2026-02-24 17:35:46', 'N/A', NULL, 'VGA-OOMFPLS3'),
(98, 'VGA to HDMI Converter', 'Technical', 100, 'Available', 'pcs', NULL, NULL, NULL, '2026-02-24 17:35:46', 'N/A', NULL, 'VGA-WDD2RLBD'),
(99, 'Shredder', 'Office Supplies', 50, 'Available', 'unit', NULL, NULL, NULL, '2026-02-24 17:35:46', 'Fellowes', NULL, 'SHR-XDTHD4Y8'),
(100, 'Speaker', 'Technical', 50, 'Available', 'unit', NULL, NULL, NULL, '2026-02-24 17:38:08', 'Crown', NULL, 'SPE-UW57DVBY'),
(101, 'Mixer', 'Technical', 30, 'Available', 'unit', NULL, NULL, NULL, '2026-02-24 17:38:08', 'Yamaha', NULL, 'MIX-EVCRV9Z4'),
(102, 'Wireless Microphone', 'Technical', 30, 'Available', 'unit', NULL, NULL, NULL, '2026-02-24 17:38:08', 'Rode', NULL, 'WIR-3ULI8LMU'),
(103, 'Projector', 'Technical', 100, 'Available', 'unit', NULL, NULL, NULL, '2026-02-24 17:40:37', 'EPSON', NULL, 'PRO-BK2DZZFL'),
(104, 'Smartboard', 'Technical', 20, 'Available', 'unit', NULL, NULL, NULL, '2026-02-24 17:40:37', 'Donview', NULL, 'SMA-SWTX1QQ5'),
(105, 'Aircon', 'Office Supplies', 100, 'Available', 'unit', NULL, NULL, NULL, '2026-02-24 17:40:37', 'Samsung', NULL, 'AIR-HCRK3I8O'),
(106, 'Ceiling Fan', 'Office Supplies', 100, 'Available', 'unit', NULL, NULL, NULL, '2026-02-24 17:40:37', 'Standard', NULL, 'CEI-2CPOAR8B');

--
-- Triggers `consumables`
--
DELIMITER $$
CREATE TRIGGER `update_status` BEFORE UPDATE ON `consumables` FOR EACH ROW BEGIN
    IF NEW.quantity <= 0 THEN
        SET NEW.status = 'Critical';
    ELSEIF NEW.quantity <= 10 THEN
        SET NEW.status = 'Low';
    ELSE
        SET NEW.status = 'Available';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `consumable_logs`
--

CREATE TABLE `consumable_logs` (
  `id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consumable_logs`
--

INSERT INTO `consumable_logs` (`id`, `action`, `remarks`, `performed_by`, `created_at`) VALUES
(1, 'user_registered', 'New user registered: James Ryan Gregorio', NULL, '2026-02-20 16:54:07'),
(2, 'user_registered', 'New user registered: James Ryan Gregorio', 18, '2026-02-20 16:54:46'),
(3, 'user_registered', 'New user registered: Reynaldo Carandang', 19, '2026-02-23 08:14:08'),
(4, 'user_registered', 'New user registered: John Arby Morante', 20, '2026-02-23 08:59:45'),
(5, 'user_registered', 'New user registered: Jan Ermaine Ureta', 21, '2026-02-23 09:00:30'),
(6, 'user_registered', 'New user registered: Ronniel Jacob Tablate', 22, '2026-02-23 09:00:45'),
(7, 'user_registered', 'New user registered: Ace Cedrhic Calasin', 23, '2026-02-23 09:01:29'),
(8, 'user_registered', 'New user registered: Ravi Gapol', 24, '2026-02-23 09:21:24'),
(9, 'user_registered', 'New user registered: Catlleya Yee', 25, '2026-02-24 04:37:55');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin Office', 'Administration Office', 1, '2026-02-27 18:40:20', '2026-03-01 14:13:01'),
(2, 'Graduate School Office', 'Graduate School Office', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(3, 'Office of the President', 'Office of the President', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(4, 'Accounting and Finance', 'Accounting and Finance Department', 1, '2026-02-27 18:40:20', '2026-03-01 14:12:46'),
(5, 'HR', 'Human Resources', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(6, 'Guidance and Counseling', 'Guidance and Counseling Office', 1, '2026-02-27 18:40:20', '2026-03-01 14:13:21'),
(7, 'CSD', 'Computer Studies Department', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(8, 'MIS', 'Management Information Systems', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(9, 'Dean\'s Office', 'Dean\'s Office', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(10, 'CLAS Coordinator Office', 'CLAS Coordinator Office', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(11, 'CBA Coordinator Office', 'CBA Coordinator Office', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(12, 'OSA', 'Office of Student Affairs', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(13, 'IT Center', 'Information Technology Center', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(14, 'LabTech', 'Laboratory Technician', 1, '2026-02-27 18:40:20', '2026-02-27 18:45:00'),
(15, 'College of Law Office', 'College of Law Office', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(16, 'Quality Assurance Office', 'Quality Assurance Office', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(17, 'Research Office', 'Research Office', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(18, 'CCJE Office', 'College of Criminal Justice Education', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(19, 'NSTP/ROTC Office', 'NSTP/ROTC Office', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(20, 'P.E Department Office', 'Physical Education Department', 1, '2026-02-27 18:40:20', '2026-02-27 18:40:20'),
(21, 'Registrar', 'Registrar\'s Office', 1, '2026-02-27 21:40:50', '2026-02-27 21:40:50'),
(22, 'Library', 'Library Office', 1, '2026-03-01 14:11:26', '2026-03-01 14:11:26'),
(23, 'Graduate School', 'Graduate School Office', 1, '2026-03-01 14:11:42', '2026-03-01 14:11:42'),
(24, 'Academic Affairs', 'Academic Affairs Office', 1, '2026-03-01 14:12:14', '2026-03-02 06:10:49'),
(25, 'GSD', 'General Services Department', 1, '2026-03-01 14:13:39', '2026-03-01 14:13:39'),
(26, 'Human Resources Mgt. Dept.', 'Human Resources Management Department', 1, '2026-03-01 14:14:10', '2026-03-01 14:14:10'),
(27, 'Planning Office', 'Planning Office', 1, '2026-03-01 14:14:26', '2026-03-01 14:14:26'),
(28, 'Extension Services Dept.', 'Extension Services Department', 1, '2026-03-01 14:14:46', '2026-03-01 14:14:46'),
(29, 'Employability Office', 'Employability Office', 1, '2026-03-01 14:15:03', '2026-03-01 14:15:03'),
(30, 'Alumni Office', 'Alumni Office', 1, '2026-03-01 14:15:12', '2026-03-01 14:15:12'),
(31, 'Scholarship and Grants Office', 'Scholarship and Grants Office', 1, '2026-03-01 14:15:24', '2026-03-01 14:15:24'),
(32, 'Student Affairs and Services Office', 'Student Affairs and Services Office', 1, '2026-03-01 14:15:39', '2026-03-01 14:15:39'),
(33, 'COE', 'College of Education', 1, '2026-03-01 14:15:49', '2026-03-01 14:15:49'),
(34, 'College of Law', 'College of Law', 1, '2026-03-01 14:16:05', '2026-03-01 14:16:05'),
(35, 'CBA', 'College of Business and Accountancy', 1, '2026-03-01 14:17:23', '2026-03-01 14:17:23'),
(36, 'CLAS', 'College of Liberal Arts and Sciences', 1, '2026-03-01 14:17:38', '2026-03-01 14:17:38'),
(37, 'CCJE', 'College of Criminal Justice Education', 1, '2026-03-01 14:17:52', '2026-03-01 14:17:52'),
(38, 'Clinic', 'University Clinic', 1, '2026-03-01 14:18:06', '2026-03-01 14:18:06'),
(39, 'FMSO', 'FMSO', 1, '2026-03-01 14:18:20', '2026-03-01 14:18:20'),
(40, 'GAD Office', 'GAD Office', 1, '2026-03-01 14:18:33', '2026-03-01 14:18:33');

-- --------------------------------------------------------

--
-- Table structure for table `general_equipment`
--

CREATE TABLE `general_equipment` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) DEFAULT NULL,
  `article` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `property_no` varchar(100) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT 0.00,
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location_id` int(11) DEFAULT NULL,
  `campus` varchar(100) DEFAULT 'Main Campus',
  `status` enum('available','assigned','maintenance','condemned') DEFAULT 'available',
  `is_condemned` tinyint(1) DEFAULT 0,
  `condemned_date` timestamp NULL DEFAULT NULL,
  `condemned_reason` text DEFAULT NULL,
  `condemned_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_date` datetime DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `general_equipment`
--

INSERT INTO `general_equipment` (`id`, `item_number`, `article`, `description`, `brand`, `model`, `serial_number`, `property_no`, `cost`, `condition_status`, `location_id`, `campus`, `status`, `is_condemned`, `condemned_date`, `condemned_reason`, `condemned_by`, `assigned_to`, `assigned_date`, `purchase_date`, `warranty_expiry`, `remarks`, `image_path`, `created_at`, `updated_at`) VALUES
(5, 'AIR-003', 'Aircon', 'Window type 2hp inverter', 'Samsung', 'N/A', 'BF7FP9CT100036V', '2022-05-02-0013-089A', 57500.00, 'Excellent', NULL, 'South Campus', 'available', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Carandang Jr., Reynaldo H.', NULL, '2026-02-20 10:34:17', '2026-02-27 20:26:23'),
(6, 'AIR-004', 'Aircon', 'Aircon window type 2.5hp manual', 'GREE', 'N/A', '80010462', '2023-05-02-0028-089A', 64000.00, 'Excellent', NULL, 'South Campus', 'available', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Carandang Jr., Reynaldo H.', NULL, '2026-02-20 10:42:38', '2026-02-27 20:26:26'),
(7, 'BOA-001', 'Board', 'Interactive - Smart Board', 'Donview', 'N/A', 'DIB0EH3086EM1981490001', '2022-05-03-0141-089A', 138750.00, 'Excellent', NULL, 'South Campus', 'available', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Macaraeg Jr., Teodoro', NULL, '2026-02-20 10:44:57', '2026-02-27 20:28:49'),
(8, 'AIR-005', 'Aircon', 'Aircon Split Type, 2HP, wall mounted, inverter', 'CARRIER', 'N/A', '54CQ037600147110170251', '2024-05-02-0097-089A', 71690.00, 'Good', NULL, 'South Campus', 'condemned', 1, '2026-02-24 19:18:34', 'TEST', 16, NULL, NULL, '2020-05-02', NULL, 'Eden, Joshua Jay O.', NULL, '2026-02-22 14:20:23', '2026-02-24 19:18:34');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_adjustments`
--

CREATE TABLE `inventory_adjustments` (
  `id` int(11) NOT NULL,
  `consumable_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `adjusted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kitchen_equipment`
--

CREATE TABLE `kitchen_equipment` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) DEFAULT NULL,
  `property_no` varchar(255) DEFAULT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location_id` int(11) DEFAULT NULL,
  `campus` varchar(100) DEFAULT 'Main Campus',
  `status` enum('available','assigned','maintenance','condemned') DEFAULT 'available',
  `is_condemned` tinyint(1) DEFAULT 0,
  `condemned_date` timestamp NULL DEFAULT NULL,
  `condemned_reason` text DEFAULT NULL,
  `condemned_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_date` datetime DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cost` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_equipment`
--

CREATE TABLE `lab_equipment` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) DEFAULT NULL,
  `property_no` varchar(255) DEFAULT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location_id` int(11) DEFAULT NULL,
  `campus` varchar(100) DEFAULT 'Main Campus',
  `status` enum('available','assigned','maintenance','condemned') DEFAULT 'available',
  `is_condemned` tinyint(1) DEFAULT 0,
  `condemned_date` timestamp NULL DEFAULT NULL,
  `condemned_reason` text DEFAULT NULL,
  `condemned_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_date` datetime DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `calibration_date` date DEFAULT NULL,
  `next_calibration_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cost` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `location_type_id` int(11) DEFAULT NULL,
  `location_type` varchar(50) DEFAULT NULL,
  `campus` varchar(100) DEFAULT 'Main Campus',
  `description` text DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `facilitator_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `location_name`, `location_type_id`, `location_type`, `campus`, `description`, `capacity`, `facilitator_id`, `created_at`, `updated_at`) VALUES
(1, 'Computer Lab 1', 10, 'computer_lab', 'South Campus', 'Main computer laboratory with 30 workstations', 30, NULL, '2026-01-09 06:13:34', '2026-03-01 14:23:54'),
(2, 'Computer Lab 2', 10, 'computer_lab', 'South Campus', 'Secondary computer lab with 20 workstations', 50, NULL, '2026-01-09 06:13:34', '2026-03-01 14:23:47'),
(3, 'Chemistry Lab', NULL, 'regular_lab', 'South Campus', 'Chemistry laboratory for experiments', 20, NULL, '2026-01-09 06:13:34', '2026-03-01 14:30:20'),
(4, 'Physics Lab', NULL, 'regular_lab', 'South Campus', 'Physics laboratory with equipment', 15, NULL, '2026-01-09 06:13:34', '2026-03-01 14:30:20'),
(5, 'DTIM Kitchen', NULL, 'kitchen', 'South Campus', 'Department kitchen facility', 10, NULL, '2026-01-09 06:13:34', '2026-03-01 14:30:20'),
(7, 'Storage Room A', NULL, 'storage', 'South Campus', 'General storage facility', 0, NULL, '2026-01-09 06:13:34', '2026-03-01 14:30:20'),
(8, 'Classroom 101', NULL, 'classroom', 'South Campus', 'Regular classroom', 40, NULL, '2026-01-09 06:13:34', '2026-03-01 14:30:20'),
(9, 'IT Center', 14, 'it-center', 'South Campus', '', 20, NULL, '2026-01-23 02:31:23', '2026-03-01 14:30:20'),
(10, 'EdTech Lab', 15, NULL, 'South Campus', 'Educational Technology Laboratory', 100, NULL, '2026-01-23 02:51:17', '2026-03-01 14:30:20'),
(11, 'LabTech Office', 16, NULL, 'South Campus', 'Bodega :3', 100, NULL, '2026-01-23 06:50:26', '2026-03-01 14:30:20'),
(12, 'Science Laboratory', 17, NULL, 'South Campus', 'SciLab', 120, NULL, '2026-01-23 07:04:28', '2026-03-01 14:30:20'),
(14, 'Admin Room', 20, NULL, 'South Campus', 'OVP Admin', 200, NULL, '2026-02-02 04:13:19', '2026-03-01 14:24:01'),
(15, 'Computer Lab 3', 10, NULL, 'South Campus', 'Tertiary room of CSD', 100, NULL, '2026-02-18 07:36:39', '2026-03-01 14:30:20'),
(16, 'Multimedia Room', 10, NULL, 'South Campus', 'Main room of CSD - Multimedia Room', 50, NULL, '2026-03-01 14:32:40', '2026-03-01 14:32:40');

-- --------------------------------------------------------

--
-- Table structure for table `location_types`
--

CREATE TABLE `location_types` (
  `id` int(11) NOT NULL,
  `type_code` varchar(50) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `campus` varchar(100) DEFAULT 'Main Campus',
  `description` text DEFAULT NULL,
  `icon_class` varchar(50) DEFAULT 'fa-building',
  `color_primary` varchar(7) DEFAULT '#008543',
  `color_secondary` varchar(7) DEFAULT '#20c997',
  `equipment_label` varchar(50) DEFAULT 'Equipment',
  `manager_title` varchar(50) DEFAULT 'Manager',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `location_types`
--

INSERT INTO `location_types` (`id`, `type_code`, `type_name`, `campus`, `description`, `icon_class`, `color_primary`, `color_secondary`, `equipment_label`, `manager_title`, `is_active`, `created_at`, `updated_at`) VALUES
(10, '3rd Floor', 'CSD', 'Main Campus', 'Computer Science Department', 'fa-desktop', '#008543', '#20c997', 'Units', 'Sir Ted', 1, '2026-01-11 15:13:29', '2026-01-11 15:13:29'),
(14, '1st Floor', 'IT Center', 'Main Campus', 'Information Technology Center', 'fa-desktop', '#2ee7ff', '#147aff', 'Equipment', 'Manager', 1, '2026-01-23 02:30:48', '2026-01-27 03:35:54'),
(15, '4th Floor', 'EdTech', 'Main Campus', 'Educational Technology', 'fa-desktop', '#47ff97', '#389fff', 'Units', 'Manager', 1, '2026-01-23 02:49:43', '2026-01-23 02:50:11'),
(16, '3rd Floor', 'LabTech', 'Main Campus', 'Bodega :3', 'fa-desktop', '#6bffb5', '#20c997', 'Units', 'Ryan Mateo', 1, '2026-01-23 06:50:05', '2026-01-23 06:50:05'),
(17, '4th Floor', 'Biology Laboratory', 'Main Campus', 'Science Lab', 'fa-flask', '#d2fe34', '#d4d742', 'Lab', 'Manager', 1, '2026-01-23 07:03:50', '2026-01-23 07:06:47'),
(20, '1st Floor', 'Admin Office', 'South Campus', 'Office of the Vice President for Administration', 'fa-briefcase', '#008543', '#20c997', 'Equipment', 'Manager', 1, '2026-02-02 04:12:51', '2026-02-02 04:12:51');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'The user who receives the notification',
  `type` varchar(50) NOT NULL COMMENT 'request_approved, request_pending, etc.',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID of the related record (request group ID, etc.)',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'request_group, equipment, etc.',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `reference_id`, `reference_type`, `is_read`, `created_at`) VALUES
(1, 18, 'request_submitted', 'Request Submitted', 'Your request #REQ-20260225-336 has been submitted successfully and is pending approval.', '../consumable/consumables.php?highlight=?group_code=REQ-20260225-336', 19, 'request_group', 0, '2026-02-24 19:40:42'),
(2, 15, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): Aircon. Check it on Consumable Management.', '../consumable/consumables.php?highlight=?group_code=REQ-20260225-336', 19, 'request_group', 0, '2026-02-24 19:40:42'),
(3, 16, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): Aircon. Check it on Consumable Management.', '../consumable/consumables.php?highlight=?group_code=REQ-20260225-336', 19, 'request_group', 1, '2026-02-24 19:40:42'),
(4, 19, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): Aircon. Check it on Consumable Management.', '../consumable/consumables.php?highlight=?group_code=REQ-20260225-336', 19, 'request_group', 0, '2026-02-24 19:40:42'),
(5, 18, 'request_submitted', 'Request Submitted', 'Your request #REQ-20260225-851 has been submitted successfully and is pending approval.', '../consumable/consumables.php?highlight=?group_code=REQ-20260225-851', 20, 'request_group', 0, '2026-02-24 19:49:03'),
(6, 15, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): A4 Bond Paper. Check it on Consumable Management.', '../consumable/consumables.php?highlight=?group_code=REQ-20260225-851', 20, 'request_group', 0, '2026-02-24 19:49:03'),
(7, 16, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): A4 Bond Paper. Check it on Consumable Management.', '../consumable/consumables.php?highlight=?group_code=REQ-20260225-851', 20, 'request_group', 1, '2026-02-24 19:49:03'),
(8, 19, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): A4 Bond Paper. Check it on Consumable Management.', '../consumable/consumables.php?highlight=?group_code=REQ-20260225-851', 20, 'request_group', 0, '2026-02-24 19:49:03'),
(9, 18, 'request_submitted', 'Request Submitted', 'Your request #REQ-20260225-139 has been submitted successfully and is pending approval.', '../consumables.php?highlight=REQ-20260225-139#request-history', 21, 'request_group', 0, '2026-02-24 20:03:36'),
(10, 15, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): All-in-One Computer. Check it on Consumable Management.', '../consumables.php?highlight=REQ-20260225-139#request-history', 21, 'request_group', 0, '2026-02-24 20:03:36'),
(11, 16, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): All-in-One Computer. Check it on Consumable Management.', '../consumables.php?highlight=REQ-20260225-139#request-history', 21, 'request_group', 1, '2026-02-24 20:03:36'),
(12, 19, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): All-in-One Computer. Check it on Consumable Management.', '../consumables.php?highlight=REQ-20260225-139#request-history', 21, 'request_group', 0, '2026-02-24 20:03:36'),
(13, 18, 'request_submitted', 'Request Submitted', 'Your request #REQ-20260225-358 has been submitted successfully and is pending approval.', '../admin/consumables.php?highlight=REQ-20260225-358#request-history', 22, 'request_group', 0, '2026-02-24 20:05:03'),
(14, 15, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): Letter Bond Paper. Check it on Consumable Management.', '../admin/consumables.php?highlight=REQ-20260225-358#request-history', 22, 'request_group', 0, '2026-02-24 20:05:03'),
(15, 16, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): Letter Bond Paper. Check it on Consumable Management.', '../admin/consumables.php?highlight=REQ-20260225-358#request-history', 22, 'request_group', 1, '2026-02-24 20:05:03'),
(16, 19, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): Letter Bond Paper. Check it on Consumable Management.', '../admin/consumables.php?highlight=REQ-20260225-358#request-history', 22, 'request_group', 0, '2026-02-24 20:05:03'),
(17, 18, 'request_submitted', 'Request Submitted', 'Your request #REQ-20260225-368 has been submitted successfully and is pending approval.', '../admin/consumables.php?highlight=REQ-20260225-368#request-history', 23, 'request_group', 0, '2026-02-24 20:21:09'),
(18, 15, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): Projector. Check it on Consumable Management.', '../admin/consumables.php?highlight=REQ-20260225-368#request-history', 23, 'request_group', 0, '2026-02-24 20:21:09'),
(19, 16, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): Projector. Check it on Consumable Management.', '../admin/consumables.php?highlight=REQ-20260225-368#request-history', 23, 'request_group', 1, '2026-02-24 20:21:09'),
(20, 19, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): Projector. Check it on Consumable Management.', '../admin/consumables.php?highlight=REQ-20260225-368#request-history', 23, 'request_group', 0, '2026-02-24 20:21:09'),
(21, 18, 'request_submitted', 'Request Submitted', 'Your request #REQ-20260225-313 has been submitted successfully and is pending approval.', '../admin/consumables.php?highlight=REQ-20260225-313#request-history', 24, 'request_group', 0, '2026-02-24 20:37:09'),
(22, 15, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): A4 Bond Paper. Check it on Consumable Management.', '../admin/consumables.php?highlight=REQ-20260225-313#request-history', 24, 'request_group', 0, '2026-02-24 20:37:15'),
(23, 16, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): A4 Bond Paper. Check it on Consumable Management.', '../admin/consumables.php?highlight=REQ-20260225-313#request-history', 24, 'request_group', 1, '2026-02-24 20:37:15'),
(24, 19, 'new_request', 'New Request Received', 'James Ryan Gregorio from LabTech requested 1 item(s): A4 Bond Paper. Check it on Consumable Management.', '../admin/consumables.php?highlight=REQ-20260225-313#request-history', 24, 'request_group', 0, '2026-02-24 20:37:16');

-- --------------------------------------------------------

--
-- Table structure for table `office_equipment`
--

CREATE TABLE `office_equipment` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) DEFAULT NULL,
  `property_no` varchar(255) DEFAULT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location_id` int(11) DEFAULT NULL,
  `campus` varchar(100) DEFAULT 'Main Campus',
  `status` enum('available','assigned','maintenance','condemned') DEFAULT 'available',
  `is_condemned` tinyint(1) DEFAULT 0,
  `condemned_date` timestamp NULL DEFAULT NULL,
  `condemned_reason` text DEFAULT NULL,
  `condemned_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_date` datetime DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cost` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office_equipment`
--

INSERT INTO `office_equipment` (`id`, `item_number`, `property_no`, `equipment_name`, `brand`, `model`, `serial_number`, `condition_status`, `location_id`, `campus`, `status`, `is_condemned`, `condemned_date`, `condemned_reason`, `condemned_by`, `assigned_to`, `assigned_date`, `purchase_date`, `warranty_expiry`, `remarks`, `image_path`, `created_at`, `updated_at`, `cost`) VALUES
(1, 'LAB-001', NULL, 'Laboratory Table', NULL, NULL, 'N/A', 'Excellent', NULL, 'South Campus', 'available', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Macaraeg Jr., Teodoro', NULL, '2026-02-20 10:15:41', '2026-02-27 20:28:49', 0.00),
(2, 'OFF-001', 'N/A', 'Office Table w/ 4 drawer', 'N/A', 'N/A', 'N/A', 'Excellent', NULL, 'South Campus', 'available', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Macaraeg Jr., Teodoro', NULL, '2026-02-20 10:39:06', '2026-02-27 20:28:49', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
(1, 18, 'bb3abce98685f6b0dffaeeb17c8ad82ff17c884bf9e441b8c1871ea644e8a3a8', '2026-02-23 02:41:34', 0, '2026-02-22 17:41:34'),
(2, 18, '105234c778c95cb76dd7d7430b09ad830194a3e0df8994280392dbe98e420040', '2026-02-23 02:42:24', 1, '2026-02-22 17:42:24'),
(3, 19, 'd9c50dcf7756a97a7d104a931803de12b3273b3adbb5ede5d9f83d51e774a2a5', '2026-02-23 17:53:24', 1, '2026-02-23 08:53:24'),
(4, 18, '1a4b28ffb015e5103a330527d859b818c8a659b6de50d194da7da0563578562a', '2026-02-25 04:39:39', 1, '2026-02-24 19:39:39');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `consumable_id` int(11) NOT NULL,
  `serial_number` varchar(50) DEFAULT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `requester_name` varchar(100) NOT NULL,
  `faculty` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `request_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `remarks` varchar(255) DEFAULT NULL,
  `employee` varchar(100) DEFAULT NULL,
  `office` varchar(100) DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `no_supplies` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requested_by` varchar(100) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `supply_officer` varchar(100) DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_groups`
--

CREATE TABLE `request_groups` (
  `id` int(11) NOT NULL,
  `group_code` varchar(20) NOT NULL,
  `employee` varchar(100) NOT NULL,
  `office` varchar(100) NOT NULL,
  `request_date` date NOT NULL,
  `requested_by` varchar(100) NOT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `supply_officer` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Partially Approved') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `request_groups`
--

INSERT INTO `request_groups` (`id`, `group_code`, `employee`, `office`, `request_date`, `requested_by`, `approved_by`, `supply_officer`, `status`, `created_at`) VALUES
(8, 'REQ-20260221-466', 'James Ryan Gregorio', 'LabTech', '2026-02-21', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Approved', '2026-02-20 16:56:34'),
(9, 'REQ-20260222-985', 'James Ryan Gregorio', 'LabTech', '2026-02-22', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Rejected', '2026-02-22 07:41:22'),
(10, 'REQ-20260222-838', 'James Ryan Gregorio', 'LabTech', '2026-02-22', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Approved', '2026-02-22 07:53:06'),
(11, 'REQ-20260223-084', 'James Ryan Gregorio', 'LabTech', '2026-02-23', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Approved', '2026-02-22 17:23:25'),
(12, 'REQ-20260223-351', 'Jan Ermaine Ureta', 'LabTech', '2026-02-23', 'Jan Ermaine Ureta', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Approved', '2026-02-23 09:02:38'),
(13, 'REQ-20260223-965', 'Ronniel Jacob Tablate', 'CSD', '2026-02-23', 'Ronniel Jacob Tablate', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Approved', '2026-02-23 09:03:35'),
(14, 'REQ-20260223-055', 'Ace Cedrhic Calasin', 'MIS', '2026-02-23', 'Ace Cedrhic Calasin', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Approved', '2026-02-23 09:03:46'),
(15, 'REQ-20260223-285', 'Ravi Gapol', 'Communication', '2026-02-23', 'Ravi Gapol', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Approved', '2026-02-23 09:24:06'),
(16, 'REQ-20260224-649', 'Catlleya Yee', 'OVPAA', '2026-02-24', 'Catlleya Yee', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Approved', '2026-02-24 04:40:41'),
(17, 'REQ-20260225-550', 'James Ryan Gregorio', 'LabTech', '2026-02-25', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Rejected', '2026-02-24 17:58:03'),
(18, 'REQ-20260225-534', 'James Ryan Gregorio', 'LabTech', '2026-02-25', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Rejected', '2026-02-24 18:16:30'),
(19, 'REQ-20260225-336', 'James Ryan Gregorio', 'LabTech', '2026-02-25', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Pending', '2026-02-24 19:40:42'),
(20, 'REQ-20260225-851', 'James Ryan Gregorio', 'LabTech', '2026-02-25', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Pending', '2026-02-24 19:49:03'),
(21, 'REQ-20260225-139', 'James Ryan Gregorio', 'LabTech', '2026-02-25', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Pending', '2026-02-24 20:03:36'),
(22, 'REQ-20260225-358', 'James Ryan Gregorio', 'LabTech', '2026-02-25', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Pending', '2026-02-24 20:05:03'),
(23, 'REQ-20260225-368', 'James Ryan Gregorio', 'LabTech', '2026-02-25', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Pending', '2026-02-24 20:21:09'),
(24, 'REQ-20260225-313', 'James Ryan Gregorio', 'LabTech', '2026-02-25', 'James Ryan Gregorio', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Pending', '2026-02-24 20:37:09'),
(25, 'REQ-20260302-544', 'cARANDANG, rEY H', 'Admin Office', '2026-03-02', 'Reynaldo Carandang', 'REYNALDO H. CARANDANG JR.', 'MARVIN Z. GERVACIO', 'Approved', '2026-03-02 06:50:52');

-- --------------------------------------------------------

--
-- Table structure for table `request_items`
--

CREATE TABLE `request_items` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `consumable_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL,
  `checked_by` int(11) DEFAULT NULL,
  `checked_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `request_items`
--

INSERT INTO `request_items` (`id`, `group_id`, `consumable_id`, `quantity`, `description`, `release_date`, `status`, `rejection_reason`, `checked_by`, `checked_at`) VALUES
(8, 8, 86, 2, 'Personal and office use - For office use and personal', '2026-02-21', 'Approved', NULL, NULL, NULL),
(9, 8, 90, 5, 'Electricity use - For office use and personal', '2026-02-21', 'Approved', NULL, NULL, NULL),
(10, 8, 88, 5, 'Office use - For office use and personal', NULL, 'Approved', NULL, NULL, NULL),
(11, 9, 89, 10, 'Computer use - N/A', NULL, 'Rejected', 'TEST LITERAL', 16, '2026-02-24 18:27:20'),
(12, 10, 87, 3, 'Office use - N/A', '2026-02-22', 'Approved', NULL, 15, '2026-02-22 08:37:19'),
(13, 10, 88, 3, 'Office use - N/A', '2026-02-22', 'Approved', NULL, 15, '2026-02-22 08:37:19'),
(14, 10, 92, 2, 'Office use - N/A', NULL, 'Approved', 'KAPOS', 15, '2026-02-22 08:37:19'),
(15, 11, 91, 3, 'For backup files use - N/A', '2026-02-22', 'Approved', NULL, 15, '2026-02-22 17:23:53'),
(16, 12, 86, 1, 'Office use - Low on stock', '2026-02-23', 'Approved', NULL, 19, '2026-02-23 09:10:00'),
(17, 13, 86, 6, 'Office - Office supply ballpen ran out', '2026-02-23', 'Approved', NULL, 19, '2026-02-23 09:11:00'),
(18, 14, 91, 1, 'Testing - For testing', '2026-02-23', 'Approved', NULL, 19, '2026-02-23 09:05:50'),
(19, 14, 92, 1, 'Testing - For testing', '2026-02-23', 'Approved', NULL, 19, '2026-02-23 09:05:50'),
(20, 15, 86, 1, 'For office stocks - For office supplies', '2026-02-23', 'Approved', NULL, 19, '2026-02-23 09:26:45'),
(21, 15, 87, 2, 'For printing documents - For office supplies', '2026-02-23', 'Approved', NULL, 19, '2026-02-23 09:26:45'),
(22, 16, 87, 1, 'Needed - Needed for communications', '2026-02-24', 'Approved', NULL, 19, '2026-02-24 04:42:33'),
(23, 16, 90, 1, 'Needed - Needed for communications', '2026-02-24', 'Approved', NULL, 19, '2026-02-24 04:42:33'),
(24, 16, 93, 2, 'Needed - Needed for communications', '2026-02-24', 'Approved', NULL, NULL, NULL),
(25, 16, 88, 2, 'Needed - Needed for communications', '2026-02-24', 'Approved', NULL, 19, '2026-02-24 04:42:33'),
(26, 17, 105, 3, 'Room use - Room use', NULL, 'Rejected', 'Too many requests', 16, '2026-02-24 18:28:38'),
(27, 18, 105, 5, 'Room use - Room use', NULL, 'Rejected', 'Too many request', 16, '2026-02-24 18:16:50'),
(28, 19, 105, 3, 'Room use - Room use', NULL, 'Pending', NULL, NULL, NULL),
(29, 20, 87, 2, 'Office use - Office use', NULL, 'Pending', NULL, NULL, NULL),
(30, 21, 95, 3, 'Office use - Office use', NULL, 'Pending', NULL, NULL, NULL),
(31, 22, 88, 3, 'Office use - Office use', NULL, 'Pending', NULL, NULL, NULL),
(32, 23, 103, 2, 'Room use - Room use', NULL, 'Pending', NULL, NULL, NULL),
(33, 24, 87, 1, 'Office use - Office use', NULL, 'Pending', NULL, NULL, NULL),
(34, 25, 87, 14, 'OFFICE', '2026-03-02', 'Approved', NULL, 16, '2026-03-02 06:52:05');

-- --------------------------------------------------------

--
-- Table structure for table `transfer_history`
--

CREATE TABLE `transfer_history` (
  `id` int(11) NOT NULL,
  `equipment_ids` text NOT NULL COMMENT 'Comma separated list of IDs',
  `equipment_type` varchar(50) NOT NULL,
  `from_campus` varchar(100) NOT NULL,
  `to_campus` varchar(100) NOT NULL,
  `previous_accountable` varchar(255) DEFAULT NULL,
  `new_accountable` varchar(255) NOT NULL,
  `transfer_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `transferred_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transfer_history`
--

INSERT INTO `transfer_history` (`id`, `equipment_ids`, `equipment_type`, `from_campus`, `to_campus`, `previous_accountable`, `new_accountable`, `transfer_date`, `transferred_by`) VALUES
(12, '175,176', 'computer_lab', 'South Campus', 'South Campus', 'Monica Mariano', 'Dr. Teodoro Macaraeg Jr.', '2026-01-30 08:47:57', 12),
(13, '5,6', 'general', 'South Campus', 'South Campus', 'Dr. Teodoro Macaraeg Jr.', 'Reynaldo H. Carandang, Jr', '2026-02-23 09:56:06', 19),
(14, '222,223', 'computer_lab', 'South Campus', 'Congressional Campus', 'Dr. Teodoro Macaraeg Jr.', 'Dionie Reyes', '2026-02-23 09:57:45', 19),
(15, '239,240,241', 'computer_lab', 'South Campus', 'Bagong Silang Campus', 'Mackay, Eloisa P.', 'Gutierez, Raul', '2026-02-24 19:16:57', 16),
(16, '245', 'computer_lab', 'South Campus', 'South Campus', 'Gregorio, James Ryan', 'Mateo, Ryan B.', '2026-03-02 06:31:19', 19);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `campus_assigned` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `password`, `full_name`, `email`, `department`, `phone`, `role`, `status`, `last_login`, `campus_assigned`, `created_at`, `updated_at`, `reset_token`, `reset_expiry`) VALUES
(15, '$2y$10$fbt5RVPXkt6wNYVBJbQOn.ECCx.KS1DnTp18/KE8IM1ZVeUx4xnnS', 'Ryan Mateo', 'ryemateo25@gmail.com', 'LabTech', '', 'admin', 'active', '2026-02-22 17:43:08', NULL, '2026-02-02 03:23:35', '2026-02-24 18:39:27', NULL, NULL),
(16, '$2y$10$GaYKV.CMfCBS6jLXsxQ1oOicRfM003vHkg4tGCCSD6J6W0zUkLc7O', 'James Ryan Gregorio', 'jamesryangregorio@gmail.com', NULL, NULL, 'admin', 'active', NULL, NULL, '2026-02-02 04:07:34', '2026-02-23 08:54:58', '5cc0b2c47eac124b72525631bfe1ad64993200eacdf34f9b9036e69f9c79c9f3', '2026-02-23 17:54:58'),
(18, '$2y$10$m0BOSxVd.QrTGFzwe9vm8O1pDAfhqxRniTbDIfEy/b0nh2.JE20CO', 'James Ryan Gregorio', 'gregorio.jamesryanbsit2022@gmail.com', 'LabTech', '09619260643', 'user', 'active', '2026-02-28 04:52:19', NULL, '2026-02-20 16:54:46', '2026-02-28 04:52:19', NULL, NULL),
(19, '$2y$10$KBR48N3eWhB1Y8AxezJyNOQsRi6IN/htmew0yuCa7tqcjHD7R.nmG', 'Reynaldo Carandang', 'rhcarandang@ucc-caloocan.edu.ph', 'Admin', '09152551868', 'admin', 'active', '2026-02-23 08:54:34', NULL, '2026-02-23 08:14:08', '2026-02-23 08:54:34', NULL, NULL),
(20, '$2y$10$NGnSrWoq/HcNHlBagtSBxebRf25QA0AI88ATEmMiCfB04cQxQsckG', 'John Arby Morante', 'johnarbymorante@gmail.com', 'HR', '09453977262', 'user', 'active', '2026-02-23 09:00:39', NULL, '2026-02-23 08:59:45', '2026-02-23 09:00:39', NULL, NULL),
(21, '$2y$10$nCfc2Be9VIGt07hhdjm4Gu4vviQFJdYNOIRtOm/mC2r8wlkHPVL/O', 'Jan Ermaine Ureta', 'uretajanermaine.bsit@gmail.com', 'LabTech', '09935155126', 'user', 'active', '2026-02-23 09:01:09', NULL, '2026-02-23 09:00:30', '2026-02-23 09:01:09', NULL, NULL),
(22, '$2y$10$ZTMK.LZRHI.UshntcFMPW.dyoEFowT3QljWcDWIhWO0Un68/35.lK', 'Ronniel Jacob Tablate', 'rj.tablate.bsit2024@gmail.com', 'LabTech', '09959486940', 'user', 'active', '2026-02-23 09:01:39', NULL, '2026-02-23 09:00:45', '2026-02-23 09:10:41', NULL, NULL),
(23, '$2y$10$5gimeEy2TqHprcyTrWyB7O/ZQSSBa5umH61olcWrHkosxHnuOc1Jy', 'Ace Cedrhic Calasin', 'cedrhicace@gmail.com', 'MIS', '09123456789', 'user', 'active', '2026-02-23 09:02:16', NULL, '2026-02-23 09:01:29', '2026-02-23 09:02:16', NULL, NULL),
(24, '$2y$10$dT0R73W//o1DV3quJMr9F.pYa6fVqvQvzY9lwO3VTc0s1BwovZLjS', 'Ravi Gapol', 'ravikrishnagapol@gmail.com', 'Communication', '09943973867', 'user', 'active', '2026-02-23 09:22:11', NULL, '2026-02-23 09:21:24', '2026-02-23 09:22:11', NULL, NULL),
(25, '$2y$10$g0rLiqSiV1CFoGmMiE5VMusc8pprsojUZCaR6Zh1JYQRWgvqQ9hnm', 'Catlleya Yee', 'ccy.commdept@gmail.com', 'OVPAA', '09436178914', 'user', 'active', '2026-02-24 04:38:40', NULL, '2026-02-24 04:37:55', '2026-02-24 04:38:40', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assignment_history`
--
ALTER TABLE `assignment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_assignment_history_computer` (`computer_id`),
  ADD KEY `idx_assignment_history_user` (`user_id`),
  ADD KEY `fk_assignment_location` (`location_id`),
  ADD KEY `idx_maintenance_resolved_by` (`maintenance_resolved_by`),
  ADD KEY `idx_equipment_lookup` (`equipment_type`,`computer_id`);

--
-- Indexes for table `computer_inventory`
--
ALTER TABLE `computer_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_computer_inventory_location` (`location_id`),
  ADD KEY `idx_computer_inventory_assigned` (`assigned_to`),
  ADD KEY `idx_computer_inventory_status` (`status`),
  ADD KEY `fk_computer_inventory_condemned_by` (`condemned_by`);

--
-- Indexes for table `condemned_equipment`
--
ALTER TABLE `condemned_equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `condemned_by` (`condemned_by`);

--
-- Indexes for table `consumables`
--
ALTER TABLE `consumables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `consumable_logs`
--
ALTER TABLE `consumable_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

--
-- Indexes for table `general_equipment`
--
ALTER TABLE `general_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_number` (`item_number`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `fk_general_equipment_condemned_by` (`condemned_by`);

--
-- Indexes for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kitchen_equipment`
--
ALTER TABLE `kitchen_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_number` (`item_number`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `fk_kitchen_equipment_condemned_by` (`condemned_by`);

--
-- Indexes for table `lab_equipment`
--
ALTER TABLE `lab_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_number` (`item_number`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `fk_lab_equipment_condemned_by` (`condemned_by`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `facilitator_id` (`facilitator_id`),
  ADD KEY `idx_locations_type` (`location_type_id`);

--
-- Indexes for table `location_types`
--
ALTER TABLE `location_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `office_equipment`
--
ALTER TABLE `office_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_number` (`item_number`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `fk_office_equipment_condemned_by` (`condemned_by`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consumable_id` (`consumable_id`);

--
-- Indexes for table `request_groups`
--
ALTER TABLE `request_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_code` (`group_code`);

--
-- Indexes for table `request_items`
--
ALTER TABLE `request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `consumable_id` (`consumable_id`),
  ADD KEY `fk_request_items_checked_by` (`checked_by`);

--
-- Indexes for table `transfer_history`
--
ALTER TABLE `transfer_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_department` (`department`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assignment_history`
--
ALTER TABLE `assignment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `computer_inventory`
--
ALTER TABLE `computer_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=246;

--
-- AUTO_INCREMENT for table `condemned_equipment`
--
ALTER TABLE `condemned_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `consumables`
--
ALTER TABLE `consumables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `consumable_logs`
--
ALTER TABLE `consumable_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `general_equipment`
--
ALTER TABLE `general_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kitchen_equipment`
--
ALTER TABLE `kitchen_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `lab_equipment`
--
ALTER TABLE `lab_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `location_types`
--
ALTER TABLE `location_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `office_equipment`
--
ALTER TABLE `office_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `request_groups`
--
ALTER TABLE `request_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `request_items`
--
ALTER TABLE `request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `transfer_history`
--
ALTER TABLE `transfer_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

-- --------------------------------------------------------

--
-- Structure for view `computer_inventory_detailed`
--
DROP TABLE IF EXISTS `computer_inventory_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u977501250_ucc_inventory`@`127.0.0.1` SQL SECURITY DEFINER VIEW `computer_inventory_detailed`  AS SELECT `ci`.`id` AS `id`, `ci`.`item_number` AS `item_number`, `ci`.`computer_set_description` AS `computer_set_description`, `ci`.`processor` AS `processor`, `ci`.`ram` AS `ram`, `ci`.`storage` AS `storage`, `ci`.`device_type` AS `device_type`, `ci`.`keyboard_status` AS `keyboard_status`, `ci`.`mouse_status` AS `mouse_status`, `ci`.`power_cord_status` AS `power_cord_status`, `ci`.`hdmi_status` AS `hdmi_status`, `ci`.`operating_system` AS `operating_system`, `ci`.`serial_number` AS `serial_number`, `ci`.`condition_status` AS `condition_status`, `ci`.`location_id` AS `location_id`, `ci`.`remarks` AS `remarks`, `ci`.`status` AS `status`, `ci`.`assigned_to` AS `assigned_to`, `ci`.`assigned_date` AS `assigned_date`, `ci`.`created_at` AS `created_at`, `ci`.`updated_at` AS `updated_at`, `l`.`location_name` AS `location_name`, `u`.`full_name` AS `assigned_user`, concat(case when `ci`.`keyboard_status` = 'OK' then '✓' else '✗' end,case when `ci`.`mouse_status` = 'OK' then '✓' else '✗' end,case when `ci`.`power_cord_status` = 'OK' then '✓' else '✗' end,case when `ci`.`hdmi_status` = 'OK' then '✓' else '✗' end) AS `peripheral_summary` FROM ((`computer_inventory` `ci` left join `locations` `l` on(`ci`.`location_id` = `l`.`id`)) left join `users` `u` on(`ci`.`assigned_to` = `u`.`id`)) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assignment_history`
--
ALTER TABLE `assignment_history`
  ADD CONSTRAINT `assignment_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_history_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_history_maintenance_resolved_by` FOREIGN KEY (`maintenance_resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assignment_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `computer_inventory`
--
ALTER TABLE `computer_inventory`
  ADD CONSTRAINT `computer_inventory_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `computer_inventory_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_computer_inventory_condemned_by` FOREIGN KEY (`condemned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `condemned_equipment`
--
ALTER TABLE `condemned_equipment`
  ADD CONSTRAINT `condemned_equipment_ibfk_1` FOREIGN KEY (`condemned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `consumable_logs`
--
ALTER TABLE `consumable_logs`
  ADD CONSTRAINT `consumable_logs_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `general_equipment`
--
ALTER TABLE `general_equipment`
  ADD CONSTRAINT `fk_general_equipment_condemned_by` FOREIGN KEY (`condemned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `general_equipment_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `general_equipment_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `kitchen_equipment`
--
ALTER TABLE `kitchen_equipment`
  ADD CONSTRAINT `fk_kitchen_equipment_condemned_by` FOREIGN KEY (`condemned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `kitchen_equipment_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `kitchen_equipment_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_equipment`
--
ALTER TABLE `lab_equipment`
  ADD CONSTRAINT `fk_lab_equipment_condemned_by` FOREIGN KEY (`condemned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_equipment_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_equipment_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `locations_ibfk_1` FOREIGN KEY (`location_type_id`) REFERENCES `location_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `locations_ibfk_2` FOREIGN KEY (`facilitator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `office_equipment`
--
ALTER TABLE `office_equipment`
  ADD CONSTRAINT `fk_office_equipment_condemned_by` FOREIGN KEY (`condemned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `office_equipment_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `office_equipment_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`consumable_id`) REFERENCES `consumables` (`id`);

--
-- Constraints for table `request_items`
--
ALTER TABLE `request_items`
  ADD CONSTRAINT `fk_request_items_checked_by` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `request_items_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `request_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_items_ibfk_2` FOREIGN KEY (`consumable_id`) REFERENCES `consumables` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
