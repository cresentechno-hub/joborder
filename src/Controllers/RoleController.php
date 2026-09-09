<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;

final class RoleController extends Controller
{
    public function index(array $params = []): void
    {
        $this->view('roles/index', [
            'roles' => Role::allWithPermissions(),
        ]);
    }

    public function create(array $params = []): void
    {
        $this->view('roles/create');
    }

    public function store(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/roles/create');
        }

        $input = $_POST;
        $errors = $this->validate($input);

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect('/roles/create');
        }

        $id = Role::create(trim($input['name']), trim((string) ($input['description'] ?? '')) ?: null);

        ActivityLog::record(Auth::id(), 'role.create', 'role', $id);
        flash('success', 'Role created. Now set its permissions below.');
        $this->redirect("/roles/{$id}/permissions");
    }

    private function validate(array $input): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Role name is required.';
        } elseif (Role::nameExists($name)) {
            $errors['name'] = 'A role with this name already exists.';
        }

        return $errors;
    }

    public function editPermissions(array $params): void
    {
        $role = Role::findById((int) $params['id']);
        if (!$role) {
            $this->notFound();
        }

        $this->view('roles/permissions', [
            'role'              => $role,
            'groupedPermissions' => Permission::allGroupedByModule(),
            'grantedPermIds'    => Role::permissionIds((int) $role['id']),
        ]);
    }

    public function updatePermissions(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect("/roles/{$id}/permissions");
        }

        $role = Role::findById($id);
        if (!$role) {
            $this->notFound();
        }

        $permissionIds = array_map('intval', $_POST['permissions'] ?? []);

        // Guard: Admin must always retain role.manage, or nobody could fix RBAC again.
        if ($role['name'] === 'Admin') {
            $roleManageId = null;
            foreach (Permission::all() as $p) {
                if ($p['code'] === 'role.manage') {
                    $roleManageId = (int) $p['id'];
                    break;
                }
            }
            if ($roleManageId !== null && !in_array($roleManageId, $permissionIds, true)) {
                $permissionIds[] = $roleManageId;
            }
        }

        Role::updatePermissions($id, $permissionIds);

        ActivityLog::record(Auth::id(), 'role.update_permissions', 'role', $id);
        flash('success', "Permissions updated for {$role['name']}.");
        $this->redirect('/roles');
    }

    private function notFound(): never
    {
        http_response_code(404);
        require ROOT_PATH . '/views/errors/404.php';
        exit;
    }
}
