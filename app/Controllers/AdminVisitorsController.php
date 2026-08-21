<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\PageView;

/** Per-visit public visitor log: IP, page/article accessed, device, timestamp. */
final class AdminVisitorsController extends AdminController
{
    private const PER_PAGE = 30;

    public function index(): void
    {
        $admin = $this->admin();

        $device = (string) ($_GET['device'] ?? 'all');
        if ($device !== 'all' && !in_array($device, PageView::DEVICES, true)) {
            $device = 'all';
        }

        $sort = (string) ($_GET['sort'] ?? 'newest');
        if (!array_key_exists($sort, PageView::SORTS)) {
            $sort = 'newest';
        }

        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);
        $hideAdmin = ($_GET['hide_admin_set'] ?? '') === '1'
            ? ($_GET['hide_admin'] ?? '') === '1'
            : true;

        $total = PageView::searchAdminCount($device, $q, $hideAdmin);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($lastPage, (int) ($_GET['page'] ?? 1)));

        $rows = PageView::searchAdmin($device, $q, $sort, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $hideAdmin);

        View::render('admin/visitors/index', [
            'pageTitle' => 'Visitors — Admin',
            'admin' => $admin,
            'rows' => $rows,
            'device' => $device,
            'sort' => $sort,
            'q' => $q,
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
            'hideAdmin' => $hideAdmin,
        ], 'layouts/admin');
    }

    public function unique(): void
    {
        $admin = $this->admin();

        $device = (string) ($_GET['device'] ?? 'all');
        if ($device !== 'all' && !in_array($device, PageView::DEVICES, true)) {
            $device = 'all';
        }

        $sort = (string) ($_GET['sort'] ?? 'most');
        if (!array_key_exists($sort, PageView::UNIQUE_SORTS)) {
            $sort = 'most';
        }

        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);
        $hideAdmin = ($_GET['hide_admin_set'] ?? '') === '1'
            ? ($_GET['hide_admin'] ?? '') === '1'
            : true;

        $total = PageView::searchAdminUniqueCount($device, $q, $hideAdmin);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($lastPage, (int) ($_GET['page'] ?? 1)));

        $rows = PageView::searchAdminUnique($device, $q, $sort, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $hideAdmin);

        View::render('admin/visitors/unique', [
            'pageTitle' => 'Unique visitors — Admin',
            'admin' => $admin,
            'rows' => $rows,
            'device' => $device,
            'sort' => $sort,
            'q' => $q,
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
            'hideAdmin' => $hideAdmin,
        ], 'layouts/admin');
    }
}
