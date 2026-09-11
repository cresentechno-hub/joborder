<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Shared customer master list — any module can reference customers.id. */
final class Customer
{
    public static function all(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM customers ORDER BY name')->fetchAll();
    }

    public static function allActive(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM customers WHERE is_active = 1 ORDER BY name')->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        $pdo = Database::getInstance();
        if ($excludeId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE name = :name AND id != :id');
            $stmt->execute(['name' => $name, 'id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE name = :name');
            $stmt->execute(['name' => $name]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(string $name): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT INTO customers (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);
        return (int) $pdo->lastInsertId();
    }

    /** Finds an active/inactive customer by exact name, creating one if it doesn't exist yet — used by CSV import. */
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
        $stmt = $pdo->prepare('UPDATE customers SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE customers SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /**
     * Hard delete — only safe when nothing references this customer.
     * Callers should check lprRentalCount()/smcContractCount() first for a
     * friendly message; the FK constraints (ON DELETE RESTRICT on both
     * lpr_rentals.customer_id and smc_contracts.customer_id) are the real
     * backstop and will throw a PDOException if something still does.
     */
    public static function delete(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Whether any SMC contract still references this customer — see lprRentalCount() above. */
    public static function smcContractCount(int $id): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM smc_contracts WHERE customer_id = :id AND is_deleted = 0');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /** Whether any LPR rental still references this customer — checked before deactivating isn't required, but useful context in the UI. */
    public static function lprRentalCount(int $id): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM lpr_rentals WHERE customer_id = :id AND is_deleted = 0');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }
}
