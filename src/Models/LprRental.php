<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTime;
use PDO;

/**
 * LPR rental contracts + the per-month "invoice printed" checkbox grid.
 * Each contract gets one row per covered month in lpr_rental_invoice_checks,
 * generated from start_date/coverage_months — regenerated (preserving any
 * overlapping months' checked state) whenever those two fields change.
 */
final class LprRental
{
    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT lr.*, c.name AS customer_name FROM lpr_rentals lr
             JOIN customers c ON c.id = lr.customer_id
             WHERE lr.id = :id AND lr.is_deleted = 0 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Builds a ` AND {$column} IN (:prefix0, :prefix1, ...)` fragment + its
     * named params for a branch_id filter. $branchIds === null means
     * unrestricted (no filter at all); an empty array means a restricted
     * user with no branch of their own, so the fragment guarantees zero
     * rows rather than silently matching everything. Named (not `?`)
     * placeholders so this can be merged into a query that already binds
     * other named params — PDO can't mix the two styles in one statement.
     */
    private static function branchIdsClause(?array $branchIds, string $column, string $prefix): array
    {
        if ($branchIds === null) {
            return ['', []];
        }
        if (empty($branchIds)) {
            return [' AND 1 = 0', []];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($branchIds) as $i => $id) {
            $key = "{$prefix}{$i}";
            $placeholders[] = ":{$key}";
            $params[$key] = $id;
        }
        return [' AND ' . $column . ' IN (' . implode(',', $placeholders) . ')', $params];
    }

