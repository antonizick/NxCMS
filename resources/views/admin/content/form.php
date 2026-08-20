<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\View;
use App\Models\ContentPost;

$id = $data['id'];
$f = $data['fields'];
$action = $id === null ? '/admin/content' : '/admin/content/' . $id;
?>
<div class="tile page page--split">
    <div class="editor-col">
        <p class="page-back"><a href="/admin/content"><?= icon('arrow-right', 'icon icon--flip') ?><span>Back to posts</span></a></p>
        <h1 class="page-title"><?= $id === null ? 'Post content' : 'Edit post' ?></h1>

        <?php if (!empty($data['error'])): ?>
            <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
        <?php endif; ?>

        <form class="form" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate data-preview-form>
            <?= Csrf::field() ?>
            <input type="hidden" name="image_url" value="<?= e($f['image_url'] ?? '') ?>">

            <div class="field">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <?php foreach ($data['categories'] as $c): ?>
                        <option value="<?= e($c) ?>" <?= $c === ($f['category'] ?? '') ? 'selected' : '' ?>><?= e(ContentPost::LABELS[$c] ?? $c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="title">Title</label>
                <input id="title" name="title" type="text" maxlength="255" required value="<?= e($f['title'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="author">Author</label>
                <input id="author" name="author" type="text" maxlength="128" value="<?= e($f['author'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="body">Contents (Markdown/HTML)</label>
                <textarea id="body" name="body" rows="10"><?= e($f['body'] ?? '') ?></textarea>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="link_url">Link (article/video, optional)</label>
                    <input id="link_url" name="link_url" type="url" maxlength="512" value="<?= e($f['link_url'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="published_at">Publish date</label>
                    <input id="published_at" name="published_at" type="datetime-local" required value="<?= e($f['published_at'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label>Image</label>
                <div class="upload-preview"<?= empty($f['image_url']) ? ' hidden' : '' ?>>
                    <img src="<?= e($f['image_url'] ?? '') ?>" alt="">
                </div>
                <label class="checkbox" data-remove-image-wrap<?= empty($f['image_url']) ? ' hidden' : '' ?>>
                    <input type="checkbox" name="remove_image" value="1"> Remove current image
                </label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </div>

            <fieldset class="field-group">
                <legend>Home page</legend>
                <p class="field-hint">
                    Every category already has its own tile except Misc. These put this post into
                    the rotating carousel on tile 1 or tile 7 as well, whatever its category —
                    newest <?= (int) ContentPost::ROTATION_LIMIT ?> flagged posts per tile.
                </p>
                <label class="checkbox">
                    <input type="checkbox" name="show_in_profile" value="1" <?= !empty($f['show_in_profile']) ? 'checked' : '' ?>>
                    Rotate on the profile tile
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="show_in_map" value="1" <?= !empty($f['show_in_map']) ? 'checked' : '' ?>>
                    Rotate on the map tile
                </label>
            </fieldset>

            <label class="checkbox">
                <input type="checkbox" name="is_suppressed" value="1" <?= !empty($f['is_suppressed']) ? 'checked' : '' ?>>
                Suppress (unpublish — the site treats it as though it never existed)
            </label>

            <button class="btn btn--accent" type="submit"><span>Save post</span></button>
        </form>
    </div>

    <div class="preview-col">
        <p class="tile-label">Preview</p>
        <div class="tile preview-frame">
            <article class="focus-main" id="contentPreview">
                <?= View::partial('admin/content/preview', ['fields' => $f]) ?>
            </article>
        </div>
    </div>
</div>
