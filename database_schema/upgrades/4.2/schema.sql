-- MySQL 8.0 does not support ADD COLUMN IF NOT EXISTS, and phing-tasks/UpgradeDbTask.php reruns every
-- upgrade folder unconditionally on every invocation, so this stays idempotent via information_schema guards
-- (same pattern as database_schema/upgrades/4.1/schema.sql).

-- Stable identifier for future AD objectGUID-based matching (CoAsia employee pre-provisioning import).
-- Not populated by the current import (the HR export has no objectGUID column) or by the live AD login
-- sync path (plugins/Authentication/ActiveDirectory) -- reserved for a future enhancement. Matching for
-- now relies on normalized username/email, which is what Registration::Synchronize() already uses.
SET @ad_object_guid_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'ad_object_guid'
);
SET @ad_object_guid_sql := IF(
    @ad_object_guid_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `ad_object_guid` VARCHAR(64) NULL AFTER `public_id`',
    'SELECT 1'
);
PREPARE stmt FROM @ad_object_guid_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ad_object_guid_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND INDEX_NAME = 'ix_users_ad_object_guid'
);
SET @ad_object_guid_index_sql := IF(
    @ad_object_guid_index_exists = 0,
    'ALTER TABLE `users` ADD UNIQUE INDEX `ix_users_ad_object_guid` (`ad_object_guid`)',
    'SELECT 1'
);
PREPARE stmt FROM @ad_object_guid_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
