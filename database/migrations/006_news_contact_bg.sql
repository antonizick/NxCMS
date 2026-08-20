-- project.portal — dedicated background color pickers for the News1 and
-- Contact tiles (dark + light), so they're no longer tied to the shared
-- Card color or hardcoded in app.css.
--
-- Defaults match the current effective look: dark theme's News1/Contact
-- already render as plain Card (#07161a), so those defaults preserve that.
-- Light theme's News1 currently uses a two-stop gray gradient
-- (#cfcfcd -> #bdbdbb) that a flat color picker can't represent — the
-- lighter stop (#cfcfcd) is used as the flat default so the visual change
-- on migrate is minimal (loses the gradient, keeps the same steel-gray
-- tone). Light Contact's near-black (#0c0a09) is already flat, no change.

SET NAMES utf8mb4;

ALTER TABLE site_settings
    ADD COLUMN theme_dark_news_bg     VARCHAR(7) NOT NULL DEFAULT '#07161a' AFTER theme_light_orange,
    ADD COLUMN theme_dark_contact_bg  VARCHAR(7) NOT NULL DEFAULT '#07161a' AFTER theme_dark_news_bg,
    ADD COLUMN theme_light_news_bg    VARCHAR(7) NOT NULL DEFAULT '#cfcfcd' AFTER theme_dark_contact_bg,
    ADD COLUMN theme_light_contact_bg VARCHAR(7) NOT NULL DEFAULT '#0c0a09' AFTER theme_light_news_bg;
