<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;

$id = $data['id'];
$target = $data['target'];
$f = $data['fields'];
$tempPassword = $data['tempPassword'];
$isProtected = $target !== null && $target['is_protected'];
$isSelf = $target !== null && (int) $target['id'] === (int) $data['admin']['id'];
?>
<div class="tile page page--narrow">
    <p class="page-back"><a href="/admin/users"><?= icon('arrow-right', 'icon icon--flip') ?><span>Back to admins</span></a></p>
    <h1 class="page-title"><?= $id === null ? 'New admin' : 'Edit admin' ?></h1>
    <?php if ($isProtected): ?>
        <p class="page-sub">This is the protected account &mdash; it can never be deleted or disabled.</p>
    <?php endif; ?>

    <?php if (!empty($data['error'])): ?>
        <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
    <?php endif; ?>

    <?php if ($tempPassword !== null): ?>
        <p class="notice notice--ok" role="status">
            New temporary password (shown once &mdash; copy it now):<br>
            <code><?= e($tempPassword) ?></code><br>
            <?= $isSelf
                ? 'Your current session stays signed in. You\'ll be required to set a new password the next time you log in with it.'
                : 'They\'ll be required to set a new password the moment they log in with it.' ?>
        </p>
    <?php endif; ?>

    <?php if ($id === null): ?>
        <form class="form" method="post" action="/admin/users" novalidate>
            <?= Csrf::field() ?>
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" maxlength="64" required autofocus value="<?= e($f['username'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="password">Password (12+ characters)</label>
                <input id="password" name="password" type="password" minlength="12" required autocomplete="new-password">
            </div>
            <p class="page-sub">MFA enrollment is forced on this account's first login, same as every other admin.</p>
            <button class="btn btn--accent" type="submit"><span>Create admin</span></button>
        </form>
    <?php else: ?>
        <form class="form" method="post" action="/admin/users/<?= $id ?>" novalidate>
            <?= Csrf::field() ?>
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" maxlength="64" required value="<?= e($f['username'] ?? '') ?>">
            </div>
            <div class="field field--inline">
                <label class="checkbox">
                    <input type="checkbox" name="status" value="disabled" <?= ($target['status'] ?? 'active') === 'disabled' ? 'checked' : '' ?> <?= $isProtected ? 'disabled' : '' ?>>
                    Disabled
                </label>
            </div>
            <button class="btn btn--accent" type="submit"><span>Save</span></button>
        </form>

        <fieldset class="field-group" style="margin-top:1.5rem">
            <legend><?= $isSelf ? 'Your account security' : 'Account recovery' ?></legend>
            <?php if ($isSelf): ?>
                <p class="page-sub" style="margin-bottom:.75rem">
                    Neither of these signs you out of this session &mdash; they take effect the next time you log in.
                </p>
            <?php endif; ?>
            <form method="post" action="/admin/users/<?= $id ?>/reset-password" style="display:inline-block;margin-right:.5rem"
                  data-confirm="<?= $isSelf
                      ? 'This replaces your password with a one-time temp password — you\'ll be forced to set a new one on your next login. Continue?'
                      : 'This replaces their password with a one-time temp password — they\'ll be forced to set a new one on their next login. Continue?' ?>">
                <?= Csrf::field() ?>
                <button class="btn btn--ghost" type="submit"><span><?= $isSelf ? 'Reset my password' : 'Reset password' ?></span></button>
            </form>
            <?php if ($target['mfa_enabled']): ?>
                <form method="post" action="/admin/users/<?= $id ?>/reset-mfa" style="display:inline-block"
                      data-confirm="<?= $isSelf
                          ? 'This clears your MFA enrollment — you\'ll be forced to set it up again next login. Continue?'
                          : 'This clears their MFA enrollment — they\'ll be forced to set it up again next login. Continue?' ?>">
                    <?= Csrf::field() ?>
                    <button class="btn btn--ghost" type="submit"><span><?= $isSelf ? 'Reset my MFA' : 'Reset MFA' ?></span></button>
                </form>
            <?php endif; ?>
        </fieldset>
    <?php endif; ?>
</div>
