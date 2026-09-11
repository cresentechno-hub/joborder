<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class LprPartner
{
    public static function all(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM lpr_partners ORDER BY name')->fetchAll();
    }

    public static function allActive(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM lpr_partners WHERE is_active = 1 ORDER BY name')->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM lpr_partners WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM lpr_partners WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $pdo = Database::getInstance();
        if ($excludeId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM lpr_partners WHERE name = :name AND id != :id');
            $stmt->execute(['name' => $name, 'id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM lpr_partners WHERE name = :name');
            $stmt->execute(['name' => $name]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(string $name): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT INTO lpr_partners (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);
        return (int) $pdo->lastInsertId();
    }

    /** Finds a partner by exact name, creating one if it doesn't exist yet — used by CSV import. */
    public static function findOrCreateByName(string $name): int
    {
        $existing = self::findByName($name);
        if ($existing) {
            return (int) $existing['id'];
        }
        return self::create($name);
    }

    public static function update(int $id, string $name): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE lpr_partners SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE lpr_partners SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    public static function rentalCount(int $id): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM lpr_rentals WHERE partner_id = :id AND is_deleted = 0');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Hard delete — only safe when nothing references this partner.
     * Callers should check rentalCount() first for a friendly message; the
     * FK constraint (ON DELETE RESTRICT on lpr_rentals.partner_id) is the
     * real backstop and will throw a PDOException if something still does.
     */
    public static function delete(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('DELETE FROM lpr_partners WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
