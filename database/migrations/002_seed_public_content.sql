-- Sample content, so a brand-new install renders as designed instead of as
-- nine empty cards.
--
-- Every row and value written here is fictional and marked is_demo = 1.
-- "Delete demo content" in the admin panel removes exactly these, and nothing
-- an admin has since written: saving any of these screens clears the flag, so
-- edited content is never treated as demo content again.
--
-- The persona (Nora Xie) is invented. Do not replace it with a real person's
-- details -- this file ships to every install.

SET NAMES utf8mb4;

-- Tile 7 needs a timezone to show local time at the pinned location.
ALTER TABLE site_settings
    ADD COLUMN recon_timezone VARCHAR(64) NULL AFTER recon_lng;

UPDATE site_settings SET
    is_demo             = 1,
    page_title          = 'nora.xie',
    initials            = 'NX',
    display_name        = 'Nora Xie',
    copyright_year      = '2026',
    copyright_text      = 'Nora Xie',
    footer_text         = 'Built with NxCMS',
    copy_link_text      = 'Copy link',
    copy_link_url       = '',
    theme_default       = 'dark',
    recon_location_label = 'Portland, OR',
    recon_lat           = 45.515200,
    recon_lng           = -122.678400,
    recon_timezone      = 'America/Los_Angeles',
    contact_main_title  = 'Let''s build something',
    contact_sub_title   = 'Currently taking on one new project.',
    contact_button_text = 'Say hello'
WHERE id = 1;

UPDATE profile SET
    is_demo       = 1,
    headshot_url  = '/assets/img/default-avatar.jpg',
    logo_url      = '/assets/img/demo-logo.jpg',
    status_phrase = 'Available for projects',
    bio           = 'This is sample text, so you can see how the page looks with something in it. Replace it from the admin panel.

I''m a security and platform engineer. I build detection tooling, automate the boring parts of incident response, and spend more time than I''d like explaining why the logs stopped. Mostly Python, MySQL and containers, usually somewhere near a cloud console.

Outside work: long walks, short deadlines, and an ongoing argument with my own note-taking system.'
WHERE id = 1;

-- example.com by design: a shipped install must not send its first visitors to
-- anyone's real profile. Replace these with your own from the admin panel.
INSERT INTO social_links (platform, label, url, sort_order, is_demo) VALUES
    ('github',   NULL, 'https://example.com/github',   10, 1),
    ('linkedin', NULL, 'https://example.com/linkedin', 20, 1),
    ('x',        NULL, 'https://example.com/x',        30, 1);

INSERT INTO projects (title, description, github_url, external_url, sort_order, is_demo) VALUES
    ('Beacon',   'Sample project. A small service that watches log streams and raises an alert before the dashboard does.', 'https://example.com/beacon', '', 10, 1),
    ('Ledger',   'Sample project. Tracks configuration drift across environments and explains what changed, and when.',     '', '', 20, 1),
    ('Halfpipe', 'Sample project. A deployment pipeline that fits on one screen and refuses to grow past it.',              '', '', 30, 1),
    ('Quietly',  'Sample project. Turns noisy alerting into a short daily digest nobody dreads reading.',                   '', '', 40, 1);

INSERT INTO skills (name, icon_key, sort_order, is_demo) VALUES
    ('Python',     'code',       10,  1),
    ('Bash',       'terminal',   20,  1),
    ('MySQL',      'database',   30,  1),
    ('Docker',     'box',        40,  1),
    ('AWS',        'cloud',      50,  1),
    ('Terraform',  'layers',     60,  1),
    ('Git',        'git-branch', 70,  1),
    ('Linux',      'server',     80,  1),
    ('Grafana',    'chart',      90,  1),
    ('PostgreSQL', 'database',   100, 1);

INSERT INTO content_posts (category, title, body, link_url, image_url, published_at, is_demo) VALUES
    ('news',
     'Sample post: what belongs on this page',
     'This is sample content, included so the layout has something to show on a fresh install. Write over it, or remove every sample row at once from Settings in the admin panel.

Posts support Markdown, so headings, lists, links and code all work. The first post in each category is the one that gets the large card; the rest stack underneath it.

Nothing here is real. The name, the projects and the links are all invented.',
     NULL, '/assets/img/demo-shield.jpg', '2026-08-15 09:30:00', 1),

    ('news',
     'Sample post: the shorter format',
     'Shorter posts sit below the featured one. Useful for a quick note, a link worth keeping, or a change worth announcing.',
     NULL, NULL, '2026-08-12 08:00:00', 1),

    ('youtube',
     'Sample video post',
     'Point this at a talk, a demo, or a walkthrough. Paste the link and the card handles the rest.',
     NULL, NULL, '2026-08-14 17:00:00', 1),

    ('project_work',
     'Sample work note',
     'A place for progress on whatever you are building right now -- shorter than a blog post, longer than a status update.',
     NULL, NULL, '2026-08-16 11:15:00', 1);
