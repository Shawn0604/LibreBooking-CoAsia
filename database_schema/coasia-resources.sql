-- CoAsia real resource / schedule seed
-- Replaces the stock LibreBooking demo data ("Conference Room 1/2" on the
-- America/New_York demo schedule) with the actual resource list from
-- SPEC_LibreBooking_CoAsia.md section 2: 7 physical rooms + 5 Video
-- Conference resources, each on its own Asia/Taipei schedule (12 total).
--
-- Assumes a fresh/dev database where resource_id 1-2 and schedule_id 1 are
-- still the original demo rows ("Conference Room 1" / "Conference Room 2").
-- Not meant to be re-run twice on the same database.
--
-- Run with an explicit utf8mb4 client charset, otherwise the CJK resource
-- names get mangled (mojibake) by the mysql client's default charset, e.g.:
--   mysql --default-character-set=utf8mb4 -u root -p librebooking < coasia-resources.sql
SET NAMES utf8mb4;

-- 1) Fix the existing schedule's layout timezone (was America/New_York demo
--    default) and repurpose it as the dedicated "台北 Taipei" schedule.
UPDATE layouts SET timezone = 'Asia/Taipei' WHERE layout_id = 1;
UPDATE schedules SET name = '台北 Taipei' WHERE schedule_id = 1;

-- 2) Create 11 more layouts (one per remaining resource), all Asia/Taipei.
INSERT INTO layouts (layout_id, timezone, layout_type) VALUES
  (2, 'Asia/Taipei', 0),
  (3, 'Asia/Taipei', 0),
  (4, 'Asia/Taipei', 0),
  (5, 'Asia/Taipei', 0),
  (6, 'Asia/Taipei', 0),
  (7, 'Asia/Taipei', 0),
  (8, 'Asia/Taipei', 0),
  (9, 'Asia/Taipei', 0),
  (10, 'Asia/Taipei', 0),
  (11, 'Asia/Taipei', 0),
  (12, 'Asia/Taipei', 0);

-- Copy the same 08:00-18:00 (30-minute slot) reservable pattern from layout 1
-- onto each new layout.
INSERT INTO time_blocks (label, end_label, availability_code, layout_id, start_time, end_time, day_of_week)
SELECT tb.label, tb.end_label, tb.availability_code, l.layout_id, tb.start_time, tb.end_time, tb.day_of_week
FROM time_blocks tb
CROSS JOIN (
  SELECT 2 AS layout_id UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
  SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL
  SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
) l
WHERE tb.layout_id = 1;

-- 3) Create the 11 new schedules, one per new layout, matching resource names.
INSERT INTO schedules (schedule_id, name, isdefault, weekdaystart, daysvisible, layout_id, allow_calendar_subscription, allow_concurrent_bookings, default_layout, total_concurrent_reservations, max_resources_per_reservation, published) VALUES
  (2, '上海 Shanghai', 0, 0, 7, 2, 0, 0, 0, 0, 0, 0),
  (3, '新加坡 Singapore', 0, 0, 7, 3, 0, 0, 0, 0, 0, 0),
  (4, '新德里 New Delhi', 0, 0, 7, 4, 0, 0, 0, 0, 0, 0),
  (5, '深圳 Shenzhen', 0, 0, 7, 5, 0, 0, 0, 0, 0, 0),
  (6, '首爾 Seoul', 0, 0, 7, 6, 0, 0, 0, 0, 0, 0),
  (7, '高爾夫球室 Golf Room', 0, 0, 7, 7, 0, 0, 0, 0, 0, 0),
  (8, 'Zoom TW', 0, 0, 7, 8, 0, 0, 0, 0, 0, 0),
  (9, 'Zoom CN', 0, 0, 7, 9, 0, 0, 0, 0, 0, 0),
  (10, 'Zoom Oversea', 0, 0, 7, 10, 0, 0, 0, 0, 0, 0),
  (11, 'Google Meet1', 0, 0, 7, 11, 0, 0, 0, 0, 0, 0),
  (12, 'Google Meet2', 0, 0, 7, 12, 0, 0, 0, 0, 0, 0);

-- 4) Rename the two demo resources, and create the remaining 10 real ones,
--    each pointed at its own schedule.
UPDATE resources SET name = '台北 Taipei' WHERE resource_id = 1;
UPDATE resources SET name = '上海 Shanghai', schedule_id = 2 WHERE resource_id = 2;

INSERT INTO resources (resource_id, name, schedule_id, autoassign, requires_approval, allow_multiday_reservations, status_id) VALUES
  (3, '新加坡 Singapore', 3, 1, 0, 1, 1),
  (4, '新德里 New Delhi', 4, 1, 0, 1, 1),
  (5, '深圳 Shenzhen', 5, 1, 0, 1, 1),
  (6, '首爾 Seoul', 6, 1, 0, 1, 1),
  (7, '高爾夫球室 Golf Room', 7, 1, 0, 1, 1),
  (8, 'Zoom TW', 8, 1, 0, 1, 1),
  (9, 'Zoom CN', 9, 1, 0, 1, 1),
  (10, 'Zoom Oversea', 10, 1, 0, 1, 1),
  (11, 'Google Meet1', 11, 1, 0, 1, 1),
  (12, 'Google Meet2', 12, 1, 0, 1, 1);

-- 5) Resource groups matching the admin checklist in the spec.
INSERT INTO resource_groups (resource_group_id, resource_group_name, parent_id) VALUES
  (1, '台北會議室', NULL),
  (2, 'Video Conference', NULL);

INSERT INTO resource_group_assignment (resource_group_id, resource_id) VALUES
  (1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7),
  (2, 8), (2, 9), (2, 10), (2, 11), (2, 12);

-- 6) Video Conference custom attribute values (custom_attribute_id 5/6/7 =
--    video_url / video_meeting_id / video_passcode, seeded earlier in this
--    environment while building Task 1-3). Clear the stale values that were
--    parked on the old "Conference Room 1" resource (now 台北 Taipei, a
--    physical room) and assign real-looking values to the 5 Video
--    Conference resources. Google Meet resources intentionally get only
--    video_url — video_meeting_id/video_passcode stay unset.
DELETE FROM custom_attribute_values WHERE custom_attribute_id IN (5, 6, 7) AND entity_id = 1 AND attribute_category = 4;

INSERT INTO custom_attribute_values (custom_attribute_id, attribute_value, entity_id, attribute_category) VALUES
  (5, 'https://zoom.us/j/94512345678', 8, 4),
  (6, '945 1234 5678', 8, 4),
  (7, 'coasia2026', 8, 4),
  (5, 'https://zoom.com.cn/j/81234567890', 9, 4),
  (6, '812 3456 7890', 9, 4),
  (7, 'coasia2026', 9, 4),
  (5, 'https://zoom.us/j/76543210987', 10, 4),
  (6, '765 4321 0987', 10, 4),
  (7, 'coasia2026', 10, 4),
  (5, 'https://meet.google.com/abc-defg-hij', 11, 4),
  (5, 'https://meet.google.com/xyz-uvwx-klm', 12, 4);

-- 7) Keep auto-increment counters ahead of the manually-assigned IDs above.
ALTER TABLE layouts AUTO_INCREMENT = 13;
ALTER TABLE schedules AUTO_INCREMENT = 13;
ALTER TABLE resources AUTO_INCREMENT = 13;
ALTER TABLE resource_groups AUTO_INCREMENT = 3;
