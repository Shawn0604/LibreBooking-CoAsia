-- CoAsia regional resource groups
-- Follow-up to coasia-resources.sql: turns the single flat "台北會議室" group into
-- three region-scoped physical groups (台北/深圳/上海) plus the existing video group,
-- per the left-sidebar spec (7 Taipei + 5 Shenzhen + 4 Shanghai physical rooms, 5
-- shared video rooms). Assumes coasia-resources.sql has already been run (depends on
-- resources 1-12 / schedules 1-12 / resource_groups 1-2 existing with those exact
-- names/assignments).
--
-- New layout/schedule/resource/group ids are computed from the current MAX(id) rather
-- than hardcoded — confirmed live against the actual CoAsia dev database that this
-- can't assume a pristine "IDs 1-12 only" state: layouts had already drifted to a
-- gappy 13/14/15/18 (schedule edits via the admin UI orphan the old layout row and
-- create a new one) even though schedules/resources were still exactly 1-12. Hardcoding
-- 13-21 for every table would have collided with those existing layout rows.
--
-- Not meant to be re-run twice on the same database (re-running would recompute the
-- MAX(id) bases from the state IT just inserted and add a second, redundant set of
-- rows rather than erroring, since there's no uniqueness check against room name).
--
-- Run with an explicit utf8mb4 client charset, otherwise the CJK resource
-- names get mangled (mojibake):
--   mysql --default-character-set=utf8mb4 -u root -p librebooking < coasia-resources-regions.sql
SET NAMES utf8mb4;

-- 0) `resources.sort_order` is read by GetAllResourceGroupAssignmentsCommand
--    (ORDER BY COALESCE(r.sort_order,0), r.name) -- confirmed already present on the
--    live CoAsia dev database (MySQL 8.0.46 doesn't actually support `ADD COLUMN IF NOT
--    EXISTS` despite some docs suggesting otherwise, so this can't defend against a
--    database where it's missing without erroring on one where it already exists; if a
--    target database is missing it, run this first:
--      ALTER TABLE resources ADD COLUMN sort_order SMALLINT UNSIGNED DEFAULT NULL;

-- 1) Rename the two existing groups to match the new region-scoped naming.
UPDATE resource_groups SET resource_group_name = '台北實體會議室' WHERE resource_group_name = '台北會議室';
UPDATE resource_groups SET resource_group_name = '線上會議室' WHERE resource_group_name = 'Video Conference';

-- 2) Cosmetic rename of the 3 video resources whose names drifted from the spec text
--    ("Zoom Oversea" -> "Zoom Overseas", "Google Meet1/2" -> "Google Meet 1/2").
UPDATE resources SET name = 'Zoom Overseas' WHERE name = 'Zoom Oversea';
UPDATE resources SET name = 'Google Meet 1' WHERE name = 'Google Meet1';
UPDATE resources SET name = 'Google Meet 2' WHERE name = 'Google Meet2';

-- 3) Explicit sort_order on every existing resource so each group's left-sidebar list
--    renders in the exact order from the spec (GetAllResourceGroupAssignmentsCommand
--    orders by sort_order then name, and plain alphabetical order doesn't reproduce the
--    Shenzhen/Shanghai lists below). Matched by name (not a hardcoded resource_id)
--    since coasia-resources.sql's own resource_id assignment for 1/2 came from an
--    UPDATE of pre-existing demo rows, not a fresh INSERT.
UPDATE resources SET sort_order = 1 WHERE name = '上海 Shanghai' AND resource_id <= 12;
UPDATE resources SET sort_order = 2 WHERE name = '台北 Taipei' AND resource_id <= 12;
UPDATE resources SET sort_order = 3 WHERE name = '新加坡 Singapore' AND resource_id <= 12;
UPDATE resources SET sort_order = 4 WHERE name = '新德里 New Delhi' AND resource_id <= 12;
UPDATE resources SET sort_order = 5 WHERE name = '深圳 Shenzhen' AND resource_id <= 12;
UPDATE resources SET sort_order = 6 WHERE name = '首爾 Seoul' AND resource_id <= 12;
UPDATE resources SET sort_order = 7 WHERE name = '高爾夫球室 Golf Room' AND resource_id <= 12;
UPDATE resources SET sort_order = 1 WHERE name = 'Google Meet 1' AND resource_id <= 12;
UPDATE resources SET sort_order = 2 WHERE name = 'Google Meet 2' AND resource_id <= 12;
UPDATE resources SET sort_order = 3 WHERE name = 'Zoom CN' AND resource_id <= 12;
UPDATE resources SET sort_order = 4 WHERE name = 'Zoom Overseas' AND resource_id <= 12;
UPDATE resources SET sort_order = 5 WHERE name = 'Zoom TW' AND resource_id <= 12;

-- 4) New ids for everything this script inserts, computed from current state rather
--    than assumed — see the note at the top of this file about layout id drift.
SET @base_layout = (SELECT COALESCE(MAX(layout_id), 0) FROM layouts);
SET @base_schedule = (SELECT COALESCE(MAX(schedule_id), 0) FROM schedules);
SET @base_resource = (SELECT COALESCE(MAX(resource_id), 0) FROM resources);
SET @base_group = (SELECT COALESCE(MAX(resource_group_id), 0) FROM resource_groups);

-- 5) 9 new layouts (one per new physical resource), all Asia/Taipei, same 08:00-18:00
--    (30-minute slot) pattern as layout 1 -- same technique coasia-resources.sql used.
INSERT INTO layouts (layout_id, timezone, layout_type) VALUES
  (@base_layout + 1, 'Asia/Taipei', 0),
  (@base_layout + 2, 'Asia/Taipei', 0),
  (@base_layout + 3, 'Asia/Taipei', 0),
  (@base_layout + 4, 'Asia/Taipei', 0),
  (@base_layout + 5, 'Asia/Taipei', 0),
  (@base_layout + 6, 'Asia/Taipei', 0),
  (@base_layout + 7, 'Asia/Taipei', 0),
  (@base_layout + 8, 'Asia/Taipei', 0),
  (@base_layout + 9, 'Asia/Taipei', 0);

INSERT INTO time_blocks (label, end_label, availability_code, layout_id, start_time, end_time, day_of_week)
SELECT tb.label, tb.end_label, tb.availability_code, l.layout_id, tb.start_time, tb.end_time, tb.day_of_week
FROM time_blocks tb
CROSS JOIN (
  SELECT @base_layout + 1 AS layout_id UNION ALL SELECT @base_layout + 2 UNION ALL
  SELECT @base_layout + 3 UNION ALL SELECT @base_layout + 4 UNION ALL
  SELECT @base_layout + 5 UNION ALL SELECT @base_layout + 6 UNION ALL
  SELECT @base_layout + 7 UNION ALL SELECT @base_layout + 8 UNION ALL SELECT @base_layout + 9
) l
WHERE tb.layout_id = 1;

-- 6) 9 new schedules, one per new layout, matching resource names (same convention as
--    coasia-resources.sql: schedule name mirrors its one resource's name, even though
--    names repeat across offices -- the resource-picker groups by office already).
INSERT INTO schedules (schedule_id, name, isdefault, weekdaystart, daysvisible, layout_id, allow_calendar_subscription, allow_concurrent_bookings, default_layout, total_concurrent_reservations, max_resources_per_reservation, published) VALUES
  (@base_schedule + 1, '深圳 Shenzhen', 0, 0, 7, @base_layout + 1, 0, 0, 0, 0, 0, 0),
  (@base_schedule + 2, '上海 Shanghai', 0, 0, 7, @base_layout + 2, 0, 0, 0, 0, 0, 0),
  (@base_schedule + 3, '首爾 Seoul', 0, 0, 7, @base_layout + 3, 0, 0, 0, 0, 0, 0),
  (@base_schedule + 4, '台北 Taipei', 0, 0, 7, @base_layout + 4, 0, 0, 0, 0, 0, 0),
  (@base_schedule + 5, '新加坡 Singapore', 0, 0, 7, @base_layout + 5, 0, 0, 0, 0, 0, 0),
  (@base_schedule + 6, '上海 Shanghai', 0, 0, 7, @base_layout + 6, 0, 0, 0, 0, 0, 0),
  (@base_schedule + 7, '首爾 Seoul', 0, 0, 7, @base_layout + 7, 0, 0, 0, 0, 0, 0),
  (@base_schedule + 8, '深圳 Shenzhen', 0, 0, 7, @base_layout + 8, 0, 0, 0, 0, 0, 0),
  (@base_schedule + 9, '台北 Taipei', 0, 0, 7, @base_layout + 9, 0, 0, 0, 0, 0, 0);

-- 7) 9 new physical resources -- 5 for the Shenzhen office, 4 for the Shanghai office --
--    each cloning the same (autoassign, requires_approval, allow_multiday_reservations,
--    status_id) settings the existing Taipei-office rooms (resources 3-7) use, per the
--    "套用台北同名會議室的設定" decision. sort_order reproduces the exact per-office
--    room order from the spec.
INSERT INTO resources (resource_id, name, schedule_id, autoassign, requires_approval, allow_multiday_reservations, status_id, sort_order) VALUES
  (@base_resource + 1, '深圳 Shenzhen', @base_schedule + 1, 1, 0, 1, 1, 1),
  (@base_resource + 2, '上海 Shanghai', @base_schedule + 2, 1, 0, 1, 1, 2),
  (@base_resource + 3, '首爾 Seoul', @base_schedule + 3, 1, 0, 1, 1, 3),
  (@base_resource + 4, '台北 Taipei', @base_schedule + 4, 1, 0, 1, 1, 4),
  (@base_resource + 5, '新加坡 Singapore', @base_schedule + 5, 1, 0, 1, 1, 5),
  (@base_resource + 6, '上海 Shanghai', @base_schedule + 6, 1, 0, 1, 1, 1),
  (@base_resource + 7, '首爾 Seoul', @base_schedule + 7, 1, 0, 1, 1, 2),
  (@base_resource + 8, '深圳 Shenzhen', @base_schedule + 8, 1, 0, 1, 1, 3),
  (@base_resource + 9, '台北 Taipei', @base_schedule + 9, 1, 0, 1, 1, 4);

-- 8) New resource groups: 深圳實體會議室 / 上海實體會議室 (siblings of the renamed
--    台北實體會議室 / 線上會議室 groups from step 1).
INSERT INTO resource_groups (resource_group_id, resource_group_name, parent_id) VALUES
  (@base_group + 1, '深圳實體會議室', NULL),
  (@base_group + 2, '上海實體會議室', NULL);

INSERT INTO resource_group_assignment (resource_group_id, resource_id) VALUES
  (@base_group + 1, @base_resource + 1), (@base_group + 1, @base_resource + 2),
  (@base_group + 1, @base_resource + 3), (@base_group + 1, @base_resource + 4),
  (@base_group + 1, @base_resource + 5),
  (@base_group + 2, @base_resource + 6), (@base_group + 2, @base_resource + 7),
  (@base_group + 2, @base_resource + 8), (@base_group + 2, @base_resource + 9);

-- 9) Same "grant every current user Full booking permission on every resource" step
--    coasia-resources.sql did for the original 12 -- INSERT IGNORE so it's safe to
--    run again across the full resource list (new rows only actually insert).
INSERT IGNORE INTO user_resource_permissions (user_id, resource_id, permission_id, permission_type)
SELECT u.user_id, r.resource_id, 1, 0
FROM users u
CROSS JOIN resources r;
