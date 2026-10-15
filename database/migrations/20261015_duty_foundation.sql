-- Sisaket PAO: Duty/Swap/Leave/Compensation Foundation (phase 1)
-- MySQL 8.0; run against a selected, BACKED-UP DEV database.
-- No DROP/DELETE/ALTER of V11 tables. No USE statement; choose DB explicitly.
-- Financial rates are DRAFT, not payable, pending authorized confirmation.
CREATE TABLE IF NOT EXISTS duty_types (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code CHAR(1) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  starts_at TIME NOT NULL,
  ends_at TIME NOT NULL,
  end_day_offset TINYINT UNSIGNED NOT NULL DEFAULT 0,
  billable_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CHECK (end_day_offset IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO duty_types (code,name,starts_at,ends_at,end_day_offset,billable_minutes)
VALUES
('ย','เวรวันหยุดราชการ','08:30:00','16:30:00',0,0),
('บ','เวรนอกเวลาราชการ','16:31:00','20:30:00',0,240),
('ร','เวรเรียก On Call','20:31:00','08:29:00',1,0)
ON DUPLICATE KEY UPDATE code=VALUES(code);

CREATE TABLE IF NOT EXISTS compensation_groups (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  name VARCHAR(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO compensation_groups(id,name) VALUES
(1,'วิชาชีพ/ปริญญาตรี'),(2,'เจ้าพนักงาน'),(3,'เจ้าหน้าที่อื่น')
ON DUPLICATE KEY UPDATE id=VALUES(id);

CREATE TABLE IF NOT EXISTS compensation_rates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id TINYINT UNSIGNED NOT NULL,
  duty_type_id SMALLINT UNSIGNED NOT NULL,
  rule_version VARCHAR(40) NOT NULL,
  rate_unit ENUM('PER_SHIFT','PER_HOUR') NOT NULL,
  rate_amount DECIMAL(12,2) NOT NULL,
  effective_from DATE NULL,
  effective_to DATE NULL,
  approval_status ENUM('DRAFT','APPROVED','REVOKED') NOT NULL DEFAULT 'DRAFT',
  source_reference VARCHAR(500) NOT NULL DEFAULT '',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  UNIQUE KEY uq_rate_rule (group_id,duty_type_id,rule_version),
  KEY idx_rate_effective (group_id,duty_type_id,approval_status,effective_from,effective_to),
  CONSTRAINT fk_rate_group FOREIGN KEY (group_id) REFERENCES compensation_groups(id),
  CONSTRAINT fk_rate_duty FOREIGN KEY (duty_type_id) REFERENCES duty_types(id),
  CONSTRAINT fk_rate_approver FOREIGN KEY (approved_by) REFERENCES users(id),
  CONSTRAINT ck_rate_amount CHECK (rate_amount >= 0),
  CONSTRAINT ck_rate_dates CHECK (effective_to IS NULL OR (effective_from IS NOT NULL AND effective_to >= effective_from)),
  CONSTRAINT ck_rate_approved CHECK (
    approval_status <> 'APPROVED' OR
    (effective_from IS NOT NULL AND approved_by IS NOT NULL AND approved_at IS NOT NULL AND source_reference <> '')
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Proposed rates from requirements, deliberately NOT legally approved.
INSERT INTO compensation_rates
(group_id,duty_type_id,rule_version,rate_unit,rate_amount,source_reference)
SELECT cfg.group_id, t.id, 'BP-20261015-DRAFT', cfg.rate_unit,
       cfg.amount, 'Business requirements - requires signed legal/finance approval'
FROM (
 SELECT 1 group_id,'ย' code,'PER_SHIFT' rate_unit,650.00 amount UNION ALL
 SELECT 1,'บ','PER_HOUR',80.00 UNION ALL
 SELECT 1,'ร','PER_SHIFT',150.00 UNION ALL
 SELECT 2,'ย','PER_SHIFT',520.00 UNION ALL
 SELECT 2,'บ','PER_HOUR',60.00 UNION ALL
 SELECT 2,'ร','PER_SHIFT',0.00 UNION ALL
 SELECT 3,'ย','PER_SHIFT',330.00 UNION ALL
 SELECT 3,'บ','PER_SHIFT',160.00 UNION ALL
 SELECT 3,'ร','PER_SHIFT',0.00
) cfg JOIN duty_types t ON t.code=cfg.code
ON DUPLICATE KEY UPDATE rule_version=VALUES(rule_version);

CREATE TABLE IF NOT EXISTS staff_compensation_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  group_id TINYINT UNSIGNED NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  verified_by BIGINT UNSIGNED NOT NULL,
  verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  evidence_reference VARCHAR(500) NOT NULL,
  KEY idx_staff_group_time (user_id,effective_from,effective_to),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (group_id) REFERENCES compensation_groups(id),
  FOREIGN KEY (verified_by) REFERENCES users(id),
  CHECK (effective_to IS NULL OR effective_to >= effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS duty_rosters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NOT NULL,
  roster_year SMALLINT UNSIGNED NOT NULL,
  roster_month TINYINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  revision INT UNSIGNED NOT NULL DEFAULT 0,
  base_roster_id BIGINT UNSIGNED NULL,
  status ENUM('DRAFT','SUBMITTED','APPROVED','PUBLISHED','LOCKED')
      NOT NULL DEFAULT 'DRAFT',
  created_by BIGINT UNSIGNED NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_duty_month_version (hospital_id,roster_year,roster_month,version),
  FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id),
  FOREIGN KEY (base_roster_id) REFERENCES duty_rosters(id) ON DELETE SET NULL,
  CHECK (roster_month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS duty_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  duty_roster_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  duty_type_id SMALLINT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  status ENUM('ACTIVE','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  created_by BIGINT UNSIGNED NOT NULL,
  updated_by BIGINT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_duty_person_day_type (duty_roster_id,user_id,work_date,duty_type_id),
  KEY idx_duty_person_interval (user_id,start_at,end_at),
  KEY idx_duty_roster_date (duty_roster_id,work_date),
  FOREIGN KEY (duty_roster_id) REFERENCES duty_rosters(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (duty_type_id) REFERENCES duty_types(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (updated_by) REFERENCES users(id),
  CHECK (end_at > start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS approval_delegations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NULL,
  delegate_user_id BIGINT UNSIGNED NOT NULL,
  approval_scope VARCHAR(60) NOT NULL,
  order_reference VARCHAR(250) NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  evidence_uri VARCHAR(500) NOT NULL,
  verified_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_delegation_period (delegate_user_id,approval_scope,effective_from,effective_to),
  FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  FOREIGN KEY (delegate_user_id) REFERENCES users(id),
  FOREIGN KEY (verified_by) REFERENCES users(id),
  CHECK (effective_to IS NULL OR effective_to >= effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shift_swaps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NOT NULL,
  reciprocal_assignment_id BIGINT UNSIGNED NULL,
  requester_id BIGINT UNSIGNED NOT NULL,
  replacement_id BIGINT UNSIGNED NOT NULL,
  reason TEXT NOT NULL,
  expected_revision INT UNSIGNED NOT NULL,
  status ENUM('DRAFT','PENDING_CONSENT','PENDING_DIRECTOR',
    'PENDING_AUTHORITY','REVISION_REQUIRED','APPROVED','REJECTED','CANCELLED')
    NOT NULL DEFAULT 'DRAFT',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  FOREIGN KEY (assignment_id) REFERENCES duty_assignments(id),
  FOREIGN KEY (reciprocal_assignment_id) REFERENCES duty_assignments(id),
  FOREIGN KEY (requester_id) REFERENCES users(id),
  FOREIGN KEY (replacement_id) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id),
  KEY idx_swaps_pending (hospital_id,status,created_at),
  CHECK (requester_id <> replacement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS swap_approvals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shift_swap_id BIGINT UNSIGNED NOT NULL,
  actor_id BIGINT UNSIGNED NOT NULL,
  step_code ENUM('CONSENT','DIRECTOR_REVIEW','AUTHORITY_APPROVAL') NOT NULL,
  decision ENUM('APPROVED','REJECTED') NOT NULL,
  delegation_id BIGINT UNSIGNED NULL,
  document_hash CHAR(64) NULL,
  signed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_swap_step (shift_swap_id,step_code),
  FOREIGN KEY (shift_swap_id) REFERENCES shift_swaps(id),
  FOREIGN KEY (actor_id) REFERENCES users(id),
  FOREIGN KEY (delegation_id) REFERENCES approval_delegations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leave_types (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO leave_types(code,name) VALUES
('SICK','ลาป่วย'),('PERSONAL','ลากิจส่วนตัว'),
('VACATION','ลาพักผ่อน'),('MATERNITY','ลาคลอด')
ON DUPLICATE KEY UPDATE code=VALUES(code);

CREATE TABLE IF NOT EXISTS leave_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NOT NULL,
  requester_id BIGINT UNSIGNED NOT NULL,
  leave_type_id SMALLINT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  -- Store any sensitive reasons/medical details ENCRYPTED in app layer; no plaintext here.
  encrypted_reason VARBINARY(4096) NULL,
  status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','CANCELLED')
    NOT NULL DEFAULT 'DRAFT',
  approver_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  FOREIGN KEY (requester_id) REFERENCES users(id),
  FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
  FOREIGN KEY (approver_id) REFERENCES users(id),
  KEY idx_leave_dates (requester_id,starts_at,ends_at),
  CHECK (ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NOT NULL,
  run_year SMALLINT UNSIGNED NOT NULL,
  run_month TINYINT UNSIGNED NOT NULL,
  run_number INT UNSIGNED NOT NULL,
  status ENUM('DRAFT','VERIFIED','APPROVED','PAID') NOT NULL DEFAULT 'DRAFT',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payroll_run (hospital_id,run_year,run_month,run_number),
  FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  CHECK (run_month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payroll_run_id BIGINT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NOT NULL,
  payee_user_id BIGINT UNSIGNED NOT NULL,
  rate_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(8,2) NOT NULL,
  unit_rate DECIMAL(12,2) NOT NULL,
  total_amount DECIMAL(12,2) NOT NULL,
  entry_type ENUM('ORIGINAL','ADJUSTMENT') NOT NULL DEFAULT 'ORIGINAL',
  source_evidence_reference VARCHAR(500) NOT NULL,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payroll_item (payroll_run_id,assignment_id,entry_type),
  FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id),
  FOREIGN KEY (assignment_id) REFERENCES duty_assignments(id),
  FOREIGN KEY (payee_user_id) REFERENCES users(id),
  FOREIGN KEY (rate_id) REFERENCES compensation_rates(id),
  CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS duty_audit_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT UNSIGNED NOT NULL,
  actor_id BIGINT UNSIGNED NOT NULL,
  action_code VARCHAR(80) NOT NULL,
  record_type VARCHAR(60) NOT NULL,
  record_id BIGINT UNSIGNED NOT NULL,
  -- Redact sensitive reasons and tokens before writing JSON.
  change_summary JSON NOT NULL,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_duty_audit (hospital_id,recorded_at),
  FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
