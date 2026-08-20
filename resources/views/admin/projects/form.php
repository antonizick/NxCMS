<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;

$id = $data['id'];
$f = $data['fields'];
$action = $id === null ? '/admin/projects' : '/admin/projects/' . $id;
?>
<div class="tile page page--narrow">
    <p class="page-back"><a href="/admin/projects"><?= icon('arrow-right', 'icon icon--flip') ?><span>Back to projects</span></a></p>
    <h1 class="page-title"><?= $id === null ? 'New project' : 'Edit project' ?></h1>

    <?php if (!empty($data['error'])): ?>
        <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e($action) ?>" novalidate>
        <?= Csrf::field() ?>

        <div class="field">
            <label for="title">Title</label>
            <input id="title" name="title" type="text" maxlength="128" required value="<?= e($f['title'] ?? '') ?>">
        </div>

        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3" maxlength="512" required><?= e($f['description'] ?? '') ?></textarea>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="github_url">GitHub URL (blank hides the icon)</label>
                <input id="github_url" name="github_url" type="url" maxlength="512" value="<?= e($f['github_url'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="external_url">Link URL (blank hides the icon)</label>
                <input id="external_url" name="external_url" type="url" maxlength="512" value="<?= e($f['external_url'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="sort_order">Sort order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="<?= (int) ($f['sort_order'] ?? 0) ?>">
            </div>
            <div class="field field--inline">
                <label class="checkbox"><input type="checkbox" name="published" value="1" <?= !empty($f['published']) ? 'checked' : '' ?>> Published</label>
            </div>
        </div>

        <button class="btn btn--accent" type="submit"><span>Save project</span></button>
    </form>
</div>
