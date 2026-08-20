<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;

$projects = $data['projects'];
?>
<div class="tile page page--wide">
    <div class="archive-head">
        <h1 class="page-title">Latest projects</h1>
        <a class="btn btn--accent" href="/admin/projects/new"><span>New project</span></a>
    </div>

    <?php if (!$projects): ?>
        <p class="notice">No projects yet.</p>
    <?php else: ?>
        <div class="archive-table-wrap">
            <table class="archive-table">
                <thead><tr><th>Order</th><th>Title</th><th>Description</th><th>Published</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($projects as $p): ?>
                        <tr>
                            <td data-label="Order"><?= (int) $p['sort_order'] ?></td>
                            <td data-label="Title"><a class="archive-title" href="/admin/projects/<?= (int) $p['id'] ?>/edit"><?= e($p['title']) ?></a></td>
                            <td data-label="Description"><?= e(excerpt($p['description'], 90)) ?></td>
                            <td data-label="Published"><span class="badge <?= $p['published'] ? 'badge--ok' : 'badge--off' ?>"><?= $p['published'] ? 'Yes' : 'No' ?></span></td>
                            <td data-label="" class="crud-actions">
                                <form method="post" action="/admin/projects/<?= (int) $p['id'] ?>/delete" data-confirm="Delete this project?">
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
