<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Writes to, and reads back, the audit trail (activity_logs) designed in the Step 1 schema. */
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

    /**
     * @param array{action?:string, user_id?:int} $filters
     * @return array{data: array, total: int, page: int, per_page: int}
     */
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::getInstance();
        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'al.action = :action';
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = :user_id';
            $params['user_id'] = $filters['user_id'];
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT al.*, u.full_name AS user_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             {$whereSql}
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /** Distinct action codes seen so far, for the filter dropdown. */
    public static function distinctActions(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
    }
}
