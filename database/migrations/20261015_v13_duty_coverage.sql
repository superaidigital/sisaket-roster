-- Sisaket V13 Duty skill/coverage requirements.
-- ADDITIVE, never modifies V11 tables; run on a backed-up DEV DB after Phase 1 migration.
-- NO default professional requirements are invented. Administrators must record
-- documented minima, evidence and a verified-by identity before Duty approval.
CREATE TABLE IF NOT EXISTS duty_coverage_requirements (
  hospital_id INT UNSIGNED NOT NULL,
  duty_type_id SMALLINT UNSIGNED NOT NULL,
  skill_code VARCHAR(40) NOT NULL,
  min_count SMALLINT UNSIGNED NOT NULL,
  evidence_reference VARCHAR(500) NOT NULL,
  verified_by BIGINT UNSIGNED NOT NULL,
  verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (hospital_id,duty_type_id,skill_code),
  KEY idx_duty_coverage_type (duty_type_id,skill_code),
  CONSTRAINT fk_duty_cov_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
  CONSTRAINT fk_duty_cov_type FOREIGN KEY (duty_type_id) REFERENCES duty_types(id),
  CONSTRAINT fk_duty_cov_verifier FOREIGN KEY (verified_by) REFERENCES users(id),
  CONSTRAINT ck_duty_cov_min CHECK (min_count BETWEEN 0 AND 100),
  CONSTRAINT ck_duty_cov_evidence CHECK (evidence_reference<>'')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Set skill_code='__TOTAL__' for minimum total staff; set RN/CLINICAL/
-- EMERGENCY/PUBLIC_HEALTH for professional minima. Explicit 0 is allowed only
-- when a qualified official has reviewed and documented that requirement.
-- WARNING: this table is scoped to each hospital. Do not copy policy records
-- across facilities without independently verifying authority/evidence.
