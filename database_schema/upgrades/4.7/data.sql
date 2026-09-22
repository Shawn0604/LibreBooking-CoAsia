-- Bumps dbversion so composer preflight / the web installer report this upgrade as applied.
-- Every upgrade folder needs this (see database_schema/upgrades/4.2/data.sql, and the
-- 4.5 fix that had to add this after the fact) -- schema.sql only does the DDL, it never
-- touches dbversion itself. Guarded with WHERE NOT EXISTS since
-- phing-tasks/UpgradeDbTask.php reruns data.sql unconditionally on every invocation.

INSERT INTO `dbversion` (`version_number`, `version_date`)
SELECT 4.7, NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM `dbversion`
    WHERE `version_number` = 4.7
);
