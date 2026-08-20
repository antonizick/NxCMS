<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Skill;
use App\Support\Icons;

/** Tile 3: Toolbox. */
final class AdminSkillController extends AdminController
{
    public function index(): void
    {
        $admin = $this->admin();

        View::render('admin/skills/index', [
            'pageTitle' => 'Toolbox — Admin',
            'admin' => $admin,
            'skills' => Skill::all(),
        ], 'layouts/admin');
    }

    public function new(): void
    {
        $admin = $this->admin();

        $this->form($admin, null, ['name' => '', 'icon_key' => Icons::TOOLBOX_KEYS[0], 'sort_order' => 0]);
    }

    public function edit(string $id): void
    {
        $admin = $this->admin();

        $skill = Skill::find((int) $id);
        if ($skill === null) {
            $this->redirect('/admin/skills');
            return;
        }

        $this->form($admin, (int) $id, $skill);
    }

    public function create(): void
    {
        $admin = $this->admin();
        [$name, $icon, $sort, $error] = $this->validated();

        if (!$this->csrfOk()) {
            $this->form($admin, null, $_POST, 'Session expired — please try again.');
            return;
        }
        if ($error !== null) {
            $this->form($admin, null, $_POST, $error);
            return;
        }

        Skill::create($name, $icon, $sort);
        ActivityLog::record((int) $admin['id'], 'skill_created', $name);
        $this->redirect('/admin/skills');
    }

    public function update(string $id): void
    {
        $admin = $this->admin();
        $skillId = (int) $id;
        [$name, $icon, $sort, $error] = $this->validated();

        if (!$this->csrfOk()) {
            $this->form($admin, $skillId, $_POST, 'Session expired — please try again.');
            return;
        }
        if ($error !== null) {
            $this->form($admin, $skillId, $_POST, $error);
            return;
        }

        Skill::update($skillId, $name, $icon, $sort);
        ActivityLog::record((int) $admin['id'], 'skill_updated', $name);
        $this->redirect('/admin/skills');
    }

    public function delete(string $id): void
    {
        $admin = $this->admin();

        if ($this->csrfOk()) {
            $skill = Skill::find((int) $id);
            Skill::delete((int) $id);
            ActivityLog::record((int) $admin['id'], 'skill_deleted', $skill['name'] ?? (string) $id);
        }
        $this->redirect('/admin/skills');
    }

    /** @return array{0: string, 1: string, 2: int, 3: string|null} */
    private function validated(): array
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $icon = (string) ($_POST['icon_key'] ?? '');
        $sort = max(0, (int) ($_POST['sort_order'] ?? 0));

        if ($name === '' || mb_strlen($name) > 64) {
            return [$name, $icon, $sort, 'Name is required (max 64 characters).'];
        }
        if (!in_array($icon, Icons::TOOLBOX_KEYS, true)) {
            return [$name, $icon, $sort, 'Choose a valid icon.'];
        }

        return [$name, $icon, $sort, null];
    }

    /** @param array<string, mixed> $admin @param array<string, mixed> $fields */
    private function form(array $admin, ?int $id, array $fields, ?string $error = null): void
    {
        View::render('admin/skills/form', [
            'pageTitle' => ($id === null ? 'New skill' : 'Edit skill') . ' — Admin',
            'admin' => $admin,
            'id' => $id,
            'fields' => $fields,
            'error' => $error,
            'iconKeys' => Icons::TOOLBOX_KEYS,
        ], 'layouts/admin');
    }
}
