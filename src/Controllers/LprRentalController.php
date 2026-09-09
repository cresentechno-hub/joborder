<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BranchAccessGuard;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\LprPartner;
use App\Models\LprRental;
use DateTime;
use Throwable;

final class LprRentalController extends Controller
{
    use BranchAccessGuard;

    private const COVERAGE_OPTIONS = [12, 24, 36, 48];

    public function index(array $params = []): void
    {
        $customerId = (int) $this->input('customer_id', 0) ?: null;
        $branchId = Auth::can('data.view_all_branches')
            ? ((int) $this->input('branch_id', 0) ?: null)
            : (int) (Auth::user()['branch_id'] ?? 0);

        $rentals = LprRental::allWithDetails($customerId, $branchId);
        $months = LprRental::globalMonthColumns();
        $checks = LprRental::checksByRental(array_map(static fn (array $r): int => (int) $r['id'], $rentals));

        foreach ($rentals as &$r) {
            $r['covered_months'] = LprRental::monthSequenceFromStart(
                substr($r['start_date'], 0, 7),
                (int) $r['coverage_months']
            );
        }
        unset($r);

        $groups = [];
        foreach ($rentals as $r) {
            $groups[$r['partner_name']][] = $r;
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        $this->view('lpr_rentals/index', [
            'groups'             => $groups,
            'months'             => $months,
            'checks'             => $checks,
            'currentYm'          => date('Y-m'),
            'canManage'          => Auth::can('lpr_rental.manage'),
            'canSelectBranch'    => Auth::can('data.view_all_branches'),
            'showBranchColumn'   => Auth::can('data.view_branch_column'),
            'customers'          => Customer::allActive(),
            'branches'           => Branch::allActive(),
            'selectedCustomerId' => $customerId,
            'selectedBranchId'   => $branchId,
        ]);
    }

    public function create(array $params = []): void
    {
        $this->view('lpr_rentals/create', $this->formContext());
    }

    public function store(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/lpr-rentals/create');
        }

        $input = $_POST;
        $errors = $this->validate($input);

        $branchId = $this->resolveBranchId($input);
        if ($branchId === null) {
            $errors['branch_id'] = Auth::can('data.view_all_branches')
                ? 'Please select a valid branch.'
                : 'You have no branch assigned — ask an Admin to assign you one before creating contracts.';
        }

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect('/lpr-rentals/create');
        }

        $id = LprRental::create([
            'customer_id'     => (int) $input['customer_id'],
            'partner_id'      => (int) $input['partner_id'],
            'branch_id'       => $branchId,
            'start_date'      => $input['start_date'],
            'coverage_months' => (int) $input['coverage_months'],
            'customer_email'  => trim((string) ($input['customer_email'] ?? '')) ?: null,
            'created_by'      => Auth::id(),
        ]);

