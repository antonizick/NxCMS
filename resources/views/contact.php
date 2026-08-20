<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;

$site = $data['site'];
$old = $data['old'] ?? [];
$captcha = $data['captcha'] ?? null;
?>
<div class="tile page page--narrow">
    <p class="page-back"><a href="/"><?= icon('arrow-right', 'icon icon--flip') ?><span>Back to the portal</span></a></p>

    <h1 class="page-title"><?= e($site['contact_main_title'] ?: 'Get in touch') ?></h1>
    <?php if (($site['contact_sub_title'] ?? '') !== ''): ?>
        <p class="page-sub"><?= e($site['contact_sub_title']) ?></p>
    <?php endif; ?>

    <?php if (!empty($data['sent'])): ?>
        <p class="notice notice--ok" role="status">Thanks — your message is through. I'll get back to you.</p>
    <?php endif; ?>

    <?php if (!empty($data['errors'])): ?>
        <div class="notice notice--error" role="alert">
            <ul>
                <?php foreach ($data['errors'] as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="form" method="post" action="/contact" novalidate>
        <?= Csrf::field() ?>

        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" maxlength="128" required
                   autocomplete="name" value="<?= e($old['name'] ?? '') ?>">
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="255" required
                   autocomplete="email" value="<?= e($old['email'] ?? '') ?>">
        </div>

        <div class="field">
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="7" maxlength="5000" required><?= e($old['message'] ?? '') ?></textarea>
        </div>

        <!-- Honeypot: hidden from people, irresistible to bots. -->
        <div class="hp" aria-hidden="true">
            <label for="website">Website</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>

        <?php if ($captcha !== null): ?>
            <div class="captcha" data-captcha
                 data-salt="<?= e($captcha['salt']) ?>"
                 data-challenge="<?= e($captcha['challenge']) ?>"
                 data-maxnumber="<?= (int) $captcha['maxnumber'] ?>">
                <input type="hidden" name="captcha_payload" value="<?= e(base64_encode(json_encode($captcha))) ?>">
                <input type="hidden" name="captcha_number" value="">
                <p class="captcha-status" role="status">Verifying you're not a bot&hellip;</p>
            </div>
        <?php endif; ?>

        <button class="btn btn--accent" type="submit"<?= $captcha !== null ? ' disabled data-captcha-submit' : '' ?>>
            <span><?= e($site['contact_button_text'] ?: 'Send message') ?></span><?= icon('arrow-right', 'icon') ?>
        </button>
    </form>
</div>
