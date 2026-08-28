<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class JobOrderComment
{
    public static function create(array $data): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO job_order_comments (job_order_id, stage_id, invoice_no, assigned_to, remark, created_by)
             VALUES (:job_order_id, :stage_id, :invoice_no, :assigned_to, :remark, :created_by)'
        );
        $stmt->execute([
            'job_order_id' => $data['job_order_id'],
            'stage_id'     => $data['stage_id'],
            'invoice_no'   => $data['invoice_no'] ?? null,
            'assigned_to'  => $data['assigned_to'],
            'remark'       => $data['remark'] ?? null,
            'created_by'   => $data['created_by'],
        ]);
        return (int) $pdo->lastInsertId();
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

        $ccStmt = $pdo->prepare(
            'SELECT cc.comment_id, u.full_name
             FROM job_order_comment_cc cc
             JOIN users u ON u.id = cc.user_id
             WHERE cc.comment_id IN (' . implode(',', array_fill(0, count($comments), '?')) . ')
             ORDER BY u.full_name'
        );
        $ccStmt->execute(array_map(static fn (array $c): int => (int) $c['id'], $comments));

        $ccByComment = [];
        foreach ($ccStmt->fetchAll() as $row) {
            $ccByComment[(int) $row['comment_id']][] = $row['full_name'];
        }

        foreach ($comments as &$comment) {
            $comment['cc_names'] = $ccByComment[(int) $comment['id']] ?? [];
        }
        unset($comment);

        return $comments;
    }
}
