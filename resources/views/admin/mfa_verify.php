<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;
?>
<div class="tile page page--narrow page--center">
    <h1 class="page-title">Verify it's you</h1>
    <p class="page-sub">Enter the code from your authenticator app, or one of your recovery codes.</p>

    <?php if (!empty($data['error'])): ?>
        <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
    <?php endif; ?>

    <form class="form form--center" method="post" action="/admin/mfa/verify" novalidate>
        <?= Csrf::field() ?>

        <div class="field">
            <label for="code">Authentication or recovery code</label>
            <input id="code" name="code" type="text" maxlength="16" required autofocus autocomplete="one-time-code">
        </div>

        <button class="btn btn--accent" type="submit">
            <span>Verify</span><?= icon('arrow-right', 'icon') ?>
        </button>
    </form>
</div>
