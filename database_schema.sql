-- ============================================================
-- IAMS - Internship & Attachment Management System
-- University of Botswana
-- Database Schema
-- ============================================================
-- Run this SQL in your hosting control panel's phpMyAdmin
-- after creating your database.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+02:00";  -- Botswana is UTC+2

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`       INT AUTO_INCREMENT PRIMARY KEY,
  `email`         VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(150) NOT NULL,
  `student_number` VARCHAR(50) UNIQUE,
  `programme`     VARCHAR(150),
  `phone`         VARCHAR(30),
  `role`          ENUM('student','coordinator','admin') NOT NULL DEFAULT 'student',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: student_profiles
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_profiles` (
  `profile_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT NOT NULL,
  `linkedin_url`  VARCHAR(300),
  `github_url`    VARCHAR(300),
  `portfolio_url` VARCHAR(300),
  `skills`        TEXT,
  `bio`           TEXT,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: applications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `applications` (
  `app_id`            INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`           INT NOT NULL,
  `full_name`         VARCHAR(150) NOT NULL,
  `student_number`    VARCHAR(50) NOT NULL,
  `programme`         VARCHAR(150) NOT NULL,
  `skills`            TEXT,
  `preferred_location` VARCHAR(150),
  `status`            ENUM('pending','under_review','accepted','rejected') NOT NULL DEFAULT 'pending',
  `submission_date`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by`       INT,
  `review_notes`      TEXT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: documents
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documents` (
  `doc_id`        INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT NOT NULL,
  `doc_type`      VARCHAR(100) NOT NULL,
  `filename`      VARCHAR(255) NOT NULL,
  `file_path`     VARCHAR(500) NOT NULL,
  `file_size`     INT,
  `uploaded_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: job_posts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_posts` (
  `job_id`        INT AUTO_INCREMENT PRIMARY KEY,
  `title`         VARCHAR(200) NOT NULL,
  `organization`  VARCHAR(200) NOT NULL DEFAULT 'Ministry of Labour and Home Affairs',
  `location`      VARCHAR(150),
  `description`   TEXT,
  `requirements`  TEXT,
  `salary_range`  VARCHAR(100),
  `duration`      VARCHAR(100),
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `posted_by`     INT,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: job_interests
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_interests` (
  `interest_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT NOT NULL,
  `job_id`        INT NOT NULL,
  `expressed_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_interest` (`user_id`, `job_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`job_id`)  REFERENCES `job_posts`(`job_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SAMPLE DATA — remove before going live if desired
-- ============================================================

-- Sample admin user (password: Admin@1234 — CHANGE THIS!)
INSERT INTO `users` (`email`, `password_hash`, `full_name`, `role`) VALUES
('admin@ub.ac.bw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

-- Sample job posts
INSERT INTO `job_posts` (`title`, `organization`, `location`, `description`, `salary_range`, `duration`) VALUES
('IT Support Intern', 'Ministry of Labour and Home Affairs', 'Gaborone', 'Assist the IT department with helpdesk support, hardware maintenance, and system monitoring.', 'BWP 1,500/month', '6 months'),
('Data Entry Clerk', 'Ministry of Labour and Home Affairs', 'Gaborone', 'Input and verify data from physical forms into the national employment registry system.', 'BWP 1,200/month', '3 months'),
('HR Administration Intern', 'Department of Public Service Management', 'Gaborone', 'Support HR operations including records management, staff onboarding, and leave administration.', 'BWP 1,500/month', '6 months'),
('Network Technician Intern', 'Botswana Communications Regulatory Authority', 'Gaborone', 'Assist with network monitoring, cable management, and telecom infrastructure documentation.', 'BWP 1,800/month', '6 months'),
('Accounts Assistant', 'Botswana Unified Revenue Service', 'Gaborone', 'Support the accounts team with data entry, reconciliation, and financial reporting tasks.', 'BWP 1,600/month', '6 months'),
('Web Development Intern', 'Ministry of Labour and Home Affairs', 'Gaborone', 'Assist in maintaining and improving government web portals using PHP, HTML, and CSS.', 'BWP 2,000/month', '6 months');
