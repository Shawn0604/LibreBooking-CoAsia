--
-- P0 concurrency-safety schema additions (see COASIA-CHANGES.md):
--   1. reservation_series.version — integer optimistic-locking counter. UPDATE ... WHERE
--      series_id = ? AND version = ? with 0 affected rows means someone else changed the
--      reservation since it was loaded (used instead of the pre-existing `last_modified`
--      datetime column, which only has second-level precision and could theoretically miss
--      two edits landing in the same second).
--   2. reservation_requests — idempotency ledger for reservation creation. A client-
--      generated UUID (kept for the lifetime of one "create" attempt, including any HTTP
--      retries/double-clicks) claims a row here atomically before the reservation itself is
--      created; a resend of the same request_id finds its own row instead of creating a
--      second reservation.
--
-- Existing data: the ADD COLUMN below backfills every existing reservation_series row with
-- version = 0 automatically (DEFAULT 0, no separate UPDATE needed) — no reservation this
-- deployment already has stops working, they just start at version 0.
--
-- Rollback (not run automatically by this project's upgrade mechanism, kept here for a
-- failed-deploy scenario):
--   ALTER TABLE `reservation_series` DROP COLUMN `version`;
--   DROP TABLE IF EXISTS `reservation_requests`;
--

ALTER TABLE `reservation_series`
  ADD COLUMN `version` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_modified`;

CREATE TABLE IF NOT EXISTS `reservation_requests` (
  `request_id` VARCHAR(36) NOT NULL,
  `user_id` MEDIUMINT UNSIGNED NOT NULL,
  `status` VARCHAR(16) NOT NULL,
  `reference_number` VARCHAR(50) NULL,
  `date_created` DATETIME NOT NULL,
  `last_modified` DATETIME NOT NULL,
  PRIMARY KEY (`request_id`, `user_id`),
  KEY `status` (`status`),
  CONSTRAINT `reservation_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
