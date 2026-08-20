<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Settings;

/** Theme colors — dark + light palettes, one record shared with site_settings, edited in place. */
final class AdminThemeController extends AdminController
{
    private const HEX = '/^#[0-9a-f]{6}$/i';

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

        $fields = [];
        $errors = [];
        foreach (Settings::THEME_COLOR_KEYS as $key) {
            $value = trim((string) ($_POST[$key] ?? ''));
            if (!preg_match(self::HEX, $value)) {
                $errors[] = "$key must be a hex color like #22d3ee.";
                $value = (string) (Settings::site()[$key] ?? '#000000');
            }
            $fields[$key] = strtolower($value);
        }

        if ($errors) {
            $this->render($admin, $fields, implode(' ', $errors));
            return;
        }

        Settings::updateThemeColors($fields);
        ActivityLog::record((int) $admin['id'], 'theme_colors_updated', null);

        $this->render($admin, Settings::site(), null, 'Saved.');
    }

    /** @param array<string, mixed> $admin @param array<string, mixed> $fields */
    private function render(array $admin, array $fields, ?string $error = null, ?string $saved = null): void
    {
        View::render('admin/theme', [
            'pageTitle' => 'Theme colors — Admin',
            'admin' => $admin,
            'fields' => $fields,
            'error' => $error,
            'saved' => $saved,
        ], 'layouts/admin');
    }
}
