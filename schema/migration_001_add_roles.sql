-- migration_001_add_roles.sql
--
-- The original zatcher_db.sql only defines users.role as enum('user','admin'),
-- but the RBAC layer (includes/auth_guard.php) and every role dashboard
-- (victim/analyst/police/zicta) route on four distinct roles per the README.
-- This is the one schema change required to actually join auth to the four
-- dashboards — everything else in this migration is additive seed data,
-- nothing here drops or renames existing tables/columns.
--
-- Run this once, after importing zatcher_db.sql.
--
-- IMPORTANT ORDERING NOTE: you can't narrow straight to the final ENUM in
-- one step. MySQL checks every existing row against a new ENUM's value
-- list the instant you MODIFY the column — and zatcher_db.sql's seed rows
-- use 'user'/'admin', neither of which is in the new list, so that ALTER
-- fails immediately with "Data truncated for column 'role'" before the
-- remap below ever gets a chance to run. Fix: widen to VARCHAR first
-- (accepts anything), remap the data, THEN narrow to the final ENUM.

ALTER TABLE `users`
  MODIFY `role` VARCHAR(20) NOT NULL DEFAULT 'victim';

-- Existing demo rows from zatcher_db.sql used 'user'/'admin' — remap them
-- so nothing is left in a role the final enum won't accept.
UPDATE `users` SET `role` = 'victim' WHERE `role` NOT IN ('victim','analyst','police','zicta');

ALTER TABLE `users`
  MODIFY `role` ENUM('victim','analyst','police','zicta') NOT NULL DEFAULT 'victim';

-- Back-office roles (analyst/police/zicta) aren't self-registered — README
-- describes ZICTA as the body that authorizes analysts — so seed one demo
-- account per role for local testing. Change these passwords before any
-- real deployment; 'demo12345' is only here so login.php has something to
-- authenticate against out of the box.
-- Password hash below is a real bcrypt hash for the plaintext 'demo12345'
-- (verified against PHP's password_verify() format).
INSERT INTO `users` (`full_name`, `email`, `phone_number`, `password_hash`, `role`, `created_at`)
VALUES
  ('Demo Analyst', 'analyst@zatcher.local', '0977000010',
   '$2b$10$.A/fOHM3Lh.5ELmOp6Iir.3wPvWVVSJ5GvayIUwfu2GB/kCiO3X9G', 'analyst', NOW()),
  ('Demo Police',  'police@zatcher.local',  '0977000011',
   '$2b$10$.A/fOHM3Lh.5ELmOp6Iir.3wPvWVVSJ5GvayIUwfu2GB/kCiO3X9G', 'police', NOW()),
  ('Demo ZICTA',   'zicta@zatcher.local',   '0977000012',
   '$2b$10$.A/fOHM3Lh.5ELmOp6Iir.3wPvWVVSJ5GvayIUwfu2GB/kCiO3X9G', 'zicta', NOW());
