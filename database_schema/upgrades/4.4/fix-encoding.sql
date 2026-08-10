-- One-off hotfix: the two resource_type_name rows inserted by schema.sql in this same
-- folder got mojibake'd on the production server because the `mysql < schema.sql` command
-- used to apply the migration there didn't pass --default-character-set=utf8mb4 (dev/this
-- repo's own testing did, so this was only caught after prod deploy -- see COASIA-CHANGES.md).
-- Idempotent: harmless to re-run, always sets the same two known-good values by id.
SET NAMES utf8mb4;

UPDATE `resource_types` SET `resource_type_name` = '實體會議室' WHERE `resource_type_id` = 1;
UPDATE `resource_types` SET `resource_type_name` = '線上會議' WHERE `resource_type_id` = 2;
