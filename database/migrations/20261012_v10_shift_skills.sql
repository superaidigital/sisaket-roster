-- V10: run ONCE in phpMyAdmin after backing up the database, BEFORE installing PHP patch.
-- Database selected in phpMyAdmin must be sisaket_roster.
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
