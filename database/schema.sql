-- ============================================================
--  LoeiTech E-Sign System – Database Schema (Live Sync)
--  Database: e-sign
--  Generated At: 2026-04-14
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ───────────────────────────────────────────────
-- 1. INSTITUTION TABLE
-- ───────────────────────────────────────────────
DROP TABLE IF EXISTS `institution`;
CREATE TABLE `institution` (
  `inst_id` int(11) NOT NULL AUTO_INCREMENT,
  `inst_name` varchar(255) NOT NULL,
  PRIMARY KEY (`inst_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- ───────────────────────────────────────────────
-- 2. LOOKUP TABLES
-- ───────────────────────────────────────────────
DROP TABLE IF EXISTS `role`;
CREATE TABLE `role` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(255) NOT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `position`;
CREATE TABLE `position` (
  `position_id` int(11) NOT NULL AUTO_INCREMENT,
  `inst_id` int(11) NOT NULL DEFAULT 1,
  `position_name` varchar(255) NOT NULL,
  PRIMARY KEY (`position_id`),
  KEY `idx_position_inst` (`inst_id`),
  CONSTRAINT `fk_position_inst` FOREIGN KEY (`inst_id`) REFERENCES `institution` (`inst_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `department`;
CREATE TABLE `department` (
  `dep_id` int(11) NOT NULL AUTO_INCREMENT,
  `dep_name` varchar(255) NOT NULL,
  `inst_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`dep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `document_types`;
CREATE TABLE `document_types` (
  `doc_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `inst_id` int(11) NOT NULL DEFAULT 1,
  `doc_type_name` varchar(255) NOT NULL,
  PRIMARY KEY (`doc_type_id`),
  KEY `idx_doctype_inst` (`inst_id`),
  CONSTRAINT `fk_doctype_inst` FOREIGN KEY (`inst_id`) REFERENCES `institution` (`inst_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- ───────────────────────────────────────────────
-- 3. USER & SIGNATURES
-- ───────────────────────────────────────────────
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `sign` varchar(255) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `dep_id` int(11) DEFAULT NULL,
  `inst_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`),
  KEY `fk_user_position` (`position_id`),
  KEY `fk_user_role` (`role_id`),
  KEY `fk_user_department` (`dep_id`),
  KEY `fk_user_inst` (`inst_id`),
  CONSTRAINT `fk_user_department` FOREIGN KEY (`dep_id`) REFERENCES `department` (`dep_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_inst` FOREIGN KEY (`inst_id`) REFERENCES `institution` (`inst_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_position` FOREIGN KEY (`position_id`) REFERENCES `position` (`position_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `user_signatures`;
CREATE TABLE `user_signatures` (
  `sig_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sign_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`sig_id`),
  KEY `fk_usersign_user` (`user_id`),
  CONSTRAINT `fk_usersign_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- ───────────────────────────────────────────────
-- 4. DOCUMENT TABLES
-- ───────────────────────────────────────────────
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `doc_id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_no` varchar(50) NOT NULL,
  `doc_name` varchar(255) NOT NULL,
  `doc_upload_date` datetime NOT NULL,
  `doc_type_id` int(11) NOT NULL,
  `doc_uploader` int(11) NOT NULL,
  `doc_from` varchar(255) DEFAULT NULL,
  `inst_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`doc_id`),
  KEY `idx_documents_upload` (`doc_upload_date`),
  KEY `idx_documents_type` (`doc_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `document_files`;
CREATE TABLE `document_files` (
  `file_id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  PRIMARY KEY (`file_id`),
  KEY `doc_id` (`doc_id`),
  CONSTRAINT `document_files_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`doc_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- ───────────────────────────────────────────────
-- 5. WORKFLOW TABLES
-- ───────────────────────────────────────────────
DROP TABLE IF EXISTS `sign_doc`;
CREATE TABLE `sign_doc` (
  `sign_doc_id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `dep_id` int(11) DEFAULT NULL,
  `doc_status` varchar(50) NOT NULL DEFAULT 'approve',
  `sign_sarabun` varchar(50) NOT NULL DEFAULT 'pending',
  `sign_codirector` varchar(50) NOT NULL DEFAULT 'pending',
  `sign_director` varchar(50) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`sign_doc_id`),
  KEY `doc_id` (`doc_id`),
  KEY `user_id` (`user_id`),
  KEY `dep_id` (`dep_id`),
  KEY `idx_sign_doc_status` (`doc_status`,`sign_sarabun`,`sign_codirector`,`sign_director`),
  KEY `idx_sign_doc_dep` (`dep_id`),
  CONSTRAINT `sign_doc_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`doc_id`),
  CONSTRAINT `sign_doc_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  CONSTRAINT `sign_doc_ibfk_3` FOREIGN KEY (`dep_id`) REFERENCES `department` (`dep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `sign_detail`;
CREATE TABLE `sign_detail` (
  `detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `sign_doc_id` int(11) DEFAULT NULL,
  `sign_file_id` int(11) NOT NULL,
  `sign_txt` text DEFAULT NULL,
  `sign_pic` varchar(255) DEFAULT NULL,
  `sign_by` int(11) DEFAULT NULL,
  `sign_datetime` datetime DEFAULT NULL,
  `x_pos` float DEFAULT NULL,
  `y_pos` float DEFAULT NULL,
  `page_num` int(11) DEFAULT NULL,
  PRIMARY KEY (`detail_id`),
  KEY `sign_detail_ibfk_2` (`sign_by`),
  KEY `idx_sign_detail_page` (`sign_doc_id`,`sign_file_id`,`page_num`),
  CONSTRAINT `sign_detail_ibfk_1` FOREIGN KEY (`sign_doc_id`) REFERENCES `sign_doc` (`sign_doc_id`) ON DELETE CASCADE,
  CONSTRAINT `sign_detail_ibfk_2` FOREIGN KEY (`sign_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
