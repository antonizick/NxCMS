<?php
/**
 * Article focus view (article.focus.jpg) — full post plus a chronological
 * sidebar of the same category's other posts.
 *
 * @var array<string, mixed> $data
 */

declare(strict_types=1);

use App\Models\ContentPost;

$post = $data['post'];
$history = $data['history'];
$hasImage = ($post['image_url'] ?? '') !== '';
$ytId = $post['category'] === 'youtube' ? youtube_id((string) ($post['link_url'] ?? '')) : null;
?>
<div class="tile page focus">
    <aside class="focus-side">
        <h2 class="focus-side-label">Article history</h2>
        <ul class="focus-list">
            <?php foreach ($history as $h): ?>
                <li>
                    <a class="focus-item<?= (int) $h['id'] === (int) $post['id'] ? ' is-active' : '' ?>"
                       href="<?= e(post_url($h)) ?>"
                       <?= (int) $h['id'] === (int) $post['id'] ? 'aria-current="page"' : '' ?>>
                        <span class="focus-item-date">
                            <span class="focus-item-cat focus-item-cat--<?= e($h['category']) ?>"
                                  aria-label="<?= e(ContentPost::LABELS[$h['category']] ?? '') ?>"><?= e(ContentPost::INITIALS[$h['category']] ?? '') ?></span>
                            <?= e(fmt_date($h['published_at'])) ?>
                        </span>
                        <span class="focus-item-title"><?= e($h['title']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <a class="focus-see-all" href="/articles">
            <span>View all articles</span>
            <?= icon('arrow-right', 'icon') ?>
        </a>
    </aside>

    <article class="focus-main">
        <p class="page-back"><a href="/"><?= icon('arrow-right', 'icon icon--flip') ?><span>Back to the portal</span></a></p>

        <p class="kicker kicker--tag"><span><?= e(ContentPost::LABELS[$post['category']] ?? $post['category']) ?></span></p>
        <h1 class="page-title"><?= e($post['title']) ?></h1>

        <p class="page-meta">
            <?= icon('calendar', 'icon') ?><span><?= e(fmt_date($post['published_at'])) ?></span>
        </p>
        <?php if (($post['author'] ?? '') !== ''): ?>
            <p class="page-author"><?= e($post['author']) ?></p>
        <?php endif; ?>

        <?php if ($hasImage || $ytId): ?>
            <div class="page-media<?= $hasImage && $ytId ? ' page-media--split' : '' ?>">
                <?php if ($ytId): ?>
                    <div class="page-embed">
                        <iframe src="https://www.youtube-nocookie.com/embed/<?= e($ytId) ?>"
                                title="<?= e($post['title']) ?>" loading="lazy"
                                allow="encrypted-media; picture-in-picture" allowfullscreen></iframe>
                    </div>
                <?php endif; ?>
                <?php if ($hasImage): ?>
                    <div class="page-art"><img src="<?= e($post['image_url']) ?>" alt=""></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="prose"><?= $data['bodyHtml'] ?></div>

        <?php if (($post['link_url'] ?? '') !== ''): ?>
            <p class="page-actions">
                <a class="btn btn--accent" href="<?= e($post['link_url']) ?>" target="_blank" rel="noopener">
                    <span><?= $post['category'] === 'youtube' ? 'Watch on YouTube' : 'Read the source' ?></span>
                    <?= icon('external-link', 'icon') ?>
                </a>
            </p>
        <?php endif; ?>
    </article>
</div>
