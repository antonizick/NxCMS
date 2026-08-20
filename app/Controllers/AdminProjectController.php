<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Project;

/** Tile 2: Latest Projects. */
final class AdminProjectController extends AdminController
{
    public function index(): void
    {
        $admin = $this->admin();

        View::render('admin/projects/index', [
            'pageTitle' => 'Projects — Admin',
            'admin' => $admin,
            'projects' => Project::all(),
        ], 'layouts/admin');
    }

    public function new(): void
    {
        $admin = $this->admin();

        $this->form($admin, null, [
            'title' => '', 'description' => '', 'github_url' => '', 'external_url' => '',
            'sort_order' => 0, 'published' => 1,
        ]);
    }

    public function edit(string $id): void
    {
        $admin = $this->admin();

        $project = Project::find((int) $id);
        if ($project === null) {
            $this->redirect('/admin/projects');
            return;
        }

        $this->form($admin, (int) $id, $project);
    }

    public function create(): void
    {
        $admin = $this->admin();
        [$fields, $error] = $this->validated();

        if (!$this->csrfOk()) {
            $this->form($admin, null, $_POST, 'Session expired — please try again.');
            return;
        }
        if ($error !== null) {
            $this->form($admin, null, $_POST, $error);
            return;
        }

        Project::create($fields);
        ActivityLog::record((int) $admin['id'], 'project_created', $fields['title']);
        $this->redirect('/admin/projects');
    }

    public function update(string $id): void
    {
        $admin = $this->admin();
        $projectId = (int) $id;
        [$fields, $error] = $this->validated();

        if (!$this->csrfOk()) {
            $this->form($admin, $projectId, $_POST, 'Session expired — please try again.');
            return;
        }
        if ($error !== null) {
            $this->form($admin, $projectId, $_POST, $error);
            return;
        }

        Project::update($projectId, $fields);
        ActivityLog::record((int) $admin['id'], 'project_updated', $fields['title']);
        $this->redirect('/admin/projects');
    }

    public function delete(string $id): void
    {
        $admin = $this->admin();

        if ($this->csrfOk()) {
            $project = Project::find((int) $id);
            Project::delete((int) $id);
            ActivityLog::record((int) $admin['id'], 'project_deleted', $project['title'] ?? (string) $id);
        }
        $this->redirect('/admin/projects');
    }

    /** @return array{0: array<string, mixed>, 1: string|null} */
    private function validated(): array
    {
        $fields = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'github_url' => trim((string) ($_POST['github_url'] ?? '')),
            'external_url' => trim((string) ($_POST['external_url'] ?? '')),
            'sort_order' => max(0, (int) ($_POST['sort_order'] ?? 0)),
            'published' => !empty($_POST['published']) ? 1 : 0,
        ];

        if ($fields['title'] === '' || mb_strlen($fields['title']) > 128) {
            return [$fields, 'Title is required (max 128 characters).'];
        }
        if ($fields['description'] === '' || mb_strlen($fields['description']) > 512) {
            return [$fields, 'Description is required (max 512 characters).'];
        }

        return [$fields, null];
    }

    /** @param array<string, mixed> $admin @param array<string, mixed> $fields */
    private function form(array $admin, ?int $id, array $fields, ?string $error = null): void
    {
        View::render('admin/projects/form', [
            'pageTitle' => ($id === null ? 'New project' : 'Edit project') . ' — Admin',
            'admin' => $admin,
            'id' => $id,
            'fields' => $fields,
            'error' => $error,
        ], 'layouts/admin');
    }
}
