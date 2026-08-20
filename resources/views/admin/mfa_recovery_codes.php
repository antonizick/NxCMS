<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

$codes = $data['codes'];
?>
<div class="tile page page--narrow">
    <h1 class="page-title">Save your recovery codes</h1>

    <?php if ($codes === null): ?>
        <p class="page-sub">These are only shown once, immediately after enrollment, and this isn't that moment.</p>
        <p class="page-actions"><a class="btn btn--accent" href="/admin"><span>Go to dashboard</span><?= icon('arrow-right', 'icon') ?></a></p>
    <?php else: ?>
        <p class="page-sub">
            Each code works once, if you lose access to your authenticator app. Store them somewhere safe —
            this page will not show them again.
        </p>

        <ul class="recovery-codes">
            <?php foreach ($codes as $code): ?>
                <li><code><?= e($code) ?></code></li>
            <?php endforeach; ?>
        </ul>

        <p class="page-actions"><a class="btn btn--accent" href="/admin"><span>I've saved these — continue</span><?= icon('arrow-right', 'icon') ?></a></p>
    <?php endif; ?>
</div>
