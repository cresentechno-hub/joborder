-- =====================================================================
-- Job Order Management System - Database Schema
-- Engine: MySQL 8.0+  |  Charset: utf8mb4  |  Collation: utf8mb4_unicode_ci
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `job_order_system`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `job_order_system`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. roles  (RBAC)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(50)  NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. permissions
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`        VARCHAR(80)  NOT NULL COMMENT 'e.g. job_order.create',
  `description` VARCHAR(255) NULL,
  UNIQUE KEY `uq_permissions_code` (`code`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. role_permissions (pivot)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission`
    FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. users (system login accounts)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50)  NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(150) NOT NULL,
  `role_id`       INT UNSIGNED NOT NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `failed_login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`  DATETIME     NULL COMMENT 'brute-force lockout expiry',
  `last_login_at` DATETIME     NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_role`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. job_stages (lookup / ordered list, editable by Admin)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `job_stages`;
CREATE TABLE `job_stages` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `stage_code` VARCHAR(10)  NOT NULL COMMENT 'e.g. 1, 3.1, 4.2',
  `stage_name` VARCHAR(150) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL,
  `color_code` VARCHAR(7)   NULL COMMENT 'hex badge color for UI',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY `uq_job_stages_code` (`stage_code`),
  KEY `idx_job_stages_sort` (`sort_order`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. job_orders (core entity: quotation capture + job order tracking)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `job_orders`;
CREATE TABLE `job_orders` (
  `id`                            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- captured from quotation upload
  `quotation_no`                  VARCHAR(50)   NOT NULL,
  `customer_name`                 VARCHAR(150)  NOT NULL,
  `subject`                       VARCHAR(255)  NOT NULL,
  `total_cost`                    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `quotation_file_path`           VARCHAR(255)  NOT NULL,
  `quotation_file_original_name`  VARCHAR(255)  NULL,

  -- PO upload (received later in the workflow)
  `po_file_path`                  VARCHAR(255)  NULL,
  `po_file_original_name`         VARCHAR(255)  NULL,

  -- job order tracking fields
  `job_start_date`                DATE          NOT NULL,
  `assigned_to`                   BIGINT UNSIGNED NOT NULL,
  `stage_id`                      INT UNSIGNED  NOT NULL,
  `current_stage_since`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 COMMENT 'refreshed by trigger whenever stage_id changes',
  `remarks`                       TEXT          NULL,

  -- audit
  `created_by`                    BIGINT UNSIGNED NOT NULL,
  `updated_by`                    BIGINT UNSIGNED NULL,
  `is_deleted`                    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`                    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY `uq_job_orders_quotation_no` (`quotation_no`),
  KEY `idx_job_orders_customer` (`customer_name`),
  KEY `idx_job_orders_stage` (`stage_id`),
  KEY `idx_job_orders_assigned` (`assigned_to`),
  KEY `idx_job_orders_start_date` (`job_start_date`),
  KEY `idx_job_orders_deleted` (`is_deleted`),

  CONSTRAINT `fk_jo_assigned_to`
    FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_jo_stage`
    FOREIGN KEY (`stage_id`) REFERENCES `job_stages` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_jo_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_jo_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. job_order_stage_history (audit trail; powers the "stuck > 7 days
--    in a stage" dashboard stat and gives a full timeline per job order)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `job_order_stage_history`;
CREATE TABLE `job_order_stage_history` (
  `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_order_id`  BIGINT UNSIGNED NOT NULL,
  `from_stage_id` INT UNSIGNED NULL,
  `to_stage_id`   INT UNSIGNED NOT NULL,
  `changed_by`    BIGINT UNSIGNED NULL,
  `changed_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_josh_job_order` (`job_order_id`),
  KEY `idx_josh_changed_at` (`changed_at`),
  CONSTRAINT `fk_josh_job_order`
    FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_josh_from_stage`
    FOREIGN KEY (`from_stage_id`) REFERENCES `job_stages` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_josh_to_stage`
    FOREIGN KEY (`to_stage_id`) REFERENCES `job_stages` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_josh_changed_by`
    FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. activity_logs (general admin/audit log)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     BIGINT UNSIGNED NULL,
  `action`      VARCHAR(100) NOT NULL COMMENT 'e.g. job_order.create, user.deactivate',
  `entity_type` VARCHAR(50)  NULL,
  `entity_id`   BIGINT UNSIGNED NULL,
  `details`     JSON NULL,
  `ip_address`  VARCHAR(45) NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_activity_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. settings (system settings key-value store)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- TRIGGERS
-- Keep current_stage_since and job_order_stage_history consistent
-- regardless of which application code path updates a job order.
-- =====================================================================
DELIMITER $$

