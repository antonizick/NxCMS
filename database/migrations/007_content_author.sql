-- project.portal — author byline for News/YouTube/Project Work posts.
-- Defaults every existing and future row to an empty author so nothing
-- publishes blank; the admin form still lets it be overridden per post.

SET NAMES utf8mb4;

ALTER TABLE content_posts
    ADD COLUMN author VARCHAR(128) NOT NULL DEFAULT '' AFTER title;
