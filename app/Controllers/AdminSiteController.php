<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ActivityLog;
use App\Models\DemoContent;
use App\Models\Settings;

/** Site settings (Circles 1-4, Tile 7 Recon, Tile 9 Contact copy) — one record, edited in place. */
final class AdminSiteController extends AdminController
{
    public function edit(): void
    {
        $admin = $this->admin();

        $this->render($admin, Settings::site());
    }

    public function update(): void
    {
        $admin = $this->admin();

        if (!$this->csrfOk()) {
            $this->render($admin, $_POST, 'Session expired — please try again.');
            return;
        }

        $fields = [
            'page_title' => trim((string) ($_POST['page_title'] ?? '')),
            'initials' => trim((string) ($_POST['initials'] ?? '')),
            'display_name' => trim((string) ($_POST['display_name'] ?? '')),
            'copyright_year' => trim((string) ($_POST['copyright_year'] ?? '')),
            'copyright_text' => trim((string) ($_POST['copyright_text'] ?? '')),
            'footer_text' => trim((string) ($_POST['footer_text'] ?? '')),
            'copy_link_text' => trim((string) ($_POST['copy_link_text'] ?? '')),
            'copy_link_url' => trim((string) ($_POST['copy_link_url'] ?? '')),
            'theme_default' => ($_POST['theme_default'] ?? 'dark') === 'light' ? 'light' : 'dark',
            'recon_location_label' => trim((string) ($_POST['recon_location_label'] ?? '')),
            'recon_lat' => trim((string) ($_POST['recon_lat'] ?? '')),
            'recon_lng' => trim((string) ($_POST['recon_lng'] ?? '')),
            'recon_timezone' => trim((string) ($_POST['recon_timezone'] ?? '')),
            'contact_main_title' => trim((string) ($_POST['contact_main_title'] ?? '')),
            'contact_sub_title' => trim((string) ($_POST['contact_sub_title'] ?? '')),
            'contact_button_text' => trim((string) ($_POST['contact_button_text'] ?? '')),
        ];

        $errors = [];
        if ($fields['page_title'] === '' || mb_strlen($fields['page_title']) > 255) {
            $errors[] = 'Page title is required (max 255 characters).';
        }
        if ($fields['initials'] === '' || mb_strlen($fields['initials']) > 8) {
            $errors[] = 'Initials are required (max 8 characters).';
        }
        if ($fields['display_name'] === '' || mb_strlen($fields['display_name']) > 128) {
            $errors[] = 'Display name is required (max 128 characters).';
        }
        if ($fields['recon_lat'] !== '' && !is_numeric($fields['recon_lat'])) {
            $errors[] = 'Recon latitude must be a number.';
        }
        if ($fields['recon_lng'] !== '' && !is_numeric($fields['recon_lng'])) {
            $errors[] = 'Recon longitude must be a number.';
        }

        if ($errors) {
            $this->render($admin, $fields, implode(' ', $errors));
            return;
        }

        Settings::updateSite($fields);
        ActivityLog::record((int) $admin['id'], 'settings_updated', null);

        $this->render($admin, Settings::site(), null, 'Saved.');
    }

    /**
     * Removes the sample content a fresh install ships with. Irreversible, so
     * it is a deliberate POST of its own rather than a checkbox on the save
     * above -- and it only ever touches rows still flagged is_demo (see
     * DemoContent), never anything the admin has edited since.
     */
    public function deleteDemo(): void
    {
        $admin = $this->admin();

        if (!$this->csrfOk()) {
            $this->render($admin, Settings::site(), 'Session expired — please try again.');
            return;
        }

        $removed = DemoContent::purge();
        ActivityLog::record((int) $admin['id'], 'demo_content_deleted', $removed . ' rows');

        $this->render($admin, Settings::site(), null, "Demo content removed ({$removed} items).");
    }

    /** @param array<string, mixed> $admin @param array<string, mixed> $fields */
    private function render(array $admin, array $fields, ?string $error = null, ?string $saved = null): void
    {
        View::render('admin/settings', [
            'pageTitle' => 'Site settings — Admin',
            'admin' => $admin,
            'fields' => $fields,
            'error' => $error,
            'saved' => $saved,
            'demoCounts' => DemoContent::counts(),
        ], 'layouts/admin');
    }
}
