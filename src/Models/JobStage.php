<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class JobStage
{
    /** Active stages in workflow order, for dropdowns. */
    public static function allActive(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query(
            'SELECT * FROM job_stages WHERE is_active = 1 ORDER BY sort_order'
        )->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM job_stages WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
