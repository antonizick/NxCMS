<?php
/** @var array<string, mixed> $data */
declare(strict_types=1);

use App\Core\Csrf;

$f = $data['fields'];
?>
<div class="tile page page--wide">
    <h1 class="page-title">Site settings</h1>
    <p class="page-sub">Circles 1&ndash;4, the Recon tile, and the Contact tile copy.</p>

    <?php if (!empty($data['error'])): ?>
        <p class="notice notice--error" role="alert"><?= e($data['error']) ?></p>
    <?php endif; ?>
    <?php if (!empty($data['saved'])): ?>
        <p class="notice notice--ok" role="status"><?= e($data['saved']) ?></p>
    <?php endif; ?>

    <form class="form" method="post" action="/admin/settings" novalidate>
        <?= Csrf::field() ?>

        <fieldset class="field-group">
            <legend>Page &amp; brand</legend>
            <div class="field-grid">
                <div class="field">
                    <label for="page_title">Browser page title</label>
                    <input id="page_title" name="page_title" type="text" maxlength="255" required value="<?= e($f['page_title'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="initials">Initials (upper-left mark)</label>
                    <input id="initials" name="initials" type="text" maxlength="8" required value="<?= e($f['initials'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="display_name">Display name</label>
                    <input id="display_name" name="display_name" type="text" maxlength="128" required value="<?= e($f['display_name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="theme_default">Default theme</label>
                    <select id="theme_default" name="theme_default">
                        <option value="dark" <?= ($f['theme_default'] ?? 'dark') === 'dark' ? 'selected' : '' ?>>Dark</option>
                        <option value="light" <?= ($f['theme_default'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset class="field-group">
            <legend>Footer &amp; copy-link</legend>
            <div class="field-grid">
                <div class="field">
                    <label for="copyright_year">Copyright year</label>
                    <input id="copyright_year" name="copyright_year" type="text" maxlength="4" value="<?= e($f['copyright_year'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="copyright_text">Copyright text (after the year)</label>
                    <input id="copyright_text" name="copyright_text" type="text" maxlength="255" value="<?= e($f['copyright_text'] ?? '') ?>">
                </div>
                <div class="field field--wide">
                    <label for="footer_text">Footnote</label>
                    <input id="footer_text" name="footer_text" type="text" maxlength="255" value="<?= e($f['footer_text'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="copy_link_text">Copy-link button text</label>
                    <input id="copy_link_text" name="copy_link_text" type="text" maxlength="255" value="<?= e($f['copy_link_text'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="copy_link_url">Copy-link URL</label>
                    <input id="copy_link_url" name="copy_link_url" type="url" maxlength="512" value="<?= e($f['copy_link_url'] ?? '') ?>">
                </div>
            </div>
        </fieldset>

        <fieldset class="field-group">
            <legend>Tile 7 &mdash; Recon</legend>
            <div class="field-grid">
                <div class="field field--wide">
                    <label for="recon_location_label">Location label</label>
                    <input id="recon_location_label" name="recon_location_label" type="text" maxlength="255" value="<?= e($f['recon_location_label'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="recon_lat">Latitude</label>
                    <input id="recon_lat" name="recon_lat" type="text" inputmode="decimal" value="<?= e((string) ($f['recon_lat'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label for="recon_lng">Longitude</label>
                    <input id="recon_lng" name="recon_lng" type="text" inputmode="decimal" value="<?= e((string) ($f['recon_lng'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label for="recon_timezone">Timezone (IANA, e.g. America/Chicago)</label>
                    <input id="recon_timezone" name="recon_timezone" type="text" maxlength="64" value="<?= e($f['recon_timezone'] ?? '') ?>">
                </div>
            </div>
        </fieldset>

        <fieldset class="field-group">
            <legend>Tile 9 &mdash; Contact</legend>
            <div class="field-grid">
                <div class="field">
                    <label for="contact_main_title">Main title</label>
                    <input id="contact_main_title" name="contact_main_title" type="text" maxlength="255" value="<?= e($f['contact_main_title'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="contact_sub_title">Sub title</label>
                    <input id="contact_sub_title" name="contact_sub_title" type="text" maxlength="255" value="<?= e($f['contact_sub_title'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="contact_button_text">Button text</label>
                    <input id="contact_button_text" name="contact_button_text" type="text" maxlength="64" value="<?= e($f['contact_button_text'] ?? '') ?>">
                </div>
            </div>
        </fieldset>

        <button class="btn btn--accent" type="submit"><span>Save settings</span></button>
    </form>

<?php
/* Only shown while seeded sample content is actually still present, so the
   panel disappears for good once it has been dealt with — either by pressing
   the button or by editing every seeded item by hand. */
$demo = $data['demoCounts'] ?? [];
$demoRows = (int) ($demo['content_posts'] ?? 0) + (int) ($demo['projects'] ?? 0)
          + (int) ($demo['skills'] ?? 0) + (int) ($demo['social_links'] ?? 0);
$demoSingles = (int) ($demo['profile'] ?? 0) + (int) ($demo['site_settings'] ?? 0);
?>
<?php if ($demoRows + $demoSingles > 0): ?>
    <div class="field-group field-group--danger">
        <h2 class="page-title page-title--sm">Demo content</h2>
        <p class="page-sub">
            This install still has the sample content it shipped with:
            <?= (int) ($demo['content_posts'] ?? 0) ?> posts,
            <?= (int) ($demo['projects'] ?? 0) ?> projects,
            <?= (int) ($demo['skills'] ?? 0) ?> skills,
            <?= (int) ($demo['social_links'] ?? 0) ?> social links<?php if ($demoSingles > 0): ?>,
            plus the sample profile and footer text<?php endif; ?>.
        </p>
        <p class="page-sub">
            Removing it does not touch anything you have edited yourself &mdash; saving
            any item marks it as yours and it will be kept.
        </p>
        <form method="post" action="/admin/settings/delete-demo"
              data-confirm="Delete all remaining demo content? This cannot be undone.">
            <?= Csrf::field() ?>
            <button class="btn btn--danger" type="submit"><span>Delete demo content</span></button>
        </form>
    </div>
<?php endif; ?>
</div>
