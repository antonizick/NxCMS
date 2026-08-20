<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;

$id = $data['id'];
$f = $data['fields'];
$action = $id === null ? '/admin/skills' : '/admin/skills/' . $id;
$selectedIcon = $f['icon_key'] ?? '';
?>
<div class="tile page page--narrow">
    <p class="page-back"><a href="/admin/skills"><?= icon('arrow-right', 'icon icon--flip') ?><span>Back to toolbox</span></a></p>
    <h1 class="page-title"><?= $id === null ? 'New skill' : 'Edit skill' ?></h1>

    <?php if (!empty($data['error'])): ?>
        <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e($action) ?>" novalidate>
        <?= Csrf::field() ?>

        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" maxlength="64" required value="<?= e($f['name'] ?? '') ?>">
        </div>

        <div class="field">
            <label for="sort_order">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="<?= (int) ($f['sort_order'] ?? 0) ?>">
        </div>

        <fieldset class="field-group">
            <legend>Icon</legend>
            <div class="icon-picker">
                <?php foreach ($data['iconKeys'] as $key): ?>
                    <label class="icon-picker-option<?= $key === $selectedIcon ? ' is-selected' : '' ?>">
                        <input type="radio" name="icon_key" value="<?= e($key) ?>" <?= $key === $selectedIcon ? 'checked' : '' ?>>
                        <?= icon($key, 'icon') ?>
                        <span><?= e($key) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <button class="btn btn--accent" type="submit"><span>Save skill</span></button>
    </form>
</div>
