<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;

$admins = $data['admins'];
$self = (int) $data['admin']['id'];
?>
<div class="tile page page--wide">
    <div class="archive-head">
        <h1 class="page-title">Admin accounts</h1>
        <a class="btn btn--accent" href="/admin/users/new"><span>New admin</span></a>
    </div>

    <div class="archive-table-wrap">
        <table class="archive-table">
            <thead><tr><th>Username</th><th>Status</th><th>Password</th><th>MFA</th><th>Last login</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($admins as $a): ?>
                    <tr>
                        <td data-label="Username">
                            <a class="archive-title" href="/admin/users/<?= (int) $a['id'] ?>/edit"><?= e($a['username']) ?></a>
                            <?php if ($a['is_protected']): ?><span class="badge badge--protected">protected</span><?php endif; ?>
                            <?php if ((int) $a['id'] === $self): ?><span class="badge">you</span><?php endif; ?>
                        </td>
                        <td data-label="Status"><span class="badge <?= $a['status'] === 'active' ? 'badge--ok' : 'badge--off' ?>"><?= e($a['status']) ?></span></td>
                        <td data-label="Password"><?= $a['must_change_password'] ? 'Pending change' : '&mdash;' ?></td>
                        <td data-label="MFA"><?= $a['mfa_enabled'] ? 'Enabled' : ($a['force_mfa_setup'] ? 'Pending setup' : 'Off') ?></td>
                        <td data-label="Last login"><?= $a['last_login_at'] ? e(fmt_date($a['last_login_at'], 'j M Y, g:i a')) : '&mdash;' ?></td>
                        <td data-label="" class="crud-actions">
                            <?php if (!$a['is_protected'] && (int) $a['id'] !== $self): ?>
                                <form method="post" action="/admin/users/<?= (int) $a['id'] ?>/delete" data-confirm="Delete this admin account?">
                                    <?= Csrf::field() ?>
                                    <button class="btn btn--ghost btn--small" type="submit"><span>Delete</span></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
