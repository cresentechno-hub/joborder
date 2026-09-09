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
-- 4. branches (data-isolation boundary — a branch-restricted user only
--    sees job orders/LPR rentals/SMC contracts belonging to their own
--    branch; other roles are unrestricted)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_branches_name` (`name`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. users (system login accounts)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50)  NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(150) NOT NULL,
  `role_id`       INT UNSIGNED NOT NULL,
  `branch_id`     INT UNSIGNED NULL COMMENT 'NULL = unaffiliated (fine for Admin/Manager)',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `failed_login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`  DATETIME     NULL COMMENT 'brute-force lockout expiry',
  `last_login_at` DATETIME     NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_branch` (`branch_id`),
  CONSTRAINT `fk_users_role`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_users_branch`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. job_stages (lookup / ordered list, editable by Admin)
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
-- 7. job_orders (core entity: quotation capture + job order tracking)
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
  `branch_id`                     INT UNSIGNED  NOT NULL,
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
  KEY `idx_job_orders_branch` (`branch_id`),
  KEY `idx_job_orders_start_date` (`job_start_date`),
  KEY `idx_job_orders_deleted` (`is_deleted`),

  CONSTRAINT `fk_jo_assigned_to`
    FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_jo_branch`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_jo_stage`
    FOREIGN KEY (`stage_id`) REFERENCES `job_stages` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_jo_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_jo_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. job_order_stage_history (audit trail; powers the "stuck > 7 days
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
-- 9. job_order_comments (progress-update log: stage change, invoice/DO
--    upload, reassignment, remark — added from the job order edit form)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `job_order_comments`;
CREATE TABLE `job_order_comments` (
  `id`                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_order_id`                BIGINT UNSIGNED NOT NULL,
  `stage_id`                    INT UNSIGNED NOT NULL,
  `invoice_no`                  VARCHAR(100) NULL,
  `po_no`                       VARCHAR(100) NULL COMMENT 'the customer''s PO/Ref No. shown on the invoice — informational only, not linked to job_orders.po_file',
  `invoice_file_path`           VARCHAR(255) NULL,
  `invoice_file_original_name`  VARCHAR(255) NULL,
  `do_file_path`                VARCHAR(255) NULL,
  `do_file_original_name`       VARCHAR(255) NULL,
  `assigned_to`                 BIGINT UNSIGNED NOT NULL,
  `remark`                      TEXT NULL,
  `created_by`                  BIGINT UNSIGNED NOT NULL,
  `created_at`                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_joc_job_order` (`job_order_id`),
  KEY `idx_joc_created_at` (`created_at`),
  CONSTRAINT `fk_joc_job_order`
    FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_joc_stage`
    FOREIGN KEY (`stage_id`) REFERENCES `job_stages` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_joc_assigned_to`
    FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_joc_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9a. job_order_documents ("Other Documents" — any number of extra files
--     attached from the job order form, alongside quotation/PO). Stored
--     under uploads/{quotation_no}/other/ — see job_order_upload_dir()
--     in Helpers/helpers.php.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `job_order_documents`;
CREATE TABLE `job_order_documents` (
  `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_order_id`  BIGINT UNSIGNED NOT NULL,
  `file_path`     VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `uploaded_by`   BIGINT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_jod_job_order` (`job_order_id`),
  CONSTRAINT `fk_jod_job_order` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jod_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. job_order_comment_cc (multi-user CC list per comment — record
--     keeping only, this app has no outbound email/notification system)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `job_order_comment_cc`;
CREATE TABLE `job_order_comment_cc` (
  `comment_id` BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`comment_id`, `user_id`),
  CONSTRAINT `fk_jocc_comment`
    FOREIGN KEY (`comment_id`) REFERENCES `job_order_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jocc_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10a. job_order_assignees (multi-user "Assign To" list for a job order.
--      job_orders.assigned_to is kept as the PRIMARY assignee — first
--      person selected — so existing FK/history/trigger
--      logic that reads a single owner keeps working unchanged; this
--      table is the full list used for display and visibility checks.)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `job_order_assignees`;
CREATE TABLE `job_order_assignees` (
  `job_order_id` BIGINT UNSIGNED NOT NULL,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`job_order_id`, `user_id`),
  CONSTRAINT `fk_joa_job_order`
    FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_joa_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10b. job_order_comment_assignees (same multi-user pattern, for the
--      comment's own "Assign To" — reassigning a job order via a comment
--      now accepts multiple users too).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `job_order_comment_assignees`;
CREATE TABLE `job_order_comment_assignees` (
  `comment_id` BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`comment_id`, `user_id`),
  CONSTRAINT `fk_jocm_comment`
    FOREIGN KEY (`comment_id`) REFERENCES `job_order_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jocm_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10c. customers (shared master list of customer names — usable by any
--      module; only LPR Rental references it directly so far)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(150) NOT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_customers_name` (`name`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10d. lpr_partners (master list of LPR rental partners)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `lpr_partners`;
CREATE TABLE `lpr_partners` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(150) NOT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_lpr_partners_name` (`name`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10e. lpr_rentals (LPR rental contracts — invoice-printing tracker)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `lpr_rentals`;
CREATE TABLE `lpr_rentals` (
  `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id`     INT UNSIGNED NOT NULL,
  `partner_id`      INT UNSIGNED NOT NULL,
  `branch_id`       INT UNSIGNED NOT NULL,
  `start_date`      DATE NOT NULL,
  `coverage_months` TINYINT UNSIGNED NOT NULL COMMENT '12/24/36/48',
  `customer_email`  VARCHAR(150) NULL,
  `renewal_reminder_sent_at` DATETIME NULL COMMENT 'NULL = not yet emailed for the current coverage period; reset on every edit',
  `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_by`      BIGINT UNSIGNED NOT NULL,
  `updated_by`      BIGINT UNSIGNED NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_lpr_rentals_customer` (`customer_id`),
  KEY `idx_lpr_rentals_partner` (`partner_id`),
  KEY `idx_lpr_rentals_branch` (`branch_id`),
  KEY `idx_lpr_rentals_deleted` (`is_deleted`),
  CONSTRAINT `fk_lpr_rentals_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_lpr_rentals_partner` FOREIGN KEY (`partner_id`) REFERENCES `lpr_partners` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_lpr_rentals_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_lpr_rentals_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_lpr_rentals_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10f. lpr_rental_invoice_checks (one row per contract-month; the
--      checkbox grid on the LPR Rental listing reads/writes this)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `lpr_rental_invoice_checks`;
CREATE TABLE `lpr_rental_invoice_checks` (
  `rental_id`  BIGINT UNSIGNED NOT NULL,
  `year_month` CHAR(7) NOT NULL COMMENT 'YYYY-MM',
  `is_checked` TINYINT(1) NOT NULL DEFAULT 0,
  `checked_by` BIGINT UNSIGNED NULL,
  `checked_at` DATETIME NULL,
  PRIMARY KEY (`rental_id`, `year_month`),
  CONSTRAINT `fk_lric_rental` FOREIGN KEY (`rental_id`) REFERENCES `lpr_rentals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lric_checked_by` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10g. smc_contracts (SMC contracts — same shape as lpr_rentals but with
--      no partner concept; the per-month tracker below stores a
--      tri-state status instead of a plain checked/unchecked flag)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `smc_contracts`;
CREATE TABLE `smc_contracts` (
  `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id`     INT UNSIGNED NOT NULL,
  `branch_id`       INT UNSIGNED NOT NULL,
  `start_date`      DATE NOT NULL,
  `coverage_months` TINYINT UNSIGNED NOT NULL COMMENT '12/24/36/48',
  `customer_email`  VARCHAR(150) NULL,
  `renewal_reminder_sent_at` DATETIME NULL COMMENT 'NULL = not yet emailed for the current coverage period; reset on every edit',
  `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_by`      BIGINT UNSIGNED NOT NULL,
  `updated_by`      BIGINT UNSIGNED NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_smc_contracts_customer` (`customer_id`),
  KEY `idx_smc_contracts_branch` (`branch_id`),
  KEY `idx_smc_contracts_deleted` (`is_deleted`),
  CONSTRAINT `fk_smc_contracts_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_smc_contracts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_smc_contracts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_smc_contracts_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10h. smc_contract_statuses (one row per contract-month; the status
--      dropdown on the SMC listing reads/writes this — '' = Blank,
--      'SCH' = Scheduled, 'DONE' = Done)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `smc_contract_statuses`;
CREATE TABLE `smc_contract_statuses` (
  `contract_id` BIGINT UNSIGNED NOT NULL,
  `year_month`  CHAR(7) NOT NULL COMMENT 'YYYY-MM',
  `status`      ENUM('', 'SCH', 'DONE') NOT NULL DEFAULT '',
  `updated_by`  BIGINT UNSIGNED NULL,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`contract_id`, `year_month`),
  CONSTRAINT `fk_scs_contract` FOREIGN KEY (`contract_id`) REFERENCES `smc_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scs_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. activity_logs (general admin/audit log)
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
-- lpr_renewal_recipients (admin-curated: exactly who gets LPR renewal
-- reminders, independent of role/permission — see
-- src/Models/LprRenewalRecipient.php. SMC renewal reminders are NOT
-- affected by this table; they stay permission-based, see
-- User::activeWithPermission('smc.manage') in database/send_renewal_reminders.php)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `lpr_renewal_recipients`;
CREATE TABLE `lpr_renewal_recipients` (
  `user_id`    BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_lpr_renewal_recipients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- notifications (in-app copy of every email this app sends — see
-- src/Models/Notification.php. Created independently of whether the
-- matching email actually succeeds; this is a second, more reliable
-- channel, not a delivery receipt for the email.)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    BIGINT UNSIGNED NOT NULL COMMENT 'recipient',
  `type`       VARCHAR(50)  NOT NULL COMMENT 'e.g. job_order_assigned, renewal_reminder',
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT NULL,
  `link_url`   VARCHAR(255) NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_notifications_user_unread` (`user_id`, `is_read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. settings (system settings key-value store)
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
  (SELECT GROUP_CONCAT(u2.full_name ORDER BY u2.full_name SEPARATOR ', ')
     FROM job_order_assignees ja2 JOIN users u2 ON u2.id = ja2.user_id
     WHERE ja2.job_order_id = jo.id)                  AS assignee_names,
  (SELECT COUNT(*) FROM job_order_comments jc
     WHERE jc.job_order_id = jo.id)                   AS comment_count,
  jo.branch_id,
  b.name                                               AS branch_name,
  jo.stage_id,
  js.stage_code,
  js.stage_name,
  js.color_code                                       AS stage_color,
  jo.is_deleted,
  jo.created_at,
  jo.updated_at
FROM job_orders jo
JOIN users au            ON au.id = jo.assigned_to
JOIN branches b          ON b.id = jo.branch_id
JOIN job_stages js       ON js.id = jo.stage_id
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
  ('job_order.view_completed', 'View job orders that are Completed (stage 7) or Cancelled (stage 8)'),
  ('job_order.create',       'Create job orders (upload quotation/PO)'),
  ('job_order.edit',         'Edit job order details'),
  ('job_order.delete',       'Delete (soft-delete) job orders'),
  ('job_order.change_stage', 'Change job order stage'),
  ('data.view_all_branches', 'View job orders/LPR rentals/SMC contracts from every branch, not just your own'),
  ('data.view_branch_column', 'See the Branch column/selector in the job order/LPR/SMC lists and forms'),
  ('user.manage',            'Create/edit/deactivate system users'),
  ('role.manage',            'Manage roles and permissions'),
  ('settings.manage',        'Manage system settings'),
  ('report.view',            'View dashboard statistics and reports'),
  ('activity_log.view',      'View the system activity/audit log'),
  ('customer.manage',        'Manage the shared customer list'),
  ('lpr_partner.manage',     'Manage the LPR partner list'),
  ('lpr_rental.view',        'View the LPR rental invoice-tracking grid'),
  ('lpr_rental.manage',      'Create/edit/delete LPR rental contracts, tick invoice checks, import/export'),
  ('smc.view',                'View the SMC schedule-tracking grid'),
  ('smc.manage',              'Create/edit/delete SMC contracts, set month status, import/export');

-- Role <-> Permission mapping
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p WHERE r.name = 'Admin';

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Manager'
  AND p.code IN ('job_order.view','data.view_all_branches','job_order.create',
                 'job_order.edit','job_order.change_stage','report.view',
                 'customer.manage','lpr_partner.manage','lpr_rental.view','lpr_rental.manage',
                 'smc.view','smc.manage');

-- Sales intentionally does NOT get data.view_all_branches: they only see
-- records belonging to their own branch (see JobOrderController/LprRentalController/SmcController).
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Sales'
  AND p.code IN ('job_order.view','job_order.create','job_order.edit',
                 'job_order.change_stage');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Viewer'
  AND p.code IN ('job_order.view','data.view_all_branches','report.view','lpr_rental.view','smc.view');

-- Branches (starter placeholder — Admin can rename/add more under Branches)
INSERT INTO `branches` (`name`) VALUES
  ('Main Branch');

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
  ('allowed_upload_types',  'pdf,jpg,jpeg,png'),
  ('renewal_reminder_months', '3');

-- NOTE: no admin user is seeded here on purpose — password_hash() must be
-- generated by PHP (bcrypt), not hand-written into SQL. The Step 2 backend
-- setup will ship a one-time seeder script that creates the first Admin
-- account interactively.
