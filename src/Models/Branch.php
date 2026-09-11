<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Branch
{
    public static function all(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM branches ORDER BY name')->fetchAll();
    }

    public static function allActive(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM branches WHERE is_active = 1 ORDER BY name')->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM branches WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM branches WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $pdo = Database::getInstance();
        if ($excludeId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM branches WHERE name = :name AND id != :id');
            $stmt->execute(['name' => $name, 'id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM branches WHERE name = :name');
            $stmt->execute(['name' => $name]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(string $name): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT INTO branches (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE branches SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE branches SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /** Member count, for the Branches list ("3 users") and to warn before deactivating. */
    public static function memberCount(int $id): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE branch_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Whether anything still references this branch — job orders, LPR
     * rentals, SMC contracts (all ON DELETE RESTRICT — the real backstop
     * that delete() would hit anyway) or assigned users (ON DELETE SET
     * NULL, so the DB wouldn't block it, but silently unassigning real
     * staff from their branch as a side effect of a delete isn't
     * acceptable — checked explicitly here instead).
     */
    public static function isInUse(int $id): bool
    {
        $pdo = Database::getInstance();

        if (self::memberCount($id) > 0) {
            return true;
        }

        $checks = [
            'SELECT COUNT(*) FROM job_orders WHERE branch_id = :id AND is_deleted = 0',
            'SELECT COUNT(*) FROM lpr_rentals WHERE branch_id = :id AND is_deleted = 0',
            'SELECT COUNT(*) FROM smc_contracts WHERE branch_id = :id AND is_deleted = 0',
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
     * Hard delete — only safe when nothing references this branch.
     * Callers should check isInUse() first for a friendly message; the FK
     * constraints are the real backstop and will throw a PDOException if
     * something still does (including soft-deleted rows isInUse() can't
     * see, since they still physically exist).
     */
    public static function delete(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('DELETE FROM branches WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
