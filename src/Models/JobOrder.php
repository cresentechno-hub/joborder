<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class JobOrder
{
    /** Rewrites the job order's own file paths after its per-order upload folder was renamed (quotation no changed). */
    public static function rewritePathsForRenamedFolder(int $id, string $oldRel, string $newRel): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE job_orders SET
                quotation_file_path = REPLACE(quotation_file_path, :old_rel, :new_rel),
                po_file_path = REPLACE(po_file_path, :old_rel2, :new_rel2)
             WHERE id = :id'
        );
        $stmt->execute(['old_rel' => $oldRel, 'new_rel' => $newRel, 'old_rel2' => $oldRel, 'new_rel2' => $newRel, 'id' => $id]);
    }

    /**
     * $data['assigned_to'] is an array of user IDs (one or more). The first
     * one is stored as job_orders.assigned_to (the "primary" assignee) so
     * existing FK/history/trigger logic that reads a single owner keeps
     * working unchanged; the full list is written to job_order_assignees.
     */
    public static function create(array $data): int
    {
        $assigneeIds = self::normalizeAssigneeIds($data['assigned_to']);

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO job_orders
                    (quotation_no, customer_name, subject, total_cost,
                     quotation_file_path, quotation_file_original_name,
                     po_file_path, po_file_original_name,
                     job_start_date, assigned_to, branch_id, stage_id, remarks, created_by)
                 VALUES
                    (:quotation_no, :customer_name, :subject, :total_cost,
                     :quotation_file_path, :quotation_file_original_name,
                     :po_file_path, :po_file_original_name,
                     :job_start_date, :assigned_to, :branch_id, :stage_id, :remarks, :created_by)'
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
                'assigned_to'                   => $assigneeIds[0],
                'branch_id'                     => $data['branch_id'],
                'stage_id'                      => $data['stage_id'],
                'remarks'                       => $data['remarks'] ?? null,
                'created_by'                    => $data['created_by'],
            ]);

            $id = (int) $pdo->lastInsertId();
            self::insertAssignees($id, $assigneeIds);

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param int[]|string[] $ids @return int[] deduped, at least one element */
    private static function normalizeAssigneeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            throw new \InvalidArgumentException('At least one assignee is required.');
        }
        return $ids;
    }

    /** @param int[] $assigneeIds */
    private static function insertAssignees(int $jobOrderId, array $assigneeIds): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT IGNORE INTO job_order_assignees (job_order_id, user_id) VALUES (:jid, :uid)');
        foreach ($assigneeIds as $uid) {
            $stmt->execute(['jid' => $jobOrderId, 'uid' => $uid]);
        }
    }

    /** @return int[] */
    public static function getAssigneeIds(int $jobOrderId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT user_id FROM job_order_assignees WHERE job_order_id = :id');
        $stmt->execute(['id' => $jobOrderId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Updates the core fields, plus the quotation/PO file fields only when
     * present in $data (a re-upload on edit) — leaves existing files intact
     * otherwise.
     */
    /** $data['assigned_to'] is an array of user IDs — see create(). */
    public static function update(int $id, array $data): void
    {
        $assigneeIds = self::normalizeAssigneeIds($data['assigned_to']);

        $fields = [
            'quotation_no = :quotation_no',
            'customer_name = :customer_name',
            'subject = :subject',
            'total_cost = :total_cost',
            'job_start_date = :job_start_date',
            'assigned_to = :assigned_to',
            'branch_id = :branch_id',
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
            'assigned_to'    => $assigneeIds[0],
            'branch_id'      => $data['branch_id'],
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
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $del = $pdo->prepare('DELETE FROM job_order_assignees WHERE job_order_id = :id');
            $del->execute(['id' => $id]);
            self::insertAssignees($id, $assigneeIds);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
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

    /** Distinct customer names already used on job orders, for the list's customer filter dropdown. */
    public static function distinctCustomerNames(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT DISTINCT customer_name FROM job_orders ORDER BY customer_name')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Column names a caller may sort the list by — never build ORDER BY from raw user input directly. */
    private const SORTABLE_COLUMNS = [
        'quotation_no', 'customer_name', 'subject', 'total_cost',
        'job_start_date', 'days_elapsed', 'assignee_names', 'branch_name', 'stage_name', 'created_at',
    ];

    /**
     * @param array{q?:string, stage_id?:int|null, customer_name?:string|null, branch_id?:int, hide_completed?:bool, sort?:string, dir?:string} $filters
     *   `branch_id` restricts results to job orders belonging to that
     *   branch, ONLY when the key is present — pass 0 (never a real branch
     *   id) for a restricted user with no branch of their own, so they see
     *   nothing until assigned one. Omit the key entirely for unrestricted
     *   access.
     *   `hide_completed` excludes stage 7 (Sales Completed) and 8 (Cancel PO)
     *   — set true for any viewer without job_order.view_completed.
     *   `sort`/`dir` pick the ORDER BY column (see SORTABLE_COLUMNS) and
     *   direction; both default when omitted or invalid.
     * @return array{data: array, total: int, page: int, per_page: int}
     */
    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::getInstance();
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            // PDO with native (non-emulated) prepares can't reuse one named
            // placeholder more than once, so each LIKE gets its own.
            $where[] = '(quotation_no LIKE :q1 OR customer_name LIKE :q2 OR subject LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        if (!empty($filters['stage_id'])) {
            $where[] = 'stage_id = :stage_id';
            $params['stage_id'] = $filters['stage_id'];
        }

        if (!empty($filters['customer_name'])) {
            $where[] = 'customer_name = :customer_name';
            $params['customer_name'] = $filters['customer_name'];
        }

        if (array_key_exists('branch_id', $filters)) {
            $where[] = 'branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }

        if (!empty($filters['hide_completed'])) {
            $where[] = "stage_code NOT IN ('7', '8')";
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM v_job_orders_overview {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sortCol = in_array($filters['sort'] ?? '', self::SORTABLE_COLUMNS, true) ? $filters['sort'] : 'created_at';
        $sortDir = strtolower((string) ($filters['dir'] ?? '')) === 'asc' ? 'ASC' : 'DESC';

        $stmt = $pdo->prepare(
            "SELECT * FROM v_job_orders_overview {$whereSql} ORDER BY {$sortCol} {$sortDir}, id DESC LIMIT :limit OFFSET :offset"
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

    /**
     * Job orders created (captured into the system) this calendar year.
     * $branchId scopes the count to that branch — pass 0 (never a real
     * branch id) for a restricted user with no branch of their own, so
     * they see nothing. null means unrestricted (every branch).
     */
    public static function countThisYear(?int $branchId = null): int
    {
        $pdo = Database::getInstance();
        $sql = 'SELECT COUNT(*) FROM job_orders WHERE is_deleted = 0 AND YEAR(created_at) = YEAR(CURDATE())';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Every active stage with its live job count (0 for empty stages), in
     * workflow order. $branchId scopes counts to that branch (0 = a
     * restricted user with no branch, sees nothing; null = unrestricted).
     * $hideCompleted drops stage 7/8 rows entirely — set true for any
     * viewer without job_order.view_completed, so the dashboard doesn't
     * leak completed/cancelled counts to roles that can't see the records.
     */
    public static function countByStage(?int $branchId = null, bool $hideCompleted = false): array
    {
        $pdo = Database::getInstance();
        $branchJoin = '';
        $params = [];

        if ($branchId !== null) {
            $branchJoin = ' AND jo.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $stageFilter = $hideCompleted ? " AND js.stage_code NOT IN ('7', '8')" : '';

        $stmt = $pdo->prepare(
            'SELECT js.id AS stage_id, js.stage_code, js.stage_name, js.color_code AS stage_color,
                    COUNT(jo.id) AS total
             FROM job_stages js
             LEFT JOIN job_orders jo ON jo.stage_id = js.id AND jo.is_deleted = 0' . $branchJoin . '
             WHERE js.is_active = 1' . $stageFilter . '
             GROUP BY js.id, js.stage_code, js.stage_name, js.color_code, js.sort_order
             ORDER BY js.sort_order'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Job orders that have sat in their current stage longer than $days,
     * excluding the two terminal stages (7 Sales Completed, 8 Cancel PO) —
     * those are finished, not "pending". $branchId scopes to that branch.
     */
    public static function stuckJobs(int $days, ?int $branchId = null): array
    {
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM v_job_orders_overview
                WHERE days_in_current_stage > :days AND stage_code NOT IN ('7', '8')";
        $params = ['days' => $days];

        if ($branchId !== null) {
            $sql .= ' AND branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY days_in_current_stage DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
