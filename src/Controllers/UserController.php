<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;

final class UserController extends Controller
{
    public function index(array $params = []): void
    {
        $this->view('users/index', [
            'users' => User::allWithRole(),
        ]);
    }

    public function create(array $params = []): void
    {
        $this->view('users/create', [
            'roles' => Role::all(),
        ]);
    }

    public function store(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/users/create');
        }

        $input = $_POST;
        $errors = $this->validate($input);

        if (strlen((string) ($input['password'] ?? '')) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect('/users/create');
        }

        $newId = User::create([
            'username'      => trim($input['username']),
            'email'         => trim($input['email']),
            'full_name'     => trim($input['full_name']),
            'role_id'       => (int) $input['role_id'],
            'password_hash' => password_hash((string) $input['password'], PASSWORD_BCRYPT),
            'is_active'     => 1,
        ]);

        ActivityLog::record(Auth::id(), 'user.create', 'user', $newId);
        flash('success', 'User created successfully.');
        $this->redirect('/users');
    }

    public function edit(array $params): void
    {
        $user = User::findById((int) $params['id']);
        if (!$user) {
            $this->notFound();
        }

        $this->view('users/edit', [
            'targetUser' => $user,
            'roles'      => Role::all(),
        ]);
    }

    public function update(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect("/users/{$id}/edit");
        }

        $user = User::findById($id);
        if (!$user) {
            $this->notFound();
        }

        $input = $_POST;
        $errors = $this->validate($input, $id);

        $newPassword = (string) ($input['password'] ?? '');
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        // Guard: don't let the last active Admin be demoted away from Admin.
        if (empty($errors) && (int) $user['role_id'] !== (int) $input['role_id']) {
            $wasAdmin = Role::findById((int) $user['role_id'])['name'] === 'Admin';
            if ($wasAdmin && $user['is_active'] && User::countActiveAdmins() <= 1) {
                $errors['role_id'] = 'Cannot change the role of the last active Admin.';
            }
        }

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect("/users/{$id}/edit");
        }

        User::updateProfile($id, [
            'full_name' => trim($input['full_name']),
            'email'     => trim($input['email']),
            'role_id'   => (int) $input['role_id'],
        ]);

        if ($newPassword !== '') {
            User::updatePassword($id, password_hash($newPassword, PASSWORD_BCRYPT));
        }

        ActivityLog::record(Auth::id(), 'user.update', 'user', $id);
        flash('success', 'User updated successfully.');
        $this->redirect('/users');
    }

    public function toggleActive(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/users');
        }

        $user = User::findById($id);
        if (!$user) {
            $this->notFound();
        }

        if ($id === Auth::id()) {
            flash('error', 'You cannot deactivate your own account.');
            $this->redirect('/users');
        }

        if ((bool) $user['is_active']) {
            $role = Role::findById((int) $user['role_id']);
            if ($role && $role['name'] === 'Admin' && User::countActiveAdmins() <= 1) {
                flash('error', 'Cannot deactivate the last active Admin.');
                $this->redirect('/users');
            }
        }

        User::setActive($id, !$user['is_active']);
        ActivityLog::record(Auth::id(), $user['is_active'] ? 'user.deactivate' : 'user.activate', 'user', $id);
        flash('success', $user['is_active'] ? 'User deactivated.' : 'User activated.');
        $this->redirect('/users');
    }

    private function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];

        $username = trim((string) ($input['username'] ?? ''));
        if ($excludeId === null) {
            if ($username === '') {
                $errors['username'] = 'Username is required.';
            } elseif (User::usernameExists($username)) {
                $errors['username'] = 'This username is already taken.';
            }
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required.';
        } elseif (User::emailExists($email, $excludeId)) {
            $errors['email'] = 'This email is already in use.';
        }

        if (trim((string) ($input['full_name'] ?? '')) === '') {
            $errors['full_name'] = 'Full name is required.';
        }

        if (empty($input['role_id']) || !Role::findById((int) $input['role_id'])) {
            $errors['role_id'] = 'Please select a valid role.';
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
