-- Lets an admin pair a resource with another one (e.g. a physical room with its usual
-- video-conference resource) so booking the first automatically books the second too --
-- see COASIA-CHANGES.md for the reservation-form auto-add behaviour this backs.
--
-- Self-referencing, nullable, same idiom as resource_groups.parent_id: ON DELETE SET NULL
-- (not CASCADE) since deleting the linked resource should just drop the pairing, not the
-- room it was paired with.

ALTER TABLE `resources` ADD COLUMN `linked_resource_id` smallint(5) unsigned DEFAULT NULL;
ALTER TABLE `resources` ADD INDEX `resources_linked_resource_id` (`linked_resource_id`);
ALTER TABLE `resources` ADD CONSTRAINT `fk_resources_linked_resource_id`
	FOREIGN KEY (`linked_resource_id`) REFERENCES `resources`(`resource_id`) ON DELETE SET NULL;
