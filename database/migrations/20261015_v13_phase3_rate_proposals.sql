-- Sisaket Roster V13 Phase 3 (DEV only, AFTER 20261015_duty_foundation.sql)
-- Select backed-up sisaket_roster_dev explicitly before importing.
-- Creates 2 new tables only; no V11 mutation and no payable rate activation.
CREATE TABLE IF NOT EXISTS duty_rate_change_proposals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 hospital_id INT UNSIGNED NOT NULL,
 group_id TINYINT UNSIGNED NOT NULL,
 duty_type_id SMALLINT UNSIGNED NOT NULL,
 proposal_version INT UNSIGNED NOT NULL,
 proposed_rate_unit ENUM('PER_SHIFT','PER_HOUR') NOT NULL,
 proposed_rate_amount DECIMAL(12,2) NOT NULL,
 on_call_pay_eligible TINYINT(1) NOT NULL DEFAULT 0,
 proposed_effective_from DATE NOT NULL,
 proposed_effective_to DATE NULL,
 legal_reference VARCHAR(500) NOT NULL,
 reason VARCHAR(500) NOT NULL,
 status ENUM('DRAFT','SUBMITTED','REJECTED','ENDORSED','APPLIED') NOT NULL DEFAULT 'DRAFT',
 proposed_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_rate_proposal_ver (hospital_id,group_id,duty_type_id,proposal_version),
 KEY idx_rate_proposal_review (hospital_id,status,proposed_effective_from),
 CONSTRAINT fk_v13_prop_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
 CONSTRAINT fk_v13_prop_group FOREIGN KEY (group_id) REFERENCES compensation_groups(id),
 CONSTRAINT fk_v13_prop_duty FOREIGN KEY (duty_type_id) REFERENCES duty_types(id),
 CONSTRAINT fk_v13_prop_user FOREIGN KEY (proposed_by) REFERENCES users(id),
 CONSTRAINT ck_v13_prop_amount CHECK (proposed_rate_amount >= 0),
 CONSTRAINT ck_v13_prop_period CHECK (proposed_effective_to IS NULL OR proposed_effective_to >= proposed_effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS duty_rate_audit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 hospital_id INT UNSIGNED NOT NULL,
 proposal_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 action_code VARCHAR(60) NOT NULL,
 before_snapshot JSON NULL,
 after_snapshot JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_v13_rate_audit (hospital_id,created_at),
 CONSTRAINT fk_v13_audit_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
 CONSTRAINT fk_v13_audit_proposal FOREIGN KEY (proposal_id) REFERENCES duty_rate_change_proposals(id),
 CONSTRAINT fk_v13_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
