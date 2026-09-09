<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTime;
use PDO;

/**
 * SMC contracts + the per-month status grid. Each contract gets one row
 * per covered month in smc_contract_statuses, generated from
 * start_date/coverage_months — regenerated (preserving any overlapping
 * months' status) whenever those two fields change. Status is a tri-state
 * string: '' (Blank), 'SCH' (Scheduled), 'DONE' (Done) — the dropdown
 * equivalent of LprRental's plain checked/unchecked flag.
 */
final class SmcContract
{
    public const STATUSES = ['', 'SCH', 'DONE'];

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM smc_contracts WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** All active contracts with customer/branch names, ordered for display by customer name then start date. */
    public static function allWithDetails(?int $customerId = null, ?int $branchId = null): array
    {
        $pdo = Database::getInstance();
        $sql = 'SELECT sc.*, c.name AS customer_name, b.name AS branch_name
                FROM smc_contracts sc
                JOIN customers c ON c.id = sc.customer_id
                JOIN branches b ON b.id = sc.branch_id
                WHERE sc.is_deleted = 0';
        $params = [];
        if ($customerId !== null) {
            $sql .= ' AND sc.customer_id = :customer_id';
            $params['customer_id'] = $customerId;
        }
        if ($branchId !== null) {
            $sql .= ' AND sc.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }
        $sql .= ' ORDER BY c.name, sc.start_date';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Active contracts whose coverage ends within $months from today —
     * including any already past their end date — for the Dashboard's
     * renewal reminder. end_date is the last day of the covered period:
     * start_date + coverage_months, minus one day. Soonest/most overdue first.
     */
    public static function expiringSoon(int $months = 3, ?int $branchId = null): array
    {
        $pdo = Database::getInstance();
        $branchFilter = $branchId !== null ? ' AND sc.branch_id = :branch_id' : '';
        $stmt = $pdo->prepare(
            'SELECT sc.*, c.name AS customer_name,
                    DATE_SUB(DATE_ADD(sc.start_date, INTERVAL sc.coverage_months MONTH), INTERVAL 1 DAY) AS end_date
             FROM smc_contracts sc
             JOIN customers c ON c.id = sc.customer_id
             WHERE sc.is_deleted = 0' . $branchFilter . '
             HAVING end_date <= DATE_ADD(CURDATE(), INTERVAL :months MONTH)
             ORDER BY end_date ASC'
        );
        $params = ['months' => $months];
        if ($branchId !== null) {
            $params['branch_id'] = $branchId;
        }
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** The full sequential 'YYYY-MM' column list spanning every active contract's coverage window. */
    public static function globalMonthColumns(): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query(
            'SELECT MIN(scs.`year_month`) AS min_ym, MAX(scs.`year_month`) AS max_ym
             FROM smc_contract_statuses scs
             JOIN smc_contracts sc ON sc.id = scs.contract_id
             WHERE sc.is_deleted = 0'
        );
        $row = $stmt->fetch();
        if (empty($row['min_ym']) || empty($row['max_ym'])) {
            return [];
        }
        return self::monthSequence($row['min_ym'], $row['max_ym']);
    }

    /** @return array<int, array<string, string>> contract_id => [year_month => status] */
    public static function statusesByContract(array $contractIds): array
    {
        if (empty($contractIds)) {
            return [];
        }
        $pdo = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($contractIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT contract_id, `year_month`, status FROM smc_contract_statuses
             WHERE contract_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($contractIds));

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['contract_id']][$row['year_month']] = $row['status'];
        }
        return $map;
    }

