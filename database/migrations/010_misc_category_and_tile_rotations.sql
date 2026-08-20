-- project.portal — "Misc" category + per-tile content rotations.
--
-- Misc is a fourth publishable category for articles that aren't News,
-- YouTube or Project Work. It is public and behaves exactly like the other
-- three everywhere it appears (archive listing, search, sort, focus view,
-- sitemap) — the only difference is that the nine-tile home page has no
-- tile dedicated to it, which is what the rotation flags below exist for.
--
-- show_in_profile / show_in_map put a post into the carousel on tile 1
-- (profile) or tile 7 (map). Any category can be flagged, not just Misc.
-- Tile 1 always leads with the profile itself and tile 7 always leads with
-- the map, so these append slides after slide 0 rather than replacing it;
-- with nothing flagged, both tiles render exactly as they did before.
--
-- The two indexes match the rotation query's shape (flag + visibility, then
-- newest-first) so the home page keeps costing two index reads no matter
-- how large content_posts grows.

SET NAMES utf8mb4;

ALTER TABLE content_posts
    MODIFY COLUMN category ENUM('news','youtube','project_work','misc') NOT NULL;

ALTER TABLE content_posts
    ADD COLUMN show_in_profile TINYINT(1) NOT NULL DEFAULT 0 AFTER is_suppressed,
    ADD COLUMN show_in_map     TINYINT(1) NOT NULL DEFAULT 0 AFTER show_in_profile,
    ADD INDEX idx_profile_rotation (show_in_profile, is_suppressed, published_at DESC),
    ADD INDEX idx_map_rotation     (show_in_map,     is_suppressed, published_at DESC);

-- ---------------------------------------------------------------------
-- Sample content for the two new capabilities, so a fresh install shows
-- them working rather than hiding them behind a checkbox nobody knows to
-- look for. Misc especially: it is the one category with no tile of its
-- own, so without a sample in a rotation there is nothing to discover.
--
-- Every statement here is guarded on site_settings.is_demo, which stays
-- set only until "Delete demo content" is used (App\Models\DemoContent
-- clears it as part of the purge). On an install that has already been
-- made real the guard fails and this section does nothing at all — an
-- upgrade must never resurrect sample content, and must never silently
-- rearrange a home page someone is already running.
-- ---------------------------------------------------------------------

INSERT INTO content_posts (category, title, body, link_url, image_url, published_at, is_demo, show_in_profile)
SELECT 'misc',
       'Sample post: something that fits no category',
       'Not every post is news, a video, or project work. Misc is for the rest -- a note to send to someone, a page worth keeping, anything that deserves a real article without belonging to one of the tiles above.

Because Misc has no tile of its own, this post reaches the home page a different way: it is flagged into the profile tile rotation, which is why you are reading it there. Any post can be, whatever its category. The switches are on the post editor, and in the Carousel column of the post list if you would rather curate several at once.

Like everything else on this page, this is sample content -- remove it along with the rest from Settings in the admin panel.',
       NULL, '/assets/img/demo-logo.jpg', '2026-08-17 10:00:00', 1, 1
  FROM DUAL
 WHERE EXISTS (SELECT 1 FROM site_settings WHERE id = 1 AND is_demo = 1);

-- ...and one already-seeded post into the map tile, so both rotations show
-- something. This one carries an image, which is what the tile renders as
-- its muted backdrop.
UPDATE content_posts
   SET show_in_map = 1
 WHERE is_demo = 1
   AND title = 'Sample post: what belongs on this page';
