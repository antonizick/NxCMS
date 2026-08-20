<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;
?>
<div class="tile page page--narrow page--center">
    <h1 class="page-title">Set a new password</h1>
    <p class="page-sub">Your password was reset by an administrator. Choose a new one to continue.</p>

    <?php if (!empty($data['error'])): ?>
        <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
    <?php endif; ?>

    <form class="form form--center" method="post" action="/admin/password/change" novalidate>
        <?= Csrf::field() ?>

        <div class="field">
            <label for="password">New password (12+ characters)</label>
            <input id="password" name="password" type="password" minlength="12" required autofocus autocomplete="new-password">
        </div>

        <div class="field">
            <label for="password_confirm">Confirm new password</label>
            <input id="password_confirm" name="password_confirm" type="password" minlength="12" required autocomplete="new-password">
        </div>

        <button class="btn btn--accent" type="submit">
            <span>Set password</span><?= icon('arrow-right', 'icon') ?>
        </button>
    </form>
</div>
