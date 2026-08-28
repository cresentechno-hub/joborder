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

    /** User row + role_name + flat permissions[] code array, for Auth::user(). */
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

    /** Active users for the "Assign to" dropdown. */
    public static function allActive(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query(
            'SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name'
        )->fetchAll();
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
}
