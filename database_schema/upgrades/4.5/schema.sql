-- MySQL 8.0 does not support ADD COLUMN IF NOT EXISTS, and phing-tasks/UpgradeDbTask.php reruns every
-- upgrade folder unconditionally on every invocation, so this stays idempotent via information_schema guards
-- (same pattern as database_schema/upgrades/4.2/schema.sql).
--
-- Four new `users` columns supporting the AD login flow rework (see COASIA-CHANGES.md for the full
-- requirement/root-cause/fix history):
--   - is_local_auth_only: per-account "emergency admin" exemption from the ActiveDirectory plugin -- the
--     ONLY accounts still allowed to authenticate with a local password once the AD outage bypass stops
--     being a blanket, unscoped config toggle (plugins/Authentication/ActiveDirectory/ActiveDirectory.php).
--   - is_ad_managed: set by ActiveDirectory::Login() on every successful AD sync, not just by the one-off
--     HR import job's own separate "AD Managed Account" custom attribute (which never covered accounts
--     first created by a live AD login) -- lets the new reconciliation job (Jobs/ad-reconcile-account-
--     status.php) scope itself to real AD accounts only.
--   - department: AD's own `department` LDAP attribute, synced alongside name/email/organization/position.
--   - credentials_invalidated_at: set inside User::Deactivate() itself, so every deactivation path (manual
--     admin action or the new reconciliation job) automatically revokes that user's already-open browser
--     session on their next request (Pages/Page.php::IsAuthenticated()).

SET @is_local_auth_only_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'is_local_auth_only'
);
SET @is_local_auth_only_sql := IF(
    @is_local_auth_only_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `is_local_auth_only` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `ad_object_guid`',
    'SELECT 1'
);
PREPARE stmt FROM @is_local_auth_only_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @is_ad_managed_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'is_ad_managed'
);
SET @is_ad_managed_sql := IF(
    @is_ad_managed_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `is_ad_managed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `is_local_auth_only`',
    'SELECT 1'
);
PREPARE stmt FROM @is_ad_managed_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @department_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'department'
);
SET @department_sql := IF(
    @department_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `department` VARCHAR(85) NULL AFTER `is_ad_managed`',
    'SELECT 1'
);
PREPARE stmt FROM @department_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @credentials_invalidated_at_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'credentials_invalidated_at'
);
SET @credentials_invalidated_at_sql := IF(
    @credentials_invalidated_at_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `credentials_invalidated_at` DATETIME NULL AFTER `department`',
    'SELECT 1'
);
PREPARE stmt FROM @credentials_invalidated_at_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
