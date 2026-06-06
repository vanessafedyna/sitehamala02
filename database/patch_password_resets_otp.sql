-- Patch: recuperation mot de passe par OTP telephone
-- Flux cible:
-- 1) demande via numero
-- 2) verification OTP
-- 3) definition nouveau mot de passe

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS password_resets_otp (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  phone VARCHAR(32) NOT NULL,
  otp_code VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pro_user (user_id),
  KEY ix_pro_phone (phone),
  KEY ix_pro_expires (expires_at),
  KEY ix_pro_used (used),
  CONSTRAINT fk_pro_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

