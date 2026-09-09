<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BranchAccessGuard;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\SmcContract;
use DateTime;
use Throwable;

final class SmcController extends Controller
{
    use BranchAccessGuard;

    private const COVERAGE_OPTIONS = [12, 24, 36, 48];

    public function index(array $params = []): void
    {
        $customerId = (int) $this->input('customer_id', 0) ?: null;
        $branchId = Auth::can('data.view_all_branches')
            ? ((int) $this->input('branch_id', 0) ?: null)
            : (int) (Auth::user()['branch_id'] ?? 0);

        $contracts = SmcContract::allWithDetails($customerId, $branchId);
        $months = SmcContract::globalMonthColumns();
        $statuses = SmcContract::statusesByContract(array_map(static fn (array $c): int => (int) $c['id'], $contracts));

        foreach ($contracts as &$c) {
            $c['covered_months'] = SmcContract::monthSequenceFromStart(
                substr($c['start_date'], 0, 7),
                (int) $c['coverage_months']
            );
        }
        unset($c);

        $this->view('smc/index', [
            'contracts'          => $contracts,
            'months'             => $months,
            'statuses'           => $statuses,
            'currentYm'          => date('Y-m'),
            'canManage'          => Auth::can('smc.manage'),
            'canSelectBranch'    => Auth::can('data.view_all_branches'),
            'customers'          => Customer::allActive(),
            'branches'           => Branch::allActive(),
            'selectedCustomerId' => $customerId,
            'selectedBranchId'   => $branchId,
        ]);
    }

    public function create(array $params = []): void
    {
        $this->view('smc/create', $this->formContext());
    }

    public function store(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/smc/create');
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
            $this->redirect('/smc/create');
        }

        $id = SmcContract::create([
            'customer_id'     => (int) $input['customer_id'],
            'branch_id'       => $branchId,
            'start_date'      => $input['start_date'],
            'coverage_months' => (int) $input['coverage_months'],
            'customer_email'  => trim((string) ($input['customer_email'] ?? '')) ?: null,
            'created_by'      => Auth::id(),
        ]);

