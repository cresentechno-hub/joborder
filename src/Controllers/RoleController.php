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

    public function editPermissions(array $params): void
    {
        $role = Role::findById((int) $params['id']);
        if (!$role) {
            $this->notFound();
        }

        $this->view('roles/permissions', [
            'role'              => $role,
            'permissions'       => Permission::all(),
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
