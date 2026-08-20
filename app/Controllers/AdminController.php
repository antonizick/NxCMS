<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Support\Auth;

/**
 * Shared guard for every CMS screen behind /admin (Phase 5+). AuthController
 * itself doesn't extend this — it's the pre-auth entry point (login/MFA).
 */
abstract class AdminController
{
    /** @return array<string, mixed> the logged-in admin row; halts (redirect) if not authenticated. */
    protected function admin(): array
    {
        $admin = Auth::user();
        if ($admin === null) {
            $this->redirect('/admin/login');
            exit;
        }

        return $admin;
    }

    protected function csrfOk(): bool
    {
        return Csrf::check($_POST['_csrf'] ?? null);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path, true, 303);
    }
}
