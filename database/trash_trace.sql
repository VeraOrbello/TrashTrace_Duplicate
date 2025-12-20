-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 18, 2025 at 05:01 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `trash_trace`
--

-- --------------------------------------------------------

--
-- Table structure for table `driver_applications`
--

CREATE TABLE `driver_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `application_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('Missed Pickup','Other Issue') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Pending','Resolved') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `barangay` varchar(50) DEFAULT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `type` enum('pickup_scheduled','pickup_completed','pickup_delayed','pickup_cancelled','emergency','general') DEFAULT 'general',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pickup_assignments`
--

CREATE TABLE `pickup_assignments` (
  `id` int(11) NOT NULL,
  `pickup_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('assigned','in_progress','completed','cancelled','delayed') DEFAULT 'assigned',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pickup_schedules`
--

CREATE TABLE `pickup_schedules` (
  `id` int(11) NOT NULL,
  `barangay` varchar(50) NOT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `schedule_date` date NOT NULL,
  `status` enum('Scheduled','Completed','Delayed','Cancelled') DEFAULT 'Scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `barangay` varchar(50) NOT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('user','driver_pending','driver','admin') DEFAULT 'user',
  `notification_channel` enum('SMS','Email','Both') DEFAULT 'Email',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `mobile_number`, `city`, `barangay`, `zone`, `address`, `password`, `user_type`, `notification_channel`, `created_at`) VALUES
(1, 'Mary Grace', 'vmtherese7@gmail.com', '09764717026', 'CEBU CITY', 'Talamban', '1', '1', '$2y$10$gXrC9wFwLnRCX.MqqAN66Oy/tfQ0pFKL.R9s2o0yffK6cUoflPt7C', 'user', 'Email', '2025-12-16 15:54:54'),
(2, 'Barangay Admin', 'admin@example.com', NULL, 'Your City', 'Your Barangay', NULL, 'Barangay Hall', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Email', '2025-12-18 08:58:34');

--
-- Update user_type enum to remove barangay_driver and barangay_driver_pending
--
ALTER TABLE users MODIFY COLUMN user_type ENUM('user','driver_pending','driver','admin_pending','admin') DEFAULT 'user';

-- --------------------------------------------------------

--
-- Table structure for table `users_backup_2024`
--

CREATE TABLE `users_backup_2024` (
  `id` int(11) NOT NULL DEFAULT 0,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `barangay` varchar(50) NOT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('user','admin_pending','admin') DEFAULT 'user',
  `notification_channel` enum('SMS','Email','Both') DEFAULT 'Email',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users_backup_2024`
--

INSERT INTO `users_backup_2024` (`id`, `full_name`, `email`, `mobile_number`, `city`, `barangay`, `zone`, `address`, `password`, `user_type`, `notification_channel`, `created_at`) VALUES
(1, 'Mary Grace', 'vmtherese7@gmail.com', '09764717026', 'CEBU CITY', 'Talamban', '1', '1', '$2y$10$gXrC9wFwLnRCX.MqqAN66Oy/tfQ0pFKL.R9s2o0yffK6cUoflPt7C', 'user', 'Email', '2025-12-16 15:54:54'),
(2, 'Barangay Admin', 'admin@example.com', NULL, 'Your City', 'Your Barangay', NULL, 'Barangay Hall', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Email', '2025-12-18 08:58:34');

-- --------------------------------------------------------

--
-- Table structure for table `worker_applications`
--

CREATE TABLE `worker_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `birthdate` date NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `availability` varchar(50) DEFAULT NULL,
  `vehicle_access` varchar(10) DEFAULT NULL,
  `health_conditions` text DEFAULT NULL,
  `reason_application` text DEFAULT NULL,
  `status` enum('pending','reviewing','accepted','rejected') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `driver_applications`
--
ALTER TABLE `driver_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pickup_assignments`
--
ALTER TABLE `pickup_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_pickup_assignments_driver` (`driver_id`,`status`),
  ADD KEY `idx_pickup_assignments_pickup` (`pickup_id`);

--
-- Indexes for table `pickup_schedules`
--
ALTER TABLE `pickup_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `worker_applications`
--
ALTER TABLE `worker_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `driver_applications`
--
ALTER TABLE `driver_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pickup_assignments`
--
ALTER TABLE `pickup_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pickup_schedules`
--
ALTER TABLE `pickup_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `worker_applications`
--
ALTER TABLE `worker_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `driver_applications`
--
ALTER TABLE `driver_applications`
  ADD CONSTRAINT `driver_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pickup_assignments`
--
ALTER TABLE `pickup_assignments`
  ADD CONSTRAINT `pickup_assignments_ibfk_1` FOREIGN KEY (`pickup_id`) REFERENCES `pickup_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pickup_assignments_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pickup_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `worker_applications`
--
ALTER TABLE `worker_applications`
  ADD CONSTRAINT `worker_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