        ActivityLog::record(Auth::id(), 'smc.create', 'smc_contract', $id);
        flash('success', 'SMC contract created successfully.');
        $this->redirect('/smc');
    }

    public function edit(array $params): void
    {
        $contract = SmcContract::findById((int) $params['id']);
        if (!$contract) {
            $this->notFound();
        }
        $this->assertBranchAccess($contract);

        $this->view('smc/edit', array_merge(['contract' => $contract], $this->formContext()));
    }

    public function update(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect("/smc/{$id}/edit");
        }

        $contract = SmcContract::findById($id);
        if (!$contract) {
            $this->notFound();
        }
        $this->assertBranchAccess($contract);

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
            $this->redirect("/smc/{$id}/edit");
        }

        SmcContract::update($id, [
            'customer_id'     => (int) $input['customer_id'],
            'branch_id'       => $branchId,
            'start_date'      => $input['start_date'],
            'coverage_months' => (int) $input['coverage_months'],
            'customer_email'  => trim((string) ($input['customer_email'] ?? '')) ?: null,
            'updated_by'      => Auth::id(),
        ]);

        ActivityLog::record(Auth::id(), 'smc.update', 'smc_contract', $id);
        flash('success', 'SMC contract updated successfully.');
        $this->redirect('/smc');
    }

    public function destroy(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/smc');
        }

        $contract = SmcContract::findById($id);
        if (!$contract) {
            $this->notFound();
        }
        $this->assertBranchAccess($contract);

        SmcContract::softDelete($id, (int) Auth::id());
        ActivityLog::record(Auth::id(), 'smc.delete', 'smc_contract', $id);
        flash('success', 'SMC contract deleted.');
        $this->redirect('/smc');
    }

    /** AJAX: sets one month's status (Blank/SCH/DONE) for a contract. */
    public function updateStatus(array $params): void
    {
        if (!verify_csrf()) {
            $this->json(['error' => 'Your session expired, please refresh and try again.'], 419);
        }

        $id = (int) $params['id'];
        $yearMonth = (string) $params['yearMonth'];
        $status = strtoupper(trim((string) ($_POST['status'] ?? '')));
        if ($status === 'BLANK') {
            $status = '';
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $this->json(['error' => 'Invalid month.'], 422);
        }
        if (!in_array($status, SmcContract::STATUSES, true)) {
            $this->json(['error' => 'Invalid status.'], 422);
        }

        $contract = SmcContract::findById($id);
        if (!$contract) {
            $this->json(['error' => 'Contract not found.'], 404);
        }
        if (!Auth::can('data.view_all_branches') && (int) $contract['branch_id'] !== (int) (Auth::user()['branch_id'] ?? 0)) {
            $this->json(['error' => 'Contract not found.'], 404);
        }

        $ok = SmcContract::setStatus($id, $yearMonth, $status, (int) Auth::id());
        if (!$ok) {
            $this->json(['error' => 'That month is not part of this contract\'s coverage.'], 422);
        }

        ActivityLog::record(Auth::id(), 'smc.status_update', 'smc_contract', $id, ['year_month' => $yearMonth, 'status' => $status]);
        $this->json(['status' => $status]);
    }

    public function exportCsv(array $params = []): void
    {
        $contracts = SmcContract::allWithDetails();
        $months = SmcContract::globalMonthColumns();
        $statuses = SmcContract::statusesByContract(array_map(static fn (array $c): int => (int) $c['id'], $contracts));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="smc-contracts-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly

        fputcsv($out, array_merge(
            ['Customer', 'Start Date', 'Coverage Months', 'Customer Email'],
            $months
        ));

        foreach ($contracts as $c) {
            $contractId = (int) $c['id'];
            $row = [
                $c['customer_name'],
                $c['start_date'],
                $c['coverage_months'],
                $c['customer_email'] ?? '',
            ];
            foreach ($months as $ym) {
                if (!array_key_exists($ym, $statuses[$contractId] ?? [])) {
                    $row[] = ''; // month outside this contract's coverage
                } else {
                    $row[] = $statuses[$contractId][$ym] ?: 'BLANK';
                }
            }
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }

    public function importForm(array $params = []): void
    {
        $this->view('smc/import');
    }

    public function import(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/smc/import');
        }

        $file = $_FILES['import_file'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            flash('error', 'Please choose a CSV file to import.');
            $this->redirect('/smc/import');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            flash('error', 'Upload failed, please try again.');
            $this->redirect('/smc/import');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            flash('error', 'Could not read the uploaded file.');
            $this->redirect('/smc/import');
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
            $this->redirect('/smc/import');
        }

        $header = array_map(static fn ($h): string => trim((string) $h), $header);
        $monthCols = [];
        foreach ($header as $idx => $h) {
            if (preg_match('/^\d{4}-\d{2}$/', $h)) {
                $monthCols[$idx] = $h;
            }
        }

        $required = ['Customer', 'Start Date', 'Coverage Months'];
        foreach ($required as $col) {
            if (!in_array($col, $header, true)) {
                fclose($handle);
                flash('error', "The file is missing a required column: {$col}.");
                $this->redirect('/smc/import');
            }
        }
        $colIndex = array_flip($header);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, static fn ($v): bool => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }

            $customerName = trim((string) ($row[$colIndex['Customer']] ?? ''));
            $startDate = trim((string) ($row[$colIndex['Start Date']] ?? ''));
            $coverage = (int) ($row[$colIndex['Coverage Months']] ?? 0);
            $email = isset($colIndex['Customer Email']) ? trim((string) ($row[$colIndex['Customer Email']] ?? '')) : '';

            $startDateObj = DateTime::createFromFormat('Y-m-d', $startDate);
            if ($customerName === '' || !$startDateObj || !in_array($coverage, self::COVERAGE_OPTIONS, true)) {
                $skipped++;
                continue;
            }

            $customerId = Customer::findOrCreateByName($customerName);

            $existingId = $this->findMatchingContract($customerId, $startDate);

            $data = [
                'customer_id'     => $customerId,
                'start_date'      => $startDate,
                'coverage_months' => $coverage,
                'customer_email'  => $email !== '' ? $email : null,
            ];

            if ($existingId !== null) {
                // Preserve the existing branch — a re-import shouldn't move
                // a contract to a different branch as a side effect.
                $existing = SmcContract::findById($existingId);
                SmcContract::update($existingId, $data + ['branch_id' => $existing['branch_id'], 'updated_by' => Auth::id()]);
                $contractId = $existingId;
                $updated++;
            } else {
                $branchId = $this->importBranchId();
                if ($branchId === null) {
                    $skipped++;
                    continue;
                }
                $contractId = SmcContract::create($data + ['branch_id' => $branchId, 'created_by' => Auth::id()]);
                $created++;
            }

            $coveredMonths = SmcContract::monthSequenceFromStart(substr($startDate, 0, 7), $coverage);
            foreach ($monthCols as $idx => $ym) {
                if (!in_array($ym, $coveredMonths, true)) {
                    continue;
                }
                $value = strtoupper(trim((string) ($row[$idx] ?? '')));
                if ($value === 'BLANK') {
                    $value = '';
                }
                if (!in_array($value, SmcContract::STATUSES, true)) {
                    continue; // leave whatever it already was
                }
                SmcContract::setStatus($contractId, $ym, $value, (int) Auth::id());
            }
        }

        fclose($handle);

        ActivityLog::record(Auth::id(), 'smc.import', 'smc_contract', null, [
            'created' => $created, 'updated' => $updated, 'skipped' => $skipped,
        ]);

        flash('success', "Import complete: {$created} created, {$updated} updated" . ($skipped > 0 ? ", {$skipped} row(s) skipped (missing/invalid data)" : '') . '.');
        $this->redirect('/smc');
    }

    /** Matches an existing (not-deleted) contract by customer+start_date — the dedup key import uses to decide create vs. update. */
    private function findMatchingContract(int $customerId, string $startDate): ?int
    {
        foreach (SmcContract::allWithDetails() as $c) {
            if ((int) $c['customer_id'] === $customerId && $c['start_date'] === $startDate) {
                return (int) $c['id'];
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
