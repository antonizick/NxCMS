-- project.portal — widen social_links.platform to cover the rest of the
-- major networks (SocialLink::PLATFORMS must be kept in sync with this list).

SET NAMES utf8mb4;

ALTER TABLE social_links
    MODIFY COLUMN platform ENUM(
        'x','facebook','instagram','tiktok','threads','youtube','linkedin','github',
        'discord','twitch','reddit','mastodon','bluesky','dribbble','other'
    ) NOT NULL;
