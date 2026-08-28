<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Writes to the audit trail (activity_logs) designed in the Step 1 schema. */
final class ActivityLog
{
    public static function record(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)'
        );
        $stmt->execute([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => $details ? json_encode($details) : null,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
