<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\JobOrderAccessGuard;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\JobOrder;
use App\Models\JobOrderComment;
use App\Models\JobOrderDocument;
use App\Models\JobStage;
use App\Models\User;
use App\Services\AiQuotationExtractor;
use App\Services\FileUploadService;
use DateTime;
use Throwable;

final class JobOrderController extends Controller
{
    use JobOrderAccessGuard;

    public function index(array $params = []): void
    {
        $canViewCompleted = Auth::can('job_order.view_completed');

        $filters = [
            'q'             => trim((string) $this->input('q', '')),
            'stage_id'      => (int) $this->input('stage_id', 0) ?: null,
            'customer_name' => trim((string) $this->input('customer_name', '')) ?: null,
            'sort'          => trim((string) $this->input('sort', '')) ?: null,
            'dir'           => trim((string) $this->input('dir', '')) ?: null,
        ];
        if (!Auth::can('data.view_all_branches')) {
            // 0 (never a real branch id) when the user has no branch yet —
            // restricted with nothing to show, never "unrestricted" by accident.
            $filters['branch_id'] = (int) (Auth::user()['branch_id'] ?? 0);
        }
        if (!$canViewCompleted) {
            $filters['hide_completed'] = true;
        }
        $page = max(1, (int) $this->input('page', 1));
        $perPage = max(1, (int) setting('items_per_page', '20'));

        $result = JobOrder::paginate($filters, $page, $perPage);

        // Don't offer "7 - Sales Completed" / "8 - Cancel PO" as filter
        // options to a viewer who'd never get results back for them.
        $stages = JobStage::allActive();
        if (!$canViewCompleted) {
            $stages = array_values(array_filter(
                $stages,
                static fn (array $s): bool => !in_array($s['stage_code'], ['7', '8'], true)
            ));
        }

        $this->view('job_orders/index', [
            'jobOrders'     => $result['data'],
            'total'         => $result['total'],
            'page'          => $result['page'],
            'perPage'       => $result['per_page'],
            'stages'        => $stages,
            'customerNames' => JobOrder::distinctCustomerNames(),
            'filters'       => $filters,
        ]);
    }

    public function create(array $params = []): void
    {
        $this->view('job_orders/create', array_merge(
            ['stages' => JobStage::allActive()],
            $this->branchContext()
        ));
    }

    /**
     * AJAX endpoint backing the "Read Quotation" button on both the create
     * and edit forms. Best-effort extraction only (PDF or a photo/scan of
     * one) — never blocks or replaces manual entry. Doesn't persist the
     * file; the same file gets uploaded again for real when the form is
     * actually submitted.
     */
    public function extractQuotation(array $params = []): void
    {
        if (!verify_csrf()) {
            $this->json(['error' => 'Your session expired, please refresh and try again.'], 419);
        }

        $file = $_FILES['quotation_file'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $this->json(['error' => 'No file was uploaded.'], 422);
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $this->json(['error' => 'Upload failed, please try again.'], 422);
        }

        $maxBytes = (int) setting('upload_max_size_mb', (string) UPLOAD_MAX_SIZE_MB) * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            $this->json(['error' => 'File is too large.'], 422);
        }

