<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;

final class BranchController extends Controller
{
    public function index(array $params = []): void
    {
        $branches = Branch::all();
        foreach ($branches as &$branch) {
            $branch['member_count'] = Branch::memberCount((int) $branch['id']);
        }
        unset($branch);

        $this->view('branches/index', ['branches' => $branches]);
    }

    public function create(array $params = []): void
    {
        $this->view('branches/create');
    }

    public function store(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/branches/create');
        }

        $input = $_POST;
        $errors = $this->validate($input);

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect('/branches/create');
        }

        $id = Branch::create(trim($input['name']));

        ActivityLog::record(Auth::id(), 'branch.create', 'branch', $id);
        flash('success', 'Branch created successfully.');
        $this->redirect('/branches');
    }

    public function edit(array $params): void
    {
        $branch = Branch::findById((int) $params['id']);
        if (!$branch) {
            $this->notFound();
        }

        $this->view('branches/edit', ['branch' => $branch]);
    }

    public function update(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect("/branches/{$id}/edit");
        }

        $branch = Branch::findById($id);
        if (!$branch) {
            $this->notFound();
        }

        $input = $_POST;
        $errors = $this->validate($input, $id);

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect("/branches/{$id}/edit");
        }

        Branch::update($id, trim($input['name']));

        ActivityLog::record(Auth::id(), 'branch.update', 'branch', $id);
        flash('success', 'Branch updated successfully.');
        $this->redirect('/branches');
    }

    public function toggleActive(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/branches');
        }

        $branch = Branch::findById($id);
        if (!$branch) {
            $this->notFound();
        }

        Branch::setActive($id, !$branch['is_active']);
        ActivityLog::record(Auth::id(), $branch['is_active'] ? 'branch.deactivate' : 'branch.activate', 'branch', $id);
        flash('success', $branch['is_active'] ? 'Branch deactivated.' : 'Branch activated.');
        $this->redirect('/branches');
    }

    private function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Branch name is required.';
        } elseif (Branch::nameExists($name, $excludeId)) {
            $errors['name'] = 'A branch with this name already exists.';
        }

        return $errors;
    }

    private function notFound(): never
    {
        http_response_code(404);
        require ROOT_PATH . '/views/errors/404.php';
        exit;
    }
}
