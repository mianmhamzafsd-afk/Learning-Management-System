-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 20, 2025 at 05:50 AM
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
-- Database: `my project`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'admin',
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `admin_name`, `username`, `email`, `password`, `phone`, `role`, `created_at`, `last_login`) VALUES
(1, 'admin', 'admin', 'admin@gmail.com', '$2y$10$GCr7x.1mluqFx1xJxtcCf.rbEGzEU2L8/ygxJNzoIE.n.vHlLpLlu', '03005555653', 'admin', '2025-11-12 15:56:50', NULL),
(3, 'admin2', 'admin2', 'admin2@gmail.com', '$2y$10$GCr7x.1mluqFx1xJxtcCf.rbEGzEU2L8/ygxJNzoIE.n.vHlLpLlu', '00000000000', 'admin', '2025-12-06 20:07:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `booking_requests`
--

CREATE TABLE `booking_requests` (
  `request_id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` text NOT NULL,
  `attendees_count` int(11) NOT NULL,
  `equipment_needed` text DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `admin_response` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_requests`
--

INSERT INTO `booking_requests` (`request_id`, `venue_id`, `faculty_id`, `department`, `booking_date`, `start_time`, `end_time`, `purpose`, `attendees_count`, `equipment_needed`, `additional_notes`, `status`, `admin_response`, `requested_at`, `responded_at`, `admin_id`) VALUES
(3, 3, 17, 'Data Science', '2025-12-21', '09:00:00', '10:00:00', 'No', 10, '', '', 'pending', NULL, '2025-12-20 03:00:53', NULL, NULL),
(4, 1, 17, 'Data Science', '2025-12-22', '09:00:00', '10:00:00', 'No', 10, '', '', 'rejected', 'no reason', '2025-12-20 03:01:15', '2025-12-20 09:34:46', 1),
(5, 2, 17, 'Data Science', '2025-12-23', '09:00:00', '10:00:00', 'No', 10, '', '', 'approved', '', '2025-12-20 03:01:35', '2025-12-20 09:03:43', 1),
(6, 2, 17, 'Data Science', '2025-12-24', '09:00:00', '10:00:00', 'Seminar', 10, 'NO', 'No', 'cancelled', NULL, '2025-12-20 03:02:13', NULL, NULL),
(7, 2, 6, 'Zoology', '2025-12-21', '09:00:00', '10:00:00', 'Seminar', 10, 'No', 'No', 'pending', NULL, '2025-12-20 04:45:14', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `booking_venues`
--

CREATE TABLE `booking_venues` (
  `venue_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('conference_room','auditorium','seminar_hall') NOT NULL,
  `capacity` int(11) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `amenities` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_venues`
--

INSERT INTO `booking_venues` (`venue_id`, `name`, `type`, `capacity`, `location`, `amenities`, `status`, `created_at`, `admin_id`) VALUES
(1, 'LTR', 'seminar_hall', 1000, 'Near clock tower', 'All except wifi', 'active', '2025-12-20 02:51:58', 1),
(2, 'Exhibition center', 'conference_room', 1000, 'Near clock tower', 'All except wifi', 'active', '2025-12-20 02:54:10', 1),
(3, 'Iqbal', 'auditorium', 1500, 'Near clock tower', 'All except wifi', 'active', '2025-12-20 02:56:07', 1);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `credits` int(11) DEFAULT 3,
  `semester` varchar(50) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `schedule` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `prerequisites` varchar(255) DEFAULT NULL,
  `max_students` int(11) DEFAULT 30,
  `current_students` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `event_type` enum('conference','workshop','seminar','meeting','cultural','sports') DEFAULT 'meeting',
  `max_participants` int(11) DEFAULT NULL,
  `current_participants` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `faculty_id`, `title`, `description`, `event_date`, `event_time`, `venue`, `event_type`, `max_participants`, `current_participants`, `is_active`, `created_at`) VALUES
(2, 1, 'Great Game', 'Conflict between civilization', '2025-11-27', '01:58:00', 'Alaska', '', NULL, 0, 1, '2025-11-27 05:59:24');

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `attendance_status` enum('registered','attended','absent') DEFAULT 'registered',
  `certificate_issued` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `faculty_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  `office_hours` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_focal_person` tinyint(1) DEFAULT 0,
  `focal_responsibility` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`faculty_id`, `admin_id`, `name`, `username`, `email`, `phone`, `password`, `department`, `designation`, `specialization`, `office_location`, `office_hours`, `address`, `hire_date`, `created_at`, `is_focal_person`, `focal_responsibility`) VALUES
(1, 1, 'Isaac Newton', 'Newton', 'Newton@gmail.com', '00000000000', '$2y$10$AkSQOSaBSQhecpVp8ZFJJOdicJZ0p8w2.V0tCIUDTFBAQ6lN6t/E2', 'Physics', 'Professor', 'Gravity', 'None', 'None2', 'None3', '2025-11-26', '2025-11-26 11:31:19', 0, NULL),
(3, 1, 'Al Khwarizmi', 'Khwarizmi', 'Khwarizmi@gmail.com', '00000000000', '$2y$10$j4kRyfLFTvL4CEI5KvRd7evTxu0naadKROMfL12TOQ2CXHp1LhgzG', 'Mathematics', 'Professor', 'Algebra', 'None', 'None2', 'None3', '2025-11-27', '2025-11-27 14:44:53', 0, NULL),
(5, 1, 'Gilgamesh', 'Gilgamesh', 'Gilgamesh@gmail.com', '00000000000', '$2y$10$gOPAi7TJolwKcCVgzYt6mOqYHmT38zWw4n1OJ/WMe8Y4LEC3qma8S', 'Mathematics', 'Assistant Professor', 'Book', 'None', 'None2', 'None3', '2025-11-28', '2025-11-28 04:22:28', 1, 'Mathematics department manage notifications, events, news ets'),
(6, 1, 'Ali', 'Ali', 'Ali@gmail.com', '00000000000', '$2y$10$66xQ3rV9eLPjKuKBaKENV.qp8JRtHW6/QElYq9cZnSNWFp0DTbTn.', 'Zoology', 'Assistant Professor', 'Book', 'None', 'None2', 'None3', '2025-11-28', '2025-11-28 04:52:20', 1, 'Zoology department manage notifications, events, news ets'),
(7, 1, 'usman', 'usman', 'usman@gmail.com', '00000000000', '$2y$10$eAXW55vPQRoVnXbuKSMyuuRqucFfYsfp/KdcxbBeQ/n8lXUZ5baAK', 'Physics', 'Assistant Professor', 'Theorem', 'None', 'None2', 'None3', '2025-11-20', '2025-11-28 05:18:10', 0, ''),
(16, 1, 'Hamza', 'Hamza Nadeem', 'hamza@gmail.com', '00000000000', '$2y$10$4TNA5ShfekiJ.s.XruWOjO2HO6zsERtDonME6rgPd.aOCStYKYb3G', 'Data Science', 'Professor', 'Big Data', 'None', 'None2', 'no address', '2025-12-19', '2025-12-19 04:09:04', 0, ''),
(17, 1, 'focal2', 'focal2', 'focal2@gmail.com', '00000000000', '$2y$10$q1sc5iSXf6vk2dtT763Bx.N49yYZGqtbtkmNSWLpOAxz53m6HG9ZG', 'Data Science', 'Senior Lecturer', 'Big Data', 'None', 'None2', 'no address', '2025-12-19', '2025-12-19 04:32:36', 0, ''),
(18, 1, 'focal3', 'focal3', 'focal3@gmail.com', '00000000000', '$2y$10$z1F74De7Yu34gaL69XykgOALHRbKlJThK74.cD9gx9sqtYweI6RUG', 'Information Technology', 'Associate Professor', 'Book', 'None', 'None2', 'No address', '2025-12-11', '2025-12-20 03:56:17', 1, ''),
(19, 1, 'focal4', 'focal4', 'focal4@gmail.com', '00000000000', '$2y$10$C67v5eC1EToDm2Ksc9kfbumqrsZ4g5Kk.t4PJSdNa/sYIF7UfqqDy', 'Data Science', 'Professor', 'Book', 'None', 'None2', 'no address', '2025-12-20', '2025-12-20 04:05:51', 1, '');

-- --------------------------------------------------------

--
-- Table structure for table `news_updates`
--

CREATE TABLE `news_updates` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('news','update') DEFAULT 'news',
  `attachment` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news_updates`
--

INSERT INTO `news_updates` (`id`, `faculty_id`, `title`, `content`, `type`, `attachment`, `is_published`, `created_at`, `updated_at`) VALUES
(1, 1, 'none', 'kdjfiefjdisiofodjfif', 'news', '', 1, '2025-11-26 12:16:20', '2025-11-26 12:16:20'),
(2, 1, 'fgfg', 'ghtyjuy', 'update', NULL, 1, '2025-11-27 05:40:09', '2025-11-27 05:40:09'),
(3, 6, 'no title for zoology', 'no content', 'news', NULL, 1, '2025-11-28 06:00:01', '2025-11-28 06:00:01');

-- --------------------------------------------------------

--
-- Table structure for table `notices`
--

CREATE TABLE `notices` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` enum('academic','administrative','event','general') DEFAULT 'general',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notices`
--

INSERT INTO `notices` (`id`, `faculty_id`, `title`, `content`, `category`, `priority`, `start_date`, `end_date`, `is_active`, `created_at`) VALUES
(1, 1, 'vdfvdf', 'fdfdf', 'event', 'medium', NULL, NULL, 1, '2025-11-27 05:40:37');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('info','warning','success','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `target_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `faculty_id`, `title`, `message`, `notification_type`, `is_read`, `target_url`, `created_at`) VALUES
(1, 1, 'non', 'Art of war', 'info', 0, '', '2025-11-27 04:28:45'),
(2, 1, 'Resources conflict', 'nothing', 'warning', 1, NULL, '2025-11-27 05:42:50'),
(3, 17, 'no title', 'dfkfkdjnfdk', 'info', 0, '', '2025-12-19 05:17:50');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `course` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `registration_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) DEFAULT 'student'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `admin_id`, `name`, `username`, `email`, `phone`, `password`, `department`, `course`, `address`, `profile_picture`, `registration_date`, `created_at`, `role`) VALUES
(1, 1, 'Pluto', 'Pluto', 'Pluto@gmail.com', '00000000000', '$2y$10$Gk2I6vi6KuCYUkG7fFvE9uw1Rhja4hxrwmdsPn.NN2MximG56B/IW', 'Computer Science', 'Machine Learning', 'Non', 'student_1_1765080868.png', '2025-11-12', '2025-11-12 10:58:57', 'student'),
(3, 1, 'student2', 'student2', 'student2@gmail.com', '00000000000', '$2y$10$fKAvH8st.9Ym8FWu5fySU.XHTEH66mXG89gYbJZzLw729SJe0iXzC', 'Computer Science', 'Web programming', 'no address', NULL, '2025-11-02', '2025-12-19 04:10:20', 'student');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `booking_requests`
--
ALTER TABLE `booking_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `venue_id` (`venue_id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `booking_venues`
--
ALTER TABLE `booking_venues`
  ADD PRIMARY KEY (`venue_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`event_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `news_updates`
--
ALTER TABLE `news_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `admin_id` (`admin_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `booking_requests`
--
ALTER TABLE `booking_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `booking_venues`
--
ALTER TABLE `booking_venues`
  MODIFY `venue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `news_updates`
--
ALTER TABLE `news_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notices`
--
ALTER TABLE `notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_requests`
--
ALTER TABLE `booking_requests`
  ADD CONSTRAINT `booking_requests_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `booking_venues` (`venue_id`),
  ADD CONSTRAINT `booking_requests_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`),
  ADD CONSTRAINT `booking_requests_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`);

--
-- Constraints for table `booking_venues`
--
ALTER TABLE `booking_venues`
  ADD CONSTRAINT `booking_venues_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`);

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`);

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_registrations_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty`
--
ALTER TABLE `faculty`
  ADD CONSTRAINT `faculty_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`);

--
-- Constraints for table `news_updates`
--
ALTER TABLE `news_updates`
  ADD CONSTRAINT `news_updates_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`);

--
-- Constraints for table `notices`
--
ALTER TABLE `notices`
  ADD CONSTRAINT `notices_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
