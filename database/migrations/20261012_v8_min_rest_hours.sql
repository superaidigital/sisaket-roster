-- V8: run ONCE before opening the V8 UI. Safe to re-run on MySQL 8/MariaDB.
-- IMPORTANT: do not import schema.sql or seed.sql again on an existing database.
USE sisaket_roster;
SET @column_present := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roster_settings' AND COLUMN_NAME='min_rest_hours');
SET @ddl := IF(@column_present=0,
  'ALTER TABLE roster_settings ADD COLUMN min_rest_hours TINYINT UNSIGNED NOT NULL DEFAULT 11 AFTER max_night_shifts',
  'SELECT ''min_rest_hours column already exists'' AS message');
PREPARE roster_v8_stmt FROM @ddl;
EXECUTE roster_v8_stmt;
DEALLOCATE PREPARE roster_v8_stmt;
SELECT hospital_id,min_rest_hours FROM roster_settings ORDER BY hospital_id;
