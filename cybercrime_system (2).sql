-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 13, 2026 at 04:21 AM
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
-- Database: `cybercrime_system`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_cases`
-- (See below for the actual view)
--
CREATE TABLE `active_cases` (
`complaint_id` int(11)
,`complainant_id` int(11)
,`queue_number` int(11)
,`case_type` varchar(50)
,`case_status` enum('visit','complaint','inquiry','follow-up','under_review','dismissed')
,`description` text
,`date_reported` datetime
,`formatted_date_reported` varchar(19)
,`date_reported_display` varchar(73)
,`time_reported_display` varchar(8)
,`incident_date` date
,`formatted_incident_date` varchar(73)
,`incident_time` time
,`formatted_incident_time` varchar(8)
,`suspect_info` text
,`evidence_path` varchar(255)
,`reported_by` int(11)
,`created_at` timestamp
,`formatted_created_at` varchar(19)
,`complainant_name` varchar(100)
,`contact_number` varchar(20)
,`email` varchar(255)
,`address` text
,`category` enum('general_cases','womens_desk')
,`assigned_investigator` varchar(100)
,`complainant_status` enum('pending','processing','completed','dismissed')
,`reported_by_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `case_updates`
--

CREATE TABLE `case_updates` (
  `update_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `update_text` text NOT NULL,
  `update_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complainants`
--

CREATE TABLE `complainants` (
  `complainant_id` int(11) NOT NULL,
  `queue_number` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `category` enum('general_cases','womens_desk') NOT NULL,
  `assigned_investigator` varchar(100) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','processing','completed','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complainants`
--

INSERT INTO `complainants` (`complainant_id`, `queue_number`, `name`, `contact_number`, `email`, `address`, `category`, `assigned_investigator`, `photo_path`, `status`, `created_at`, `updated_at`) VALUES
(27, 1, 'Emmy Sarmiento Del Mundo', '09480304744', 'emmydelmundo61@gmail.com', 'Mananao, Tinambac, Camarines Sur', 'general_cases', 'Pat - Balaguer, Efren S', NULL, 'completed', '2026-04-06 06:34:49', '2026-04-07 07:09:41'),
(28, 2, 'Ma. Luisa Joy Serrano Barsaga', '09518717671', 'luisajoy484@gmail.com', 'Mapid, Lagonoy Camarines Sur', 'general_cases', 'Pat - Evangelista, Jay Andrew G', NULL, 'completed', '2026-04-06 06:43:53', '2026-04-07 07:17:58'),
(29, 3, 'Nicole Pron Delos Santos', '09383686370', 'nicoledelossantos334@gmail.com', 'Zone 4 Sta Cruz, Tinambac Camarines Sur', 'womens_desk', 'Pcpl - Virata, Mergielyn C', NULL, 'completed', '2026-04-06 06:51:30', '2026-04-06 07:16:41'),
(30, 4, 'Juan Cruz Dela Cruz', '09219629896', 'eddie154@gmail.com', 'Mapid, Lagonoy Camarines Sur', 'general_cases', 'PMSg - Pacardo, Karlo C', NULL, 'completed', '2026-04-07 06:43:03', '2026-04-07 06:51:02'),
(31, 5, 'Juan Cruz Dela Cruz', '09219629896', 'eddie154@gmail.com', 'Mapid, Lagonoy Camarines Sur', 'general_cases', 'PMSg - Pacardo, Karlo C', NULL, 'completed', '2026-04-07 07:10:18', '2026-04-07 07:10:52'),
(32, 6, 'Juan Cruz Dela Cruz', '09219629896', 'eddie154@gmail.com', 'Mapid, Lagonoy Camarines Sur', 'general_cases', 'PMSg - Pacardo, Karlo C', NULL, 'pending', '2026-04-07 07:35:27', '2026-04-07 07:35:27'),
(33, 7, 'Juan Cruz Dela Cruz', '09219629896', 'eddie154@gmail.com', 'Mapid, Lagonoy Camarines Sur', 'general_cases', 'PMSg - Madregalijos, Eddie S', NULL, 'pending', '2026-04-10 07:01:33', '2026-04-10 07:01:33'),
(34, 8, 'Juan Cruz Dela Cruz', '09219629896', 'eddie154@gmail.com', 'Mapid, Lagonoy Camarines Sur', 'general_cases', 'Pcpl - Abelinde, James Robert T', NULL, 'pending', '2026-04-10 07:20:39', '2026-04-10 07:20:39'),
(35, 9, 'Juan Cruz Dela Cruz', '09219629896', 'eddie154@gmail.com', 'Mapid, Lagonoy Camarines Sur', 'general_cases', 'Pcpl - Abelinde, James Robert T', NULL, 'pending', '2026-04-10 07:26:58', '2026-04-10 07:26:58'),
(36, 10, 'Juan Cruz Dela Cruz', '09219629896', 'eddie154@gmail.com', 'Mapid, Lagonoy Camarines Sur', 'general_cases', 'Pcpl - Abelinde, James Robert T', NULL, 'pending', '2026-04-10 07:30:22', '2026-04-10 07:30:22'),
(37, 11, 'Juan Cruz Dela Cruz', '09219629896', 'eddie154@gmail.com', 'Mapid, Lagonoy Camarines Sur', 'general_cases', 'Pat - Evangelista, Jay Andrew G', NULL, 'pending', '2026-04-10 07:30:51', '2026-04-10 07:30:51'),
(38, 12, 'Juan Cruz Dela Cruz', '09219629896', 'eddie154@gmail.com', 'Mapid, Lagonoy Camarines Sur', 'general_cases', 'Pcpl - Abelinde, James Robert T', NULL, 'pending', '2026-04-10 07:36:49', '2026-04-10 07:36:49');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL,
  `complainant_id` int(11) NOT NULL,
  `queue_number` int(11) NOT NULL,
  `case_type` varchar(50) NOT NULL,
  `case_status` enum('visit','complaint','inquiry','follow-up','under_review','dismissed') DEFAULT 'complaint',
  `description` text DEFAULT NULL,
  `date_reported` datetime NOT NULL,
  `incident_date` date DEFAULT NULL,
  `incident_time` time DEFAULT NULL,
  `suspect_info` text DEFAULT NULL,
  `evidence_path` varchar(255) DEFAULT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`complaint_id`, `complainant_id`, `queue_number`, `case_type`, `case_status`, `description`, `date_reported`, `incident_date`, `incident_time`, `suspect_info`, `evidence_path`, `reported_by`, `created_at`, `updated_at`) VALUES
