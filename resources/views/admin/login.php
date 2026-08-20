<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;
use App\Models\Settings;

$siteName = trim((string) (Settings::site()['page_title'] ?? '')) ?: 'this portal';
?>
<div class="tile page page--narrow page--center">
    <h1 class="page-title">Sign in</h1>
    <p class="page-sub">Admin access for <?= e($siteName) ?></p>

    <?php if (!empty($data['error'])): ?>
        <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
    <?php endif; ?>

    <form class="form form--center" method="post" action="/admin/login" novalidate>
        <?= Csrf::field() ?>

        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" maxlength="64" required autofocus autocomplete="username">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>

        <button class="btn btn--accent" type="submit">
            <span>Sign in</span><?= icon('arrow-right', 'icon') ?>
        </button>
    </form>
</div>
