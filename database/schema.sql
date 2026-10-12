-- Sisaket PAO Roster Module (MySQL 8 / MariaDB 10.5+)
CREATE DATABASE IF NOT EXISTS sisaket_roster CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sisaket_roster;

CREATE TABLE IF NOT EXISTS hospitals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hospital_code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  district VARCHAR(120) NOT NULL DEFAULT '',
  subdistrict VARCHAR(120) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(200) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  role ENUM('SUPERADMIN','ADMIN','SCHEDULER','DIRECTOR','STAFF','HR') NOT NULL,
  position VARCHAR(150) NOT NULL DEFAULT '',
  personnel_type VARCHAR(100) NOT NULL DEFAULT '',
  photo_url VARCHAR(500) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_users_staff (hospital_id,role,is_active),
  CONSTRAINT fk_users_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shift_types (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  short_name VARCHAR(20) NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  color_class VARCHAR(50) NOT NULL DEFAULT 'shift-neutral',
  is_working TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roster_settings (
  hospital_id INT UNSIGNED NOT NULL PRIMARY KEY,
  min_morning SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  min_afternoon SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  min_night SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  max_consecutive_work_days SMALLINT UNSIGNED NOT NULL DEFAULT 6,
  max_night_shifts SMALLINT UNSIGNED NOT NULL DEFAULT 8,
  min_rest_hours TINYINT UNSIGNED NOT NULL DEFAULT 11,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS holidays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NULL,
  holiday_date DATE NOT NULL,
  name VARCHAR(200) NOT NULL,
  KEY idx_holidays_date (holiday_date,hospital_id),
  CONSTRAINT fk_holidays_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rosters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `month` TINYINT UNSIGNED NOT NULL,
  status ENUM('DRAFT','PENDING_APPROVAL','APPROVED','PUBLISHED','LOCKED') NOT NULL DEFAULT 'DRAFT',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  lock_version INT UNSIGNED NOT NULL DEFAULT 0,
  base_roster_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  submitted_by BIGINT UNSIGNED NULL,
  submitted_at DATETIME NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  published_by BIGINT UNSIGNED NULL,
  published_at DATETIME NULL,
  locked_by BIGINT UNSIGNED NULL,
  locked_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roster_version (hospital_id,`year`,`month`,version),
  KEY idx_rosters_status (hospital_id,`year`,`month`,status),
  CONSTRAINT ck_roster_month CHECK (`month` BETWEEN 1 AND 12),
  CONSTRAINT fk_roster_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  CONSTRAINT fk_roster_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_roster_base FOREIGN KEY (base_roster_id) REFERENCES rosters(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roster_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  roster_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  shift_type_id SMALLINT UNSIGNED NOT NULL,
  note VARCHAR(500) NOT NULL DEFAULT '',
  created_by BIGINT UNSIGNED NOT NULL,
  updated_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roster_person_day (roster_id,user_id,work_date),
  KEY idx_entry_date (roster_id,work_date,shift_type_id),
  CONSTRAINT fk_entry_roster FOREIGN KEY (roster_id) REFERENCES rosters(id) ON DELETE CASCADE,
  CONSTRAINT fk_entry_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_entry_shift FOREIGN KEY (shift_type_id) REFERENCES shift_types(id),
  CONSTRAINT fk_entry_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_entry_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roster_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  roster_id BIGINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  action VARCHAR(50) NOT NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL DEFAULT '',
  before_snapshot LONGTEXT NULL,
  after_snapshot LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_versions_roster (roster_id,created_at),
  CONSTRAINT fk_versions_roster FOREIGN KEY (roster_id) REFERENCES rosters(id) ON DELETE CASCADE,
  CONSTRAINT fk_versions_user FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roster_validation_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  roster_id BIGINT UNSIGNED NOT NULL,
  severity ENUM('CRITICAL','WARNING','INFO') NOT NULL,
  rule_code VARCHAR(70) NOT NULL,
  work_date DATE NULL,
  user_id BIGINT UNSIGNED NULL,
  message VARCHAR(500) NOT NULL,
  checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_validations_roster (roster_id,severity),
  CONSTRAINT fk_validation_roster FOREIGN KEY (roster_id) REFERENCES rosters(id) ON DELETE CASCADE,
  CONSTRAINT fk_validation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roster_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(80) NOT NULL,
  roster_id BIGINT UNSIGNED NULL,
  hospital_id INT UNSIGNED NOT NULL,
  work_date DATE NULL,
  old_value LONGTEXT NULL,
  new_value LONGTEXT NULL,
  ip_hash CHAR(64) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_scope (hospital_id,created_at),
  KEY idx_audit_roster (roster_id,created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_audit_roster FOREIGN KEY (roster_id) REFERENCES rosters(id) ON DELETE SET NULL,
  CONSTRAINT fk_audit_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
) ENGINE=InnoDB;


-- V10 verified skill assignments and shift skill requirements
CREATE TABLE IF NOT EXISTS roster_staff_skills (
  hospital_id INT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  skill_code VARCHAR(40) NOT NULL,
  verified_by BIGINT UNSIGNED NOT NULL,
  verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (hospital_id,user_id,skill_code),
  KEY idx_staff_skills_user (user_id),
  CONSTRAINT fk_rss_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  CONSTRAINT fk_rss_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_rss_verifier FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS roster_shift_skill_requirements (
  hospital_id INT UNSIGNED NOT NULL,
  shift_type_id SMALLINT UNSIGNED NOT NULL,
  skill_code VARCHAR(40) NOT NULL,
  min_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (hospital_id,shift_type_id,skill_code),
  CONSTRAINT fk_rsr_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  CONSTRAINT fk_rsr_shift FOREIGN KEY (shift_type_id) REFERENCES shift_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Existing data remains untouched, and without configured minimums behavior stays unchanged.
