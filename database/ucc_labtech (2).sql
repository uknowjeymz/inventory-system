-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 19, 2026 at 03:57 AM
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
-- Database: `ucc_labtech`
--

-- --------------------------------------------------------

--
-- Table structure for table `assignment_history`
--

CREATE TABLE `assignment_history` (
  `id` int(11) NOT NULL,
  `computer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `returned_date` timestamp NULL DEFAULT NULL,
  `assigned_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','returned','transferred') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `computer_inventory`
--

CREATE TABLE `computer_inventory` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) NOT NULL,
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
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location_id` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('available','assigned','maintenance','damaged','retired') DEFAULT 'available',
  `is_condemned` tinyint(1) DEFAULT 0,
  `condemned_date` timestamp NULL DEFAULT NULL,
  `condemned_reason` text DEFAULT NULL,
  `condemned_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `computer_inventory`
--

INSERT INTO `computer_inventory` (`id`, `item_number`, `computer_set_description`, `processor`, `ram`, `storage`, `device_type`, `keyboard_status`, `mouse_status`, `power_cord_status`, `hdmi_status`, `operating_system`, `serial_number`, `condition_status`, `location_id`, `remarks`, `status`, `is_condemned`, `condemned_date`, `condemned_reason`, `condemned_by`, `assigned_to`, `assigned_date`, `created_at`, `updated_at`) VALUES
(1, '1', 'COMLAB1-PC 01', 'Intel Core i5', '8GB', '256GB SSD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'SN-DTBK7SP00241200DDC9600', 'Good', 1, 'Sample computer equipment', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-09 06:13:34', '2026-01-18 06:29:29'),
(2, '2', 'COMLAB1-PC 02', 'Intel Core i5', '8GB', '256GB SSD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'SN-DTBK7SP00241200E329600', 'Good', 1, 'Sample computer equipment', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-09 06:13:34', '2026-01-18 06:29:29'),
(3, '3', 'COMLAB1-PC 03', 'Intel Core i5', '8GB', '256GB SSD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'SN-DTBK7SP00241200D6F9600', 'Good', 1, 'Sample computer equipment', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-09 06:13:34', '2026-01-18 06:29:29'),
(4, '4', 'COMLAB1-PC 04', 'Intel Core i5', '8GB', '256GB SSD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'SN-DTBK7SP00241200E1C9600', 'Good', 1, 'Sample computer equipment', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-09 06:13:34', '2026-01-18 06:29:29'),
(5, '5', 'COMLAB1-PC 05', 'Intel Core i5', '8GB', '256GB SSD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'SN-DTBK7SP00241200D619600', 'Good', 1, 'Sample computer equipment', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-09 06:13:34', '2026-01-18 06:29:29'),
(6, '6', 'COMLAB2-PC 01', 'Intel Core i5', '8GB', '256GB SSD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'SN-DTBK7SP00241200E249600', 'Good', NULL, 'Sample computer equipment', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-09 06:13:34', '2026-01-11 15:14:17'),
(7, '7', 'COMLAB2-PC 02', 'Intel Core i5', '8GB', '256GB SSD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'SN-DTBK7SP00241200D099600', 'Good', NULL, 'Sample computer equipment', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-09 06:13:34', '2026-01-11 15:14:20'),
(8, '8', 'COMLAB2-PC 03', 'Intel Core i5', '8GB', '256GB SSD', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'SN-DTBK7SP00241200CD19600', 'Good', NULL, 'Sample computer equipment', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-09 06:13:34', '2026-01-11 15:14:27'),
(9, '9', 'LAPTOP-01', 'Intel Core i7', '16GB', '512GB SSD', 'Laptop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'SN-LAPTOP-001', 'Good', NULL, 'Sample computer equipment', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-09 06:13:34', '2026-01-11 15:15:40'),
(10, '10', 'AIO-01', 'Intel Core i5', '8GB', '256GB SSD', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'Windows 11', 'SN-AIO-001', 'Good', NULL, 'Sample computer equipment', '', 1, '2026-01-18 12:11:37', 'Nabasa', 1, NULL, NULL, '2026-01-09 06:13:34', '2026-01-18 12:11:37');

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
  `disposal_status` enum('pending','disposed','recycled','repaired','donated') DEFAULT 'pending',
  `disposal_date` timestamp NULL DEFAULT NULL,
  `disposal_notes` text DEFAULT NULL,
  `estimated_value` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `general_equipment`
--

CREATE TABLE `general_equipment` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location_id` int(11) DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kitchen_equipment`
--

CREATE TABLE `kitchen_equipment` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location_id` int(11) DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kitchen_equipment`
--

INSERT INTO `kitchen_equipment` (`id`, `item_number`, `equipment_name`, `brand`, `model`, `serial_number`, `condition_status`, `location_id`, `status`, `is_condemned`, `condemned_date`, `condemned_reason`, `condemned_by`, `assigned_to`, `assigned_date`, `purchase_date`, `warranty_expiry`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'KIT001', 'Commercial Refrigerator', 'Samsung', 'RF28R7351SG', 'SN-REF-001', 'Good', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2023-01-15', '2026-01-15', 'Sample kitchen equipment', '2026-01-09 06:13:34', '2026-01-11 15:15:40'),
(2, 'KIT002', 'Microwave Oven', 'Panasonic', 'NN-SN966S', 'SN-MIC-002', 'Good', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2023-01-15', '2026-01-15', 'Sample kitchen equipment', '2026-01-09 06:13:34', '2026-01-11 15:15:40'),
(3, 'KIT003', 'Electric Stove', 'GE', 'JB735SPSS', 'SN-STO-003', 'Good', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2023-01-15', '2026-01-15', 'Sample kitchen equipment', '2026-01-09 06:13:34', '2026-01-11 15:15:40'),
(4, 'KIT004', 'Dishwasher', 'Bosch', 'SHPM88Z75N', 'SN-DIS-004', 'Good', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2023-01-15', '2026-01-15', 'Sample kitchen equipment', '2026-01-09 06:13:34', '2026-01-11 15:15:40'),
(5, 'KIT005', 'Coffee Machine', 'Breville', 'BES870XL', 'SN-COF-005', 'Good', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2023-01-15', '2026-01-15', 'Sample kitchen equipment', '2026-01-09 06:13:34', '2026-01-11 15:15:40');

-- --------------------------------------------------------

--
-- Table structure for table `lab_equipment`
--

CREATE TABLE `lab_equipment` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location_id` int(11) DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_equipment`
--

INSERT INTO `lab_equipment` (`id`, `item_number`, `equipment_name`, `brand`, `model`, `serial_number`, `condition_status`, `location_id`, `status`, `is_condemned`, `condemned_date`, `condemned_reason`, `condemned_by`, `assigned_to`, `assigned_date`, `purchase_date`, `warranty_expiry`, `calibration_date`, `next_calibration_date`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'LAB001', 'Microscope', 'Olympus', 'CX23', 'SN-MIC-001', 'Good', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2023-01-05', '2026-01-05', NULL, NULL, 'Sample lab equipment', '2026-01-09 06:13:34', '2026-01-11 15:15:40'),
(2, 'LAB002', 'Centrifuge', 'Eppendorf', '5424R', 'SN-CEN-002', 'Good', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2023-01-05', '2026-01-05', NULL, NULL, 'Sample lab equipment', '2026-01-09 06:13:34', '2026-01-11 15:15:40'),
(3, 'LAB003', 'pH Meter', 'Hanna Instruments', 'HI-2020', 'SN-PH-003', 'Good', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2023-01-05', '2026-01-05', NULL, NULL, 'Sample lab equipment', '2026-01-09 06:13:34', '2026-01-11 15:15:40'),
(4, 'LAB004', 'Analytical Balance', 'Mettler Toledo', 'XS205', 'SN-BAL-004', 'Good', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2023-01-05', '2026-01-05', NULL, NULL, 'Sample lab equipment', '2026-01-09 06:13:34', '2026-01-11 15:14:32'),
(5, 'LAB005', 'Incubator', 'Thermo Fisher', 'Heratherm', 'SN-INC-005', 'Good', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2023-01-05', '2026-01-05', NULL, NULL, 'Sample lab equipment', '2026-01-09 06:13:34', '2026-01-11 15:14:29');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `location_type_id` int(11) DEFAULT NULL,
  `location_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `facilitator_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `location_name`, `location_type_id`, `location_type`, `description`, `capacity`, `facilitator_id`, `created_at`, `updated_at`) VALUES
(1, 'Computer Lab 1', 10, 'computer_lab', 'Main computer laboratory with 30 workstations', 30, 2, '2026-01-09 06:13:34', '2026-01-11 15:15:20'),
(2, 'Computer Lab 2', 10, 'computer_lab', 'Secondary computer lab with 25 workstations', 25, 6, '2026-01-09 06:13:34', '2026-01-18 09:53:17'),
(3, 'Chemistry Lab', NULL, 'regular_lab', 'Chemistry laboratory for experiments', 20, 4, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(4, 'Physics Lab', NULL, 'regular_lab', 'Physics laboratory with equipment', 15, 5, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(5, 'DTIM Kitchen', NULL, 'kitchen', 'Department kitchen facility', 10, 6, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(7, 'Storage Room A', NULL, 'storage', 'General storage facility', 0, NULL, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(8, 'Classroom 101', NULL, 'classroom', 'Regular classroom', 40, NULL, '2026-01-09 06:13:34', '2026-01-09 06:13:34');

-- --------------------------------------------------------

--
-- Table structure for table `location_types`
--

CREATE TABLE `location_types` (
  `id` int(11) NOT NULL,
  `type_code` varchar(50) NOT NULL,
  `type_name` varchar(100) NOT NULL,
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

INSERT INTO `location_types` (`id`, `type_code`, `type_name`, `description`, `icon_class`, `color_primary`, `color_secondary`, `equipment_label`, `manager_title`, `is_active`, `created_at`, `updated_at`) VALUES
(10, '3rd Floor', 'CSD', 'Computer Science Department', 'fa-desktop', '#008543', '#20c997', 'Units', 'Sir Ted', 1, '2026-01-11 15:13:29', '2026-01-11 15:13:29'),
(11, '1st Floor', 'Barracks', 'Crim', 'fa-building', '#1d0085', '#9190ea', 'Equipment', 'Sir Jose', 1, '2026-01-18 06:26:59', '2026-01-18 09:53:05');

-- --------------------------------------------------------

--
-- Table structure for table `office_equipment`
--

CREATE TABLE `office_equipment` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location_id` int(11) DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$O97BmEq16/fy6syozQFUceFmK2MbPSdESHRfDv0yf0DMxo0Sqfd66', 'System Administrator', 'admin@ucc.edu.ph', 'admin', '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(2, 'john_doe', '$2y$10$4Ky7WnvB6ck4zrvqe8kP7ezG2VV0Djo9/Uy9S.Y4FKH/pM7EQtMdm', 'John Doe', 'john.doe@ucc.edu.ph', 'user', '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(3, 'jane_smith', '$2y$10$Om4eNZa.zlz9.fq2n2EIVuCT.Max95XLtmmf4fzsJpOVa1ErRF.m.', 'Jane Smith', 'jane.smith@ucc.edu.ph', 'user', '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(4, 'mike_johnson', '$2y$10$hcskqp..rcevJPZzkM2w0eqqaveHnI0U7GYN9K47DZbnUh2/YGx9i', 'Mike Johnson', 'mike.johnson@ucc.edu.ph', 'user', '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(5, 'sarah_wilson', '$2y$10$ovQicSNRVL8SbdV27xG35ODk6PDAo/Gp2BjNZHDfPngDC3lmG2FF6', 'Sarah Wilson', 'sarah.wilson@ucc.edu.ph', 'user', '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(6, 'david_brown', '$2y$10$uxTX2T.d1UDpp2xDGFoP2eSSq1YL7Xq71zvV6EdE/FLDBbqlbsU6m', 'David Brown', 'david.brown@ucc.edu.ph', 'user', '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(7, '', '$2y$10$PyX9qoN1zeduZrb5sa3CLelPTC0lR5zIyjzAD87GeO4IOf83JfTYG', 'Renz Rodriguez', 'kill@gmail.com', 'user', '2026-01-18 06:38:07', '2026-01-18 06:38:07');

-- --------------------------------------------------------

--
-- Structure for view `computer_inventory_detailed`
--
DROP TABLE IF EXISTS `computer_inventory_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `computer_inventory_detailed`  AS SELECT `ci`.`id` AS `id`, `ci`.`item_number` AS `item_number`, `ci`.`computer_set_description` AS `computer_set_description`, `ci`.`processor` AS `processor`, `ci`.`ram` AS `ram`, `ci`.`storage` AS `storage`, `ci`.`device_type` AS `device_type`, `ci`.`keyboard_status` AS `keyboard_status`, `ci`.`mouse_status` AS `mouse_status`, `ci`.`power_cord_status` AS `power_cord_status`, `ci`.`hdmi_status` AS `hdmi_status`, `ci`.`operating_system` AS `operating_system`, `ci`.`serial_number` AS `serial_number`, `ci`.`condition_status` AS `condition_status`, `ci`.`location_id` AS `location_id`, `ci`.`remarks` AS `remarks`, `ci`.`status` AS `status`, `ci`.`assigned_to` AS `assigned_to`, `ci`.`assigned_date` AS `assigned_date`, `ci`.`created_at` AS `created_at`, `ci`.`updated_at` AS `updated_at`, `l`.`location_name` AS `location_name`, `u`.`full_name` AS `assigned_user`, concat(case when `ci`.`keyboard_status` = 'OK' then '✓' else '✗' end,case when `ci`.`mouse_status` = 'OK' then '✓' else '✗' end,case when `ci`.`power_cord_status` = 'OK' then '✓' else '✗' end,case when `ci`.`hdmi_status` = 'OK' then '✓' else '✗' end) AS `peripheral_summary` FROM ((`computer_inventory` `ci` left join `locations` `l` on(`ci`.`location_id` = `l`.`id`)) left join `users` `u` on(`ci`.`assigned_to` = `u`.`id`)) ;

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
  ADD KEY `idx_assignment_history_user` (`user_id`);

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
-- Indexes for table `general_equipment`
--
ALTER TABLE `general_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_number` (`item_number`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `fk_general_equipment_condemned_by` (`condemned_by`);

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_code` (`type_code`);

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assignment_history`
--
ALTER TABLE `assignment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `computer_inventory`
--
ALTER TABLE `computer_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `condemned_equipment`
--
ALTER TABLE `condemned_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `general_equipment`
--
ALTER TABLE `general_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kitchen_equipment`
--
ALTER TABLE `kitchen_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lab_equipment`
--
ALTER TABLE `lab_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `location_types`
--
ALTER TABLE `location_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `office_equipment`
--
ALTER TABLE `office_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assignment_history`
--
ALTER TABLE `assignment_history`
  ADD CONSTRAINT `assignment_history_ibfk_1` FOREIGN KEY (`computer_id`) REFERENCES `computer_inventory` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_history_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
