<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class JobOrder
{
    public static function create(array $data): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO job_orders
                (quotation_no, customer_name, subject, total_cost,
                 quotation_file_path, quotation_file_original_name,
                 po_file_path, po_file_original_name,
                 job_start_date, assigned_to, stage_id, remarks, created_by)
             VALUES
                (:quotation_no, :customer_name, :subject, :total_cost,
                 :quotation_file_path, :quotation_file_original_name,
                 :po_file_path, :po_file_original_name,
                 :job_start_date, :assigned_to, :stage_id, :remarks, :created_by)'
        );
        $stmt->execute([
            'quotation_no'                  => $data['quotation_no'],
            'customer_name'                 => $data['customer_name'],
            'subject'                       => $data['subject'],
            'total_cost'                    => $data['total_cost'],
            'quotation_file_path'           => $data['quotation_file_path'],
            'quotation_file_original_name'  => $data['quotation_file_original_name'],
            'po_file_path'                  => $data['po_file_path'] ?? null,
            'po_file_original_name'         => $data['po_file_original_name'] ?? null,
            'job_start_date'                => $data['job_start_date'],
            'assigned_to'                   => $data['assigned_to'],
            'stage_id'                      => $data['stage_id'],
            'remarks'                       => $data['remarks'] ?? null,
            'created_by'                    => $data['created_by'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Updates the core fields, plus the quotation/PO file fields only when
     * present in $data (a re-upload on edit) — leaves existing files intact
     * otherwise.
     */
    public static function update(int $id, array $data): void
    {
        $fields = [
            'quotation_no = :quotation_no',
            'customer_name = :customer_name',
            'subject = :subject',
            'total_cost = :total_cost',
            'job_start_date = :job_start_date',
            'assigned_to = :assigned_to',
            'stage_id = :stage_id',
            'remarks = :remarks',
            'updated_by = :updated_by',
        ];
        $params = [
            'id'             => $id,
            'quotation_no'   => $data['quotation_no'],
            'customer_name'  => $data['customer_name'],
            'subject'        => $data['subject'],
            'total_cost'     => $data['total_cost'],
            'job_start_date' => $data['job_start_date'],
            'assigned_to'    => $data['assigned_to'],
            'stage_id'       => $data['stage_id'],
            'remarks'        => $data['remarks'] ?? null,
            'updated_by'     => $data['updated_by'],
        ];

        if (isset($data['quotation_file_path'])) {
            $fields[] = 'quotation_file_path = :quotation_file_path';
            $fields[] = 'quotation_file_original_name = :quotation_file_original_name';
            $params['quotation_file_path'] = $data['quotation_file_path'];
            $params['quotation_file_original_name'] = $data['quotation_file_original_name'];
        }

        if (isset($data['po_file_path'])) {
            $fields[] = 'po_file_path = :po_file_path';
            $fields[] = 'po_file_original_name = :po_file_original_name';
            $params['po_file_path'] = $data['po_file_path'];
            $params['po_file_original_name'] = $data['po_file_original_name'];
        }

        $sql = 'UPDATE job_orders SET ' . implode(', ', $fields) . ' WHERE id = :id AND is_deleted = 0';
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM job_orders WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function quotationNoExists(string $quotationNo, ?int $excludeId = null): bool
    {
        $pdo = Database::getInstance();

        if ($excludeId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM job_orders WHERE quotation_no = :no AND id != :id');
            $stmt->execute(['no' => $quotationNo, 'id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM job_orders WHERE quotation_no = :no');
            $stmt->execute(['no' => $quotationNo]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function softDelete(int $id, int $userId): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE job_orders SET is_deleted = 1, updated_by = :uid WHERE id = :id');
        $stmt->execute(['uid' => $userId, 'id' => $id]);
    }

    /**
     * @param array{q?:string, stage_id?:int|null} $filters
     * @return array{data: array, total: int, page: int, per_page: int}
     */
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::getInstance();
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(quotation_no LIKE :q OR customer_name LIKE :q OR subject LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['stage_id'])) {
            $where[] = 'stage_id = :stage_id';
            $params['stage_id'] = $filters['stage_id'];
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM v_job_orders_overview {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT * FROM v_job_orders_overview {$whereSql} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
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

    /** Job orders created (captured into the system) this calendar year. */
    public static function countThisYear(): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query(
            'SELECT COUNT(*) FROM job_orders WHERE is_deleted = 0 AND YEAR(created_at) = YEAR(CURDATE())'
        );
        return (int) $stmt->fetchColumn();
    }

    /** Every active stage with its live job count (0 for empty stages), in workflow order. */
    public static function countByStage(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query(
            'SELECT js.id AS stage_id, js.stage_code, js.stage_name, js.color_code AS stage_color,
                    COUNT(jo.id) AS total
             FROM job_stages js
             LEFT JOIN job_orders jo ON jo.stage_id = js.id AND jo.is_deleted = 0
             WHERE js.is_active = 1
             GROUP BY js.id, js.stage_code, js.stage_name, js.color_code, js.sort_order
             ORDER BY js.sort_order'
        )->fetchAll();
    }

    /**
     * Job orders that have sat in their current stage longer than $days,
     * excluding the two terminal stages (7 Sales Completed, 8 Cancel PO) —
     * those are finished, not "pending".
     */
    public static function stuckJobs(int $days): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            "SELECT * FROM v_job_orders_overview
             WHERE days_in_current_stage > :days AND stage_code NOT IN ('7', '8')
             ORDER BY days_in_current_stage DESC"
        );
        $stmt->execute(['days' => $days]);
        return $stmt->fetchAll();
    }
}
