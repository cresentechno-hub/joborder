<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class JobOrderComment
{
    /** Rewrites every comment's invoice/DO file paths for a job order after its per-order upload folder was renamed (quotation no changed). */
    public static function rewritePathsForRenamedFolder(int $jobOrderId, string $oldRel, string $newRel): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE job_order_comments SET
                invoice_file_path = REPLACE(invoice_file_path, :old_rel, :new_rel),
                do_file_path = REPLACE(do_file_path, :old_rel2, :new_rel2)
             WHERE job_order_id = :job_order_id'
        );
        $stmt->execute(['old_rel' => $oldRel, 'new_rel' => $newRel, 'old_rel2' => $oldRel, 'new_rel2' => $newRel, 'job_order_id' => $jobOrderId]);
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM job_order_comments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** $data['assigned_to'] is an array of user IDs — first is stored as the primary assigned_to column, full list goes to job_order_comment_assignees. */
    public static function create(array $data): int
    {
        $assigneeIds = self::normalizeAssigneeIds($data['assigned_to']);

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO job_order_comments (job_order_id, stage_id, invoice_no, po_no, assigned_to, remark, created_by)
                 VALUES (:job_order_id, :stage_id, :invoice_no, :po_no, :assigned_to, :remark, :created_by)'
            );
            $stmt->execute([
                'job_order_id' => $data['job_order_id'],
                'stage_id'     => $data['stage_id'],
                'invoice_no'   => $data['invoice_no'] ?? null,
                'po_no'        => $data['po_no'] ?? null,
                'assigned_to'  => $assigneeIds[0],
                'remark'       => $data['remark'] ?? null,
                'created_by'   => $data['created_by'],
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

    /** $data['assigned_to'] is an array of user IDs — see create(). */
    public static function update(int $id, array $data): void
    {
        $assigneeIds = self::normalizeAssigneeIds($data['assigned_to']);

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE job_order_comments SET stage_id = :stage_id, invoice_no = :invoice_no, po_no = :po_no,
                        assigned_to = :assigned_to, remark = :remark WHERE id = :id'
            );
            $stmt->execute([
                'stage_id'    => $data['stage_id'],
                'invoice_no'  => $data['invoice_no'] ?? null,
                'po_no'       => $data['po_no'] ?? null,
                'assigned_to' => $assigneeIds[0],
                'remark'      => $data['remark'] ?? null,
                'id'          => $id,
            ]);

            $del = $pdo->prepare('DELETE FROM job_order_comment_assignees WHERE comment_id = :id');
            $del->execute(['id' => $id]);
            self::insertAssignees($id, $assigneeIds);

            $pdo->commit();
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
    private static function insertAssignees(int $commentId, array $assigneeIds): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT IGNORE INTO job_order_comment_assignees (comment_id, user_id) VALUES (:cid, :uid)');
        foreach ($assigneeIds as $uid) {
            $stmt->execute(['cid' => $commentId, 'uid' => $uid]);
        }
    }

    /** @return int[] */
    public static function getAssigneeIds(int $commentId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT user_id FROM job_order_comment_assignees WHERE comment_id = :id');
        $stmt->execute(['id' => $commentId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Deletes the comment row (CC pivot rows cascade automatically). Caller is responsible for removing any attached files from disk first. */
    public static function delete(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('DELETE FROM job_order_comments WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function updateInvoiceFile(int $commentId, string $path, string $originalName): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE job_order_comments SET invoice_file_path = :path, invoice_file_original_name = :name WHERE id = :id'
        );
        $stmt->execute(['path' => $path, 'name' => $originalName, 'id' => $commentId]);
    }

    public static function updateDoFile(int $commentId, string $path, string $originalName): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE job_order_comments SET do_file_path = :path, do_file_original_name = :name WHERE id = :id'
        );
        $stmt->execute(['path' => $path, 'name' => $originalName, 'id' => $commentId]);
    }

    /** Replaces the entire CC list for a comment (used on edit — attachCcUsers alone would never remove a deselected user). */
    public static function replaceCcUsers(int $commentId, array $userIds): void
    {
        $pdo = Database::getInstance();
        $del = $pdo->prepare('DELETE FROM job_order_comment_cc WHERE comment_id = :id');
        $del->execute(['id' => $commentId]);
        self::attachCcUsers($commentId, $userIds);
    }

    /** @return int[] */
    public static function getCcUserIds(int $commentId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT user_id FROM job_order_comment_cc WHERE comment_id = :id');
        $stmt->execute(['id' => $commentId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param int[] $userIds */
    public static function attachCcUsers(int $commentId, array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT IGNORE INTO job_order_comment_cc (comment_id, user_id) VALUES (:comment_id, :user_id)');
        foreach (array_unique($userIds) as $userId) {
            $stmt->execute(['comment_id' => $commentId, 'user_id' => $userId]);
        }
    }

    /** Full comment history for a job order, newest first, with stage/assignee/author names and CC list. */
    public static function findByJobOrderId(int $jobOrderId): array
    {
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare(
            'SELECT c.*, js.stage_code, js.stage_name, js.color_code AS stage_color,
                    au.full_name AS assigned_to_name, cb.full_name AS created_by_name
             FROM job_order_comments c
             JOIN job_stages js ON js.id = c.stage_id
             JOIN users au ON au.id = c.assigned_to
             JOIN users cb ON cb.id = c.created_by
             WHERE c.job_order_id = :job_order_id
             ORDER BY c.created_at DESC, c.id DESC'
        );
        $stmt->execute(['job_order_id' => $jobOrderId]);
        $comments = $stmt->fetchAll();

        if (empty($comments)) {
            return [];
        }

        $commentIds = array_map(static fn (array $c): int => (int) $c['id'], $comments);
        $placeholders = implode(',', array_fill(0, count($comments), '?'));

        $ccStmt = $pdo->prepare(
            "SELECT cc.comment_id, u.full_name
             FROM job_order_comment_cc cc
             JOIN users u ON u.id = cc.user_id
             WHERE cc.comment_id IN ({$placeholders})
             ORDER BY u.full_name"
        );
        $ccStmt->execute($commentIds);

        $ccByComment = [];
        foreach ($ccStmt->fetchAll() as $row) {
            $ccByComment[(int) $row['comment_id']][] = $row['full_name'];
        }

        $assigneeStmt = $pdo->prepare(
            "SELECT ca.comment_id, u.full_name
             FROM job_order_comment_assignees ca
             JOIN users u ON u.id = ca.user_id
             WHERE ca.comment_id IN ({$placeholders})
             ORDER BY u.full_name"
        );
        $assigneeStmt->execute($commentIds);

        $assigneesByComment = [];
        foreach ($assigneeStmt->fetchAll() as $row) {
            $assigneesByComment[(int) $row['comment_id']][] = $row['full_name'];
        }

        foreach ($comments as &$comment) {
            $comment['cc_names'] = $ccByComment[(int) $comment['id']] ?? [];
            $comment['assignee_names'] = $assigneesByComment[(int) $comment['id']] ?? [$comment['assigned_to_name']];
        }
        unset($comment);

        return $comments;
    }
}
