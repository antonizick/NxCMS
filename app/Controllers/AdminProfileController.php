<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Settings;
use App\Models\SocialLink;
use App\Support\Uploads;

/** Tile 1: profile (headshot/logo/status/bio) + social links. */
final class AdminProfileController extends AdminController
{
    public function edit(): void
    {
        $admin = $this->admin();

        $this->render($admin, Settings::profile());
    }

    public function update(): void
    {
        $admin = $this->admin();
        $profile = Settings::profile();

        if (!$this->csrfOk()) {
            $this->render($admin, $_POST, 'Session expired — please try again.');
            return;
        }

        $statusPhrase = trim((string) ($_POST['status_phrase'] ?? ''));
        $statusDotColor = trim((string) ($_POST['status_dot_color'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));

        $errors = [];
        if (mb_strlen($statusPhrase) > 255) {
            $errors[] = 'Status phrase is too long (max 255 characters).';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $statusDotColor)) {
            $errors[] = 'Status dot color must be a hex color like #2dd4bf.';
        }

        $headshotUrl = $profile['headshot_url'] ?? null;
        $logoUrl = $profile['logo_url'] ?? null;
        $photo3Url = $profile['photo3_url'] ?? null;
        $photo4Url = $profile['photo4_url'] ?? null;

        try {
            $newHeadshot = Uploads::store($_FILES['headshot'] ?? null);
            if ($newHeadshot !== null) {
                Uploads::delete($headshotUrl);
                $headshotUrl = $newHeadshot;
            }
            if (!empty($_POST['remove_headshot'])) {
                Uploads::delete($headshotUrl);
                $headshotUrl = null;
            }

            $newLogo = Uploads::store($_FILES['logo'] ?? null);
            if ($newLogo !== null) {
                Uploads::delete($logoUrl);
                $logoUrl = $newLogo;
            }
            if (!empty($_POST['remove_logo'])) {
                Uploads::delete($logoUrl);
                $logoUrl = null;
            }

            $newPhoto3 = Uploads::store($_FILES['photo3'] ?? null);
            if ($newPhoto3 !== null) {
                Uploads::delete($photo3Url);
                $photo3Url = $newPhoto3;
            }
            if (!empty($_POST['remove_photo3'])) {
                Uploads::delete($photo3Url);
                $photo3Url = null;
            }

            $newPhoto4 = Uploads::store($_FILES['photo4'] ?? null);
            if ($newPhoto4 !== null) {
                Uploads::delete($photo4Url);
                $photo4Url = $newPhoto4;
            }
            if (!empty($_POST['remove_photo4'])) {
                Uploads::delete($photo4Url);
                $photo4Url = null;
            }
        } catch (\RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        if ($errors) {
            $this->render($admin, [
                'status_phrase' => $statusPhrase,
                'status_dot_color' => $statusDotColor,
                'bio' => $bio,
                'headshot_url' => $headshotUrl,
                'logo_url' => $logoUrl,
                'photo3_url' => $photo3Url,
                'photo4_url' => $photo4Url,
            ], implode(' ', $errors));
            return;
        }

        Settings::updateProfile([
            'status_phrase' => $statusPhrase,
            'status_dot_color' => strtolower($statusDotColor),
            'bio' => $bio,
            'headshot_url' => $headshotUrl,
            'logo_url' => $logoUrl,
            'photo3_url' => $photo3Url,
            'photo4_url' => $photo4Url,
        ]);
        ActivityLog::record((int) $admin['id'], 'profile_updated', null);

        $this->render($admin, Settings::profile(), null, 'Saved.');
    }

    // ── Social links ─────────────────────────────────────────────────────

    public function createSocialLink(): void
    {
        $admin = $this->admin();

        if (!$this->csrfOk()) {
            $this->redirect('/admin/profile');
            return;
        }

        $this->saveSocialLink($admin, null);
        $this->redirect('/admin/profile');
    }

    public function updateSocialLink(string $id): void
    {
        $admin = $this->admin();

        if (!$this->csrfOk()) {
            $this->redirect('/admin/profile');
            return;
        }

        $this->saveSocialLink($admin, (int) $id);
        $this->redirect('/admin/profile');
    }

    public function deleteSocialLink(string $id): void
    {
        $admin = $this->admin();

        if ($this->csrfOk()) {
            SocialLink::delete((int) $id);
            ActivityLog::record((int) $admin['id'], 'social_link_deleted', (string) $id);
        }
        $this->redirect('/admin/profile');
    }

    /** @param array<string, mixed> $admin */
    private function saveSocialLink(array $admin, ?int $id): void
    {
        $platform = (string) ($_POST['platform'] ?? '');
        if (!in_array($platform, SocialLink::PLATFORMS, true)) {
            return;
        }

        $label = trim((string) ($_POST['label'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));

        if (mb_strlen($url) > 512 || mb_strlen($label) > 64) {
            return;
        }

        if ($id === null) {
            SocialLink::create($platform, $label !== '' ? $label : null, $url, $sortOrder);
            ActivityLog::record((int) $admin['id'], 'social_link_created', $platform);
        } else {
            SocialLink::update($id, $platform, $label !== '' ? $label : null, $url, $sortOrder);
            ActivityLog::record((int) $admin['id'], 'social_link_updated', $platform);
        }
    }

    /** @param array<string, mixed> $admin @param array<string, mixed> $profile */
    private function render(array $admin, array $profile, ?string $error = null, ?string $saved = null): void
    {
        View::render('admin/profile', [
            'pageTitle' => 'Profile — Admin',
            'admin' => $admin,
            'profile' => $profile,
            'socialLinks' => Settings::allSocialLinks(),
            'platforms' => SocialLink::PLATFORMS,
            'error' => $error,
            'saved' => $saved,
        ], 'layouts/admin');
    }
}