(27, 27, 1, 'online_scam', '', NULL, '2026-04-06 14:34:49', '2026-04-01', NULL, NULL, NULL, NULL, '2026-04-06 06:34:49', '2026-04-07 07:09:41'),
(28, 28, 2, 'hacking', '', NULL, '2026-04-06 14:43:53', '2026-04-06', NULL, NULL, NULL, NULL, '2026-04-06 06:43:53', '2026-04-07 07:17:58'),
(29, 29, 3, 'cyber_libel', '', NULL, '2026-04-06 14:51:30', '2005-05-21', NULL, NULL, NULL, NULL, '2026-04-06 06:51:30', '2026-04-06 07:16:41'),
(30, 30, 4, 'online_scam', '', NULL, '2026-04-07 14:43:03', '2026-04-07', NULL, NULL, NULL, NULL, '2026-04-07 06:43:03', '2026-04-07 06:51:02'),
(31, 31, 5, 'illegal_online_gambling', '', NULL, '2026-04-07 15:10:18', '2026-04-07', NULL, NULL, NULL, NULL, '2026-04-07 07:10:18', '2026-04-07 07:10:52'),
(32, 32, 6, 'child_pornography', 'visit', NULL, '2026-04-07 15:35:27', '2026-04-07', NULL, NULL, NULL, NULL, '2026-04-07 07:35:27', '2026-04-07 07:35:27'),
(33, 33, 7, 'online_scam', 'complaint', NULL, '2026-04-10 15:01:33', '2026-04-07', NULL, NULL, NULL, NULL, '2026-04-10 07:01:33', '2026-04-10 07:01:33'),
(34, 34, 8, 'illegal_online_gambling', 'visit', NULL, '2026-04-10 15:20:39', '2026-04-07', NULL, NULL, NULL, NULL, '2026-04-10 07:20:39', '2026-04-10 07:20:39'),
(35, 35, 9, 'cyber_libel', 'complaint', NULL, '2026-04-10 15:26:58', '2026-04-07', NULL, NULL, NULL, NULL, '2026-04-10 07:26:58', '2026-04-10 07:26:58'),
(36, 36, 10, 'hacking', 'visit', NULL, '2026-04-10 15:30:22', '2026-04-07', NULL, NULL, NULL, NULL, '2026-04-10 07:30:22', '2026-04-10 07:30:22'),
(37, 37, 11, 'hacking', 'complaint', NULL, '2026-04-10 15:30:51', '2026-04-07', NULL, NULL, NULL, NULL, '2026-04-10 07:30:51', '2026-04-10 07:30:51'),
(38, 38, 12, 'online_scam', 'complaint', NULL, '2026-04-10 15:36:49', '2026-04-07', NULL, NULL, NULL, NULL, '2026-04-10 07:36:49', '2026-04-10 07:36:49');

-- --------------------------------------------------------

