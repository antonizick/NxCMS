<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;
use App\Support\Icons;

$p = $data['profile'];
$links = $data['socialLinks'];
$platforms = $data['platforms'];
$platformLabels = [
    'x' => 'X', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok',
    'threads' => 'Threads', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'github' => 'GitHub',
    'discord' => 'Discord', 'twitch' => 'Twitch', 'reddit' => 'Reddit', 'mastodon' => 'Mastodon',
    'bluesky' => 'Bluesky', 'dribbble' => 'Dribbble', 'other' => 'Other',
];

$iconPreview = static function (array $platforms, string $selected): string {
    $out = '<span class="social-icon-preview">';
    foreach ($platforms as $plat) {
        $key = $plat === 'other' ? 'globe' : $plat;
        $cls = 'social-icon-swatch' . ($plat === $selected ? ' is-active' : '');
        $out .= '<span class="' . $cls . '" data-platform="' . e($plat) . '">'
            . icon(Icons::has($key) ? $key : 'globe', 'icon') . '</span>';
    }
    return $out . '</span>';
};
?>
<div class="tile page page--wide">
    <h1 class="page-title">Profile</h1>
    <p class="page-sub">Tile 1 &mdash; headshot/logo, status, bio, and social icons.</p>

    <?php if (!empty($data['error'])): ?>
        <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
    <?php endif; ?>
    <?php if (!empty($data['saved'])): ?>
        <p class="notice notice--ok" role="status"><?= e($data['saved']) ?></p>
    <?php endif; ?>

    <form class="form" method="post" action="/admin/profile" enctype="multipart/form-data" novalidate>
        <?= Csrf::field() ?>

        <div class="field-grid">
            <div class="field">
                <label>Headshot</label>
                <?php if (!empty($p['headshot_url'])): ?>
                    <div class="upload-preview"><img src="<?= e($p['headshot_url']) ?>" alt=""></div>
                    <label class="checkbox"><input type="checkbox" name="remove_headshot" value="1"> Remove current headshot</label>
                <?php endif; ?>
                <input type="file" name="headshot" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="field">
                <label>Logo</label>
                <?php if (!empty($p['logo_url'])): ?>
                    <div class="upload-preview"><img src="<?= e($p['logo_url']) ?>" alt=""></div>
                    <label class="checkbox"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
                <?php endif; ?>
                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="field">
                <label>Extra photo 3</label>
                <?php if (!empty($p['photo3_url'])): ?>
                    <div class="upload-preview"><img src="<?= e($p['photo3_url']) ?>" alt=""></div>
                    <label class="checkbox"><input type="checkbox" name="remove_photo3" value="1"> Remove current photo</label>
                <?php endif; ?>
                <input type="file" name="photo3" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="field">
                <label>Extra photo 4</label>
                <?php if (!empty($p['photo4_url'])): ?>
                    <div class="upload-preview"><img src="<?= e($p['photo4_url']) ?>" alt=""></div>
                    <label class="checkbox"><input type="checkbox" name="remove_photo4" value="1"> Remove current photo</label>
                <?php endif; ?>
                <input type="file" name="photo4" accept="image/jpeg,image/png,image/webp">
            </div>
        </div>
        <p class="page-sub">JavaScript oscillates between whichever of these four images are present, roughly every 20 seconds; a missing image is simply skipped.</p>

        <div class="field-grid">
            <div class="field">
                <label for="status_phrase">Status phrase</label>
                <input id="status_phrase" name="status_phrase" type="text" maxlength="255" value="<?= e($p['status_phrase'] ?? '') ?>">
            </div>
            <div class="field field--color">
                <label for="status_dot_color">Status dot color</label>
                <div class="color-input">
                    <input type="color" id="status_dot_color" name="status_dot_color" value="<?= e($p['status_dot_color'] ?? '#2dd4bf') ?>">
                    <span class="color-hex"><?= e($p['status_dot_color'] ?? '#2dd4bf') ?></span>
                </div>
            </div>
        </div>

        <div class="field">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" rows="6"><?= e($p['bio'] ?? '') ?></textarea>
        </div>

        <button class="btn btn--accent" type="submit"><span>Save profile</span></button>
    </form>

    <h2 class="page-subtitle">Social links</h2>
    <p class="page-sub">A blank URL hides the icon on the public page.</p>

    <div class="social-links-list">
        <?php foreach ($links as $link): ?>
            <form class="social-link-row" method="post" action="/admin/profile/social-links/<?= (int) $link['id'] ?>">
                <?= Csrf::field() ?>
                <?= $iconPreview($platforms, (string) $link['platform']) ?>
                <select name="platform" class="social-platform-select">
                    <?php foreach ($platforms as $platform): ?>
                        <option value="<?= e($platform) ?>" <?= $platform === $link['platform'] ? 'selected' : '' ?>><?= e($platformLabels[$platform] ?? $platform) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="label" placeholder="Label (for 'Other')" maxlength="64" value="<?= e($link['label'] ?? '') ?>">
                <input type="url" name="url" placeholder="https://&hellip;" maxlength="512" value="<?= e($link['url']) ?>" class="social-link-url">
                <input type="number" name="sort_order" min="0" value="<?= (int) $link['sort_order'] ?>" class="social-link-sort">
                <button class="btn btn--ghost btn--small" type="submit"><span>Save</span></button>
            </form>
            <form method="post" action="/admin/profile/social-links/<?= (int) $link['id'] ?>/delete" class="social-link-delete"
                  data-confirm="Remove this social link?">
                <?= Csrf::field() ?>
                <button class="btn btn--ghost btn--small" type="submit"><span>Remove</span></button>
            </form>
        <?php endforeach; ?>
    </div>

    <form class="social-link-row social-link-row--new" method="post" action="/admin/profile/social-links">
        <?= Csrf::field() ?>
        <?= $iconPreview($platforms, $platforms[0] ?? '') ?>
        <select name="platform" class="social-platform-select">
            <?php foreach ($platforms as $platform): ?>
                <option value="<?= e($platform) ?>"><?= e($platformLabels[$platform] ?? $platform) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="label" placeholder="Label (for 'Other')" maxlength="64">
        <input type="url" name="url" placeholder="https://&hellip;" maxlength="512" class="social-link-url">
        <input type="number" name="sort_order" min="0" value="0" class="social-link-sort">
        <button class="btn btn--accent btn--small" type="submit"><span>Add link</span></button>
    </form>
</div>
