<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\SalesTeam;

final class TeamController extends Controller
{
    public function index(array $params = []): void
    {
        $teams = SalesTeam::all();
        foreach ($teams as &$team) {
            $team['member_count'] = SalesTeam::memberCount((int) $team['id']);
        }
        unset($team);

        $this->view('teams/index', ['teams' => $teams]);
    }

    public function create(array $params = []): void
    {
        $this->view('teams/create');
    }

    public function store(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/teams/create');
        }

        $input = $_POST;
        $errors = $this->validate($input);

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect('/teams/create');
        }

        $id = SalesTeam::create(trim($input['name']), trim((string) ($input['description'] ?? '')) ?: null);

        ActivityLog::record(Auth::id(), 'team.create', 'sales_team', $id);
        flash('success', 'Sales team created successfully.');
        $this->redirect('/teams');
    }

    public function edit(array $params): void
    {
        $team = SalesTeam::findById((int) $params['id']);
        if (!$team) {
            $this->notFound();
        }

        $this->view('teams/edit', ['team' => $team]);
    }

    public function update(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect("/teams/{$id}/edit");
        }

        $team = SalesTeam::findById($id);
        if (!$team) {
            $this->notFound();
        }

        $input = $_POST;
        $errors = $this->validate($input, $id);

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect("/teams/{$id}/edit");
        }

        SalesTeam::update($id, trim($input['name']), trim((string) ($input['description'] ?? '')) ?: null);

        ActivityLog::record(Auth::id(), 'team.update', 'sales_team', $id);
        flash('success', 'Sales team updated successfully.');
        $this->redirect('/teams');
    }

    public function toggleActive(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/teams');
        }

        $team = SalesTeam::findById($id);
        if (!$team) {
            $this->notFound();
        }

        SalesTeam::setActive($id, !$team['is_active']);
        ActivityLog::record(Auth::id(), $team['is_active'] ? 'team.deactivate' : 'team.activate', 'sales_team', $id);
        flash('success', $team['is_active'] ? 'Team deactivated.' : 'Team activated.');
        $this->redirect('/teams');
    }

    private function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Team name is required.';
        } elseif (SalesTeam::nameExists($name, $excludeId)) {
            $errors['name'] = 'A team with this name already exists.';
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
