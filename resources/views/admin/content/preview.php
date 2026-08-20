<?php
/**
 * Live "what will this look like" preview — same markup shape as
 * article.php's <article class="focus-main"> content, minus the back-link
 * and history sidebar (neither makes sense for an unsaved draft). Rendered
 * both inline on the edit form's initial load and, as a bare fragment, by
 * AdminContentController::preview() for the debounced fetch — this file is
 * the one place that shape lives, so the two never drift apart.
 *
 * @var array<string, mixed> $data
 */
declare(strict_types=1);

use App\Models\ContentPost;
use App\Support\Markdown;

$f = $data['fields'];
$hasImage = ($f['image_url'] ?? '') !== '';
$ytId = ($f['category'] ?? '') === 'youtube' ? youtube_id((string) ($f['link_url'] ?? '')) : null;
$bodyHtml = Markdown::toHtml((string) ($f['body'] ?? ''));
?>
<p class="kicker kicker--tag"><span><?= e(ContentPost::LABELS[$f['category']] ?? (string) ($f['category'] ?? '')) ?></span></p>
<h1 class="page-title"><?= e($f['title'] !== '' ? $f['title'] : 'Untitled post') ?></h1>

<p class="page-meta">
    <?= icon('calendar', 'icon') ?><span><?= e(fmt_date($f['published_at'] ?? null)) ?></span>
</p>
<?php if (($f['author'] ?? '') !== ''): ?>
    <p class="page-author"><?= e($f['author']) ?></p>
<?php endif; ?>

<?php if ($hasImage || $ytId): ?>
    <div class="page-media<?= $hasImage && $ytId ? ' page-media--split' : '' ?>">
        <?php if ($ytId): ?>
            <div class="page-embed">
                <iframe src="https://www.youtube-nocookie.com/embed/<?= e($ytId) ?>"
                        title="<?= e($f['title'] ?? '') ?>" loading="lazy"
                        allow="encrypted-media; picture-in-picture" allowfullscreen></iframe>
            </div>
        <?php endif; ?>
        <?php if ($hasImage): ?>
            <div class="page-art"><img src="<?= e($f['image_url']) ?>" alt=""></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="prose"><?= $bodyHtml ?></div>

<?php if (($f['link_url'] ?? '') !== ''): ?>
    <p class="page-actions">
        <a class="btn btn--accent" href="<?= e($f['link_url']) ?>" target="_blank" rel="noopener">
            <span><?= ($f['category'] ?? '') === 'youtube' ? 'Watch on YouTube' : 'Read the source' ?></span>
            <?= icon('external-link', 'icon') ?>
        </a>
    </p>
<?php endif; ?>
