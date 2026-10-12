-- Run on an existing installation only; a fresh schema already has this column.
-- If this migration was previously run, do not run it again.
USE sisaket_roster;
ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash;