    /** All active rentals with customer/partner/branch names, grouped for display by partner name then customer name. */
    public static function allWithDetails(?int $customerId = null, ?array $branchIds = null): array
    {
        $pdo = Database::getInstance();
        [$branchClause, $branchParams] = self::branchIdsClause($branchIds, 'lr.branch_id', 'b');
        $sql = 'SELECT lr.*, c.name AS customer_name, p.name AS partner_name, b.name AS branch_name
                FROM lpr_rentals lr
                JOIN customers c ON c.id = lr.customer_id
                JOIN lpr_partners p ON p.id = lr.partner_id
                JOIN branches b ON b.id = lr.branch_id
                WHERE lr.is_deleted = 0' . $branchClause;
        $params = $branchParams;
        if ($customerId !== null) {
            $sql .= ' AND lr.customer_id = :customer_id';
            $params['customer_id'] = $customerId;
        }
        $sql .= ' ORDER BY p.name, c.name, lr.start_date';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Active rentals whose coverage ends within $months from today —
     * including any already past their end date — for the Dashboard's
     * renewal reminder. end_date is the last day of the covered period:
     * start_date + coverage_months, minus one day. Soonest/most overdue first.
     */
    public static function expiringSoon(int $months = 3, ?array $branchIds = null): array
    {
        $pdo = Database::getInstance();
        [$branchFilter, $branchParams] = self::branchIdsClause($branchIds, 'lr.branch_id', 'b');
        $stmt = $pdo->prepare(
            'SELECT lr.*, c.name AS customer_name, p.name AS partner_name,
                    DATE_SUB(DATE_ADD(lr.start_date, INTERVAL lr.coverage_months MONTH), INTERVAL 1 DAY) AS end_date
             FROM lpr_rentals lr
             JOIN customers c ON c.id = lr.customer_id
             JOIN lpr_partners p ON p.id = lr.partner_id
             WHERE lr.is_deleted = 0' . $branchFilter . '
             HAVING end_date <= DATE_ADD(CURDATE(), INTERVAL :months MONTH)
             ORDER BY end_date ASC'
        );
        $params = ['months' => $months] + $branchParams;
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** The full sequential 'YYYY-MM' column list spanning every active rental's coverage window. */
    public static function globalMonthColumns(): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query(
            'SELECT MIN(lric.`year_month`) AS min_ym, MAX(lric.`year_month`) AS max_ym
             FROM lpr_rental_invoice_checks lric
             JOIN lpr_rentals lr ON lr.id = lric.rental_id
             WHERE lr.is_deleted = 0'
        );
        $row = $stmt->fetch();
        if (empty($row['min_ym']) || empty($row['max_ym'])) {
            return [];
        }
        return self::monthSequence($row['min_ym'], $row['max_ym']);
    }

    /** @return array<int, array<string, bool>> rental_id => [year_month => is_checked] */
    public static function checksByRental(array $rentalIds): array
    {
        if (empty($rentalIds)) {
            return [];
        }
        $pdo = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($rentalIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT rental_id, `year_month`, is_checked FROM lpr_rental_invoice_checks
             WHERE rental_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($rentalIds));

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['rental_id']][$row['year_month']] = (bool) $row['is_checked'];
        }
        return $map;
    }

    /**
     * @param array{customer_id:int, partner_id:int, branch_id:int, start_date:string, coverage_months:int, customer_email:?string, quotation_no:?string, rental_amount:?float, detail:?string, e_invoice:?string, created_by:int} $data
     */
    public static function create(array $data): int
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO lpr_rentals (customer_id, partner_id, branch_id, start_date, coverage_months, customer_email, quotation_no, rental_amount, detail, e_invoice, created_by)
                 VALUES (:customer_id, :partner_id, :branch_id, :start_date, :coverage_months, :customer_email, :quotation_no, :rental_amount, :detail, :e_invoice, :created_by)'
            );
            $stmt->execute([
                'customer_id'     => $data['customer_id'],
                'partner_id'      => $data['partner_id'],
                'branch_id'       => $data['branch_id'],
                'start_date'      => $data['start_date'],
                'coverage_months' => $data['coverage_months'],
                'customer_email'  => $data['customer_email'] ?? null,
                'quotation_no'    => $data['quotation_no'] ?? null,
                'rental_amount'   => $data['rental_amount'] ?? null,
                'detail'          => $data['detail'] ?? null,
                'e_invoice'       => $data['e_invoice'] ?? null,
                'created_by'      => $data['created_by'],
            ]);

            $id = (int) $pdo->lastInsertId();
            self::syncCheckRows($id, $data['start_date'], (int) $data['coverage_months']);

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array{customer_id:int, partner_id:int, branch_id:int, start_date:string, coverage_months:int, customer_email:?string, quotation_no:?string, rental_amount:?float, detail:?string, e_invoice:?string, updated_by:int} $data */
    public static function update(int $id, array $data): void
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE lpr_rentals SET customer_id = :customer_id, partner_id = :partner_id, branch_id = :branch_id,
                        start_date = :start_date, coverage_months = :coverage_months,
                        customer_email = :customer_email, quotation_no = :quotation_no,
                        rental_amount = :rental_amount, detail = :detail, e_invoice = :e_invoice,
                        updated_by = :updated_by, renewal_reminder_sent_at = NULL
                 WHERE id = :id AND is_deleted = 0'
            );
            $stmt->execute([
                'id'              => $id,
                'customer_id'     => $data['customer_id'],
                'partner_id'      => $data['partner_id'],
                'branch_id'       => $data['branch_id'],
                'start_date'      => $data['start_date'],
                'coverage_months' => $data['coverage_months'],
                'customer_email'  => $data['customer_email'] ?? null,
                'quotation_no'    => $data['quotation_no'] ?? null,
                'rental_amount'   => $data['rental_amount'] ?? null,
                'detail'          => $data['detail'] ?? null,
                'e_invoice'       => $data['e_invoice'] ?? null,
                'updated_by'      => $data['updated_by'],
            ]);

            self::syncCheckRows($id, $data['start_date'], (int) $data['coverage_months']);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Sets/replaces the uploaded contract file's path — a separate update since the file scoping key is the row's own id, only known after create(). */
    public static function updateContractFile(int $id, string $path, string $originalName): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE lpr_rentals SET contract_file_path = :path, contract_file_original_name = :name WHERE id = :id');
        $stmt->execute(['path' => $path, 'name' => $originalName, 'id' => $id]);
    }

    /**
     * Regenerates the coverage-month rows for a rental: adds any newly
     * covered months (unchecked), removes rows for months no longer
     * covered, and leaves already-checked months within the new range
     * untouched (INSERT IGNORE never overwrites an existing row).
     */
    private static function syncCheckRows(int $rentalId, string $startDate, int $coverageMonths): void
    {
        $pdo = Database::getInstance();
        $startYm = substr($startDate, 0, 7);
        $months = self::monthSequenceFromStart($startYm, $coverageMonths);

        $insert = $pdo->prepare(
            'INSERT IGNORE INTO lpr_rental_invoice_checks (rental_id, `year_month`) VALUES (:rental_id, :ym)'
        );
        foreach ($months as $ym) {
            $insert->execute(['rental_id' => $rentalId, 'ym' => $ym]);
        }

        $placeholders = implode(',', array_fill(0, count($months), '?'));
        $del = $pdo->prepare(
            "DELETE FROM lpr_rental_invoice_checks WHERE rental_id = ? AND `year_month` NOT IN ({$placeholders})"
        );
        $del->execute(array_merge([$rentalId], $months));
    }

    public static function softDelete(int $id, int $userId): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE lpr_rentals SET is_deleted = 1, updated_by = :uid WHERE id = :id');
        $stmt->execute(['uid' => $userId, 'id' => $id]);
    }

    /** Marks a contract as having had its renewal reminder emailed — see database/send_renewal_reminders.php. */
    public static function markReminderSent(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE lpr_rentals SET renewal_reminder_sent_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Flips one month's checked state for a rental. Returns the new state, or null if that month isn't part of this rental's coverage. */
    public static function toggleCheck(int $rentalId, string $yearMonth, int $userId): ?bool
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT is_checked FROM lpr_rental_invoice_checks WHERE rental_id = :rid AND `year_month` = :ym LIMIT 1'
        );
        $stmt->execute(['rid' => $rentalId, 'ym' => $yearMonth]);
        $current = $stmt->fetch();
        if ($current === false) {
            return null;
        }

        $newState = !((bool) $current['is_checked']);
        $upd = $pdo->prepare(
            'UPDATE lpr_rental_invoice_checks
             SET is_checked = :checked, checked_by = :uid, checked_at = :now
             WHERE rental_id = :rid AND `year_month` = :ym'
        );
        $upd->execute([
            'checked' => $newState ? 1 : 0,
            'uid'     => $newState ? $userId : null,
            'now'     => $newState ? date('Y-m-d H:i:s') : null,
            'rid'     => $rentalId,
            'ym'      => $yearMonth,
        ]);

        return $newState;
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
