<?php
/**
 * Public portal home — the 9-tile grid from Portal Layout.png.
 *
 * @var array<string, mixed> $data
 */

declare(strict_types=1);

$site = $data['site'];
$profile = $data['profile'];
$socials = $data['socials'];
$projects = $data['projects'];
$skills = $data['skills'];
$news1 = $data['news1'];
$news2 = $data['news2'];
$youtube = $data['youtube'];
$work = $data['projectWork'];

$headshot = $profile['headshot_url'] ?? null;
$logo = $profile['logo_url'] ?? null;

/* Avatar rotation frames — headshot first (used for alt text), then logo and
 * up to two extra photos, in upload-slot order. Missing slots are simply
 * absent from the list, per spec. app.js's oscillator and the .avatar-img
 * CSS both already operate generically over however many frames render. */
$avatarImages = array_values(array_filter([
    $headshot,
    $logo,
    $profile['photo3_url'] ?? null,
    $profile['photo4_url'] ?? null,
], static fn($url) => $url !== null && $url !== ''));

$youtubeArt = $youtube['image_url'] ?? '';
$workArt = $work['image_url'] ?? '';
$news2Art = $news2['image_url'] ?? '';

/* Tile 1 / tile 7 carousels. Slide 0 is always the tile's own content — the
 * profile, the map — and flagged posts follow it. When nothing is flagged
 * both arrays are empty, no dots render, and each tile lays out exactly as
 * it did before the carousels existed. */
$profileSlides = $data['profileRotation'];
$mapSlides = $data['mapRotation'];
$hasMap = $site['recon_lat'] !== null && $site['recon_lng'] !== null;

$profileSlideCount = 1 + count($profileSlides);
$mapSlideCount = ($hasMap ? 1 : 0) + count($mapSlides);

/* The dot row. Rendered only for a genuine rotation: a single dot would be
 * a position indicator for a position that can't change. */
$dots = static function (int $count, string $label): string {
    if ($count < 2) {
        return '';
    }

    $out = '<div class="carousel-dots" aria-label="' . e($label) . '">';
    for ($i = 0; $i < $count; $i++) {
        $out .= '<button class="carousel-dot' . ($i === 0 ? ' is-active' : '') . '" type="button"'
            . ' data-slide-to="' . $i . '"'
            . ($i === 0 ? ' aria-current="true"' : '')
            . ' aria-label="Show slide ' . ($i + 1) . ' of ' . $count . '"></button>';
    }

    return $out . '</div>';
};

/* Body of an article slide, shared by both tiles. The whole slide is the
 * click target (.stretched), so "More" is an affordance, not a second link.
 *
 * Tile 1 is served a deliberately over-long excerpt and clamped to the
 * space by CSS — see .slide-excerpt in app.css — so its budget is a ceiling
 * rather than a target. The map tile's budget is the real length. */
