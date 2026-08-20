-- project.portal — admin-editable status-dot color (Tile 1, next to status_phrase).
-- Default matches the dot's current hardcoded color (--accent-2 in dark theme,
-- app.css) so existing installs render identically until an admin changes it.

SET NAMES utf8mb4;

ALTER TABLE profile
    ADD COLUMN status_dot_color VARCHAR(7) NOT NULL DEFAULT '#2dd4bf' AFTER status_phrase;
