-- Give the 12 CoAsia resources a real ResourceType (實體會議室 / 線上會議) so the admin
-- resource-edit drawer (manage_resource_drawer_content.tpl) can branch on it: physical
-- rooms only need a Name field, video-conference resources get a dedicated "line meeting
-- info" section instead of the full generic field set. Previously resource_type_id was
-- left NULL for every resource (see database_schema/coasia-resources.sql) and "is this a
-- video resource" was only inferable indirectly from whether a video_url custom attribute
-- value existed.
--
-- Matched by resource_type_name (GET_RESOURCE_TYPE_BY_NAME is an existing lookup idiom in
-- this codebase -- lib/Database/Commands/Queries.php) rather than a new dedicated column.
--
-- phing-tasks/UpgradeDbTask.php reruns every upgrade folder unconditionally on every
-- invocation, so this stays idempotent: the INSERTs are guarded by NOT EXISTS, and the
-- UPDATEs only touch resources still on resource_type_id IS NULL, so they won't clobber
-- an admin who has since reassigned a resource's type by hand.

INSERT INTO `resource_types` (`resource_type_name`, `resource_type_description`)
SELECT '實體會議室', ''
WHERE NOT EXISTS (SELECT 1 FROM `resource_types` WHERE `resource_type_name` = '實體會議室');

INSERT INTO `resource_types` (`resource_type_name`, `resource_type_description`)
SELECT '線上會議', ''
WHERE NOT EXISTS (SELECT 1 FROM `resource_types` WHERE `resource_type_name` = '線上會議');

UPDATE `resources` r
JOIN `resource_types` rt ON rt.`resource_type_name` = '實體會議室'
SET r.`resource_type_id` = rt.`resource_type_id`
WHERE r.`resource_id` IN (1, 2, 3, 4, 5, 6, 7) AND r.`resource_type_id` IS NULL;

UPDATE `resources` r
JOIN `resource_types` rt ON rt.`resource_type_name` = '線上會議'
SET r.`resource_type_id` = rt.`resource_type_id`
WHERE r.`resource_id` IN (8, 9, 10, 11, 12) AND r.`resource_type_id` IS NULL;
