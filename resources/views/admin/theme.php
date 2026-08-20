<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;

$f = $data['fields'];

$row = static function (string $key, string $label) use ($f): string {
    $value = e((string) ($f[$key] ?? '#000000'));
    return <<<HTML
        <div class="field field--color">
            <label for="{$key}">{$label}</label>
            <div class="color-input">
                <input type="color" id="{$key}" name="{$key}" value="{$value}">
                <span class="color-hex">{$value}</span>
            </div>
        </div>
        HTML;
};
?>
<div class="tile page page--narrow">
    <h1 class="page-title">Theme colors</h1>
    <p class="page-sub">Dark and light palettes for the public site (and this CMS, since both share the same stylesheet).</p>

    <?php if (!empty($data['error'])): ?>
        <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
    <?php endif; ?>
    <?php if (!empty($data['saved'])): ?>
        <p class="notice notice--ok" role="status"><?= e($data['saved']) ?></p>
    <?php endif; ?>

    <form class="form" method="post" action="/admin/theme" novalidate>
        <?= Csrf::field() ?>

        <fieldset class="field-group">
            <legend>Dark theme</legend>
            <div class="field-grid">
                <?= $row('theme_dark_bg', 'Background') ?>
                <?= $row('theme_dark_card', 'Card') ?>
                <?= $row('theme_dark_text', 'Text') ?>
                <?= $row('theme_dark_accent', 'Accent') ?>
                <?= $row('theme_dark_accent_2', 'Accent (secondary)') ?>
                <?= $row('theme_dark_orange', 'Orange') ?>
                <?= $row('theme_dark_news_bg', 'News (background)') ?>
                <?= $row('theme_dark_contact_bg', 'Contact (background)') ?>
            </div>
        </fieldset>

        <fieldset class="field-group">
            <legend>Light theme</legend>
            <div class="field-grid">
                <?= $row('theme_light_bg', 'Background') ?>
                <?= $row('theme_light_card', 'Card') ?>
                <?= $row('theme_light_text', 'Text') ?>
                <?= $row('theme_light_accent', 'Accent') ?>
                <?= $row('theme_light_accent_2', 'Accent (secondary)') ?>
                <?= $row('theme_light_orange', 'Orange') ?>
                <?= $row('theme_light_news_bg', 'News (background)') ?>
                <?= $row('theme_light_contact_bg', 'Contact (background)') ?>
            </div>
        </fieldset>

        <button class="btn btn--accent" type="submit"><span>Save theme colors</span></button>
    </form>
</div>
