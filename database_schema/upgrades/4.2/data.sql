-- Seed USER-category (attribute_category=2) custom attributes used by the CoAsia employee
-- pre-provisioning import (Jobs/import-coasia-employees.php). Guarded with WHERE NOT EXISTS
-- because phing-tasks/UpgradeDbTask.php reruns data.sql unconditionally on every invocation,
-- and custom_attribute_id is an auto-increment surrogate key the importer looks up by label
-- at runtime rather than a hardcoded id (so it stays correct across environments).
-- admin_only=1 on all five: they are HR-sourced, refreshed by the importer on every rerun,
-- and should not be self-edited by the employee via their own profile form.

INSERT INTO `custom_attributes`
    (`display_label`, `display_type`, `attribute_category`, `validation_regex`, `is_required`, `possible_values`, `sort_order`, `admin_only`, `secondary_category`, `secondary_entity_ids`, `is_private`)
SELECT 'Employee ID', 1, 2, NULL, 0, NULL, 90, 1, NULL, NULL, 0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `custom_attributes` WHERE `display_label` = 'Employee ID' AND `attribute_category` = 2
);

INSERT INTO `custom_attributes`
    (`display_label`, `display_type`, `attribute_category`, `validation_regex`, `is_required`, `possible_values`, `sort_order`, `admin_only`, `secondary_category`, `secondary_entity_ids`, `is_private`)
SELECT 'Chinese Name', 1, 2, NULL, 0, NULL, 91, 1, NULL, NULL, 0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `custom_attributes` WHERE `display_label` = 'Chinese Name' AND `attribute_category` = 2
);

INSERT INTO `custom_attributes`
    (`display_label`, `display_type`, `attribute_category`, `validation_regex`, `is_required`, `possible_values`, `sort_order`, `admin_only`, `secondary_category`, `secondary_entity_ids`, `is_private`)
SELECT 'Korean Name', 1, 2, NULL, 0, NULL, 92, 1, NULL, NULL, 0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `custom_attributes` WHERE `display_label` = 'Korean Name' AND `attribute_category` = 2
);

INSERT INTO `custom_attributes`
    (`display_label`, `display_type`, `attribute_category`, `validation_regex`, `is_required`, `possible_values`, `sort_order`, `admin_only`, `secondary_category`, `secondary_entity_ids`, `is_private`)
SELECT 'Department Paths', 2, 2, NULL, 0, NULL, 93, 1, NULL, NULL, 0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `custom_attributes` WHERE `display_label` = 'Department Paths' AND `attribute_category` = 2
);

INSERT INTO `custom_attributes`
    (`display_label`, `display_type`, `attribute_category`, `validation_regex`, `is_required`, `possible_values`, `sort_order`, `admin_only`, `secondary_category`, `secondary_entity_ids`, `is_private`)
SELECT 'AD Managed Account', 4, 2, NULL, 0, NULL, 94, 1, NULL, NULL, 0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `custom_attributes` WHERE `display_label` = 'AD Managed Account' AND `attribute_category` = 2
);

INSERT INTO `dbversion` (`version_number`, `version_date`)
SELECT 4.2, NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM `dbversion`
    WHERE `version_number` = 4.2
);