DROP TRIGGER IF EXISTS `trg_job_orders_before_update`$$
CREATE TRIGGER `trg_job_orders_before_update`
BEFORE UPDATE ON `job_orders`
FOR EACH ROW
BEGIN
  IF NEW.stage_id <> OLD.stage_id THEN
    SET NEW.current_stage_since = NOW();
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_job_orders_after_insert`$$
CREATE TRIGGER `trg_job_orders_after_insert`
AFTER INSERT ON `job_orders`
FOR EACH ROW
BEGIN
  INSERT INTO `job_order_stage_history` (`job_order_id`, `from_stage_id`, `to_stage_id`, `changed_by`)
  VALUES (NEW.id, NULL, NEW.stage_id, NEW.created_by);
END$$

DROP TRIGGER IF EXISTS `trg_job_orders_after_update`$$
CREATE TRIGGER `trg_job_orders_after_update`
AFTER UPDATE ON `job_orders`
FOR EACH ROW
BEGIN
  IF NEW.stage_id <> OLD.stage_id THEN
    INSERT INTO `job_order_stage_history` (`job_order_id`, `from_stage_id`, `to_stage_id`, `changed_by`)
    VALUES (NEW.id, OLD.stage_id, NEW.stage_id, NEW.updated_by);
  END IF;
END$$

DELIMITER ;

-- =====================================================================
-- VIEW: convenience overview used by the dashboard / listing pages
-- "days_elapsed" and "days_in_stage" are computed live (never stored),
-- since MySQL generated columns cannot use non-deterministic functions
-- such as CURDATE().
-- =====================================================================
DROP VIEW IF EXISTS `v_job_orders_overview`;
CREATE VIEW `v_job_orders_overview` AS
SELECT
  jo.id,
  jo.quotation_no,
  jo.customer_name,
  jo.subject,
  jo.total_cost,
  jo.job_start_date,
  DATEDIFF(CURDATE(), jo.job_start_date)              AS days_elapsed,
  DATEDIFF(CURDATE(), DATE(jo.current_stage_since))   AS days_in_current_stage,
  jo.assigned_to,
  au.full_name                                        AS assigned_to_name,
  jo.stage_id,
  js.stage_code,
  js.stage_name,
  js.color_code                                       AS stage_color,
  jo.is_deleted,
  jo.created_at,
  jo.updated_at
FROM job_orders jo
JOIN users au       ON au.id = jo.assigned_to
JOIN job_stages js  ON js.id = jo.stage_id
WHERE jo.is_deleted = 0;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Roles
INSERT INTO `roles` (`name`, `description`) VALUES
  ('Admin',   'Full system access, user & role management, settings'),
  ('Manager', 'Full job order access and reporting, no system settings'),
  ('Sales',   'Create and manage own job orders'),
  ('Viewer',  'Read-only access to job orders and dashboard');

-- Permissions
INSERT INTO `permissions` (`code`, `description`) VALUES
  ('job_order.view',         'View job orders'),
  ('job_order.create',       'Create job orders (upload quotation/PO)'),
  ('job_order.edit',         'Edit job order details'),
  ('job_order.delete',       'Delete (soft-delete) job orders'),
  ('job_order.change_stage', 'Change job order stage'),
  ('user.manage',            'Create/edit/deactivate system users'),
  ('role.manage',            'Manage roles and permissions'),
  ('settings.manage',        'Manage system settings'),
  ('report.view',            'View dashboard statistics and reports');

-- Role <-> Permission mapping
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p WHERE r.name = 'Admin';

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Manager'
  AND p.code IN ('job_order.view','job_order.create','job_order.edit',
                 'job_order.change_stage','report.view');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Sales'
  AND p.code IN ('job_order.view','job_order.create','job_order.edit',
                 'job_order.change_stage');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Viewer'
  AND p.code IN ('job_order.view','report.view');

-- Job stages (exact workflow supplied by the business)
INSERT INTO `job_stages` (`stage_code`, `stage_name`, `sort_order`, `color_code`) VALUES
  ('1',   'Purchase Order Received',                 10, '#64748B'),
  ('2',   'Issued DO Invoice for Downpayment',        20, '#0EA5E9'),
  ('3',   'Deposit Receive and Start Order Stock',    30, '#0EA5E9'),
  ('3.1', 'Pending Stock Information from PIC',       31, '#D97706'),
  ('4',   'Stock Prepared',                           40, '#0EA5E9'),
  ('4.1', 'Goods Taken from PIC',                     41, '#0EA5E9'),
  ('4.2', 'Pending PO / Pending Issue Invoice',       42, '#D97706'),
  ('5',   'Issued DO Invoice For Full Payment',       50, '#0EA5E9'),
  ('6',   'Cop Sign & Delivery',                      60, '#0EA5E9'),
  ('7',   'Full Payment Received & Sales Completed',  70, '#16A34A'),
  ('8',   'Cancel PO',                                80, '#DC2626');

-- Default system settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('app_name',              'Job Order Management System'),
  ('timezone',              'Asia/Kuala_Lumpur'),
  ('date_format',           'd-m-Y'),
  ('items_per_page',        '20'),
  ('stage_pending_alert_days', '7'),
  ('upload_max_size_mb',    '10'),
  ('allowed_upload_types',  'pdf,jpg,jpeg,png');

-- NOTE: no admin user is seeded here on purpose — password_hash() must be
-- generated by PHP (bcrypt), not hand-written into SQL. The Step 2 backend
-- setup will ship a one-time seeder script that creates the first Admin
-- account interactively.
