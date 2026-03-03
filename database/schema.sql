-- ============================================================
--  LoeiTech E-Sign System – Database Schema
--  Database: e-sign
--  สำหรับ import: docker exec -i mariadb_edoc mysql -u esign -pesignpwd e-sign < database/schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ───────────────────────────────────────────────
-- 0. INSTITUTION TABLE (Multi-Tenant)
-- ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `institution` (
    `inst_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `inst_name` VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ───────────────────────────────────────────────
-- 1. LOOKUP TABLES
-- ───────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `role` (
    `role_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `position` (
    `position_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `inst_id`       INT NOT NULL,
    `position_name` VARCHAR(100) NOT NULL,
    CONSTRAINT `fk_position_inst` FOREIGN KEY (`inst_id`) REFERENCES `institution` (`inst_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `department` (
    `dep_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `inst_id`  INT NOT NULL,
    `dep_name` VARCHAR(100) NOT NULL,
    CONSTRAINT `fk_dep_inst` FOREIGN KEY (`inst_id`) REFERENCES `institution` (`inst_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `document_types` (
    `doc_type_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `inst_id`       INT NOT NULL,
    `doc_type_name` VARCHAR(100) NOT NULL,
    CONSTRAINT `fk_doctype_inst` FOREIGN KEY (`inst_id`) REFERENCES `institution` (`inst_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ───────────────────────────────────────────────
-- 2. USER TABLE
-- ───────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `user` (
    `user_id`     INT AUTO_INCREMENT PRIMARY KEY,
    `inst_id`     INT NOT NULL,
    `username`    VARCHAR(100) NOT NULL UNIQUE,
    `password`    VARCHAR(255) NOT NULL,
    `fullname`    VARCHAR(255) NOT NULL,
    `sign`        VARCHAR(255) DEFAULT NULL COMMENT 'path to signature image',
    `position_id` INT DEFAULT NULL,
    `role_id`     INT DEFAULT NULL,
    `dep_id`      INT DEFAULT NULL,
    CONSTRAINT `fk_user_inst`       FOREIGN KEY (`inst_id`)     REFERENCES `institution` (`inst_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_position`   FOREIGN KEY (`position_id`) REFERENCES `position`   (`position_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_user_role`       FOREIGN KEY (`role_id`)     REFERENCES `role`        (`role_id`)     ON DELETE SET NULL,
    CONSTRAINT `fk_user_department` FOREIGN KEY (`dep_id`)      REFERENCES `department`  (`dep_id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ───────────────────────────────────────────────
-- 3. DOCUMENT TABLES
-- ───────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `documents` (
    `doc_id`          INT AUTO_INCREMENT PRIMARY KEY,
    `inst_id`         INT           NOT NULL,
    `doc_no`          VARCHAR(100)  NOT NULL,
    `doc_name`        TEXT          NOT NULL,
    `doc_from`        VARCHAR(255)  NOT NULL,
    `doc_type_id`     INT           NOT NULL,
    `doc_uploader`    INT           NOT NULL,
    `doc_upload_date` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_doc_inst`     FOREIGN KEY (`inst_id`)      REFERENCES `institution`    (`inst_id`)     ON DELETE CASCADE,
    CONSTRAINT `fk_doc_type`     FOREIGN KEY (`doc_type_id`)  REFERENCES `document_types` (`doc_type_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_doc_uploader` FOREIGN KEY (`doc_uploader`) REFERENCES `user`           (`user_id`)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `document_files` (
    `file_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `doc_id`    INT          NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    CONSTRAINT `fk_docfile_doc` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`doc_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ───────────────────────────────────────────────
-- 4. SIGNING WORKFLOW TABLES
-- ───────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `sign_doc` (
    `sign_doc_id`      INT AUTO_INCREMENT PRIMARY KEY,
    `doc_id`           INT          NOT NULL,
    `user_id`          INT          NOT NULL  COMMENT 'ผู้เริ่มต้น workflow',
    `dep_id`           INT          DEFAULT NULL COMMENT 'ฝ่ายปลายทาง (รอง ผอ.)',
    `doc_status`       VARCHAR(50)  NOT NULL DEFAULT 'approve',
    `sign_sarabun`     VARCHAR(50)  NOT NULL DEFAULT 'pending',
    `sign_codirector`  VARCHAR(50)  NOT NULL DEFAULT 'pending',
    `sign_director`    VARCHAR(50)  NOT NULL DEFAULT 'pending',
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_signdoc_doc`  FOREIGN KEY (`doc_id`)  REFERENCES `documents`  (`doc_id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_signdoc_user` FOREIGN KEY (`user_id`) REFERENCES `user`        (`user_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_signdoc_dep`  FOREIGN KEY (`dep_id`)  REFERENCES `department`  (`dep_id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sign_detail` (
    `detail_id`      INT AUTO_INCREMENT PRIMARY KEY,
    `sign_doc_id`    INT           NOT NULL,
    `sign_file_id`   INT           NOT NULL  COMMENT 'FK → document_files.file_id',
    `page_num`       INT           NOT NULL DEFAULT 1,
    `x_pos`          FLOAT         NOT NULL DEFAULT 0,
    `y_pos`          FLOAT         NOT NULL DEFAULT 0,
    `sign_txt`       TEXT          DEFAULT NULL,
    `sign_pic`       VARCHAR(255)  DEFAULT NULL COMMENT 'path to signature image',
    `sign_by`        INT           DEFAULT NULL,
    `sign_datetime`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_signdetail_signdoc` FOREIGN KEY (`sign_doc_id`)  REFERENCES `sign_doc`       (`sign_doc_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_signdetail_file`    FOREIGN KEY (`sign_file_id`) REFERENCES `document_files` (`file_id`)     ON DELETE CASCADE,
    CONSTRAINT `fk_signdetail_by`      FOREIGN KEY (`sign_by`)      REFERENCES `user`           (`user_id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ───────────────────────────────────────────────
-- 5. INDEXES
-- ───────────────────────────────────────────────

CREATE INDEX idx_documents_upload    ON `documents`    (`doc_upload_date`);
CREATE INDEX idx_documents_type      ON `documents`    (`doc_type_id`);
CREATE INDEX idx_sign_doc_status     ON `sign_doc`     (`doc_status`, `sign_sarabun`, `sign_codirector`, `sign_director`);
CREATE INDEX idx_sign_doc_dep        ON `sign_doc`     (`dep_id`);
CREATE INDEX idx_sign_detail_page    ON `sign_detail`  (`sign_doc_id`, `sign_file_id`, `page_num`);

-- ───────────────────────────────────────────────
-- 6. SEED DATA
-- ───────────────────────────────────────────────

-- Institutions
INSERT INTO `institution` (`inst_id`, `inst_name`) VALUES
    (1, 'วิทยาลัยเทคนิคเลย'),
    (2, 'วิทยาลัยอาชีวศึกษาเลย');

-- Roles
INSERT INTO `role` (`role_id`, `role_name`) VALUES
    (1, 'ผู้ดูแลระบบ (Admin)'),
    (2, 'ผู้ใช้งานทั่วไป (User)');

-- Positions (Tenant 1)
INSERT INTO `position` (`position_id`, `inst_id`, `position_name`) VALUES
    (1, 1, 'ผู้อำนวยการ'),
    (2, 1, 'รองผู้อำนวยการ'),
    (3, 1, 'ครู'),
    (4, 1, 'เจ้าหน้าที่ธุรการ'),
    (5, 1, 'พนักงานราชการ');

-- Positions (Tenant 2)
INSERT INTO `position` (`inst_id`, `position_name`) VALUES
    (2, 'ผู้อำนวยการ'),
    (2, 'รองผู้อำนวยการ'),
    (2, 'ครู'),
    (2, 'เจ้าหน้าที่ธุรการ'),
    (2, 'พนักงานราชการ');

-- Departments (Tenant 1)
INSERT INTO `department` (`dep_id`, `inst_id`, `dep_name`) VALUES
    (1, 1, 'ฝ่ายบริหารทรัพยากร'),
    (2, 1, 'ฝ่ายวิชาการ'),
    (3, 1, 'ฝ่ายพัฒนากิจการนักเรียนนักศึกษา'),
    (4, 1, 'ฝ่ายแผนงานและความร่วมมือ'),
    (5, 1, 'งานสารบรรณ');

-- Departments (Tenant 2)
INSERT INTO `department` (`dep_id`, `inst_id`, `dep_name`) VALUES
    (6, 2, 'บริหารทรัพยากร (วอศ.)'),
    (7, 2, 'วิชาการ (วอศ.)'),
    (8, 2, 'งานสารบรรณ (วอศ.)');

-- Document Types (Tenant 1)
INSERT INTO `document_types` (`doc_type_id`, `inst_id`, `doc_type_name`) VALUES
    (1, 1, 'หนังสือภายนอก'),
    (2, 1, 'หนังสือภายใน'),
    (3, 1, 'หนังสือสั่งการ'),
    (4, 1, 'หนังสือประชาสัมพันธ์'),
    (5, 1, 'หนังสือที่เจ้าหน้าที่ทำขึ้น');

-- Document Types (Tenant 2)
INSERT INTO `document_types` (`inst_id`, `doc_type_name`) VALUES
    (2, 'หนังสือภายนอก'),
    (2, 'หนังสือภายใน'),
    (2, 'หนังสือสั่งการ'),
    (2, 'หนังสือประชาสัมพันธ์'),
    (2, 'หนังสือที่เจ้าหน้าที่ทำขึ้น');

-- Default Admin User
-- Password: admin1234  (hashed with password_hash bcrypt)
INSERT INTO `user` (`user_id`, `inst_id`, `username`, `password`, `fullname`, `position_id`, `role_id`, `dep_id`) VALUES
    (1, 1, 'admin1', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'แอดมิน เทคนิคเลย', 4, 1, 5),
    (2, 2, 'admin2', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'แอดมิน อาชีวะเลย', 4, 1, 8);

-- NOTE: Default password is "admin1234"
-- To generate a new password hash in PHP: echo password_hash('admin1234', PASSWORD_BCRYPT);

SET FOREIGN_KEY_CHECKS = 1;
