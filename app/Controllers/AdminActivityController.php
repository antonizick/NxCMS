<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ActivityLog;

/** Admin logins (success/failed) + every administrative action, searchable/filterable/sortable. */
final class AdminActivityController extends AdminController
{
    private const PER_PAGE = 30;

    public function index(): void
    {
        $admin = $this->admin();

        $actions = ActivityLog::distinctActions();

        $action = (string) ($_GET['action'] ?? 'all');
        if ($action !== 'all' && !in_array($action, $actions, true)) {
            $action = 'all';
        }

        $outcome = (string) ($_GET['outcome'] ?? 'all');
        if (!in_array($outcome, ['all', 'success', 'failed'], true)) {
            $outcome = 'all';
        }

        $sort = (string) ($_GET['sort'] ?? 'newest');
        if (!array_key_exists($sort, ActivityLog::SORTS)) {
            $sort = 'newest';
        }

        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);

        $total = ActivityLog::searchAdminCount($action, $outcome, $q);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($lastPage, (int) ($_GET['page'] ?? 1)));

        $rows = ActivityLog::searchAdmin($action, $outcome, $q, $sort, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        View::render('admin/activity/index', [
            'pageTitle' => 'Activity log — Admin',
            'admin' => $admin,
            'rows' => $rows,
            'actions' => $actions,
            'action' => $action,
            'outcome' => $outcome,
            'sort' => $sort,
            'q' => $q,
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ], 'layouts/admin');
    }
}
