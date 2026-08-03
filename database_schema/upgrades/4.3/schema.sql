-- MySQL 8.0 does not support CREATE TABLE IF NOT EXISTS ... AS SELECT-style guards for
-- everything we might want here, but plain CREATE TABLE IF NOT EXISTS is natively supported
-- and phing-tasks/UpgradeDbTask.php reruns every upgrade folder unconditionally on every
-- invocation, so this still needs to stay idempotent -- just via the simpler native syntax
-- this time (no information_schema dance needed for a brand-new table).

-- Video conference host login (Domain/Access/VideoConferenceHostRepository.php) -- separate
-- from the existing custom_attribute_values-backed video_url/video_meeting_id/video_passcode
-- (those are shown to every participant, so plain text there is fine) because host_password
-- is a real shared-account credential that must never be readable by anyone except the
-- reservation owner. Encrypted at rest (see VideoConferenceCredentialEncryption.php);
-- host_password_encrypted is base64(iv + ciphertext), never plaintext, in this table or
-- anywhere else the application writes it (logs, email headers, etc. all handle the
-- decrypted value in memory only, for exactly as long as rendering the owner's own
-- notification takes).
CREATE TABLE IF NOT EXISTS `resource_video_conference_host` (
    `resource_id` SMALLINT UNSIGNED NOT NULL,
    `host_account` VARCHAR(255) NOT NULL,
    `host_password_encrypted` VARCHAR(500) NOT NULL,
    `date_created` DATETIME NOT NULL,
    `last_modified` DATETIME NOT NULL,
    PRIMARY KEY (`resource_id`),
    CONSTRAINT `fk_resource_video_conference_host_resource`
        FOREIGN KEY (`resource_id`) REFERENCES `resources` (`resource_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
