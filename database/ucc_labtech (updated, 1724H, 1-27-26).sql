-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 28, 2026 at 10:24 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
  `item_number` varchar(50) DEFAULT NULL,
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

INSERT INTO `computer_inventory` (`id`, `item_number`, `article`, `computer_set_description`, `processor`, `ram`, `storage`, `device_type`, `keyboard_status`, `mouse_status`, `power_cord_status`, `hdmi_status`, `operating_system`, `serial_number`, `condition_status`, `location_id`, `campus`, `remarks`, `image_path`, `status`, `is_condemned`, `condemned_date`, `condemned_reason`, `condemned_by`, `assigned_to`, `assigned_date`, `created_at`, `updated_at`, `cost`) VALUES
(63, '0', 'Computer Pack', 'CPU: Fortress | Monitor: 27\" IPS Ultra-Slim Super-Slim Brand: Viewsonic', 'i7-1165G7', 'N/A', 'N/A', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'N/A', 'MX-270221100699', 'Excellent', NULL, 'Bagong Silang Campus', 'Noemi C. Jose', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 03:54:23', '2026-01-28 03:54:23', 100600.00),
(64, '0', 'Computer Pack', 'All-in-one computer system - LENOVO | PN: 2016-05-03-0049-089A', 'N/A', 'N/A', 'N/A', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'N/A', 'P9010RLU', 'Good', NULL, 'South Campus', 'N/A', 'uploads/equipment/computer_1769572888_4112.webp', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:01:28', '2026-01-28 04:01:28', 50790.00),
(65, 'COM-001', 'Computer Pack', 'All-in-one computer system - LENOVO | PN: 2016-05-03-0011-089B', 'N/A', 'N/A', 'N/A', 'All-in-One', 'OK', 'OK', 'OK', 'OK', 'N/A', 'P900TVQX', 'Excellent', NULL, 'Camarin Campus', 'N/A', 'uploads/equipment/computer_1769574501_9338.webp', 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:28:21', '2026-01-28 04:28:21', 50790.00),
(88, 'COM-002', 'Computer', 'Computer Package', 'i5-13400', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2024-05-03-0172-089A', '', 1, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 08:45:42', 68640.00),
(89, 'COM-003', 'Computer', 'Computer Package', 'i5-13401', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2024-05-03-0171-089A', '', 1, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 08:45:42', 68640.00),
(90, 'COM-004', 'Computer', 'Computer Package', 'i5-13402', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2024-05-03-0170-089A', '', 1, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 08:45:42', 68640.00),
(91, 'COM-005', 'Computer', 'Computer Package', 'i5-13403', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2024-05-03-0169-089A', '', 1, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 08:45:42', 68640.00),
(92, 'COM-006', 'Computer', 'Computer Package', 'i5-13404', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2024-05-03-0168-089A', '', 1, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 08:45:42', 68640.00),
(93, 'COM-007', 'Computer', 'Computer Package', 'i5-13405', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2024-05-03-0167-089A', '', 1, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 08:45:42', 68640.00),
(94, 'COM-008', 'Computer', 'Computer Package', 'i5-13406', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2024-05-03-0166-089A', '', 1, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 08:45:42', 68640.00),
(95, 'COM-009', 'Computer', 'Computer Package', 'i5-13407', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2024-05-03-0165-089A', '', 1, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 08:45:42', 68640.00),
(96, 'COM-010', 'Computer', 'Computer Package', 'i5-13408', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2024-05-03-0164-089A', '', 1, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 08:45:42', 68640.00),
(97, 'COM-011', 'Computer', 'Computer Package', 'i5-13409', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2024-05-03-0163-089A', '', NULL, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 04:55:54', 68640.00),
(98, 'COM-012', 'Computer', 'Computer Package', 'i5-13410', '8GB DDR4 3200MHz', '25GB', 'Desktop', 'OK', 'OK', 'OK', 'OK', 'Windows 11', '2023-05-03-0162-089A', '', NULL, 'Congressional Campus', 'Dr. Teodoro Macaraeg', NULL, 'available', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-28 04:55:54', '2026-01-28 04:55:54', 68640.00);

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
(2, 'Chair', 'Other', NULL, '', 'Broken', '2026-01-27 02:35:15', 1, 'Complete Condemned', NULL, NULL, 0.00, '2026-01-27 02:55:31', '2026-01-27 03:00:16'),
(3, 'LAPTOP-01', 'Other', 'SN-LAPTOP-001', '', 'Defective Display', '2026-01-27 02:59:29', 1, 'Complete Condemned', NULL, NULL, 0.00, '2026-01-27 02:59:43', '2026-01-27 03:00:33');

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
(25, 'Notebook', 'Stationery', 200, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'ClassMate', NULL, 'ST001'),
(26, 'Pen', 'Stationery', 500, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Reynolds', NULL, 'ST002'),
(27, 'Pencil', 'Stationery', 300, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Faber-Castell', NULL, 'ST003'),
(28, 'Eraser', 'Stationery', 150, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Camlin', NULL, 'ST004'),
(29, 'Ruler', 'Stationery', 100, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Maped', NULL, 'ST005'),
(30, 'Sharpener', 'Stationery', 120, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Faber-Castell', NULL, 'ST006'),
(31, 'Glue Stick', 'Stationery', 80, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Pritt', NULL, 'ST007'),
(32, 'Highlighter', 'Stationery', 90, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Staedtler', NULL, 'ST008'),
(33, 'Whiteboard Marker', 'Stationery', 100, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Staedtler', NULL, 'ST009'),
(34, 'Stapler', 'Stationery', 40, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Swingline', NULL, 'ST010'),
(35, 'Paper Clips', 'Stationery', 500, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'ACCO', NULL, 'ST011'),
(36, 'Notebook Divider', 'Stationery', 60, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'ClassMate', NULL, 'ST012'),
(37, 'Sticky Notes', 'Stationery', 200, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Post-it', NULL, 'ST013'),
(38, 'Sketch Book', 'Stationery', 50, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Camel', NULL, 'ST014'),
(39, 'Art Paper', 'Stationery', 40, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Camlin', NULL, 'ST015'),
(40, 'Laptop', 'Technical', 20, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Dell', 'LT1001', 'TE001'),
(41, 'Desktop Computer', 'Technical', 10, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'HP', 'DC1002', 'TE002'),
(42, 'Projector', 'Technical', 5, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Epson', 'PJ1003', 'TE003'),
(43, 'Printer', 'Technical', 5, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Canon', 'PR1004', 'TE004'),
(44, 'Scanner', 'Technical', 3, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Brother', 'SC1005', 'TE005'),
(45, 'Router', 'Technical', 10, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'TP-Link', 'RT1006', 'TE006'),
(46, 'External Hard Drive', 'Technical', 15, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Seagate', 'HD1007', 'TE007'),
(47, 'USB Flash Drive', 'Technical', 50, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'SanDisk', NULL, 'TE008'),
(48, 'Monitor', 'Technical', 15, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Samsung', 'MN1009', 'TE009'),
(49, 'Keyboard', 'Technical', 25, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Logitech', NULL, 'TE010'),
(50, 'Mouse', 'Technical', 25, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Logitech', NULL, 'TE011'),
(51, 'HDMI Cable', 'Technical', 40, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Belkin', NULL, 'TE012'),
(52, 'Ethernet Cable', 'Technical', 60, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'TP-Link', NULL, 'TE013'),
(53, 'Power Strip', 'Technical', 20, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Panasonic', NULL, 'TE014'),
(54, 'Microphone', 'Technical', 8, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Shure', 'MC1015', 'TE015'),
(55, 'Speakers', 'Technical', 6, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Logitech', 'SP1016', 'TE016'),
(56, 'Webcam', 'Technical', 10, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Logitech', 'WC1017', 'TE017'),
(57, 'Headphones', 'Technical', 15, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Sony', 'HP1018', 'TE018'),
(58, 'Beaker 100ml', 'Lab', 30, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Pyrex', NULL, 'LB001'),
(59, 'Beaker 250ml', 'Lab', 20, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Pyrex', NULL, 'LB002'),
(60, 'Test Tube', 'Lab', 50, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Pyrex', NULL, 'LB003'),
(61, 'Microscope', 'Lab', 5, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Olympus', 'MC1020', 'LB004'),
(62, 'Petri Dish', 'Lab', 100, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Corning', NULL, 'LB005'),
(63, 'Bunsen Burner', 'Lab', 10, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'LabTech', 'BB1021', 'LB006'),
(64, 'Pipette', 'Lab', 40, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Eppendorf', NULL, 'LB007'),
(65, 'Safety Goggles', 'Lab', 60, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', '3M', NULL, 'LB008'),
(66, 'Lab Coat', 'Lab', 50, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Portwest', NULL, 'LB009'),
(67, 'Oil Paint Set', 'Art', 15, 'Available', 'sets', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Camel', NULL, 'AR001'),
(68, 'Watercolor Set', 'Art', 20, 'Available', 'sets', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Winsor & Newton', NULL, 'AR002'),
(69, 'Brush Set', 'Art', 25, 'Available', 'sets', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Princeton', NULL, 'AR003'),
(70, 'Canvas Board', 'Art', 30, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Canson', NULL, 'AR004'),
(71, 'Palette', 'Art', 20, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Master Art', NULL, 'AR005'),
(72, 'Clay Pack', 'Art', 40, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Staedtler', NULL, 'AR006'),
(73, 'Chalk', 'Stationery', 100, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Dixon', NULL, 'MS001'),
(74, 'Whiteboard', 'Stationery', 10, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Quartet', 'WB1022', 'MS002'),
(75, 'Clock', 'Stationery', 5, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Casio', NULL, 'MS003'),
(76, 'Project File', 'Stationery', 60, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'ClassMate', NULL, 'MS004'),
(77, 'File Organizer', 'Stationery', 40, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Esselte', NULL, 'MS005'),
(78, 'Label Sticker', 'Stationery', 200, 'Available', 'pcs', NULL, NULL, NULL, '2026-01-23 01:24:22', 'Avery', NULL, 'MS006'),
(79, 'Ballpen', 'Panda', 0, 'Out of Stock', '5', NULL, NULL, NULL, '2026-01-27 08:04:43', 'Panda', NULL, 'BP001'),
(80, 'Computer Package', 'Computer', 16, 'Available', '1 unit', NULL, NULL, NULL, '2026-01-27 08:09:25', 'Acer', NULL, 'PC2026-001');

--
-- Triggers `consumables`
--
DELIMITER $$
CREATE TRIGGER `update_status` BEFORE UPDATE ON `consumables` FOR EACH ROW BEGIN
    IF NEW.quantity = 0 THEN
        SET NEW.status = 'Out of Stock';
    ELSEIF NEW.quantity <= 5 THEN
        SET NEW.status = 'Low';
    ELSE
        SET NEW.status = 'Available';
    END IF;
END
$$
DELIMITER ;

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

INSERT INTO `general_equipment` (`id`, `item_number`, `article`, `description`, `brand`, `model`, `property_no`, `cost`, `condition_status`, `location_id`, `campus`, `status`, `is_condemned`, `condemned_date`, `condemned_reason`, `condemned_by`, `assigned_to`, `assigned_date`, `purchase_date`, `warranty_expiry`, `remarks`, `image_path`, `created_at`, `updated_at`) VALUES
(123, '1', 'Aircon', 'Aircon 2hp window type, Carrier | SN: 3019-0233809', NULL, NULL, '2019-05-02-0060-089A', 55131.00, 'Excellent', NULL, 'South Campus', 'available', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Julius Paul Talinio', 'uploads/equipment/general_1769565381_3630.jpg', '2026-01-28 01:56:21', '2026-01-28 02:20:52'),
(124, '2', 'Aircon', 'Aircon 2hp window type, Carrier | SN: 3019-0233568', NULL, NULL, '2019-05-02-0061-089A', 55131.12, 'Excellent', NULL, 'South Campus', 'available', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Julius Paul Talinio', 'uploads/equipment/general_1769567215_6183.jpg', '2026-01-28 02:26:55', '2026-01-28 02:26:55'),
(125, '3', 'Aircon', 'Aircon 2hp window type, Carrier | SN: 3019-0233391', NULL, NULL, '2019-05-02-0063-089A', 55131.12, 'Excellent', NULL, 'South Campus', 'available', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Julius Paul Talinio', 'uploads/equipment/general_1769571895_1961.jpg', '2026-01-28 03:44:55', '2026-01-28 03:44:55'),
(126, 'AIR-001', 'Aircon', 'Aircon Kolin window type 2hp | SN: 19132401-22313', NULL, NULL, '2024-05-02-0012-089B', 69926.00, 'Excellent', NULL, 'Camarin Campus', 'available', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Monica Mariano', 'uploads/equipment/general_1769572644_7829.webp', '2026-01-28 03:57:24', '2026-01-28 03:57:24'),
(127, 'CAB-001', 'Cabinet', 'Display Cabinet', NULL, NULL, '2023-07-01-0034-089D', 70078.00, 'Excellent', NULL, 'Bagong Silang Campus', 'available', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'N/A', 'uploads/equipment/general_1769574591_2535.webp', '2026-01-28 04:29:51', '2026-01-28 04:29:51');

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
(1, 'Computer Lab 1', 10, 'computer_lab', 'Main Campus', 'Main computer laboratory with 30 workstations', 30, 2, '2026-01-09 06:13:34', '2026-01-11 15:15:20'),
(2, 'Computer Lab 2', 10, 'computer_lab', 'Main Campus', 'Secondary computer lab with 25 workstations', 25, 6, '2026-01-09 06:13:34', '2026-01-18 09:53:17'),
(3, 'Chemistry Lab', NULL, 'regular_lab', 'Main Campus', 'Chemistry laboratory for experiments', 20, 4, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(4, 'Physics Lab', NULL, 'regular_lab', 'Main Campus', 'Physics laboratory with equipment', 15, 5, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(5, 'DTIM Kitchen', NULL, 'kitchen', 'Main Campus', 'Department kitchen facility', 10, 6, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(7, 'Storage Room A', NULL, 'storage', 'Main Campus', 'General storage facility', 0, NULL, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(8, 'Classroom 101', NULL, 'classroom', 'Main Campus', 'Regular classroom', 40, NULL, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(9, 'IT Center', 14, 'it-center', 'Main Campus', '', 20, NULL, '2026-01-23 02:31:23', '2026-01-23 02:57:12'),
(10, 'EdTech Lab', 15, NULL, 'Main Campus', 'Educational Technology Laboratory', 100, NULL, '2026-01-23 02:51:17', '2026-01-23 02:51:17'),
(11, 'LabTech Office', 16, NULL, 'Main Campus', 'Bodega :3', 100, 7, '2026-01-23 06:50:26', '2026-01-23 06:50:26'),
(12, 'Science Laboratory', 17, NULL, 'Main Campus', 'SciLab', 120, NULL, '2026-01-23 07:04:28', '2026-01-23 07:04:28'),
(13, 'Computer Laboratory 301', 18, NULL, 'Congressional Campus', 'Computer Lab 301 | IS Room', 100, NULL, '2026-01-27 03:51:34', '2026-01-27 03:51:34');

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
(11, '1st Floor', 'Barracks', 'Main Campus', 'Crim', 'fa-building', '#1d0085', '#9190ea', 'Equipment', 'Sir Jose', 1, '2026-01-18 06:26:59', '2026-01-27 03:35:49'),
(14, '1st Floor', 'IT Center', 'Main Campus', 'Information Technology Center', 'fa-desktop', '#2ee7ff', '#147aff', 'Equipment', 'Manager', 1, '2026-01-23 02:30:48', '2026-01-27 03:35:54'),
(15, '4th Floor', 'EdTech', 'Main Campus', 'Educational Technology', 'fa-desktop', '#47ff97', '#389fff', 'Units', 'Manager', 1, '2026-01-23 02:49:43', '2026-01-23 02:50:11'),
(16, '3rd Floor', 'LabTech', 'Main Campus', 'Bodega :3', 'fa-desktop', '#6bffb5', '#20c997', 'Units', 'Ryan Mateo', 1, '2026-01-23 06:50:05', '2026-01-23 06:50:05'),
(17, '4th Floor', 'Biology Laboratory', 'Main Campus', 'Science Lab', 'fa-flask', '#d2fe34', '#d4d742', 'Lab', 'Manager', 1, '2026-01-23 07:03:50', '2026-01-23 07:06:47'),
(18, '3rd Floor', 'CSD - North', 'Congressional Campus', 'CSD Floor', 'fa-desktop', '#008543', '#20c997', 'Equipment', 'Manager', 1, '2026-01-27 03:42:07', '2026-01-27 08:07:24'),
(19, '1st Floor', 'Registrar Office', 'Bagong Silang Campus', 'Registrar', 'fa-briefcase', '#008543', '#20c997', 'Equipment', 'Manager', 1, '2026-01-27 08:07:56', '2026-01-27 08:07:56');

-- --------------------------------------------------------

--
-- Table structure for table `office_equipment`
--

CREATE TABLE `office_equipment` (
  `id` int(11) NOT NULL,
  `item_number` varchar(50) DEFAULT NULL,
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

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `consumable_id`, `serial_number`, `brand`, `requester_name`, `faculty`, `quantity`, `purpose`, `request_date`, `request_status`, `remarks`, `employee`, `office`, `date_received`, `no_supplies`, `description`, `requested_by`, `approved_by`, `supply_officer`, `received_by`) VALUES
(16, 25, 'ST001', 'ClassMate', 'John Doe', 'Science', 10, 'Classwork', '2026-01-22 18:00:00', 'Approved', 'Urgent', 'John Doe', 'Lab 1', '2026-01-23', 10, 'For students', 'John Doe', 'Dr. Smith', 'Ms. Admin', 'Ms. Admin'),
(17, 26, 'ST002', 'Reynolds', 'Jane Smith', 'Math', 20, 'Exam', '2026-01-22 19:00:00', 'Pending', NULL, 'Jane Smith', 'Lab 2', NULL, 20, 'For students', 'Jane Smith', NULL, 'Ms. Admin', NULL),
(18, 30, 'Faber-Castell', 'Faber-Castell', 'Mike Brown', 'Art', 15, 'Drawing', '2026-01-22 20:00:00', 'Approved', NULL, 'Mike Brown', 'Art Room', '2026-01-23', 15, 'Art class', 'Mike Brown', 'Ms. Green', 'Ms. Admin', 'Mike Brown'),
(19, 40, 'LT1001', 'Dell', 'Alice Johnson', 'IT', 2, 'Project work', '2026-01-22 08:00:00', 'Pending', NULL, 'Alice Johnson', 'IT Lab', NULL, 2, 'Laptop usage', 'Alice Johnson', '', 'Mr. Tech', ''),
(20, 45, 'RT1006', 'TP-Link', 'Robert Lee', 'IT', 5, 'Networking', '2026-01-22 08:00:00', 'Approved', 'For classroom', 'Lee Robert', 'IT Lab', '2026-01-23', 5, 'Network setup', 'Robert Lee', 'Mr. Tech', 'Mr. Tech', 'Robert Lee'),
(21, 79, NULL, NULL, '', NULL, 50, NULL, '2026-01-26 16:00:00', 'Pending', NULL, 'Ryan Mateo', 'LabTech', NULL, 2, 'Student use', 'System Administrator', 'Mr. Tech', 'Admin', ''),
(22, 80, NULL, NULL, '', NULL, 10, NULL, '2026-01-26 16:00:00', 'Pending', NULL, 'Ryan Mateo', 'Computer Laboratory 1', '2026-01-31', 10, 'Students use', 'System Administrator', 'Mr. Tech', 'Admin', 'Dr. T. Macaraeg');

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
  `campus_assigned` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `campus_assigned`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$O97BmEq16/fy6syozQFUceFmK2MbPSdESHRfDv0yf0DMxo0Sqfd66', 'System Administrator', 'admin@ucc.edu.ph', 'admin', NULL, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(2, 'john_doe', '$2y$10$4Ky7WnvB6ck4zrvqe8kP7ezG2VV0Djo9/Uy9S.Y4FKH/pM7EQtMdm', 'John Doe', 'john.doe@ucc.edu.ph', 'user', NULL, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(3, 'jane_smith', '$2y$10$Om4eNZa.zlz9.fq2n2EIVuCT.Max95XLtmmf4fzsJpOVa1ErRF.m.', 'Jane Smith', 'jane.smith@ucc.edu.ph', 'user', NULL, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(4, 'mike_johnson', '$2y$10$hcskqp..rcevJPZzkM2w0eqqaveHnI0U7GYN9K47DZbnUh2/YGx9i', 'Mike Johnson', 'mike.johnson@ucc.edu.ph', 'user', NULL, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(5, 'sarah_wilson', '$2y$10$ovQicSNRVL8SbdV27xG35ODk6PDAo/Gp2BjNZHDfPngDC3lmG2FF6', 'Sarah Wilson', 'sarah.wilson@ucc.edu.ph', 'user', NULL, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(6, 'david_brown', '$2y$10$uxTX2T.d1UDpp2xDGFoP2eSSq1YL7Xq71zvV6EdE/FLDBbqlbsU6m', 'David Brown', 'david.brown@ucc.edu.ph', 'user', NULL, '2026-01-09 06:13:34', '2026-01-09 06:13:34'),
(7, '', '$2y$10$PyX9qoN1zeduZrb5sa3CLelPTC0lR5zIyjzAD87GeO4IOf83JfTYG', 'Renz Rodriguez', 'kill@gmail.com', 'user', NULL, '2026-01-18 06:38:07', '2026-01-18 06:38:07');

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
-- Indexes for table `consumables`
--
ALTER TABLE `consumables`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `office_equipment`
--
ALTER TABLE `office_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_number` (`item_number`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `fk_office_equipment_condemned_by` (`condemned_by`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consumable_id` (`consumable_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `condemned_equipment`
--
ALTER TABLE `condemned_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `consumables`
--
ALTER TABLE `consumables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `general_equipment`
--
ALTER TABLE `general_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `location_types`
--
ALTER TABLE `location_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `office_equipment`
--
ALTER TABLE `office_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

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

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`consumable_id`) REFERENCES `consumables` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