--
-- Stand-in structure for view `formatted_complaints`
-- (See below for the actual view)
--
CREATE TABLE `formatted_complaints` (
`complaint_id` int(11)
,`complainant_id` int(11)
,`queue_number` int(11)
,`case_type` varchar(50)
,`case_status` enum('visit','complaint','inquiry','follow-up','under_review','dismissed')
,`description` text
,`date_reported` datetime
,`formatted_date_reported` varchar(19)
,`date_reported_display` varchar(73)
,`time_reported_display` varchar(8)
,`incident_date` date
,`formatted_incident_date` varchar(73)
,`incident_time` time
,`formatted_incident_time` varchar(8)
,`suspect_info` text
,`evidence_path` varchar(255)
,`reported_by` int(11)
,`created_at` timestamp
,`formatted_created_at` varchar(19)
,`complainant_name` varchar(100)
,`contact_number` varchar(20)
,`email` varchar(255)
,`address` text
,`category` enum('general_cases','womens_desk')
,`assigned_investigator` varchar(100)
,`complainant_status` enum('pending','processing','completed','dismissed')
,`reported_by_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `investigators`
--

CREATE TABLE `investigators` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `investigators`
--

INSERT INTO `investigators` (`id`, `name`, `category`, `gender`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PCPT - Babagay, Angelo A', 'all', 'male', 'inactive', '2026-03-30 14:05:13', '2026-04-06 14:59:51'),
(2, 'PMSg - Madregalijos, Eddie S', 'all', 'male', 'active', '2026-03-30 14:05:13', '2026-03-30 14:05:13'),
(3, 'PMSg - Pacardo, Karlo C', 'all', 'male', 'active', '2026-03-30 14:05:13', '2026-03-30 14:05:13'),
(4, 'PMSg - Bustamante, Paul Christian E', 'all', 'male', 'active', '2026-03-30 14:05:13', '2026-03-30 14:05:13'),
(5, 'PSSg - Balin, Levelyn S', 'all', 'male', 'inactive', '2026-03-30 14:05:13', '2026-04-13 09:53:01'),
(6, 'PSSg - Magdaraog, Joseph M', 'all', 'male', 'active', '2026-03-30 14:05:13', '2026-03-30 14:05:13'),
(7, 'PSSg - Lumanog, Ryan D', 'all', 'male', 'active', '2026-03-30 14:05:13', '2026-03-30 14:05:13'),
(8, 'PSSg - Mariano, Marc V', 'all', 'male', 'active', '2026-03-30 14:05:13', '2026-03-30 14:05:13'),
(9, 'Pcpl - Abelinde, James Robert T', 'all', 'male', 'active', '2026-03-30 14:05:13', '2026-03-30 14:05:13'),
(10, 'Pat - Balaguer, Efren S', 'all', 'male', 'active', '2026-03-30 14:05:13', '2026-03-30 14:05:13'),
(11, 'Pat - Evangelista, Jay Andrew G', 'all', 'male', 'active', '2026-03-30 14:05:13', '2026-03-30 14:05:13'),
(12, 'PSMS - Rivera, Leah H', 'womens_desk', 'female', 'active', '2026-03-30 14:05:13', '2026-04-13 09:55:47'),
(13, 'PSMS - Floro Bhong, Oida S', 'womens_desk', 'female', 'inactive', '2026-03-30 14:05:13', '2026-04-13 10:11:38'),
(14, 'Pcpl - Virata, Mergielyn C', 'womens_desk', 'female', 'active', '2026-03-30 14:05:13', '2026-04-13 09:55:47'),
(15, 'PSMS - Rivera, Leah H', 'womens_desk', 'female', 'active', '2026-03-30 14:05:13', '2026-04-13 09:55:47'),
(16, 'PSMS - Floro Bhong, Oida S', 'womens_desk', 'female', 'inactive', '2026-03-30 14:05:13', '2026-04-13 10:11:38'),
(17, 'Pcpl - Virata, Mergielyn C', 'womens_desk', 'female', 'active', '2026-03-30 14:05:13', '2026-04-13 09:55:47'),
(18, 'PSSg, Balin, Levelyn S.', 'womens_desk', 'female', 'inactive', '2026-04-06 15:03:06', '2026-04-06 15:03:42'),
(19, 'PSSg - Balin, Levelyn S.', 'womens_desk', 'female', 'inactive', '2026-04-06 15:04:19', '2026-04-13 09:52:44'),
(20, 'PSMS Rivera, Leah H.', 'womens_desk', 'female', 'inactive', '2026-04-06 15:32:50', '2026-04-06 15:37:03'),
(21, 'PCpL', 'all', 'male', 'inactive', '2026-04-06 15:33:55', '2026-04-06 15:34:17'),
(22, 'PCpL - Virata, Mergielyn', 'womens_desk', 'female', 'inactive', '2026-04-06 15:35:49', '2026-04-13 09:52:31'),
(23, 'PSMS - Rivera, Leah H.', 'all', 'female', 'inactive', '2026-04-06 15:37:31', '2026-04-07 14:41:33'),
(24, 'PSMS  Floro Bhong, Oida S', 'all', 'male', 'inactive', '2026-04-13 10:10:49', '2026-04-13 10:13:27'),
(25, 'PSMS  Oida, Floro Bhong S', 'all', 'male', 'active', '2026-04-13 10:13:13', '2026-04-13 10:13:13');

-- --------------------------------------------------------

--
-- Table structure for table `investigators_general_cases`
--

CREATE TABLE `investigators_general_cases` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `investigators_general_cases`
--

INSERT INTO `investigators_general_cases` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'PCPT - Babagay, Angelo A', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58'),
(2, 'PMSg - Madregalijos, Eddie S', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58'),
(3, 'PMSg - Pacardo, Karlo C', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58'),
(4, 'PMSg - Bustamante, Paul Christian E', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58'),
(5, 'PSSg - Balin, Levelyn S', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58'),
(6, 'PSSg - Magdaraog, Joseph M', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58'),
(7, 'PSSg - Lumanog, Ryan D', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58'),
(8, 'PSSg - Mariano, Marc V', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58'),
(9, 'Pcpl - Abelinde, James Robert T', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58'),
(10, 'Pat - Balaguer, Efren S', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58'),
(11, 'Pat - Evangelista, Jay Andrew G', 1, '2026-03-30 02:17:58', '2026-03-30 02:17:58');

-- --------------------------------------------------------

--
-- Table structure for table `investigator_assignments`
--

CREATE TABLE `investigator_assignments` (
  `assignment_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `investigator_name` varchar(100) NOT NULL,
  `investigator_category` enum('general_cases','womens_desk') NOT NULL,
  `assigned_date` datetime DEFAULT current_timestamp(),
  `status` enum('active','completed','transferred') DEFAULT 'active',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `investigator_workload`
-- (See below for the actual view)
--
CREATE TABLE `investigator_workload` (
`assigned_investigator` varchar(100)
,`category` enum('general_cases','womens_desk')
,`active_cases` bigint(21)
,`pending_cases` decimal(22,0)
,`processing_cases` decimal(22,0)
,`under_review_cases` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `monthly_summary`
-- (See below for the actual view)
--
CREATE TABLE `monthly_summary` (
`month` varchar(7)
,`total_cases` bigint(21)
,`general_cases` decimal(22,0)
,`womens_cases` decimal(22,0)
,`under_review` decimal(22,0)
,`dismissed` decimal(22,0)
,`resolved` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `otp_attempts`
--

CREATE TABLE `otp_attempts` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_attempts`
--

INSERT INTO `otp_attempts` (`id`, `identifier`, `attempt_time`) VALUES
(1, '::1', '2026-04-10 10:08:08');

-- --------------------------------------------------------

--
-- Table structure for table `queue_history`
--

CREATE TABLE `queue_history` (
  `history_id` int(11) NOT NULL,
  `queue_number` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `queue_history`
--

INSERT INTO `queue_history` (`history_id`, `queue_number`, `action`, `remarks`, `performed_by`, `created_at`) VALUES
(16, 1, 'created', 'New complaint registered for alberto makuy Aquino (Category: General Cases)', 1, '2026-03-24 01:24:30'),
(17, 2, 'created', 'New complaint registered for Ma. Luisa Joy Barsaga (Category: General Cases)', 1, '2026-03-24 01:32:56'),
(18, 3, 'created', 'New complaint registered for albert juan mayor (Category: General Cases)', 1, '2026-03-24 01:34:40'),
(19, 1, 'status_updated', 'Status changed from complaint to processing', 1, '2026-03-24 01:35:28'),
(20, 4, 'created', 'New complaint registered for Emmy Sarmiento Del mundo (Category: Women\'s Desk)', 1, '2026-03-24 01:37:17'),
(21, 5, 'created', 'New complaint registered for Emmy Sarmiento Del mundo (Category: General Cases)', 1, '2026-03-24 01:38:49'),
(22, 1, 'created', 'New complaint registered for Emmy Sarmiento Del mundo (Category: General Cases)', 1, '2026-03-24 01:47:49'),
(23, 2, 'created', 'New complaint registered for Mary Ann Buena Sarmiento Barsaga (Category: Women\'s Desk)', 1, '2026-03-24 01:48:13'),
(24, 1, 'created', 'New complaint registered for Mary Ann Buena Sarmiento Barsaga (Category: General Cases)', 1, '2026-03-24 01:56:33'),
(25, 2, 'created', 'New complaint registered for Mary Ann Buena Sarmiento Barsaga (Category: Women\'s Desk)', 1, '2026-03-24 01:56:51'),
(26, 3, 'created', 'New complaint registered for Mary Ann Buena Sarmiento Barsaga (Category: General Cases)', 1, '2026-03-24 02:22:53'),
(27, 1, 'status_updated', 'Status changed from complaint to processing', 1, '2026-03-24 02:24:10'),
(28, 1, 'status_updated', 'Status changed from  to completed', 1, '2026-03-24 02:24:26'),
(30, 4, 'created', 'New complaint registered for Jessica Agudo Mandane (Category: General Cases)', 1, '2026-03-24 06:27:30'),
(31, 5, 'created', 'New complaint registered for Mary Ann Buena Sarmiento Barsaga (Category: General Cases)', 1, '2026-04-06 00:19:59'),
(32, 6, 'created', 'New complaint registered for Jose Buenavinte De guzman (Category: Women\'s Desk)', 1, '2026-04-06 06:24:02'),
(33, 6, 'status_updated', 'Status changed from visit to processing', 1, '2026-04-06 06:25:47'),
(34, 1, 'created', 'New complaint registered for Emmy Sarmiento Del Mundo (Category: General Cases)', 1, '2026-04-06 06:34:49'),
(35, 2, 'created', 'New complaint registered for Ma. Luisa Joy Serrano Barsaga (Category: General Cases)', 1, '2026-04-06 06:43:53'),
(36, 3, 'created', 'New complaint registered for Nicole Pron Delos Santos (Category: Women\'s Desk)', 1, '2026-04-06 06:51:30'),
(37, 3, 'status_updated', 'Status changed from follow-up to completed', 1, '2026-04-06 06:54:42'),
(38, 3, 'status_updated', 'Status changed from  to completed', 1, '2026-04-06 06:55:11'),
(39, 1, 'status_updated', 'Status changed from complaint to processing', 1, '2026-04-06 06:55:34'),
(40, 3, 'status_updated', 'Status changed from  to completed', 1, '2026-04-06 06:56:50'),
(41, 2, 'status_updated', 'Status changed from complaint to processing', 1, '2026-04-06 07:15:32'),
(42, 3, 'status_updated', 'Status changed from  to completed', 1, '2026-04-06 07:16:41'),
(43, 4, 'created', 'New complaint registered for Juan Cruz Dela Cruz (Category: General Cases)', 1, '2026-04-07 06:43:03'),
(44, 4, 'status_updated', 'Status changed from visit to processing', 1, '2026-04-07 06:48:38'),
(45, 4, 'status_updated', 'Status changed from  to completed', 1, '2026-04-07 06:51:02'),
(46, 1, 'status_updated', 'Status changed from  to processing', 1, '2026-04-07 07:06:09'),
(47, 1, 'status_updated', 'Status changed from  to completed', 1, '2026-04-07 07:09:41'),
(48, 5, 'created', 'New complaint registered for Juan Cruz Dela Cruz (Category: General Cases)', 1, '2026-04-07 07:10:18'),
(49, 5, 'status_updated', 'Status changed from visit to processing', 1, '2026-04-07 07:10:31'),
(50, 5, 'status_updated', 'Status changed from  to completed', 1, '2026-04-07 07:10:52'),
(51, 2, 'status_updated', 'Status changed from  to completed', 1, '2026-04-07 07:17:58'),
(52, 6, 'created', 'New complaint registered for Juan Cruz Dela Cruz (Category: General Cases)', 1, '2026-04-07 07:35:27'),
(53, 7, 'created', 'New complaint registered for Juan Cruz Dela Cruz (Category: General Cases)', 1, '2026-04-10 07:01:33'),
(54, 8, 'created', 'New complaint registered for Juan Cruz Dela Cruz (Category: General Cases)', 1, '2026-04-10 07:20:39'),
(55, 9, 'created', 'New complaint registered for Juan Cruz Dela Cruz (Category: General Cases)', 1, '2026-04-10 07:26:58'),
(56, 10, 'created', 'New complaint registered for Juan Cruz Dela Cruz (Category: General Cases)', 1, '2026-04-10 07:30:22'),
(57, 11, 'created', 'New complaint registered for Juan Cruz Dela Cruz (Category: General Cases)', 1, '2026-04-10 07:30:51'),
(58, 12, 'created', 'New complaint registered for Juan Cruz Dela Cruz (Category: General Cases)', 1, '2026-04-10 07:36:49');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `today_cases`
-- (See below for the actual view)
--
CREATE TABLE `today_cases` (
`complaint_id` int(11)
,`complainant_id` int(11)
,`queue_number` int(11)
,`case_type` varchar(50)
,`case_status` enum('visit','complaint','inquiry','follow-up','under_review','dismissed')
,`description` text
,`date_reported` datetime
,`formatted_date_reported` varchar(19)
,`date_reported_display` varchar(73)
,`time_reported_display` varchar(8)
,`incident_date` date
,`formatted_incident_date` varchar(73)
,`incident_time` time
,`formatted_incident_time` varchar(8)
,`suspect_info` text
,`evidence_path` varchar(255)
,`reported_by` int(11)
,`created_at` timestamp
,`formatted_created_at` varchar(19)
,`complainant_name` varchar(100)
,`contact_number` varchar(20)
,`email` varchar(255)
,`address` text
,`category` enum('general_cases','womens_desk')
,`assigned_investigator` varchar(100)
,`complainant_status` enum('pending','processing','completed','dismissed')
,`reported_by_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `rank` varchar(50) DEFAULT NULL,
  `role` enum('admin','investigator','staff') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email` varchar(100) DEFAULT NULL,
  `reset_code` varchar(10) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `reset_attempts` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `rank`, `role`, `created_at`, `updated_at`, `email`, `reset_code`, `reset_expiry`, `reset_attempts`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'Administrator', 'PLTCOL', 'admin', '2026-03-23 07:55:23', '2026-04-10 02:08:06', NULL, '612895', '2026-04-10 10:13:06', 0),
(2, 'investigator1', 'ac78206549f8504800c254a01425fe5a', 'Babagay, Angelo A', 'PCPT', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(3, 'investigator2', 'ac78206549f8504800c254a01425fe5a', 'Rivera, Leah H', 'PSMS', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(4, 'investigator3', 'ac78206549f8504800c254a01425fe5a', 'Floro Bhong, Oida S', 'PSMS', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(5, 'investigator4', 'ac78206549f8504800c254a01425fe5a', 'Madregalijos, Eddie S', 'PMSg', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(6, 'investigator5', 'ac78206549f8504800c254a01425fe5a', 'Pacardo, Karlo C', 'PMSg', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(7, 'investigator6', 'ac78206549f8504800c254a01425fe5a', 'Bustamante, Paul Christian E', 'PMSg', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(8, 'investigator7', 'ac78206549f8504800c254a01425fe5a', 'Balin, Levelyn S', 'PSSg', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(9, 'investigator8', 'ac78206549f8504800c254a01425fe5a', 'Magdaraog, Joseph M', 'PSSg', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(10, 'investigator9', 'ac78206549f8504800c254a01425fe5a', 'Lumanog, Ryan D', 'PSSg', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(11, 'investigator10', 'ac78206549f8504800c254a01425fe5a', 'Mariano, Marc V', 'PSSg', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(12, 'investigator11', 'ac78206549f8504800c254a01425fe5a', 'Abelinde, James Robert T', 'Pcpl', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(13, 'investigator12', 'ac78206549f8504800c254a01425fe5a', 'Virata, Mergielyn C', 'Pcpl', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(14, 'investigator13', 'ac78206549f8504800c254a01425fe5a', 'Balaguer, Efren S', 'Pat', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(15, 'investigator14', 'ac78206549f8504800c254a01425fe5a', 'Evangelista, Jay Andrew G', 'Pat', 'investigator', '2026-03-23 07:55:23', '2026-03-23 07:55:23', NULL, NULL, NULL, 0),
(17, 'babagay', '$2y$10$YourHashedPasswordHere', 'PCPT Babagay, Angelo A', NULL, 'investigator', '2026-03-23 11:43:27', '2026-03-23 11:43:27', NULL, NULL, NULL, 0),
(18, 'madregalijos', '$2y$10$YourHashedPasswordHere', 'PMSg Madregalijos, Eddie S', NULL, 'investigator', '2026-03-23 11:43:27', '2026-03-23 11:43:27', NULL, NULL, NULL, 0),
(19, 'pacardo', '$2y$10$YourHashedPasswordHere', 'PMSg Pacardo, Karlo C', NULL, 'investigator', '2026-03-23 11:43:27', '2026-03-23 11:43:27', NULL, NULL, NULL, 0),
(20, 'rivera', '$2y$10$YourHashedPasswordHere', 'PSMS Rivera, Leah H', NULL, 'investigator', '2026-03-23 11:43:27', '2026-03-23 11:43:27', NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Structure for view `active_cases`
--
DROP TABLE IF EXISTS `active_cases`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_cases`  AS SELECT `formatted_complaints`.`complaint_id` AS `complaint_id`, `formatted_complaints`.`complainant_id` AS `complainant_id`, `formatted_complaints`.`queue_number` AS `queue_number`, `formatted_complaints`.`case_type` AS `case_type`, `formatted_complaints`.`case_status` AS `case_status`, `formatted_complaints`.`description` AS `description`, `formatted_complaints`.`date_reported` AS `date_reported`, `formatted_complaints`.`formatted_date_reported` AS `formatted_date_reported`, `formatted_complaints`.`date_reported_display` AS `date_reported_display`, `formatted_complaints`.`time_reported_display` AS `time_reported_display`, `formatted_complaints`.`incident_date` AS `incident_date`, `formatted_complaints`.`formatted_incident_date` AS `formatted_incident_date`, `formatted_complaints`.`incident_time` AS `incident_time`, `formatted_complaints`.`formatted_incident_time` AS `formatted_incident_time`, `formatted_complaints`.`suspect_info` AS `suspect_info`, `formatted_complaints`.`evidence_path` AS `evidence_path`, `formatted_complaints`.`reported_by` AS `reported_by`, `formatted_complaints`.`created_at` AS `created_at`, `formatted_complaints`.`formatted_created_at` AS `formatted_created_at`, `formatted_complaints`.`complainant_name` AS `complainant_name`, `formatted_complaints`.`contact_number` AS `contact_number`, `formatted_complaints`.`email` AS `email`, `formatted_complaints`.`address` AS `address`, `formatted_complaints`.`category` AS `category`, `formatted_complaints`.`assigned_investigator` AS `assigned_investigator`, `formatted_complaints`.`complainant_status` AS `complainant_status`, `formatted_complaints`.`reported_by_name` AS `reported_by_name` FROM `formatted_complaints` WHERE `formatted_complaints`.`complainant_status` in ('pending','processing') AND `formatted_complaints`.`case_status` not in ('dismissed','closed') ORDER BY `formatted_complaints`.`queue_number` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `formatted_complaints`
--
DROP TABLE IF EXISTS `formatted_complaints`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `formatted_complaints`  AS SELECT `c`.`complaint_id` AS `complaint_id`, `c`.`complainant_id` AS `complainant_id`, `c`.`queue_number` AS `queue_number`, `c`.`case_type` AS `case_type`, `c`.`case_status` AS `case_status`, `c`.`description` AS `description`, `c`.`date_reported` AS `date_reported`, date_format(`c`.`date_reported`,'%Y-%m-%d %h:%i %p') AS `formatted_date_reported`, date_format(`c`.`date_reported`,'%M %d, %Y') AS `date_reported_display`, date_format(`c`.`date_reported`,'%h:%i %p') AS `time_reported_display`, `c`.`incident_date` AS `incident_date`, date_format(`c`.`incident_date`,'%M %d, %Y') AS `formatted_incident_date`, `c`.`incident_time` AS `incident_time`, date_format(`c`.`incident_time`,'%h:%i %p') AS `formatted_incident_time`, `c`.`suspect_info` AS `suspect_info`, `c`.`evidence_path` AS `evidence_path`, `c`.`reported_by` AS `reported_by`, `c`.`created_at` AS `created_at`, date_format(`c`.`created_at`,'%Y-%m-%d %h:%i %p') AS `formatted_created_at`, `comp`.`name` AS `complainant_name`, `comp`.`contact_number` AS `contact_number`, `comp`.`email` AS `email`, `comp`.`address` AS `address`, `comp`.`category` AS `category`, `comp`.`assigned_investigator` AS `assigned_investigator`, `comp`.`status` AS `complainant_status`, `u`.`full_name` AS `reported_by_name` FROM ((`complaints` `c` join `complainants` `comp` on(`c`.`complainant_id` = `comp`.`complainant_id`)) left join `users` `u` on(`c`.`reported_by` = `u`.`user_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `investigator_workload`
--
DROP TABLE IF EXISTS `investigator_workload`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `investigator_workload`  AS SELECT `formatted_complaints`.`assigned_investigator` AS `assigned_investigator`, `formatted_complaints`.`category` AS `category`, count(0) AS `active_cases`, sum(case when `formatted_complaints`.`complainant_status` = 'pending' then 1 else 0 end) AS `pending_cases`, sum(case when `formatted_complaints`.`complainant_status` = 'processing' then 1 else 0 end) AS `processing_cases`, sum(case when `formatted_complaints`.`case_status` = 'under_review' then 1 else 0 end) AS `under_review_cases` FROM `formatted_complaints` WHERE `formatted_complaints`.`complainant_status` in ('pending','processing') GROUP BY `formatted_complaints`.`assigned_investigator`, `formatted_complaints`.`category` ORDER BY count(0) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `monthly_summary`
--
DROP TABLE IF EXISTS `monthly_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `monthly_summary`  AS SELECT date_format(`formatted_complaints`.`date_reported`,'%Y-%m') AS `month`, count(0) AS `total_cases`, sum(case when `formatted_complaints`.`category` = 'general_cases' then 1 else 0 end) AS `general_cases`, sum(case when `formatted_complaints`.`category` = 'womens_desk' then 1 else 0 end) AS `womens_cases`, sum(case when `formatted_complaints`.`case_status` = 'under_review' then 1 else 0 end) AS `under_review`, sum(case when `formatted_complaints`.`case_status` = 'dismissed' then 1 else 0 end) AS `dismissed`, sum(case when `formatted_complaints`.`case_status` = 'resolved' then 1 else 0 end) AS `resolved` FROM `formatted_complaints` GROUP BY date_format(`formatted_complaints`.`date_reported`,'%Y-%m') ORDER BY date_format(`formatted_complaints`.`date_reported`,'%Y-%m') DESC ;

-- --------------------------------------------------------

--
-- Structure for view `today_cases`
--
DROP TABLE IF EXISTS `today_cases`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `today_cases`  AS SELECT `formatted_complaints`.`complaint_id` AS `complaint_id`, `formatted_complaints`.`complainant_id` AS `complainant_id`, `formatted_complaints`.`queue_number` AS `queue_number`, `formatted_complaints`.`case_type` AS `case_type`, `formatted_complaints`.`case_status` AS `case_status`, `formatted_complaints`.`description` AS `description`, `formatted_complaints`.`date_reported` AS `date_reported`, `formatted_complaints`.`formatted_date_reported` AS `formatted_date_reported`, `formatted_complaints`.`date_reported_display` AS `date_reported_display`, `formatted_complaints`.`time_reported_display` AS `time_reported_display`, `formatted_complaints`.`incident_date` AS `incident_date`, `formatted_complaints`.`formatted_incident_date` AS `formatted_incident_date`, `formatted_complaints`.`incident_time` AS `incident_time`, `formatted_complaints`.`formatted_incident_time` AS `formatted_incident_time`, `formatted_complaints`.`suspect_info` AS `suspect_info`, `formatted_complaints`.`evidence_path` AS `evidence_path`, `formatted_complaints`.`reported_by` AS `reported_by`, `formatted_complaints`.`created_at` AS `created_at`, `formatted_complaints`.`formatted_created_at` AS `formatted_created_at`, `formatted_complaints`.`complainant_name` AS `complainant_name`, `formatted_complaints`.`contact_number` AS `contact_number`, `formatted_complaints`.`email` AS `email`, `formatted_complaints`.`address` AS `address`, `formatted_complaints`.`category` AS `category`, `formatted_complaints`.`assigned_investigator` AS `assigned_investigator`, `formatted_complaints`.`complainant_status` AS `complainant_status`, `formatted_complaints`.`reported_by_name` AS `reported_by_name` FROM `formatted_complaints` WHERE cast(`formatted_complaints`.`date_reported` as date) = curdate() ORDER BY `formatted_complaints`.`date_reported` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `case_updates`
--
ALTER TABLE `case_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `update_by` (`update_by`),
  ADD KEY `idx_complaint_id` (`complaint_id`);

--
-- Indexes for table `complainants`
--
ALTER TABLE `complainants`
  ADD PRIMARY KEY (`complainant_id`),
  ADD UNIQUE KEY `queue_number` (`queue_number`),
  ADD KEY `idx_queue_number` (`queue_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`complaint_id`),
  ADD KEY `complainant_id` (`complainant_id`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `idx_queue_number` (`queue_number`),
  ADD KEY `idx_case_status` (`case_status`),
  ADD KEY `idx_date_reported` (`date_reported`);

--
-- Indexes for table `investigators`
--
ALTER TABLE `investigators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `investigators_general_cases`
--
ALTER TABLE `investigators_general_cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `investigator_assignments`
--
ALTER TABLE `investigator_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `idx_complaint_id` (`complaint_id`),
  ADD KEY `idx_investigator_name` (`investigator_name`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier_time` (`identifier`,`attempt_time`);

--
-- Indexes for table `otp_attempts`
--
ALTER TABLE `otp_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier_time` (`identifier`,`attempt_time`);

--
-- Indexes for table `queue_history`
--
ALTER TABLE `queue_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `idx_queue_number` (`queue_number`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `case_updates`
--
ALTER TABLE `case_updates`
  MODIFY `update_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complainants`
--
ALTER TABLE `complainants`
  MODIFY `complainant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `investigators`
--
ALTER TABLE `investigators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `investigators_general_cases`
--
ALTER TABLE `investigators_general_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `investigator_assignments`
--
ALTER TABLE `investigator_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_attempts`
--
ALTER TABLE `otp_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `queue_history`
--
ALTER TABLE `queue_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `case_updates`
--
ALTER TABLE `case_updates`
  ADD CONSTRAINT `case_updates_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_updates_ibfk_2` FOREIGN KEY (`update_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`complainant_id`) REFERENCES `complainants` (`complainant_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `investigator_assignments`
--
ALTER TABLE `investigator_assignments`
  ADD CONSTRAINT `investigator_assignments_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE;

--
-- Constraints for table `queue_history`
--
ALTER TABLE `queue_history`
  ADD CONSTRAINT `queue_history_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
