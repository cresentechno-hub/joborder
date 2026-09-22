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
use App\Models\User;
use App\Services\FileUploadService;
use DateTime;
use Throwable;

final class SmcController extends Controller
{
    use BranchAccessGuard;

    private const COVERAGE_OPTIONS = [12, 24, 36, 48];

    public function index(array $params = []): void
    {
        $customerId = (int) $this->input('customer_id', 0) ?: null;
        $canSelectBranch = Auth::can('data.view_all_branches');
        // Unrestricted users pick ONE branch at a time from the filter
        // dropdown (or none = "All Branches"); a restricted user's query is
        // always scoped to the full set of branches they belong to.
        $selectedBranchId = $canSelectBranch ? ((int) $this->input('branch_id', 0) ?: null) : null;
        $branchIds = $canSelectBranch
            ? ($selectedBranchId !== null ? [$selectedBranchId] : null)
            : (Auth::user()['branch_ids'] ?? []);

        $contracts = SmcContract::allWithDetails($customerId, $branchIds);
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
            'canSelectBranch'    => $canSelectBranch,
            'customers'          => Customer::allActive(),
            'branches'           => Branch::allActive(),
            'selectedCustomerId' => $customerId,
            'selectedBranchId'   => $selectedBranchId,
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
            'customer_id'       => Customer::findOrCreateByName(trim($input['customer_name'])),
            'branch_id'         => $branchId,
            'start_date'        => $input['start_date'],
            'coverage_months'   => (int) $input['coverage_months'],
            'customer_email'    => trim((string) ($input['customer_email'] ?? '')) ?: null,
            'assigned_to'       => trim((string) ($input['assigned_to'] ?? '')) !== '' ? (int) $input['assigned_to'] : null,
            'quotation_no'      => trim((string) ($input['quotation_no'] ?? '')) ?: null,
            'short_name'        => trim((string) ($input['short_name'] ?? '')) ?: null,
            'site'              => trim((string) ($input['site'] ?? '')) ?: null,
            'service_frequency' => trim((string) ($input['service_frequency'] ?? '')) ?: null,
            'service_date'      => trim((string) ($input['service_date'] ?? '')) ?: null,
            'payment_term'      => trim((string) ($input['payment_term'] ?? '')) ?: null,
            'contract_status'   => trim((string) ($input['contract_status'] ?? '')) ?: null,
            'description'       => trim((string) ($input['description'] ?? '')) ?: null,
            'created_by'        => Auth::id(),
        ]);

        $ccUserIds = array_filter(array_map('intval', (array) ($input['cc_users'] ?? [])));
        SmcContract::attachCcUsers($id, $ccUserIds);

        // Contract file scoping key is this row's own id, only known now
        // that create() has run — see smc_contract_upload_dir() in helpers.php.
        if (!empty($_FILES['contract_file']['name'])) {
            try {
                $contract = FileUploadService::upload($_FILES['contract_file'], smc_contract_upload_dir($id, 'contract'));
                SmcContract::updateContractFile($id, smc_contract_upload_rel($id, 'contract') . '/' . $contract['stored_name'], $contract['original_name']);
            } catch (Throwable $e) {
                flash('error', 'Contract created, but the file upload failed: ' . $e->getMessage());
                $this->redirect("/smc/{$id}/edit");
            }
        }

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

        $this->view('smc/edit', array_merge(
            ['contract' => $contract, 'selectedCc' => SmcContract::getCcUserIds((int) $contract['id'])],
            $this->formContext()
        ));
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
            'customer_id'       => Customer::findOrCreateByName(trim($input['customer_name'])),
            'branch_id'         => $branchId,
            'start_date'        => $input['start_date'],
            'coverage_months'   => (int) $input['coverage_months'],
            'customer_email'    => trim((string) ($input['customer_email'] ?? '')) ?: null,
            'assigned_to'       => trim((string) ($input['assigned_to'] ?? '')) !== '' ? (int) $input['assigned_to'] : null,
            'quotation_no'      => trim((string) ($input['quotation_no'] ?? '')) ?: null,
            'short_name'        => trim((string) ($input['short_name'] ?? '')) ?: null,
            'site'              => trim((string) ($input['site'] ?? '')) ?: null,
            'service_frequency' => trim((string) ($input['service_frequency'] ?? '')) ?: null,
            'service_date'      => trim((string) ($input['service_date'] ?? '')) ?: null,
            'payment_term'      => trim((string) ($input['payment_term'] ?? '')) ?: null,
            'contract_status'   => trim((string) ($input['contract_status'] ?? '')) ?: null,
            'description'       => trim((string) ($input['description'] ?? '')) ?: null,
            'updated_by'        => Auth::id(),
        ]);

        $ccUserIds = array_filter(array_map('intval', (array) ($input['cc_users'] ?? [])));
        SmcContract::replaceCcUsers($id, $ccUserIds);

        if (!empty($_FILES['contract_file']['name'])) {
            try {
                $contract = FileUploadService::upload($_FILES['contract_file'], smc_contract_upload_dir($id, 'contract'));
                SmcContract::updateContractFile($id, smc_contract_upload_rel($id, 'contract') . '/' . $contract['stored_name'], $contract['original_name']);
            } catch (Throwable $e) {
                flash('error', 'Contract updated, but the file upload failed: ' . $e->getMessage());
                $this->redirect("/smc/{$id}/edit");
            }
        }

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
        if (!Auth::can('data.view_all_branches') && !in_array((int) $contract['branch_id'], Auth::user()['branch_ids'] ?? [], true)) {
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
        $myBranchIds = Auth::user()['branch_ids'] ?? [];
        $myBranches = array_values(array_filter(array_map(
            static fn (int $id): ?array => Branch::findById($id),
            $myBranchIds
        )));

        return [
            'customers'       => Customer::allActive(),
            'users'           => User::allActive(),
            'coverageOptions' => self::COVERAGE_OPTIONS,
            'canSelectBranch' => Auth::can('data.view_all_branches'),
            'branches'        => Auth::can('data.view_all_branches') ? Branch::allActive() : [],
            'myBranchIds'     => $myBranchIds,
            'myBranches'      => $myBranches,
        ];
    }

    /**
     * The branch_id a contract should be saved with: whatever a
     * branch-unrestricted user (data.view_all_branches) picked in the
     * dropdown, or — for a restricted user — their own branch if they only
     * have one, or their own pick among their own branches if they have
     * several. Returns null if there's no valid branch to use.
     */
    private function resolveBranchId(array $input): ?int
    {
        if (!Auth::can('data.view_all_branches')) {
            $myBranchIds = Auth::user()['branch_ids'] ?? [];
            if (count($myBranchIds) === 1) {
                return $myBranchIds[0];
            }
            $posted = (int) ($input['branch_id'] ?? 0);
            return $posted > 0 && in_array($posted, $myBranchIds, true) ? $posted : null;
        }

        $posted = (int) ($input['branch_id'] ?? 0);
        return $posted > 0 && Branch::findById($posted) ? $posted : null;
    }

    /** Branch to use for an imported row: the first of the importer's own branches, or the first active branch if they have none (e.g. Admin). */
    private function importBranchId(): ?int
    {
        $myBranchIds = Auth::user()['branch_ids'] ?? [];
        if (!empty($myBranchIds)) {
            return $myBranchIds[0];
        }
        $first = Branch::allActive()[0] ?? null;
        return $first ? (int) $first['id'] : null;
    }

    private function validate(array $input): array
    {
        $errors = [];

        if (trim((string) ($input['customer_name'] ?? '')) === '') {
            $errors['customer_name'] = 'Customer is required.';
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

        $serviceDate = trim((string) ($input['service_date'] ?? ''));
        if ($serviceDate !== '' && !DateTime::createFromFormat('Y-m-d', $serviceDate)) {
            $errors['service_date'] = 'Please enter a valid service date.';
        }

        $assignedTo = trim((string) ($input['assigned_to'] ?? ''));
        if ($assignedTo !== '' && !User::findById((int) $assignedTo)) {
            $errors['assigned_to'] = 'Please select a valid user.';
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