        $this->json(AiQuotationExtractor::extract($file['tmp_name'], $file['name']));
    }

    public function store(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/job-orders/create');
        }

        $input = $_POST;
        $errors = $this->validate($input);

        if (empty($_FILES['quotation_file']['name'])) {
            $errors['quotation_file'] = 'Quotation file is required.';
        }

        $assigneeIds = $this->assigneeIdsFromInput($input);
        if (empty($errors['assigned_to'])) {
            $branchError = $this->firstBranchAssignmentError($assigneeIds);
            if ($branchError !== null) {
                $errors['assigned_to'] = $branchError;
            }
        }

        $branchId = $this->resolveBranchId($input);
        if ($branchId === null) {
            $errors['branch_id'] = Auth::can('data.view_all_branches')
                ? 'Please select a valid branch.'
                : 'You have no branch assigned — ask an Admin to assign you one before creating job orders.';
        }

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect('/job-orders/create');
        }

        $quotationNo = trim($input['quotation_no']);

        try {
            $quotation = FileUploadService::upload($_FILES['quotation_file'], job_order_upload_dir($quotationNo, 'quotation'));
        } catch (Throwable $e) {
            flash_input($input);
            flash_errors(['quotation_file' => 'Quotation upload failed: ' . $e->getMessage()]);
            $this->redirect('/job-orders/create');
        }

        $po = null;
        if (!empty($_FILES['po_file']['name'])) {
            try {
                $po = FileUploadService::upload($_FILES['po_file'], job_order_upload_dir($quotationNo, 'po'));
            } catch (Throwable $e) {
                flash_input($input);
                flash_errors(['po_file' => 'PO upload failed: ' . $e->getMessage()]);
                $this->redirect('/job-orders/create');
            }
        }

        $id = JobOrder::create([
            'quotation_no'                  => $quotationNo,
            'customer_name'                 => trim($input['customer_name']),
            'subject'                       => trim($input['subject']),
            'total_cost'                    => (float) $input['total_cost'],
            'quotation_file_path'           => job_order_upload_rel($quotationNo, 'quotation') . '/' . $quotation['stored_name'],
            'quotation_file_original_name'  => $quotation['original_name'],
            'po_file_path'                  => $po ? job_order_upload_rel($quotationNo, 'po') . '/' . $po['stored_name'] : null,
            'po_file_original_name'         => $po ? $po['original_name'] : null,
            'job_start_date'                => $input['job_start_date'],
            'assigned_to'                   => $assigneeIds,
            'branch_id'                     => $branchId,
            'stage_id'                      => (int) $input['stage_id'],
            'remarks'                       => trim((string) ($input['remarks'] ?? '')) ?: null,
            'created_by'                    => Auth::id(),
        ]);

        $this->uploadOtherDocuments($id, $quotationNo);

        $this->notifyNewAssignees(
            ['id' => $id, 'quotation_no' => $quotationNo, 'customer_name' => trim($input['customer_name']), 'subject' => trim($input['subject'])],
            $assigneeIds
        );

        ActivityLog::record(Auth::id(), 'job_order.create', 'job_order', $id);
        flash('success', 'Job order created successfully.');
        $this->redirect('/job-orders');
    }

    public function edit(array $params): void
    {
        $jobOrder = JobOrder::findById((int) $params['id']);
        if (!$jobOrder) {
            $this->notFound();
        }
        $this->assertBranchAccess($jobOrder);
        $this->assertStageAccess($jobOrder);

        $this->view('job_orders/edit', array_merge(
            [
                'jobOrder'    => $jobOrder,
                'stages'      => JobStage::allActive(),
                'comments'    => JobOrderComment::findByJobOrderId((int) $jobOrder['id']),
                'assigneeIds' => JobOrder::getAssigneeIds((int) $jobOrder['id']),
                'documents'   => JobOrderDocument::findByJobOrderId((int) $jobOrder['id']),
            ],
            $this->branchContext()
        ));
    }

    /** @return int[] deduped, cast-to-int assignee IDs pulled from `assigned_to[]` (or a lone `assigned_to`). */
    private function assigneeIdsFromInput(array $input): array
    {
        $raw = (array) ($input['assigned_to'] ?? []);
        return array_values(array_unique(array_filter(array_map('intval', $raw))));
    }

    /** @param int[] $assigneeIds */
    private function firstBranchAssignmentError(array $assigneeIds): ?string
    {
        foreach ($assigneeIds as $id) {
            $error = $this->assertOwnBranchAssignment($id);
            if ($error !== null) {
                return $error;
            }
        }
        return null;
    }

    /**
     * The branch_id a job order should be saved with: whatever a
     * branch-unrestricted user (data.view_all_branches) picked in the
     * dropdown, or — for a restricted user — their own branch, ignoring
     * anything posted (never trust the client for this). Returns null if
     * there's no valid branch to use (invalid pick, or a restricted user
     * with no branch of their own).
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

    /**
     * Shared by create() and edit(): the "Assign To" list a user is allowed
     * to pick from, plus the UI variant (fixed branch label vs. a real
     * Branch dropdown). A branch-restricted user (no data.view_all_branches)
     * can assign to colleagues at their own branch or anyone with no branch
     * at all (Admin, Manager, other company staff), but never someone at a
     * DIFFERENT branch — enforced again server-side in
     * assertOwnBranchAssignment(), this alone is just what's offered in the
     * UI. A branchless Sales user (edge case) only sees unaffiliated users,
     * having no "own branch" of their own to also include.
     */
    private function branchContext(): array
    {
        $canSelectBranch = Auth::can('data.view_all_branches');
        $myBranchId = Auth::user()['branch_id'] ?? null;

        // Degrades to "unaffiliated users only" when the current user has
        // no branch of their own.
        $users = $canSelectBranch
            ? User::allActive()
            : User::allActiveOwnBranchOrUnaffiliated($myBranchId);

        $myBranch = $myBranchId !== null ? Branch::findById((int) $myBranchId) : null;

        return [
            'users'           => $users,
            'branches'        => $canSelectBranch ? Branch::allActive() : [],
            'canSelectBranch' => $canSelectBranch,
            'myBranchId'      => $myBranchId,
            'myBranchName'    => $myBranch['name'] ?? null,
        ];
    }

    public function update(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect("/job-orders/{$id}/edit");
        }

        $jobOrder = JobOrder::findById($id);
        if (!$jobOrder) {
            $this->notFound();
        }
        $this->assertBranchAccess($jobOrder);
        $this->assertStageAccess($jobOrder);

        $oldAssigneeIds = JobOrder::getAssigneeIds($id);

        $input = $_POST;
        $errors = $this->validate($input, $id);

        $assigneeIds = $this->assigneeIdsFromInput($input);
        if (empty($errors['assigned_to'])) {
            $branchError = $this->firstBranchAssignmentError($assigneeIds);
            if ($branchError !== null) {
                $errors['assigned_to'] = $branchError;
            }
        }

        $branchId = $this->resolveBranchId($input);
        if ($branchId === null) {
            $errors['branch_id'] = Auth::can('data.view_all_branches')
                ? 'Please select a valid branch.'
                : 'You have no branch assigned — ask an Admin to assign you one.';
        }

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect("/job-orders/{$id}/edit");
        }

        $newQuotationNo = trim($input['quotation_no']);

        // Keep the per-order upload folder in sync with the quotation no
        // BEFORE touching any files below, so new uploads this request
        // (and the existing quotation/PO paths, if not replaced) land
        // under the correct, current folder name.
        $this->renameFolderIfQuotationNoChanged($id, $jobOrder['quotation_no'], $newQuotationNo);

        $data = [
            'quotation_no'   => $newQuotationNo,
            'customer_name'  => trim($input['customer_name']),
            'subject'        => trim($input['subject']),
            'total_cost'     => (float) $input['total_cost'],
            'job_start_date' => $input['job_start_date'],
            'assigned_to'    => $assigneeIds,
            'branch_id'      => $branchId,
            'stage_id'       => (int) $input['stage_id'],
            'remarks'        => trim((string) ($input['remarks'] ?? '')) ?: null,
            'updated_by'     => Auth::id(),
        ];

        if (!empty($_FILES['quotation_file']['name'])) {
            try {
                $quotation = FileUploadService::upload($_FILES['quotation_file'], job_order_upload_dir($newQuotationNo, 'quotation'));
                $data['quotation_file_path'] = job_order_upload_rel($newQuotationNo, 'quotation') . '/' . $quotation['stored_name'];
                $data['quotation_file_original_name'] = $quotation['original_name'];
            } catch (Throwable $e) {
                flash_input($input);
                flash_errors(['quotation_file' => 'Quotation upload failed: ' . $e->getMessage()]);
                $this->redirect("/job-orders/{$id}/edit");
            }
        }

        if (!empty($_FILES['po_file']['name'])) {
            try {
                $po = FileUploadService::upload($_FILES['po_file'], job_order_upload_dir($newQuotationNo, 'po'));
                $data['po_file_path'] = job_order_upload_rel($newQuotationNo, 'po') . '/' . $po['stored_name'];
                $data['po_file_original_name'] = $po['original_name'];
            } catch (Throwable $e) {
                flash_input($input);
                flash_errors(['po_file' => 'PO upload failed: ' . $e->getMessage()]);
                $this->redirect("/job-orders/{$id}/edit");
            }
        }

        JobOrder::update($id, $data);
        $this->uploadOtherDocuments($id, $newQuotationNo);

        $this->notifyNewAssignees(
            ['id' => $id, 'quotation_no' => $newQuotationNo, 'customer_name' => $data['customer_name'], 'subject' => $data['subject']],
            array_diff($assigneeIds, $oldAssigneeIds)
        );

        ActivityLog::record(Auth::id(), 'job_order.update', 'job_order', $id);
        flash('success', 'Job order updated successfully.');
        $this->redirect('/job-orders');
    }

    public function destroyDocument(array $params): void
    {
        $jobOrderId = (int) $params['id'];
        $documentId = (int) $params['documentId'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect("/job-orders/{$jobOrderId}/edit");
        }

        $jobOrder = JobOrder::findById($jobOrderId);
        if (!$jobOrder) {
            $this->notFound();
        }
        $this->assertBranchAccess($jobOrder);
        $this->assertStageAccess($jobOrder);

        $document = JobOrderDocument::findById($documentId);
        if (!$document || (int) $document['job_order_id'] !== $jobOrderId) {
            $this->notFound();
        }

        $absolute = ROOT_PATH . '/public/' . $document['file_path'];
        if (is_file($absolute)) {
            @unlink($absolute);
        }

        JobOrderDocument::delete($documentId);
        ActivityLog::record(Auth::id(), 'job_order.document_delete', 'job_order', $jobOrderId);
        flash('success', 'Document deleted.');
        $this->redirect("/job-orders/{$jobOrderId}/edit");
    }

    /**
     * Renames the physical per-order upload folder (and rewrites every
     * stored path that referenced it) when the quotation no changes on
     * edit. A no-op if the sanitized folder name is unchanged, or if
     * nothing has ever been uploaded under the old name yet.
     */
    private function renameFolderIfQuotationNoChanged(int $id, string $oldQuotationNo, string $newQuotationNo): void
    {
        if ($oldQuotationNo === $newQuotationNo) {
            return;
        }

        $oldFolder = job_order_folder_name($oldQuotationNo);
        $newFolder = job_order_folder_name($newQuotationNo);
        if ($oldFolder === $newFolder) {
            return;
        }

        $oldDir = UPLOAD_BASE_DIR . '/' . $oldFolder;
        if (!is_dir($oldDir)) {
            return;
        }

        $newDir = UPLOAD_BASE_DIR . '/' . $newFolder;
        if (is_dir($newDir)) {
            // Extremely rare sanitization collision between two different
            // quotation numbers — keep them apart rather than merging.
            $newFolder .= '-' . $id;
            $newDir = UPLOAD_BASE_DIR . '/' . $newFolder;
        }

        if (!@rename($oldDir, $newDir)) {
            return; // leave paths pointing at the still-valid old folder
        }

        $oldRel = UPLOAD_BASE_REL . '/' . $oldFolder;
        $newRel = UPLOAD_BASE_REL . '/' . $newFolder;
        JobOrder::rewritePathsForRenamedFolder($id, $oldRel, $newRel);
        JobOrderComment::rewritePathsForRenamedFolder($id, $oldRel, $newRel);
        JobOrderDocument::rewritePathsForRenamedFolder($id, $oldRel, $newRel);
    }

    /**
     * Uploads any files attached via the "Other Documents" multi-file
     * input. Failures here are flashed as warnings rather than blocking
     * the redirect — the job order itself is already saved by this point,
     * and a bad extra attachment shouldn't undo that.
     */
    private function uploadOtherDocuments(int $jobOrderId, string $quotationNo): void
    {
        if (empty($_FILES['other_documents']['name'][0])) {
            return;
        }

        $targetDir = job_order_upload_dir($quotationNo, 'other');
        $targetRel = job_order_upload_rel($quotationNo, 'other');
        $failures = [];

        $count = count($_FILES['other_documents']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['other_documents']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $file = [
                'name'     => $_FILES['other_documents']['name'][$i],
                'type'     => $_FILES['other_documents']['type'][$i],
                'tmp_name' => $_FILES['other_documents']['tmp_name'][$i],
                'error'    => $_FILES['other_documents']['error'][$i],
                'size'     => $_FILES['other_documents']['size'][$i],
            ];

            try {
                $uploaded = FileUploadService::upload($file, $targetDir);
                JobOrderDocument::create($jobOrderId, $targetRel . '/' . $uploaded['stored_name'], $uploaded['original_name'], (int) Auth::id());
            } catch (Throwable $e) {
                $failures[] = $file['name'] . ': ' . $e->getMessage();
            }
        }

        if (!empty($failures)) {
            flash('error', 'Some documents could not be uploaded — ' . implode('; ', $failures));
        }
    }

    public function destroy(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/job-orders');
        }

        $jobOrder = JobOrder::findById($id);
        if (!$jobOrder) {
            $this->notFound();
        }
        $this->assertBranchAccess($jobOrder);
        $this->assertStageAccess($jobOrder);

        JobOrder::softDelete($id, (int) Auth::id());
        ActivityLog::record(Auth::id(), 'job_order.delete', 'job_order', $id);
        flash('success', 'Job order deleted.');
        $this->redirect('/job-orders');
    }

    private function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];

        $quotationNo = trim((string) ($input['quotation_no'] ?? ''));
        if ($quotationNo === '') {
            $errors['quotation_no'] = 'Quotation No is required.';
        } elseif (JobOrder::quotationNoExists($quotationNo, $excludeId)) {
            $errors['quotation_no'] = 'This Quotation No already exists.';
        }

        if (trim((string) ($input['customer_name'] ?? '')) === '') {
            $errors['customer_name'] = 'Customer Name is required.';
        }

        if (trim((string) ($input['subject'] ?? '')) === '') {
            $errors['subject'] = 'Subject is required.';
        }

        if (!isset($input['total_cost']) || !is_numeric($input['total_cost']) || (float) $input['total_cost'] < 0) {
            $errors['total_cost'] = 'Total Cost must be a valid non-negative number.';
        }

        if (empty($input['job_start_date']) || !DateTime::createFromFormat('Y-m-d', (string) $input['job_start_date'])) {
            $errors['job_start_date'] = 'Job Start Date is required.';
        }

        $assigneeIds = $this->assigneeIdsFromInput($input);
        if (empty($assigneeIds)) {
            $errors['assigned_to'] = 'Please select at least one user to assign.';
        } else {
            foreach ($assigneeIds as $aid) {
                if (!User::findById($aid)) {
                    $errors['assigned_to'] = 'One of the selected assignees is not valid.';
                    break;
                }
            }
        }

        if (empty($input['stage_id']) || !JobStage::findById((int) $input['stage_id'])) {
            $errors['stage_id'] = 'Please select a valid job stage.';
        }

        return $errors;
    }
}
