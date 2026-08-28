<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class SalesTeam
{
    public static function all(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM sales_teams ORDER BY name')->fetchAll();
    }

    public static function allActive(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM sales_teams WHERE is_active = 1 ORDER BY name')->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM sales_teams WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $pdo = Database::getInstance();
        if ($excludeId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM sales_teams WHERE name = :name AND id != :id');
            $stmt->execute(['name' => $name, 'id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM sales_teams WHERE name = :name');
            $stmt->execute(['name' => $name]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(string $name, ?string $description): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT INTO sales_teams (name, description) VALUES (:name, :description)');
        $stmt->execute(['name' => $name, 'description' => $description]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, ?string $description): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE sales_teams SET name = :name, description = :description WHERE id = :id');
        $stmt->execute(['name' => $name, 'description' => $description, 'id' => $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE sales_teams SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /** Member count, for the Teams list ("3 users") and to warn before deactivating. */
    public static function memberCount(int $id): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE team_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }
}
