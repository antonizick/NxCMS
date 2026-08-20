<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;
?>
<div class="tile page page--narrow page--center">
    <h1 class="page-title">Set up two-factor authentication</h1>
    <p class="page-sub">Scan this with an authenticator app (1Password, Authy, Google Authenticator), then enter the 6-digit code it shows.</p>

    <?php if (!empty($data['error'])): ?>
        <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
    <?php endif; ?>

    <div class="mfa-qr">
        <img src="<?= e($data['qr']) ?>" width="220" height="220" alt="QR code for authenticator app enrollment">
    </div>

    <p class="mfa-manual">
        Can't scan it? Enter this key manually:<br>
        <code><?= e($data['manualKey']) ?></code>
    </p>

    <form class="form form--center" method="post" action="/admin/mfa/setup" novalidate>
        <?= Csrf::field() ?>

        <div class="field">
            <label for="code">6-digit code</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6"
                   required autofocus autocomplete="one-time-code">
        </div>

        <button class="btn btn--accent" type="submit">
            <span>Confirm and enable</span><?= icon('arrow-right', 'icon') ?>
        </button>
    </form>
</div>