        ActivityLog::record(Auth::id(), 'lpr_rental.create', 'lpr_rental', $id);
        flash('success', 'LPR rental contract created successfully.');
        $this->redirect('/lpr-rentals');
    }

    public function edit(array $params): void
    {
        $rental = LprRental::findById((int) $params['id']);
        if (!$rental) {
            $this->notFound();
        }
        $this->assertBranchAccess($rental);

        $this->view('lpr_rentals/edit', array_merge(['rental' => $rental], $this->formContext()));
    }

    public function update(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect("/lpr-rentals/{$id}/edit");
        }

        $rental = LprRental::findById($id);
        if (!$rental) {
            $this->notFound();
        }
        $this->assertBranchAccess($rental);

        $input = $_POST;
        $errors = $this->validate($input);

        $branchId = $this->resolveBranchId($input);
        if ($branchId === null) {
            $errors['branch_id'] = Auth::can('data.view_all_branches')
                ? 'Please select a valid branch.'
                : 'You have no branch assigned — ask an Admin to assign you one.';
        }

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect("/lpr-rentals/{$id}/edit");
        }

        LprRental::update($id, [
            'customer_id'     => (int) $input['customer_id'],
            'partner_id'      => (int) $input['partner_id'],
            'branch_id'       => $branchId,
            'start_date'      => $input['start_date'],
            'coverage_months' => (int) $input['coverage_months'],
            'customer_email'  => trim((string) ($input['customer_email'] ?? '')) ?: null,
            'updated_by'      => Auth::id(),
        ]);

        ActivityLog::record(Auth::id(), 'lpr_rental.update', 'lpr_rental', $id);
        flash('success', 'LPR rental contract updated successfully.');
        $this->redirect('/lpr-rentals');
    }

    public function destroy(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/lpr-rentals');
        }

        $rental = LprRental::findById($id);
        if (!$rental) {
            $this->notFound();
        }
        $this->assertBranchAccess($rental);

        LprRental::softDelete($id, (int) Auth::id());
        ActivityLog::record(Auth::id(), 'lpr_rental.delete', 'lpr_rental', $id);
        flash('success', 'LPR rental contract deleted.');
        $this->redirect('/lpr-rentals');
    }

    /** AJAX: flips one month's checkbox for a rental. */
    public function toggleCheck(array $params): void
    {
        if (!verify_csrf()) {
            $this->json(['error' => 'Your session expired, please refresh and try again.'], 419);
        }

        $id = (int) $params['id'];
        $yearMonth = (string) $params['yearMonth'];

        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $this->json(['error' => 'Invalid month.'], 422);
        }

        $rental = LprRental::findById($id);
        if (!$rental) {
            $this->json(['error' => 'Rental not found.'], 404);
        }
        if (!Auth::can('data.view_all_branches') && (int) $rental['branch_id'] !== (int) (Auth::user()['branch_id'] ?? 0)) {
            $this->json(['error' => 'Rental not found.'], 404);
        }

        $newState = LprRental::toggleCheck($id, $yearMonth, (int) Auth::id());
        if ($newState === null) {
            $this->json(['error' => 'That month is not part of this contract\'s coverage.'], 422);
        }

        ActivityLog::record(Auth::id(), $newState ? 'lpr_rental.check' : 'lpr_rental.uncheck', 'lpr_rental', $id, ['year_month' => $yearMonth]);
        $this->json(['checked' => $newState]);
    }

    public function exportCsv(array $params = []): void
    {
        $rentals = LprRental::allWithDetails();
        $months = LprRental::globalMonthColumns();
        $checks = LprRental::checksByRental(array_map(static fn (array $r): int => (int) $r['id'], $rentals));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="lpr-rentals-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly

        fputcsv($out, array_merge(
            ['Partner', 'Customer', 'Start Date', 'Coverage Months', 'Customer Email'],
            $months
        ));

        foreach ($rentals as $r) {
            $rentalId = (int) $r['id'];
            $row = [
                $r['partner_name'],
                $r['customer_name'],
                $r['start_date'],
                $r['coverage_months'],
                $r['customer_email'] ?? '',
            ];
            foreach ($months as $ym) {
                if (!array_key_exists($ym, $checks[$rentalId] ?? [])) {
                    $row[] = ''; // month outside this contract's coverage
                } else {
                    $row[] = $checks[$rentalId][$ym] ? '1' : '0';
                }
            }
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }

    public function importForm(array $params = []): void
    {
        $this->view('lpr_rentals/import');
    }

    public function import(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/lpr-rentals/import');
        }

        $file = $_FILES['import_file'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            flash('error', 'Please choose a CSV file to import.');
            $this->redirect('/lpr-rentals/import');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            flash('error', 'Upload failed, please try again.');
            $this->redirect('/lpr-rentals/import');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            flash('error', 'Could not read the uploaded file.');
            $this->redirect('/lpr-rentals/import');
        }

        // Strip a UTF-8 BOM if present (Excel adds one on export/save).
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            flash('error', 'The file is empty.');
            $this->redirect('/lpr-rentals/import');
        }

        $header = array_map(static fn ($h): string => trim((string) $h), $header);
        $monthCols = [];
        foreach ($header as $idx => $h) {
            if (preg_match('/^\d{4}-\d{2}$/', $h)) {
                $monthCols[$idx] = $h;
            }
        }

        $required = ['Partner', 'Customer', 'Start Date', 'Coverage Months'];
        foreach ($required as $col) {
            if (!in_array($col, $header, true)) {
                fclose($handle);
                flash('error', "The file is missing a required column: {$col}.");
                $this->redirect('/lpr-rentals/import');
            }
        }
        $colIndex = array_flip($header);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count(array_filter($row, static fn ($v): bool => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }

            $partnerName = trim((string) ($row[$colIndex['Partner']] ?? ''));
            $customerName = trim((string) ($row[$colIndex['Customer']] ?? ''));
            $startDate = trim((string) ($row[$colIndex['Start Date']] ?? ''));
            $coverage = (int) ($row[$colIndex['Coverage Months']] ?? 0);
            $email = isset($colIndex['Customer Email']) ? trim((string) ($row[$colIndex['Customer Email']] ?? '')) : '';

            $startDateObj = DateTime::createFromFormat('Y-m-d', $startDate);
            if ($partnerName === '' || $customerName === '' || !$startDateObj || !in_array($coverage, self::COVERAGE_OPTIONS, true)) {
                $skipped++;
                continue;
            }

            $partnerId = LprPartner::findOrCreateByName($partnerName);
            $customerId = Customer::findOrCreateByName($customerName);

            $existingId = $this->findMatchingRental($customerId, $partnerId, $startDate);

            $data = [
                'customer_id'     => $customerId,
                'partner_id'      => $partnerId,
                'start_date'      => $startDate,
                'coverage_months' => $coverage,
                'customer_email'  => $email !== '' ? $email : null,
            ];

            if ($existingId !== null) {
                // Preserve the existing branch — a re-import shouldn't move
                // a contract to a different branch as a side effect.
                $existing = LprRental::findById($existingId);
                LprRental::update($existingId, $data + ['branch_id' => $existing['branch_id'], 'updated_by' => Auth::id()]);
                $rentalId = $existingId;
                $updated++;
            } else {
                $branchId = $this->importBranchId();
                if ($branchId === null) {
                    $skipped++;
                    continue;
                }
                $rentalId = LprRental::create($data + ['branch_id' => $branchId, 'created_by' => Auth::id()]);
                $created++;
            }

            $coveredMonths = LprRental::monthSequenceFromStart(substr($startDate, 0, 7), $coverage);
            $currentChecks = LprRental::checksByRental([$rentalId])[$rentalId] ?? [];
            foreach ($monthCols as $idx => $ym) {
                if (!in_array($ym, $coveredMonths, true)) {
                    continue;
                }
                $value = strtolower(trim((string) ($row[$idx] ?? '')));
                $wantsChecked = in_array($value, ['1', 'yes', 'true', 'checked', 'x'], true);
                $isChecked = $currentChecks[$ym] ?? false;
                if ($wantsChecked !== $isChecked) {
                    LprRental::toggleCheck($rentalId, $ym, (int) Auth::id());
                }
            }
        }

        fclose($handle);

        ActivityLog::record(Auth::id(), 'lpr_rental.import', 'lpr_rental', null, [
            'created' => $created, 'updated' => $updated, 'skipped' => $skipped,
        ]);

        flash('success', "Import complete: {$created} created, {$updated} updated" . ($skipped > 0 ? ", {$skipped} row(s) skipped (missing/invalid data)" : '') . '.');
        $this->redirect('/lpr-rentals');
    }

    /** Matches an existing (not-deleted) rental by customer+partner+start_date — the dedup key import uses to decide create vs. update. */
    private function findMatchingRental(int $customerId, int $partnerId, string $startDate): ?int
    {
        foreach (LprRental::allWithDetails() as $r) {
            if ((int) $r['customer_id'] === $customerId && (int) $r['partner_id'] === $partnerId && $r['start_date'] === $startDate) {
                return (int) $r['id'];
            }
        }
        return null;
    }

    private function formContext(): array
    {
        $myBranchId = Auth::user()['branch_id'] ?? null;
        $myBranch = $myBranchId !== null ? Branch::findById((int) $myBranchId) : null;

        return [
            'customers'       => Customer::allActive(),
            'partners'        => LprPartner::allActive(),
            'coverageOptions' => self::COVERAGE_OPTIONS,
            'canSelectBranch' => Auth::can('data.view_all_branches'),
            'branches'        => Auth::can('data.view_all_branches') ? Branch::allActive() : [],
            'myBranchId'      => $myBranchId,
            'myBranchName'    => $myBranch['name'] ?? null,
        ];
    }

    /**
     * The branch_id a contract should be saved with: whatever a
     * branch-unrestricted user (data.view_all_branches) picked in the
     * dropdown, or — for a restricted user — their own branch, ignoring
     * anything posted. Returns null if there's no valid branch to use.
     */
    private function resolveBranchId(array $input): ?int
    {
        if (!Auth::can('data.view_all_branches')) {
            $myBranchId = Auth::user()['branch_id'] ?? null;
            return $myBranchId !== null ? (int) $myBranchId : null;
        }

        $posted = (int) ($input['branch_id'] ?? 0);
        return $posted > 0 && Branch::findById($posted) ? $posted : null;
    }

    /** Branch to use for an imported row: the importer's own branch, or the first active branch if they have none (e.g. Admin). */
    private function importBranchId(): ?int
    {
        $myBranchId = Auth::user()['branch_id'] ?? null;
        if ($myBranchId !== null) {
            return (int) $myBranchId;
        }
        $first = Branch::allActive()[0] ?? null;
        return $first ? (int) $first['id'] : null;
    }

    private function validate(array $input): array
    {
        $errors = [];

        if (empty($input['customer_id']) || !Customer::findById((int) $input['customer_id'])) {
            $errors['customer_id'] = 'Please select a valid customer.';
        }

        if (empty($input['partner_id']) || !LprPartner::findById((int) $input['partner_id'])) {
            $errors['partner_id'] = 'Please select a valid partner.';
        }

        if (empty($input['start_date']) || !DateTime::createFromFormat('Y-m-d', (string) $input['start_date'])) {
            $errors['start_date'] = 'Start date is required.';
        }

        if (empty($input['coverage_months']) || !in_array((int) $input['coverage_months'], self::COVERAGE_OPTIONS, true)) {
            $errors['coverage_months'] = 'Please select a valid coverage period.';
        }

        $email = trim((string) ($input['customer_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'] = 'Please enter a valid email address.';
        }

        return $errors;
    }

    private function notFound(): never
    {
        http_response_code(404);
        require ROOT_PATH . '/views/errors/404.php';
        exit;
    }
}
