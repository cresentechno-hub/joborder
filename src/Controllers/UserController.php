<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Throwable;

final class UserController extends Controller
{
    public function index(array $params = []): void
    {
        $this->view('users/index', [
            'users'   => User::allWithRole(),
            'isAdmin' => (Auth::user()['role_name'] ?? null) === 'Admin',
        ]);
    }

    public function create(array $params = []): void
    {
        $this->view('users/create', [
            'roles'    => Role::all(),
            'branches' => Branch::allActive(),
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
            'branch_id'     => $this->branchIdFromInput($input),
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
            'branches'   => Branch::allActive(),
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
            'branch_id' => $this->branchIdFromInput($input),
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

    /**
     * Hard delete — restricted to Admin regardless of who else holds
     * user.manage (a role could grant that permission without also
     * granting the right to permanently remove accounts).
     */
    public function destroy(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/users');
        }

        if ((Auth::user()['role_name'] ?? null) !== 'Admin') {
            flash('error', 'Only an Admin can delete a user.');
            $this->redirect('/users');
        }

        $user = User::findById($id);
        if (!$user) {
            $this->notFound();
        }

        if ($id === Auth::id()) {
            flash('error', 'You cannot delete your own account.');
            $this->redirect('/users');
        }

        if (User::isInUse($id)) {
            flash('error', "Cannot delete \"{$user['full_name']}\" — they still have job orders, LPR rentals, SMC contracts, or comments on record. Deactivate them instead.");
            $this->redirect('/users');
        }

        try {
            User::delete($id);
        } catch (Throwable $e) {
            flash('error', "Cannot delete \"{$user['full_name']}\" — they're still referenced by other records. Deactivate them instead.");
            $this->redirect('/users');
        }

        ActivityLog::record(Auth::id(), 'user.delete', 'user', $id);
        flash('success', 'User deleted.');
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

        $branchId = $this->branchIdFromInput($input);
        if ($branchId !== null && !Branch::findById($branchId)) {
            $errors['branch_id'] = 'Please select a valid branch.';
        }

        return $errors;
    }

    /** A single branch ID from `branch_id`, or null for an unaffiliated user. */
    private function branchIdFromInput(array $input): ?int
    {
        $raw = trim((string) ($input['branch_id'] ?? ''));
        return $raw !== '' ? (int) $raw : null;
    }

    private function notFound(): never
    {
        http_response_code(404);
        require ROOT_PATH . '/views/errors/404.php';
        exit;
    }
}
