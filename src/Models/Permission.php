<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Permission
{
    public static function all(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM permissions ORDER BY code')->fetchAll();
    }
}
