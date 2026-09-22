<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class User
{
    private const LOCKOUT_THRESHOLD = 5;
    private const LOCKOUT_MINUTES = 15;

    public static function findByUsername(string $username): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Active users whose role grants $permissionCode — e.g. every possible
     * recipient of the LPR/SMC renewal reminder digest (see
     * database/send_renewal_reminders.php). Each row also carries
     * branch_ids + a fresh permissions[] array so the caller can resolve
     * data.view_all_branches per-recipient without a request-scoped
     * Auth session (this runs from a CLI cron, not a browser request).
     */
    public static function activeWithPermission(string $permissionCode): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT DISTINCT u.id, u.full_name, u.email
             FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE u.is_active = 1 AND p.code = :code
             ORDER BY u.full_name'
        );
        $stmt->execute(['code' => $permissionCode]);
        $users = $stmt->fetchAll();

        $branchIdsMap = self::branchIdsMap(array_map(static fn (array $u): int => (int) $u['id'], $users));
        foreach ($users as &$user) {
            $user['permissions'] = self::permissionCodesForRole((int) $user['id']);
            $user['branch_ids'] = $branchIdsMap[(int) $user['id']] ?? [];
        }
        unset($user);

        return $users;
    }

    /** @return string[] a user's permission codes, fresh from the DB — usable outside a request-scoped Auth session (e.g. from a CLI cron). */
    public static function permissionCodesForRole(int $userId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT p.code FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN users u ON u.role_id = rp.role_id
             WHERE u.id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** User row + role_name + flat permissions[] code array + branch_ids[], for Auth::user() — the single source of truth for `Auth::user()['branch_ids']` used app-wide. */
    public static function findWithRoleById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $permStmt = $pdo->prepare(
            'SELECT p.code FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role_id'
        );
        $permStmt->execute(['role_id' => $user['role_id']]);
        $user['permissions'] = $permStmt->fetchAll(PDO::FETCH_COLUMN);
        $user['branch_ids'] = self::branchIds($id);

        return $user;
    }

    public static function touchLastLogin(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @return bool true if the account is now locked as a result of this attempt. */
    public static function registerFailedLogin(int $id): bool
    {
        $pdo = Database::getInstance();
        $pdo->prepare('UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id')
            ->execute(['id' => $id]);

        $stmt = $pdo->prepare('SELECT failed_login_attempts FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $attempts = (int) $stmt->fetchColumn();

        if ($attempts >= self::LOCKOUT_THRESHOLD) {
            $lock = $pdo->prepare(
                'UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL :minutes MINUTE), failed_login_attempts = 0
                 WHERE id = :id'
            );
            $lock->execute(['minutes' => self::LOCKOUT_MINUTES, 'id' => $id]);
            return true;
        }

        return false;
    }

    public static function resetFailedLogins(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function create(array $data): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, full_name, role_id, is_active)
             VALUES (:username, :email, :password_hash, :full_name, :role_id, :is_active)'
        );
        $stmt->execute([
            'username'      => $data['username'],
            'email'         => $data['email'],
            'password_hash' => $data['password_hash'],
            'full_name'     => $data['full_name'],
            'role_id'       => $data['role_id'],
            'is_active'     => $data['is_active'] ?? 1,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** Active users for the "Assign to" dropdown, with branch_ids for client-side branch filtering. */
    public static function allActive(): array
    {
        $pdo = Database::getInstance();
        $users = $pdo->query(
            'SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name'
        )->fetchAll();

        $branchIdsMap = self::branchIdsMap(array_map(static fn (array $u): int => (int) $u['id'], $users));
        foreach ($users as &$user) {
            $user['branch_ids'] = $branchIdsMap[(int) $user['id']] ?? [];
        }
        unset($user);

        return $users;
    }

    /**
     * Active users a branch-restricted (Sales) creator is allowed to assign
     * to: staff sharing at least one of $branchIds, plus anyone with no
     * branch at all (Admin, Manager, other company staff) — but never
     * someone whose branches are entirely disjoint from the creator's,
     * which is the whole point of branch scoping. An empty $branchIds
     * means the creator has no branch of their own, so only unaffiliated
     * users are offered.
     */
    public static function allActiveOwnBranchOrUnaffiliated(array $branchIds): array
    {
        $pdo = Database::getInstance();
        $sql = 'SELECT DISTINCT u.id, u.full_name, u.username
                FROM users u
                LEFT JOIN user_branches ub ON ub.user_id = u.id
                WHERE u.is_active = 1 AND (ub.branch_id IS NULL';
        $params = [];
        if (!empty($branchIds)) {
            $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
            $sql .= " OR ub.branch_id IN ({$placeholders})";
            $params = $branchIds;
        }
        $sql .= ') ORDER BY u.full_name';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        $branchIdsMap = self::branchIdsMap(array_map(static fn (array $u): int => (int) $u['id'], $users));
        foreach ($users as &$user) {
            $user['branch_ids'] = $branchIdsMap[(int) $user['id']] ?? [];
        }
        unset($user);

        return $users;
    }

    /** All users (active + inactive) with role name, for the Users admin list. */
    public static function allWithRole(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query(
            'SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             ORDER BY u.full_name'
        )->fetchAll();
    }

    /** @return int[] a user's branch IDs, or [] for unaffiliated. */
    public static function branchIds(int $userId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT branch_id FROM user_branches WHERE user_id = :id ORDER BY branch_id');
        $stmt->execute(['id' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param int[] $userIds @return array<int, int[]> user_id => branch_ids[], batched to avoid N+1 queries when enriching a list of users. */
    public static function branchIdsMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return [];
        }

        $pdo = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("SELECT user_id, branch_id FROM user_branches WHERE user_id IN ({$placeholders}) ORDER BY branch_id");
        $stmt->execute($userIds);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['user_id']][] = (int) $row['branch_id'];
        }
        return $map;
    }

    /** Replaces a user's branch memberships wholesale — delete-all-then-reinsert, same pattern as SmcContract::replaceCcUsers(). Safe for both create (nothing to delete yet) and update. */
    public static function replaceBranches(int $userId, array $branchIds): void
    {
        $pdo = Database::getInstance();
        $pdo->prepare('DELETE FROM user_branches WHERE user_id = :id')->execute(['id' => $userId]);

        $branchIds = array_values(array_unique(array_filter(array_map('intval', $branchIds))));
        if (empty($branchIds)) {
            return;
        }

        $insert = $pdo->prepare('INSERT INTO user_branches (user_id, branch_id) VALUES (:user_id, :branch_id)');
        foreach ($branchIds as $branchId) {
            $insert->execute(['user_id' => $userId, 'branch_id' => $branchId]);
        }
    }

    public static function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $pdo = Database::getInstance();
        if ($excludeId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username AND id != :id');
            $stmt->execute(['username' => $username, 'id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
            $stmt->execute(['username' => $username]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $pdo = Database::getInstance();
        if ($excludeId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id != :id');
            $stmt->execute(['email' => $email, 'id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function updateProfile(int $id, array $data): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE users SET full_name = :full_name, email = :email, role_id = :role_id WHERE id = :id'
        );
        $stmt->execute([
            'full_name' => $data['full_name'],
            'email'     => $data['email'],
            'role_id'   => $data['role_id'],
            'id'        => $id,
        ]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $passwordHash, 'id' => $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE users SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /** Guards against deactivating/demoting the last remaining active Admin. */
    public static function countActiveAdmins(): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.name = 'Admin' AND u.is_active = 1"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Friendly pre-check before delete — the common, expected-to-happen
     * cases. FK RESTRICT constraints on job_orders/lpr_rentals/smc_contracts
     * (created_by), job_order_assignees, and job_order_comments
     * (created_by/assigned_to) are the real backstop for anything this
     * doesn't catch, surfaced via delete()'s own try/catch.
     */
    public static function isInUse(int $id): bool
    {
        $pdo = Database::getInstance();

        $checks = [
            'SELECT COUNT(*) FROM job_orders WHERE created_by = :id AND is_deleted = 0',
            'SELECT COUNT(*) FROM job_order_assignees ja JOIN job_orders jo ON jo.id = ja.job_order_id WHERE ja.user_id = :id AND jo.is_deleted = 0',
            'SELECT COUNT(*) FROM lpr_rentals WHERE created_by = :id AND is_deleted = 0',
            'SELECT COUNT(*) FROM smc_contracts WHERE created_by = :id AND is_deleted = 0',
            'SELECT COUNT(*) FROM job_order_comments WHERE created_by = :id',
        ];
        foreach ($checks as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hard delete — only safe when isInUse() is false. FK constraints on
     * the tables it doesn't cover (see isInUse()) are the real backstop;
     * callers should catch the resulting PDOException/Throwable.
     */
    public static function delete(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
