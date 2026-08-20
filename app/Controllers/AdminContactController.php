<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ActivityLog;
use App\Models\ContactSubmission;

/** Review screen for the public contact form's submissions (Tile 9). */
final class AdminContactController extends AdminController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $admin = $this->admin();

        $filter = (string) ($_GET['filter'] ?? 'all');
        if (!in_array($filter, ['all', 'unread', 'read'], true)) {
            $filter = 'all';
        }

        $sort = (string) ($_GET['sort'] ?? 'newest');
        if (!array_key_exists($sort, ContactSubmission::SORTS)) {
            $sort = 'newest';
        }

        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);

        $total = ContactSubmission::searchAdminCount($filter, $q);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($lastPage, (int) ($_GET['page'] ?? 1)));

        $submissions = ContactSubmission::searchAdmin($filter, $q, $sort, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        View::render('admin/contacts/index', [
            'pageTitle' => 'Messages — Admin',
            'admin' => $admin,
            'submissions' => $submissions,
            'filter' => $filter,
            'sort' => $sort,
            'q' => $q,
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
            'unread' => ContactSubmission::unreadCount(),
        ], 'layouts/admin');
    }

    public function show(string $id): void
    {
        $admin = $this->admin();

        $submission = ContactSubmission::find((int) $id);
        if ($submission === null) {
            $this->redirect('/admin/contacts');
            return;
        }

        if (!$submission['is_read']) {
            ContactSubmission::markRead((int) $id);
            $submission['is_read'] = 1;
        }

        View::render('admin/contacts/show', [
            'pageTitle' => 'Message from ' . $submission['name'] . ' — Admin',
            'admin' => $admin,
            'submission' => $submission,
        ], 'layouts/admin');
    }

    public function delete(string $id): void
    {
        $admin = $this->admin();

        if ($this->csrfOk()) {
            $submission = ContactSubmission::find((int) $id);
            if ($submission !== null) {
                ContactSubmission::delete((int) $id);
                ActivityLog::record((int) $admin['id'], 'contact_deleted', $submission['email']);
            }
        }
        $this->redirect('/admin/contacts');
    }
}
