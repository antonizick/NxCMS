<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;

$skills = $data['skills'];
?>
<div class="tile page page--wide">
    <div class="archive-head">
        <h1 class="page-title">Toolbox</h1>
        <a class="btn btn--accent" href="/admin/skills/new"><span>New skill</span></a>
    </div>
    <p class="page-sub">Order here matches the order shown on the public page.</p>

    <?php if (!$skills): ?>
        <p class="notice">No skills yet.</p>
    <?php else: ?>
        <div class="archive-table-wrap">
            <table class="archive-table">
                <thead><tr><th>Order</th><th>Icon</th><th>Name</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($skills as $s): ?>
                        <tr>
                            <td data-label="Order"><?= (int) $s['sort_order'] ?></td>
                            <td data-label="Icon"><?= icon($s['icon_key'], 'icon') ?></td>
                            <td data-label="Name"><a class="archive-title" href="/admin/skills/<?= (int) $s['id'] ?>/edit"><?= e($s['name']) ?></a></td>
                            <td data-label="" class="crud-actions">
                                <form method="post" action="/admin/skills/<?= (int) $s['id'] ?>/delete" data-confirm="Delete this skill?">
                                    <?= Csrf::field() ?>
                                    <button class="btn btn--ghost btn--small" type="submit"><span>Delete</span></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
