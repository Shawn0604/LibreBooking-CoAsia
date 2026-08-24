-- Backfill resource_type_id for resources that still have none. The 4.4 migration only
-- covered the original 12 CoAsia seed resources (hardcoded resource_id 1-12, see
-- database_schema/upgrades/4.4/schema.sql). Resources created afterwards via the admin
-- "Copy" feature -- duplicating the 台北 physical rooms into other 地區 groups (深圳/上海)
-- -- never got a type assigned at copy time, so they stayed on
-- manage_resource_drawer_content.tpl's "no ResourceType yet" fallback branch (the full
-- legacy field set), instead of the streamlined 實體會議室/線上會議 branches everything
-- else uses.
--
-- Same two idioms as 4.4: match resource_types by resource_type_name rather than a
-- hardcoded id, and tell a video-conference resource apart from a physical room by
-- whether it has a video_url custom attribute value.
--
-- Idempotent like 4.4: only touches resource_type_id IS NULL rows, so a resource an
-- admin has since typed by hand (or that already had a type) is never touched.

INSERT INTO `resource_types` (`resource_type_name`, `resource_type_description`)
SELECT '實體會議室', ''
WHERE NOT EXISTS (SELECT 1 FROM `resource_types` WHERE `resource_type_name` = '實體會議室');

INSERT INTO `resource_types` (`resource_type_name`, `resource_type_description`)
SELECT '線上會議', ''
WHERE NOT EXISTS (SELECT 1 FROM `resource_types` WHERE `resource_type_name` = '線上會議');

UPDATE `resources` r
JOIN `resource_types` rt ON rt.`resource_type_name` = '線上會議'
SET r.`resource_type_id` = rt.`resource_type_id`
WHERE r.`resource_type_id` IS NULL
	AND EXISTS (
		SELECT 1
		FROM `custom_attribute_values` cav
		JOIN `custom_attributes` ca ON ca.`custom_attribute_id` = cav.`custom_attribute_id`
		WHERE ca.`display_label` = 'video_url'
			AND cav.`attribute_category` = 4
			AND cav.`entity_id` = r.`resource_id`
			AND cav.`attribute_value` IS NOT NULL
			AND cav.`attribute_value` <> ''
	);

UPDATE `resources` r
JOIN `resource_types` rt ON rt.`resource_type_name` = '實體會議室'
SET r.`resource_type_id` = rt.`resource_type_id`
WHERE r.`resource_type_id` IS NULL;
