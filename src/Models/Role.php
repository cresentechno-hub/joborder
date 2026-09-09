<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Role
{
    public static function all(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name): bool
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM roles WHERE name = :name');
        $stmt->execute(['name' => $name]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(string $name, ?string $description): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT INTO roles (name, description) VALUES (:name, :description)');
        $stmt->execute(['name' => $name, 'description' => $description]);
        return (int) $pdo->lastInsertId();
    }

    /** Role rows plus a flat permissions[] code array for each, for the Roles list. */
    public static function allWithPermissions(): array
    {
        $roles = self::all();
        $pdo = Database::getInstance();

        $permStmt = $pdo->prepare(
            'SELECT p.code FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role_id
             ORDER BY p.code'
        );

        foreach ($roles as &$role) {
            $permStmt->execute(['role_id' => $role['id']]);
            $role['permissions'] = $permStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($role);

        return $roles;
    }

    /** @return int[] permission IDs currently granted to this role. */
    public static function permissionIds(int $roleId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :role_id');
        $stmt->execute(['role_id' => $roleId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param int[] $permissionIds Replaces the role's entire permission set. */
    public static function updatePermissions(int $roleId, array $permissionIds): void
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            $del = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
            $del->execute(['role_id' => $roleId]);

            $ins = $pdo->prepare(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
            );
            foreach (array_unique($permissionIds) as $permissionId) {
                $ins->execute(['role_id' => $roleId, 'permission_id' => (int) $permissionId]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
