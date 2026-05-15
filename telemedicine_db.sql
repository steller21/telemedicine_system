-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 11, 2026 at 03:36 PM
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
-- Database: `telemedicine_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_date` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `status`) VALUES
(44, 1, 1, '2026-05-05 22:38:00', 'pending'),
(45, 3, 1, '2026-05-06 15:55:00', 'pending'),
(46, 3, 1, '2026-05-07 13:28:00', 'pending'),
(47, 3, 1, '2026-05-08 11:15:00', 'pending'),
(48, 3, 1, '2026-05-10 09:30:00', 'pending'),
(49, 3, 4, '2026-05-10 10:05:00', 'pending'),
(50, 1, 4, '2026-05-12 09:30:00', 'booked');

-- --------------------------------------------------------

--
-- Table structure for table `checklists`
--

CREATE TABLE `checklists` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checklists`
--

INSERT INTO `checklists` (`id`, `patient_id`, `created_by`, `title`, `created_at`) VALUES
(12, 1, 1, 'Daily Medicines', '2026-05-05 17:07:17'),
(13, 3, 3, 'Daily Medicines', '2026-05-06 17:19:47');

-- --------------------------------------------------------

--
-- Table structure for table `checklist_items`
--

CREATE TABLE `checklist_items` (
  `id` int(11) NOT NULL,
  `checklist_id` int(11) DEFAULT NULL,
  `medicine_name` varchar(255) DEFAULT NULL,
  `medicine_image` text DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `times_of_day` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `prescribed_by` int(11) DEFAULT NULL,
  `duration_days` int(11) DEFAULT 1,
  `start_date` date NOT NULL DEFAULT curdate(),
  `prescription_file` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checklist_items`
--

