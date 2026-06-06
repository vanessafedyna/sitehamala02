-- Admin patch
-- Brute-force protection + audit logs
-- A exécuter dans phpMyAdmin sur la DB `malishop`.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =========================
-- A) LOGIN ATTEMPTS (anti brute-force)
-- =========================
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  fail_count INT UNSIGNED NOT NULL DEFAULT 0,
  first_failed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_failed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  blocked_until DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ux_login_attempts_email_ip (email, ip),
  KEY ix_login_attempts_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- B) ADMIN AUDIT LOGS
-- =========================
CREATE TABLE IF NOT EXISTS admin_audit_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id INT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_admin_audit_logs_admin (admin_id),
  KEY ix_admin_audit_logs_action (action),
  KEY ix_admin_audit_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

