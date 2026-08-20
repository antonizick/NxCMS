-- project.portal — admin-editable theme colors.
--
-- A curated set of tokens (not all ~30 in app.css) — the ones that actually
-- carry "brand color" (accent hues, orange, background, card, text), not
-- structural/derived values (borders, glows, per-tile roles) that risk
-- breaking contrast if exposed to a color picker. Defaults match the
-- existing hardcoded values in app.css so a fresh install renders identically
-- until an admin changes something.

SET NAMES utf8mb4;

ALTER TABLE site_settings
    ADD COLUMN theme_dark_bg       VARCHAR(7) NOT NULL DEFAULT '#04090b' AFTER theme_default,
    ADD COLUMN theme_dark_card     VARCHAR(7) NOT NULL DEFAULT '#07161a' AFTER theme_dark_bg,
    ADD COLUMN theme_dark_text     VARCHAR(7) NOT NULL DEFAULT '#e6f3f5' AFTER theme_dark_card,
    ADD COLUMN theme_dark_accent   VARCHAR(7) NOT NULL DEFAULT '#22d3ee' AFTER theme_dark_text,
    ADD COLUMN theme_dark_accent_2 VARCHAR(7) NOT NULL DEFAULT '#2dd4bf' AFTER theme_dark_accent,
    ADD COLUMN theme_dark_orange   VARCHAR(7) NOT NULL DEFAULT '#fb923c' AFTER theme_dark_accent_2,
    ADD COLUMN theme_light_bg       VARCHAR(7) NOT NULL DEFAULT '#f4f4f2' AFTER theme_dark_orange,
    ADD COLUMN theme_light_card     VARCHAR(7) NOT NULL DEFAULT '#ffffff' AFTER theme_light_bg,
    ADD COLUMN theme_light_text     VARCHAR(7) NOT NULL DEFAULT '#14181a' AFTER theme_light_card,
    ADD COLUMN theme_light_accent   VARCHAR(7) NOT NULL DEFAULT '#0d9488' AFTER theme_light_text,
    ADD COLUMN theme_light_accent_2 VARCHAR(7) NOT NULL DEFAULT '#14b8a6' AFTER theme_light_accent,
    ADD COLUMN theme_light_orange   VARCHAR(7) NOT NULL DEFAULT '#f97316' AFTER theme_light_accent_2;