    /**
     * @param array{customer_id:int, branch_id:int, start_date:string, coverage_months:int, customer_email:?string, created_by:int} $data
     */
    public static function create(array $data): int
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO smc_contracts (customer_id, branch_id, start_date, coverage_months, customer_email, created_by)
                 VALUES (:customer_id, :branch_id, :start_date, :coverage_months, :customer_email, :created_by)'
            );
            $stmt->execute([
                'customer_id'     => $data['customer_id'],
                'branch_id'       => $data['branch_id'],
                'start_date'      => $data['start_date'],
                'coverage_months' => $data['coverage_months'],
                'customer_email'  => $data['customer_email'] ?? null,
                'created_by'      => $data['created_by'],
            ]);

            $id = (int) $pdo->lastInsertId();
            self::syncStatusRows($id, $data['start_date'], (int) $data['coverage_months']);

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array{customer_id:int, branch_id:int, start_date:string, coverage_months:int, customer_email:?string, updated_by:int} $data */
    public static function update(int $id, array $data): void
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE smc_contracts SET customer_id = :customer_id, branch_id = :branch_id,
                        start_date = :start_date, coverage_months = :coverage_months,
                        customer_email = :customer_email, updated_by = :updated_by,
                        renewal_reminder_sent_at = NULL
                 WHERE id = :id AND is_deleted = 0'
            );
            $stmt->execute([
                'id'              => $id,
                'customer_id'     => $data['customer_id'],
                'branch_id'       => $data['branch_id'],
                'start_date'      => $data['start_date'],
                'coverage_months' => $data['coverage_months'],
                'customer_email'  => $data['customer_email'] ?? null,
                'updated_by'      => $data['updated_by'],
            ]);

            self::syncStatusRows($id, $data['start_date'], (int) $data['coverage_months']);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Regenerates the coverage-month rows for a contract: adds any newly
     * covered months (Blank), removes rows for months no longer covered,
     * and leaves already-set statuses within the new range untouched
     * (INSERT IGNORE never overwrites an existing row).
     */
    private static function syncStatusRows(int $contractId, string $startDate, int $coverageMonths): void
    {
        $pdo = Database::getInstance();
        $startYm = substr($startDate, 0, 7);
        $months = self::monthSequenceFromStart($startYm, $coverageMonths);

        $insert = $pdo->prepare(
            'INSERT IGNORE INTO smc_contract_statuses (contract_id, `year_month`) VALUES (:contract_id, :ym)'
        );
        foreach ($months as $ym) {
            $insert->execute(['contract_id' => $contractId, 'ym' => $ym]);
        }

        $placeholders = implode(',', array_fill(0, count($months), '?'));
        $del = $pdo->prepare(
            "DELETE FROM smc_contract_statuses WHERE contract_id = ? AND `year_month` NOT IN ({$placeholders})"
        );
        $del->execute(array_merge([$contractId], $months));
    }

    public static function softDelete(int $id, int $userId): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE smc_contracts SET is_deleted = 1, updated_by = :uid WHERE id = :id');
        $stmt->execute(['uid' => $userId, 'id' => $id]);
    }

    /** Marks a contract as having had its renewal reminder emailed — see database/send_renewal_reminders.php. */
    public static function markReminderSent(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE smc_contracts SET renewal_reminder_sent_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Sets one month's status for a contract. Returns false if that month isn't part of this contract's coverage, true otherwise. */
    public static function setStatus(int $contractId, string $yearMonth, string $status, int $userId): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM smc_contract_statuses WHERE contract_id = :cid AND `year_month` = :ym LIMIT 1'
        );
        $stmt->execute(['cid' => $contractId, 'ym' => $yearMonth]);
        if ($stmt->fetch() === false) {
            return false;
        }

        $upd = $pdo->prepare(
            'UPDATE smc_contract_statuses
             SET status = :status, updated_by = :uid, updated_at = :now
             WHERE contract_id = :cid AND `year_month` = :ym'
        );
        $upd->execute([
            'status' => $status,
            'uid'    => $status !== '' ? $userId : null,
            'now'    => $status !== '' ? date('Y-m-d H:i:s') : null,
            'cid'    => $contractId,
            'ym'     => $yearMonth,
        ]);

        return true;
    }

    /** @return string[] sequential 'YYYY-MM' from $startYm for $count months */
    public static function monthSequenceFromStart(string $startYm, int $count): array
    {
        $cursor = DateTime::createFromFormat('Y-m-d', $startYm . '-01');
        $months = [];
        for ($i = 0; $i < $count; $i++) {
            $months[] = $cursor->format('Y-m');
            $cursor->modify('+1 month');
        }
        return $months;
    }

    /** @return string[] sequential 'YYYY-MM' from $startYm through $endYm inclusive */
    public static function monthSequence(string $startYm, string $endYm): array
    {
        $cursor = DateTime::createFromFormat('Y-m-d', $startYm . '-01');
        $end = DateTime::createFromFormat('Y-m-d', $endYm . '-01');
        $months = [];
        while ($cursor <= $end) {
            $months[] = $cursor->format('Y-m');
            $cursor->modify('+1 month');
        }
        return $months;
    }
}
