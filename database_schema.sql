-- ============================================================
-- IAMS — Internship & Attachment Management System
-- University of Botswana  |  Release 1.0 + 2.0 Schema
-- ============================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+02:00";

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`        INT AUTO_INCREMENT PRIMARY KEY,
  `email`          VARCHAR(191) NOT NULL UNIQUE,
  `password_hash`  VARCHAR(255) NOT NULL,
  `full_name`      VARCHAR(150) NOT NULL,
  `student_number` VARCHAR(50)  UNIQUE DEFAULT NULL,
  `programme`      VARCHAR(150) DEFAULT NULL,
  `phone`          VARCHAR(30)  DEFAULT NULL,
  `role`           ENUM('student','organisation','coordinator','admin') NOT NULL DEFAULT 'student',
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login`     TIMESTAMP    NULL DEFAULT NULL,
  `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: login_attempts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `email`        VARCHAR(191) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempted_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_ip`    (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: password_resets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT NOT NULL,
  `token_hash`  VARCHAR(64) NOT NULL,
  `expires_at`  DATETIME NOT NULL,
  `used`        TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_token` (`token_hash`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: student_profiles
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_profiles` (
  `profile_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT NOT NULL UNIQUE,
  `year_of_study` TINYINT UNSIGNED DEFAULT NULL,
  `gpa`           DECIMAL(3,2)     DEFAULT NULL,
  `linkedin_url`  VARCHAR(300)     DEFAULT NULL,
  `github_url`    VARCHAR(300)     DEFAULT NULL,
  `portfolio_url` VARCHAR(300)     DEFAULT NULL,
  `skills`        TEXT             DEFAULT NULL,
  `bio`           TEXT             DEFAULT NULL,
  `updated_at`    TIMESTAMP        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: organisations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `organisations` (
  `org_id`          INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT NOT NULL UNIQUE,
  `org_name`        VARCHAR(200) NOT NULL,
  `industry`        VARCHAR(100) DEFAULT NULL,
  `contact_person`  VARCHAR(150) DEFAULT NULL,
  `contact_email`   VARCHAR(191) DEFAULT NULL,
  `contact_phone`   VARCHAR(30)  DEFAULT NULL,
  `address`         VARCHAR(300) DEFAULT NULL,
  `location`        VARCHAR(150) DEFAULT NULL,
  `description`     TEXT         DEFAULT NULL,
  `required_skills` TEXT         DEFAULT NULL,
  `capacity`        INT UNSIGNED DEFAULT 1,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: applications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `applications` (
  `app_id`             INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`            INT NOT NULL UNIQUE,
  `full_name`          VARCHAR(150) NOT NULL,
  `student_number`     VARCHAR(50)  NOT NULL,
  `programme`          VARCHAR(150) NOT NULL,
  `year_of_study`      TINYINT UNSIGNED DEFAULT NULL,
  `skills`             TEXT         DEFAULT NULL,
  `preferred_location` VARCHAR(150) DEFAULT NULL,
  `cover_letter`       TEXT         DEFAULT NULL,
  `status`             ENUM('pending','under_review','matched','accepted','rejected') NOT NULL DEFAULT 'pending',
  `matched_org_id`     INT          DEFAULT NULL,
  `submission_date`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by`        INT          DEFAULT NULL,
  `reviewed_at`        TIMESTAMP    NULL DEFAULT NULL,
  `review_notes`       TEXT         DEFAULT NULL,
  FOREIGN KEY (`user_id`)        REFERENCES `users`(`user_id`)        ON DELETE CASCADE,
  FOREIGN KEY (`matched_org_id`) REFERENCES `organisations`(`org_id`) ON DELETE SET NULL,
  FOREIGN KEY (`reviewed_by`)    REFERENCES `users`(`user_id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: documents
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documents` (
  `doc_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT NOT NULL,
  `doc_type`    VARCHAR(100) NOT NULL,
  `filename`    VARCHAR(255) NOT NULL,
  `file_path`   VARCHAR(500) NOT NULL,
  `file_size`   INT UNSIGNED DEFAULT NULL,
  `mime_type`   VARCHAR(100) DEFAULT NULL,
  `uploaded_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: job_posts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_posts` (
  `job_id`       INT AUTO_INCREMENT PRIMARY KEY,
  `title`        VARCHAR(200) NOT NULL,
  `organization` VARCHAR(200) NOT NULL DEFAULT 'Ministry of Labour and Home Affairs',
  `org_id`       INT          DEFAULT NULL,
  `location`     VARCHAR(150) DEFAULT NULL,
  `description`  TEXT         DEFAULT NULL,
  `requirements` TEXT         DEFAULT NULL,
  `salary_range` VARCHAR(100) DEFAULT NULL,
  `duration`     VARCHAR(100) DEFAULT NULL,
  `slots`        INT UNSIGNED DEFAULT 1,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `posted_by`    INT          DEFAULT NULL,
  `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`org_id`)    REFERENCES `organisations`(`org_id`) ON DELETE SET NULL,
  FOREIGN KEY (`posted_by`) REFERENCES `users`(`user_id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: job_interests
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_interests` (
  `interest_id`  INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT NOT NULL,
  `job_id`       INT NOT NULL,
  `expressed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_interest` (`user_id`,`job_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)    ON DELETE CASCADE,
  FOREIGN KEY (`job_id`)  REFERENCES `job_posts`(`job_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: matches
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `matches` (
  `match_id`       INT AUTO_INCREMENT PRIMARY KEY,
  `app_id`         INT NOT NULL,
  `user_id`        INT NOT NULL,
  `org_id`         INT NOT NULL,
  `job_id`         INT          DEFAULT NULL,
  `match_score`    DECIMAL(5,2) DEFAULT NULL,
  `status`         ENUM('suggested','confirmed','declined') NOT NULL DEFAULT 'suggested',
  `coordinator_id` INT          DEFAULT NULL,
  `notes`          TEXT         DEFAULT NULL,
  `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at`   TIMESTAMP    NULL DEFAULT NULL,
  FOREIGN KEY (`app_id`)         REFERENCES `applications`(`app_id`)  ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)        REFERENCES `users`(`user_id`)        ON DELETE CASCADE,
  FOREIGN KEY (`org_id`)         REFERENCES `organisations`(`org_id`) ON DELETE CASCADE,
  FOREIGN KEY (`job_id`)         REFERENCES `job_posts`(`job_id`)     ON DELETE SET NULL,
  FOREIGN KEY (`coordinator_id`) REFERENCES `users`(`user_id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: logbooks
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logbooks` (
  `logbook_id`        INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`           INT NOT NULL,
  `app_id`            INT NOT NULL,
  `week_number`       TINYINT UNSIGNED NOT NULL,
  `week_start_date`   DATE NOT NULL,
  `activities`        TEXT NOT NULL,
  `learning_outcomes` TEXT DEFAULT NULL,
  `challenges`        TEXT DEFAULT NULL,
  `supervisor_comment`TEXT DEFAULT NULL,
  `status`            ENUM('draft','submitted','reviewed','late') NOT NULL DEFAULT 'draft',
  `submitted_at`      TIMESTAMP NULL DEFAULT NULL,
  `reviewed_at`       TIMESTAMP NULL DEFAULT NULL,
  `reviewed_by`       INT DEFAULT NULL,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_week` (`user_id`,`week_number`),
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`user_id`)       ON DELETE CASCADE,
  FOREIGN KEY (`app_id`)      REFERENCES `applications`(`app_id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`user_id`)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: student_reports
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_reports` (
  `report_id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`           INT NOT NULL,
  `app_id`            INT NOT NULL,
  `title`             VARCHAR(255) NOT NULL,
  `executive_summary` TEXT DEFAULT NULL,
  `body`              LONGTEXT DEFAULT NULL,
  `conclusion`        TEXT DEFAULT NULL,
  `file_path`         VARCHAR(500) DEFAULT NULL,
  `status`            ENUM('draft','submitted','reviewed') NOT NULL DEFAULT 'draft',
  `submitted_at`      TIMESTAMP NULL DEFAULT NULL,
  `reviewed_at`       TIMESTAMP NULL DEFAULT NULL,
  `reviewed_by`       INT DEFAULT NULL,
  `grade`             VARCHAR(10) DEFAULT NULL,
  `feedback`          TEXT DEFAULT NULL,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`user_id`)       ON DELETE CASCADE,
  FOREIGN KEY (`app_id`)      REFERENCES `applications`(`app_id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`user_id`)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: supervisor_reports
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supervisor_reports` (
  `sup_report_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `student_user_id`    INT NOT NULL,
  `app_id`             INT NOT NULL,
  `supervisor_user_id` INT NOT NULL,
  `performance_rating` TINYINT UNSIGNED DEFAULT NULL,
  `punctuality`        TINYINT UNSIGNED DEFAULT NULL,
  `communication`      TINYINT UNSIGNED DEFAULT NULL,
  `technical_skills`   TINYINT UNSIGNED DEFAULT NULL,
  `teamwork`           TINYINT UNSIGNED DEFAULT NULL,
  `comments`           TEXT DEFAULT NULL,
  `recommendation`     ENUM('highly_recommend','recommend','neutral','not_recommend') DEFAULT NULL,
  `status`             ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
  `submitted_at`       TIMESTAMP NULL DEFAULT NULL,
  `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_user_id`)    REFERENCES `users`(`user_id`)       ON DELETE CASCADE,
  FOREIGN KEY (`app_id`)             REFERENCES `applications`(`app_id`) ON DELETE CASCADE,
  FOREIGN KEY (`supervisor_user_id`) REFERENCES `users`(`user_id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: site_visit_assessments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_visit_assessments` (
  `assessment_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `student_user_id`  INT NOT NULL,
  `app_id`           INT NOT NULL,
  `assessor_id`      INT NOT NULL,
  `visit_number`     TINYINT UNSIGNED NOT NULL,
  `visit_date`       DATE NOT NULL,
  `work_quality`     TINYINT UNSIGNED DEFAULT NULL,
  `attitude`         TINYINT UNSIGNED DEFAULT NULL,
  `technical_ability`TINYINT UNSIGNED DEFAULT NULL,
  `overall_score`    DECIMAL(4,1)     DEFAULT NULL,
  `comments`         TEXT DEFAULT NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_visit` (`student_user_id`,`visit_number`),
  FOREIGN KEY (`student_user_id`) REFERENCES `users`(`user_id`)       ON DELETE CASCADE,
  FOREIGN KEY (`app_id`)          REFERENCES `applications`(`app_id`) ON DELETE CASCADE,
  FOREIGN KEY (`assessor_id`)     REFERENCES `users`(`user_id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: notifications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `notif_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `message`    TEXT NOT NULL,
  `type`       ENUM('info','warning','success','deadline') NOT NULL DEFAULT 'info',
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `link`       VARCHAR(300) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DEFAULT ACCOUNTS  (passwords set by setup.php on first run)
-- ============================================================
-- admin@ub.ac.bw / password set via setup.php
-- coordinator@ub.ac.bw / password set via setup.php

-- Sample job posts (optional, can be removed if not needed)
INSERT INTO `job_posts` (`title`,`organization`,`location`,`description`,`salary_range`,`duration`,`slots`) VALUES
('IT Support Intern','Ministry of Labour and Home Affairs','Gaborone','Assist IT dept with helpdesk support and system monitoring.','BWP 1,500/month','6 months',2),
('Data Entry Clerk','Ministry of Labour and Home Affairs','Gaborone','Input and verify data into the national employment registry.','BWP 1,200/month','3 months',3),
('HR Administration Intern','Dept. of Public Service Management','Gaborone','Support HR operations including records management and staff onboarding.','BWP 1,500/month','6 months',2),
('Network Technician Intern','BOCRA','Gaborone','Assist with network monitoring and telecom infrastructure documentation.','BWP 1,800/month','6 months',1),
('Accounts Assistant','BURS','Gaborone','Support accounts team with reconciliation and financial reporting.','BWP 1,600/month','6 months',2),
('Web Development Intern','Ministry of Labour and Home Affairs','Gaborone','Maintain and improve government web portals using PHP/HTML/CSS.','BWP 2,000/month','6 months',2);