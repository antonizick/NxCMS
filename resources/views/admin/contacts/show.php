<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;

$s = $data['submission'];
?>
<div class="tile page page--narrow">
    <p class="page-back"><a href="/admin/contacts"><?= icon('arrow-right', 'icon icon--flip') ?><span>Back to messages</span></a></p>
    <h1 class="page-title"><?= e($s['name']) ?></h1>
    <p class="page-sub">
        <a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a>
        &middot; <?= e(fmt_date($s['created_at'], 'j M Y, g:i a')) ?>
        &middot; <?= e($s['ip_address']) ?>
    </p>

    <div class="tile" style="margin-top:1.25rem;white-space:pre-wrap;line-height:1.6"><?= e($s['message']) ?></div>

    <div class="crud-actions" style="margin-top:1.5rem">
        <a class="btn btn--accent" href="mailto:<?= e($s['email']) ?>"><span>Reply by email</span></a>
        <form method="post" action="/admin/contacts/<?= (int) $s['id'] ?>/delete" data-confirm="Delete this message?">
            <?= Csrf::field() ?>
            <button class="btn btn--ghost" type="submit"><span>Delete</span></button>
        </form>
    </div>
</div>
