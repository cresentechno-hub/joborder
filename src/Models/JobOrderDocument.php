<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** "Other Documents" — any number of extra files attached to a job order alongside its quotation/PO. */
final class JobOrderDocument
{
    public static function create(int $jobOrderId, string $filePath, string $originalName, int $uploadedBy): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO job_order_documents (job_order_id, file_path, original_name, uploaded_by)
             VALUES (:job_order_id, :file_path, :original_name, :uploaded_by)'
        );
        $stmt->execute([
            'job_order_id'  => $jobOrderId,
            'file_path'     => $filePath,
            'original_name' => $originalName,
            'uploaded_by'   => $uploadedBy,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM job_order_documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Newest first, with uploader name — for the edit form's document list. */
    public static function findByJobOrderId(int $jobOrderId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT jod.*, u.full_name AS uploaded_by_name
             FROM job_order_documents jod
             JOIN users u ON u.id = jod.uploaded_by
             WHERE jod.job_order_id = :job_order_id
             ORDER BY jod.created_at DESC, jod.id DESC'
        );
        $stmt->execute(['job_order_id' => $jobOrderId]);
        return $stmt->fetchAll();
    }

    public static function delete(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('DELETE FROM job_order_documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Rewrites every stored path for a job order after its per-order upload folder was renamed (quotation no changed). */
    public static function rewritePathsForRenamedFolder(int $jobOrderId, string $oldRel, string $newRel): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            "UPDATE job_order_documents SET file_path = REPLACE(file_path, :old_rel, :new_rel)
             WHERE job_order_id = :job_order_id"
        );
        $stmt->execute(['old_rel' => $oldRel, 'new_rel' => $newRel, 'job_order_id' => $jobOrderId]);
    }
}
