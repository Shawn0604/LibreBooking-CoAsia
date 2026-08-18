-- CoAsia region backfill for the stock (previously unused in this deployment)
-- `resources`.`location` column — powers the new 地區 filter/grouping across the admin
-- center (Manage Resources, Admin Reservations, Manage Schedules, Overview, Usage
-- Analytics, CSV import/export) added alongside the Overview FullCalendar resource picker's
-- own region groups (resource_groups / resource_group_assignment, see
-- coasia-resources-regions.sql). Deliberately a SHORT region label (台北/深圳/上海/跨地區),
-- not the fuller "台北實體會議室" phrasing used for filter-dropdown option text elsewhere —
-- see the four-value whitelist in Domain/Values/ResourceLocation.php.
--
-- Assumes coasia-resources.sql and coasia-resources-regions.sql have already been run
-- (resource_id 1-21 already exist with the real CoAsia resource list). Not meant to be
-- re-run twice on the same database.
SET NAMES utf8mb4;

UPDATE resources SET location = '台北' WHERE resource_id IN (1, 2, 3, 4, 5, 6, 7);
UPDATE resources SET location = '跨地區' WHERE resource_id IN (8, 9, 10, 11, 12);
UPDATE resources SET location = '深圳' WHERE resource_id IN (13, 14, 15, 16, 17);
UPDATE resources SET location = '上海' WHERE resource_id IN (18, 19, 20, 21);
