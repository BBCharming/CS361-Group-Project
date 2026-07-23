-- migration_002_anonymous_reports.sql
--
-- Enables filing a fraud report with no account at all.
--   - incidents.user_id becomes nullable — NULL means "anonymous".
--     (FK constraints allow NULL regardless of column nullability, so
--     fk_incident_user needs no change.)
--   - reference_code gives an anonymous reporter something to check
--     status with later, since they have no login to fall back on.
--     Only populated for anonymous submissions; logged-in reports
--     leave it NULL and just use victim/dashboard.php instead.
--
-- Run this once, after migration_001_add_roles.sql.

ALTER TABLE `incidents`
  MODIFY `user_id` INT(11) NULL;

ALTER TABLE `incidents`
  ADD COLUMN `reference_code` VARCHAR(12) NULL UNIQUE AFTER `id`;