INSERT INTO `checklist_items` (`id`, `checklist_id`, `medicine_name`, `medicine_image`, `dosage`, `times_of_day`, `status`, `completed_at`, `prescribed_by`, `duration_days`, `start_date`, `prescription_file`) VALUES
(33, 12, 'ertere', '../uploads/medicines/1778000837_Screenshot 2025-09-22 002453.png', 'erte', 'morning,night', 'pending', NULL, NULL, 1, '2026-05-05', NULL),
(34, 13, 'dolo 650', NULL, 'hjh', 'morning,night', 'pending', NULL, NULL, 1, '2026-05-06', '../uploads/prescriptions/1778087987_rx_3_0.png'),
(35, 12, 'wrsw', '../uploads/medicines/1778506285_Screenshot 2025-09-22 002453.png', 'wrf', 'morning,night', 'pending', NULL, NULL, 1, '2026-05-11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `checklist_logs`
--

CREATE TABLE `checklist_logs` (
  `id` int(11) NOT NULL,
  `checklist_item_id` int(11) NOT NULL,
  `taken_date` date NOT NULL,
  `taken_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL,
  `affiliations` text DEFAULT NULL,
  `availability_status` enum('available','not_available') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `name`, `email`, `password`, `specialization`, `license_number`, `phone`, `bio`, `created_at`, `profile_picture`, `affiliations`, `availability_status`) VALUES
(1, 'Nitin Kumar', 'nitin23@gmail.com', '$2y$10$kng8zBXHmFMuSgYit7R7w.3G1ejQCaDYZDF4JSP.McLlzuEh6EqyG', 'cardiology', '1234', '9739525084', 'good at hearts', '2026-05-04 16:44:04', NULL, NULL, 'available'),
(2, 'GaMeZaaDE', 'game23@gmail.com', '$2y$10$GrFgRh1fj4cTaVefRNrI9eBz88opdTRf3x.PyyO1yYIeWpQ.Yp61q', 'physician', '15634556', '9739525084', 'baba', '2026-05-09 15:41:00', NULL, 'american', 'available'),
(4, 'gagan', 'gagan23@gmail.com', '$2y$10$h0AezHexiVdPN6klGtW/yuw1alp0Kj4Qg4tkWMPw82O.eNf0yCK/G', 'cardiology', '548745', '9513524624', 'regfsdas', '2026-05-09 15:51:25', NULL, 'hagsduiba', 'available');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_credentials`
--

CREATE TABLE `doctor_credentials` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `credential_type` enum('License','Certification') NOT NULL,
  `credential_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_id1` int(11) NOT NULL,
  `user_role1` enum('patient','doctor') NOT NULL DEFAULT 'patient',
  `user_id2` int(11) NOT NULL,
  `user_role2` enum('patient','doctor') NOT NULL DEFAULT 'patient',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `friends`
--

INSERT INTO `friends` (`id`, `user_id1`, `user_role1`, `user_id2`, `user_role2`, `created_at`) VALUES
(12, 1, 'patient', 4, 'patient', '2026-05-09 19:30:27');

-- --------------------------------------------------------

--
-- Table structure for table `friend_requests`
--

CREATE TABLE `friend_requests` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_role` enum('patient','doctor') NOT NULL DEFAULT 'patient',
  `receiver_id` int(11) NOT NULL,
  `receiver_role` enum('patient','doctor') NOT NULL DEFAULT 'patient',
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `friend_requests`
--

INSERT INTO `friend_requests` (`id`, `sender_id`, `sender_role`, `receiver_id`, `receiver_role`, `status`, `created_at`) VALUES
(18, 1, 'patient', 4, 'patient', 'accepted', '2026-05-09 19:30:18');

-- --------------------------------------------------------

--
-- Table structure for table `medicine_intakes`
--

CREATE TABLE `medicine_intakes` (
  `id` int(11) NOT NULL,
  `checklist_item_id` int(11) NOT NULL,
  `scheduled_date` date NOT NULL,
  `time_of_day_slot` enum('morning','afternoon','evening','night') NOT NULL,
  `status` enum('pending','completed','missed') DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_intakes`
--

INSERT INTO `medicine_intakes` (`id`, `checklist_item_id`, `scheduled_date`, `time_of_day_slot`, `status`, `completed_at`) VALUES
(12, 34, '2026-05-06', 'morning', 'completed', '2026-05-06 17:20:23'),
(13, 34, '2026-05-06', 'night', 'completed', '2026-05-06 17:24:25');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `sender_role` enum('patient','doctor') NOT NULL DEFAULT 'patient',
  `receiver_id` int(11) DEFAULT NULL,
  `receiver_role` enum('patient','doctor') NOT NULL DEFAULT 'patient',
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `sender_role`, `receiver_id`, `receiver_role`, `message`, `created_at`, `is_read`) VALUES
(15, 1, 'patient', 3, 'patient', 'hii', '2026-05-07 17:40:17', 1),
(16, 3, 'patient', 1, 'patient', 'hello', '2026-05-07 17:40:52', 0),
(17, 1, 'patient', 3, 'patient', 'dsf', '2026-05-07 17:41:01', 1),
(18, 1, 'patient', 3, 'patient', 'hiii', '2026-05-07 18:21:47', 1),
(19, 3, 'patient', 1, 'patient', 'mc', '2026-05-07 18:22:44', 0),
(20, 3, 'patient', 4, 'patient', 'hewvvhadvuywbedyfbwe', '2026-05-08 12:50:26', 0),
(21, 3, 'patient', 1, 'patient', 'rjwfdbierfbu', '2026-05-08 12:50:41', 0),
(22, 3, 'patient', 4, 'patient', 'wejnhfgv uhew', '2026-05-08 12:51:00', 0),
(23, 3, 'patient', 1, 'patient', 'hiiiiii', '2026-05-09 15:32:13', 0);

-- --------------------------------------------------------

--
-- Table structure for table `monitor_requests`
--

CREATE TABLE `monitor_requests` (
  `id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `requested_user_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monitor_requests`
--

INSERT INTO `monitor_requests` (`id`, `requester_id`, `requested_user_id`, `status`, `created_at`, `updated_at`) VALUES
(78, 1, 3, 'pending', '2026-05-11 13:32:06', '2026-05-11 13:32:06');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `name`, `email`, `password`, `phone`, `dob`, `gender`, `address`, `created_at`, `profile_picture`) VALUES
(1, 'Sujith Kumar', 'sujithkumar9684@gmail.com', '$2y$10$t5iPqdCTexV3a7yGMuIhN.E6f2hgjeQ.6m8t8d5ns67wfzRkK7ALa', '9739525084', '2004-02-20', 'Male', 'Harohalli, kanakapura taluk, ramanagara district', '2026-05-04 14:48:57', 'images/profiles/profile_69f8b1d8f2453.jpg'),
(2, 'sd sd', 'helloworld@fsg.c', '$2y$10$0K7F6Kwyiob7twP3/J4C.udze3YDAoy5MwS3zd0p/j90BHJyPiDVS', '9513524624', '2004-02-20', 'Male', 'Harohalli', '2026-05-05 17:38:36', NULL),
(3, 'uhuh', 'sujithkumar69101@gmail.com', '$2y$10$orBF8AoZkYBEC9.8A8gYQu4ibrvyf6N4AF/HQy/Qq2W1l9OXxfBvC', '9739525084', '0000-00-00', 'Male', 'hbj', '2026-05-06 10:20:24', NULL),
(4, 'dsf', 'gagan23@gmail.com', '$2y$10$irreTbDGKmYbJeg3ubq7HuiGt/Ae0in4AJGGMjm6lXqaTmHc4d1aW', '9513524624', '2026-05-09', 'Male', 'Harohalli', '2026-05-08 12:47:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patient_monitors`
--

CREATE TABLE `patient_monitors` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `monitor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_vitals`
--

CREATE TABLE `patient_vitals` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `systolic` int(11) DEFAULT NULL,
  `diastolic` int(11) DEFAULT NULL,
  `glucose` decimal(5,2) DEFAULT NULL,
  `spo2` int(11) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `report_name` varchar(255) DEFAULT NULL,
  `report_type` varchar(100) DEFAULT NULL,
  `file_path` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_access_requests`
--

CREATE TABLE `report_access_requests` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `monitor_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_share_requests`
--

CREATE TABLE `report_share_requests` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `requester_role` enum('doctor','monitor') NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `users`
-- (See below for the actual view)
--
CREATE TABLE `users` (
`id` int(11)
,`name` varchar(255)
,`email` varchar(255)
,`password` varchar(255)
,`phone` varchar(20)
,`gender` enum('Male','Female','Other')
,`address` mediumtext
,`profile_picture` varchar(255)
,`role` varchar(7)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_calls`
--

CREATE TABLE `video_calls` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `status` enum('waiting','active','ongoing','ended') DEFAULT 'waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `video_calls`
--

INSERT INTO `video_calls` (`id`, `appointment_id`, `patient_id`, `doctor_id`, `status`, `created_at`) VALUES
(79, 44, 1, 1, 'ended', '2026-05-05 17:14:08'),
(80, 44, 1, 1, 'ended', '2026-05-05 17:14:34'),
(81, 44, 1, 1, 'ended', '2026-05-05 17:15:45'),
(82, 45, 3, 1, 'ended', '2026-05-06 10:27:33'),
(83, 46, 3, 1, 'ended', '2026-05-07 07:57:37'),
(84, 47, 3, 1, 'ended', '2026-05-07 14:48:29'),
(85, 48, 3, 1, 'ended', '2026-05-09 15:28:56'),
(86, 50, 1, 4, 'ended', '2026-05-11 12:59:25'),
(87, 50, 1, 4, 'ended', '2026-05-11 13:02:14');

-- --------------------------------------------------------

--
-- Structure for view `users`
--
DROP TABLE IF EXISTS `users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `users`  AS SELECT `patients`.`id` AS `id`, `patients`.`name` AS `name`, `patients`.`email` AS `email`, `patients`.`password` AS `password`, `patients`.`phone` AS `phone`, `patients`.`gender` AS `gender`, `patients`.`address` AS `address`, `patients`.`profile_picture` AS `profile_picture`, 'patient' AS `role`, `patients`.`created_at` AS `created_at` FROM `patients`union all select `doctors`.`id` AS `id`,`doctors`.`name` AS `name`,`doctors`.`email` AS `email`,`doctors`.`password` AS `password`,`doctors`.`phone` AS `phone`,NULL AS `gender`,NULL AS `address`,`doctors`.`profile_picture` AS `profile_picture`,'doctor' AS `role`,`doctors`.`created_at` AS `created_at` from `doctors`  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `checklists`
--
ALTER TABLE `checklists`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `checklist_logs`
--
ALTER TABLE `checklist_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `daily_item` (`checklist_item_id`,`taken_date`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `doctor_credentials`
--
ALTER TABLE `doctor_credentials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id1` (`user_id1`,`user_id2`);

--
-- Indexes for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sender_id` (`sender_id`,`receiver_id`);

--
-- Indexes for table `medicine_intakes`
--
ALTER TABLE `medicine_intakes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_intake` (`checklist_item_id`,`scheduled_date`,`time_of_day_slot`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `monitor_requests`
--
ALTER TABLE `monitor_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`requester_id`,`requested_user_id`),
  ADD KEY `requested_user_id` (`requested_user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `patient_monitors`
--
ALTER TABLE `patient_monitors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `report_access_requests`
--
ALTER TABLE `report_access_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`report_id`,`monitor_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `monitor_id` (`monitor_id`);

--
-- Indexes for table `report_share_requests`
--
ALTER TABLE `report_share_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`report_id`,`requester_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `requester_id` (`requester_id`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `video_calls`
--
ALTER TABLE `video_calls`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `checklists`
--
ALTER TABLE `checklists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `checklist_items`
--
ALTER TABLE `checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `checklist_logs`
--
ALTER TABLE `checklist_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `doctor_credentials`
--
ALTER TABLE `doctor_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `friend_requests`
--
ALTER TABLE `friend_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `medicine_intakes`
--
ALTER TABLE `medicine_intakes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `monitor_requests`
--
ALTER TABLE `monitor_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `patient_monitors`
--
ALTER TABLE `patient_monitors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `report_access_requests`
--
ALTER TABLE `report_access_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_share_requests`
--
ALTER TABLE `report_share_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `video_calls`
--
ALTER TABLE `video_calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `checklist_logs`
--
ALTER TABLE `checklist_logs`
  ADD CONSTRAINT `checklist_logs_ibfk_1` FOREIGN KEY (`checklist_item_id`) REFERENCES `checklist_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_credentials`
--
ALTER TABLE `doctor_credentials`
  ADD CONSTRAINT `doctor_credentials_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medicine_intakes`
--
ALTER TABLE `medicine_intakes`
  ADD CONSTRAINT `medicine_intakes_ibfk_1` FOREIGN KEY (`checklist_item_id`) REFERENCES `checklist_items` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
