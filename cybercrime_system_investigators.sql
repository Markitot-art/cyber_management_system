-- SQL import file for the Cybercrime Queuing System investigators
-- Run this in phpMyAdmin or via MySQL to create the database (if needed)
-- and populate the investigators table.

CREATE DATABASE IF NOT EXISTS `cybercrime_system`;
USE `cybercrime_system`;

CREATE TABLE IF NOT EXISTS `investigators` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'all',
  `gender` ENUM('male','female') NOT NULL DEFAULT 'male',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_investigator_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `investigators` (`name`, `category`, `gender`, `status`) VALUES
  ('Balaguer, Efren S', 'all', 'male', 'active'),
  ('Evangelista, Jay Andrew G', 'all', 'male', 'active'),
  ('Abelinde, James Robert T', 'all', 'male', 'active'),
  ('Bustamante, Paul Christian E', 'all', 'male', 'active'),
  ('Madregalijos, Eddie S', 'all', 'male', 'active'),
  ('Pacardo, Karlo C', 'all', 'male', 'active'),
  ('Floro Bhong, Oida S', 'all', 'male', 'active'),
  ('Lumanog, Ryan D', 'all', 'male', 'active'),
  ('Magdaraog, Joseph M', 'all', 'male', 'active'),
  ('Mariano, Marc V', 'all', 'male', 'active');