$slideBody = static function (array $p, int $length, bool $clampedByCss = false): string {
    $out = '<p class="kicker kicker--tag"><span>' . e(\App\Models\ContentPost::LABELS[$p['category']] ?? $p['category']) . '</span></p>'
        . '<h3 class="slide-title"><a class="stretched" href="' . e(post_url($p)) . '">' . e($p['title']) . '</a></h3>'
        . '<p class="slide-excerpt">' . e(excerpt($p['body'], $length)) . '</p>'
        . '<p class="slide-meta">' . e(relative_date($p['published_at'])) . '</p>';

    /* Where CSS clamps the text to the space available, only the browser
       knows how much fit, so the element always renders and app.js hides it
       when nothing was actually cut. Elsewhere the character budget is the
       whole truth and decides here. */
    if ($clampedByCss || excerpt_truncated($p['body'], $length)) {
        $out .= '<p class="slide-more"><span>More</span>' . icon('arrow-right', 'icon') . '</p>';
    }

    return $out;
};
?>
<div class="portal">

    <!-- ── Tile 1: Profile (carousel: profile + flagged posts) ──────── -->
    <?php $avatarTag = count($avatarImages) > 1 ? 'button' : 'div'; ?>
    <section class="tile tile--profile<?= $profileSlideCount > 1 ? ' carousel' : '' ?>" aria-labelledby="t1"<?= $profileSlideCount > 1 ? ' data-carousel' : '' ?>>
        <h2 class="sr-only" id="t1">Profile</h2>

        <?php /* Persistent across every slide, per spec: a sibling of the track
                 rather than a child of slide 0, so sliding never moves it.
                 It becomes a real <button> only when there is more than one
                 photo — a control that can't do anything shouldn't be focusable. */ ?>
        <<?= $avatarTag ?> class="avatar<?= count($avatarImages) > 1 ? ' avatar--swaps' : '' ?>"<?= $avatarTag === 'button' ? ' type="button" aria-label="Show next photo"' : '' ?>>
            <?php foreach ($avatarImages as $i => $img): ?>
                <img class="avatar-img<?= $i === 0 ? ' is-active' : '' ?>" src="<?= e($img) ?>"
                     alt="<?= $i === 0 ? e($site['display_name'] ?? '') : '' ?>" width="160" height="160">
            <?php endforeach; ?>
        </<?= $avatarTag ?>>

        <div class="carousel-viewport">
            <div class="carousel-track">

                <div class="carousel-slide is-active">
                    <div class="slide-head">
                        <?php if (($profile['status_phrase'] ?? '') !== ''):
                            $dotColor = (string) ($profile['status_dot_color'] ?? '');
                            $dotColor = preg_match('/^#[0-9a-fA-F]{6}$/', $dotColor) === 1 ? $dotColor : '#2dd4bf';
                        ?>
                            <p class="status"><span class="status-dot" aria-hidden="true" style="background:<?= e($dotColor) ?>;box-shadow:0 0 8px <?= e($dotColor) ?>"></span><?= e($profile['status_phrase']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="slide-body">
                        <?php if (($profile['bio'] ?? '') !== ''): ?>
                            <p class="bio"><?= nl2br(e($profile['bio'])) ?></p>
                        <?php endif; ?>

                        <?php if ($socials): ?>
                            <ul class="socials">
                                <?php foreach ($socials as $link):
                                    $key = $link['platform'] === 'other' ? 'globe' : $link['platform'];
                                    $name = $link['label'] ?: ucfirst((string) $link['platform']); ?>
                                    <li>
                                        <a class="social" href="<?= e($link['url']) ?>" rel="me noopener" target="_blank"
                                           aria-label="<?= e($name) ?>" title="<?= e($name) ?>">
                                            <?= icon(\App\Support\Icons::has($key) ? $key : 'globe', 'icon') ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <?php foreach ($profileSlides as $i => $p):
                    $art = $p['image_url'] ?? '';
                    /* --i is the slide's position in the strip. These slides are
                       positioned absolutely (see app.css) so an over-long excerpt
                       cannot grow the tile, which means each has to be told where
                       in the strip it belongs. +1 for the profile slide ahead of them. */
                    $slideStyle = '--i:' . ($i + 1) . ($art !== '' ? ';--art-img:url(\'' . e($art) . '\')' : '');
                ?>
                    <div class="carousel-slide carousel-slide--post<?= $art !== '' ? ' has-artbg' : '' ?>" style="<?= $slideStyle ?>">
                        <div class="slide-head">
                            <?php if ($art !== ''): ?>
                                <span class="slide-thumb"><img src="<?= e($art) ?>" alt="" loading="lazy"></span>
                            <?php endif; ?>
                        </div>
                        <div class="slide-body"><?= $slideBody($p, 2000, true) ?></div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>

        <?= $dots($profileSlideCount, 'Profile tile slides') ?>
    </section>

    <!-- ── Tile 2: Latest Projects ───────────────────────────────────── -->
    <section class="tile tile--projects" aria-labelledby="t2">
        <h2 class="tile-label" id="t2">Latest Projects</h2>

        <?php if ($projects): ?>
            <ul class="project-list">
                <?php foreach ($projects as $p):
                    /* Row-wide click target — Link-URL wins over GitHub when both exist,
                       per spec; a row with neither stays plain (nothing to click through to). */
                    $rowLink = ($p['external_url'] ?? '') !== '' ? $p['external_url']
                        : ((($p['github_url'] ?? '') !== '') ? $p['github_url'] : null);
                ?>
                    <li class="project">
                        <h3 class="project-title">
                            <?php if ($rowLink !== null): ?>
                                <a class="stretched" href="<?= e($rowLink) ?>" target="_blank" rel="noopener"><?= e($p['title']) ?></a>
                            <?php else: ?>
                                <?= e($p['title']) ?>
                            <?php endif; ?>
                        </h3>
                        <p class="project-desc"><?= e($p['description']) ?></p>
                        <div class="project-links">
                            <?php if (($p['github_url'] ?? '') !== ''): ?>
                                <a href="<?= e($p['github_url']) ?>" target="_blank" rel="noopener"
                                   aria-label="<?= e($p['title']) ?> on GitHub"><?= icon('github', 'icon') ?></a>
                            <?php endif; ?>
                            <?php if (($p['external_url'] ?? '') !== ''): ?>
                                <a href="<?= e($p['external_url']) ?>" target="_blank" rel="noopener"
                                   aria-label="<?= e($p['title']) ?> website"><?= icon('external-link', 'icon') ?></a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- ── Tile 3: Toolbox ───────────────────────────────────────────── -->
    <section class="tile tile--toolbox" aria-labelledby="t3">
        <h2 class="tile-label" id="t3">Toolbox</h2>

        <?php if ($skills): ?>
            <ul class="chips">
                <?php foreach ($skills as $s): ?>
                    <li class="chip"><?= icon($s['icon_key'] ?: 'box', 'icon') ?><span><?= e($s['name']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- ── Tile 4: News (most recent) ────────────────────────────────── -->
    <section class="tile tile--news1" aria-labelledby="t4">
        <?php if ($news1): ?>
            <h2 class="news1-title" id="t4">
                <a class="stretched" href="<?= e(post_url($news1)) ?>"><?= e($news1['title']) ?></a>
            </h2>
            <p class="news1-excerpt"><?= e(excerpt($news1['body'], 330)) ?></p>
            <div class="news1-art">
                <?php if (($news1['image_url'] ?? '') !== ''): ?>
                    <img src="<?= e($news1['image_url']) ?>" alt="" loading="lazy">
                <?php endif; ?>
            </div>
            <p class="datestamp"><?= icon('calendar', 'icon') ?><span><?= e(fmt_date($news1['published_at'])) ?></span></p>
            <p class="read-more"><span>Read the full analysis</span><?= icon('arrow-right', 'icon') ?></p>
        <?php else: ?>
            <h2 class="sr-only" id="t4">News</h2>
        <?php endif; ?>
    </section>

    <!-- ── Tile 5: YouTube ───────────────────────────────────────────── -->
    <section class="tile tile--youtube<?= $youtubeArt !== '' ? ' has-artbg' : '' ?>" aria-labelledby="t5"<?= $youtubeArt !== '' ? ' style="--art-img:url(\'' . e($youtubeArt) . '\')"' : '' ?>>
        <?php if ($youtube): ?>
            <p class="kicker kicker--yt"><?= icon('play', 'icon') ?><span>Watch</span></p>
            <h2 class="card-title" id="t5">
                <a class="stretched" href="<?= e(post_url($youtube)) ?>"><?= e($youtube['title']) ?></a>
            </h2>
            <p class="card-desc"><?= e(excerpt($youtube['body'], 150)) ?></p>
            <p class="card-meta"><?= icon('youtube', 'icon icon--yt-badge') ?>YouTube &middot; <?= e(relative_date($youtube['published_at'])) ?></p>
        <?php else: ?>
            <h2 class="sr-only" id="t5">YouTube</h2>
        <?php endif; ?>
    </section>

    <!-- ── Tile 6: Project Work ──────────────────────────────────────── -->
    <section class="tile tile--work<?= $workArt !== '' ? ' has-artbg' : '' ?>" aria-labelledby="t6"<?= $workArt !== '' ? ' style="--art-img:url(\'' . e($workArt) . '\')"' : '' ?>>
        <?php if ($work): ?>
            <p class="kicker kicker--tag"><span>Project Work</span></p>
            <h2 class="card-title" id="t6">
                <a class="stretched" href="<?= e(post_url($work)) ?>"><?= e($work['title']) ?></a>
            </h2>
            <p class="card-desc"><?= e(excerpt($work['body'], 170)) ?></p>
            <p class="card-meta"><?= e(relative_date($work['published_at'])) ?></p>
        <?php else: ?>
            <h2 class="sr-only" id="t6">Project work</h2>
        <?php endif; ?>
    </section>

    <!-- ── Tile 7: Recon (carousel: map + flagged posts) ─────────────── -->
    <section class="tile tile--recon<?= $mapSlideCount > 1 ? ' carousel' : '' ?>" aria-labelledby="t7"<?= $mapSlideCount > 1 ? ' data-carousel' : '' ?>>
        <h2 class="sr-only" id="t7">Location</h2>

        <div class="carousel-viewport">
            <div class="carousel-track">

                <?php if ($hasMap): ?>
                    <div class="carousel-slide carousel-slide--map is-active">
                        <?= \App\Support\Map::render((float) $site['recon_lat'], (float) $site['recon_lng'], (string) ($site['recon_location_label'] ?? '')) ?>
                        <p class="recon-pill"><?= icon('map-pin', 'icon') ?><span><?= e($site['recon_location_label'] ?? '') ?></span></p>
                        <p class="recon-time"><?= e(\App\Support\Map::localTime($site['recon_timezone'] ?? null)) ?></p>
                    </div>
                <?php endif; ?>

                <?php foreach ($mapSlides as $i => $p): $art = $p['image_url'] ?? ''; ?>
                    <div class="carousel-slide carousel-slide--post carousel-slide--pad<?= !$hasMap && $i === 0 ? ' is-active' : '' ?><?= $art !== '' ? ' has-artbg' : '' ?>"<?= $art !== '' ? ' style="--art-img:url(\'' . e($art) . '\')"' : '' ?>>
                        <?= $slideBody($p, 320) ?>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>

        <?= $dots($mapSlideCount, 'Location tile slides') ?>
    </section>

    <!-- ── Tile 8: News2 (second most recent) ────────────────────────── -->
    <section class="tile tile--news2<?= $news2Art !== '' ? ' has-artbg' : '' ?>" aria-labelledby="t8"<?= $news2Art !== '' ? ' style="--art-img:url(\'' . e($news2Art) . '\')"' : '' ?>>
        <?php if ($news2): ?>
            <span class="news2-glyph" aria-hidden="true"><?= icon('newspaper', 'icon') ?></span>
            <h2 class="card-title" id="t8">
                <a class="stretched" href="<?= e(post_url($news2)) ?>"><?= e($news2['title']) ?></a>
            </h2>
            <p class="card-desc"><?= e(excerpt($news2['body'], 320)) ?></p>
            <p class="card-meta"><?= e(relative_date($news2['published_at'])) ?></p>
        <?php else: ?>
            <h2 class="sr-only" id="t8">More news</h2>
        <?php endif; ?>
    </section>

    <!-- ── Tile 9: Contact ───────────────────────────────────────────── -->
    <section class="tile tile--contact" aria-labelledby="t9">
        <div>
            <h2 class="contact-title" id="t9"><?= e($site['contact_main_title'] ?? '') ?></h2>
            <?php if (($site['contact_sub_title'] ?? '') !== ''): ?>
                <p class="contact-sub"><?= e($site['contact_sub_title']) ?></p>
            <?php endif; ?>
        </div>
        <a class="btn btn--accent" href="/contact">
            <span><?= e($site['contact_button_text'] ?: 'Say hello') ?></span><?= icon('arrow-right', 'icon') ?>
        </a>
    </section>

</div>
