<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\JobOrder;
use App\Models\JobStage;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\QuotationExtractor;
use DateTime;
use Throwable;

final class JobOrderController extends Controller
{
    public function index(array $params = []): void
    {
        $canViewCompleted = Auth::can('job_order.view_completed');

        $filters = [
            'q'        => trim((string) $this->input('q', '')),
            'stage_id' => (int) $this->input('stage_id', 0) ?: null,
        ];
        if (!Auth::can('job_order.view_all')) {
            // 0 (not null) when the user has no team yet — restricted with
            // nothing to show, never "unrestricted" by accident.
            $filters['team_id'] = (int) (Auth::user()['team_id'] ?? 0);
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
            'jobOrders' => $result['data'],
            'total'     => $result['total'],
            'page'      => $result['page'],
            'perPage'   => $result['per_page'],
            'stages'    => $stages,
            'filters'   => $filters,
        ]);
    }

    public function create(array $params = []): void
    {
        $this->view('job_orders/create', [
            'stages' => JobStage::allActive(),
            'users'  => User::allActive(),
        ]);
    }

    /**
     * AJAX endpoint backing the "Read Quotation" button on the create form.
     * Best-effort text extraction only — never blocks or replaces manual
     * entry. Doesn't persist the file; the same file gets uploaded again
     * for real when the form is actually submitted.
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

        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            $this->json(['quotation_no' => null, 'customer_name' => null, 'total_cost' => null,
                'note' => 'Auto-fill only works for PDF quotations — please enter the details manually.']);
        }

        $this->json(QuotationExtractor::extract($file['tmp_name']));
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

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect('/job-orders/create');
        }

        try {
            $quotation = FileUploadService::upload($_FILES['quotation_file'], UPLOAD_QUOTATION_DIR);
        } catch (Throwable $e) {
            flash_input($input);
            flash_errors(['quotation_file' => 'Quotation upload failed: ' . $e->getMessage()]);
            $this->redirect('/job-orders/create');
        }

        $po = null;
        if (!empty($_FILES['po_file']['name'])) {
            try {
                $po = FileUploadService::upload($_FILES['po_file'], UPLOAD_PO_DIR);
            } catch (Throwable $e) {
                flash_input($input);
                flash_errors(['po_file' => 'PO upload failed: ' . $e->getMessage()]);
                $this->redirect('/job-orders/create');
            }
        }

        $id = JobOrder::create([
            'quotation_no'                  => trim($input['quotation_no']),
            'customer_name'                 => trim($input['customer_name']),
            'subject'                       => trim($input['subject']),
            'total_cost'                    => (float) $input['total_cost'],
            'quotation_file_path'           => UPLOAD_QUOTATION_REL . '/' . $quotation['stored_name'],
            'quotation_file_original_name'  => $quotation['original_name'],
            'po_file_path'                  => $po ? UPLOAD_PO_REL . '/' . $po['stored_name'] : null,
            'po_file_original_name'         => $po ? $po['original_name'] : null,
            'job_start_date'                => $input['job_start_date'],
            'assigned_to'                   => (int) $input['assigned_to'],
            'stage_id'                      => (int) $input['stage_id'],
            'remarks'                       => trim((string) ($input['remarks'] ?? '')) ?: null,
            'created_by'                    => Auth::id(),
        ]);

        ActivityLog::record(Auth::id(), 'job_order.create', 'job_order', $id);
        flash('success', 'Job order created successfully.');
        $this->redirect('/job-orders/' . $id . '/edit');
    }

    public function edit(array $params): void
    {
        $jobOrder = JobOrder::findById((int) $params['id']);
        if (!$jobOrder) {
            $this->notFound();
        }
        $this->assertTeamAccess($jobOrder);
        $this->assertStageAccess($jobOrder);

        $this->view('job_orders/edit', [
            'jobOrder' => $jobOrder,
            'stages'   => JobStage::allActive(),
            'users'    => User::allActive(),
        ]);
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
        $this->assertTeamAccess($jobOrder);
        $this->assertStageAccess($jobOrder);

        $input = $_POST;
        $errors = $this->validate($input, $id);

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect("/job-orders/{$id}/edit");
        }

        $data = [
            'quotation_no'   => trim($input['quotation_no']),
            'customer_name'  => trim($input['customer_name']),
            'subject'        => trim($input['subject']),
            'total_cost'     => (float) $input['total_cost'],
            'job_start_date' => $input['job_start_date'],
            'assigned_to'    => (int) $input['assigned_to'],
            'stage_id'       => (int) $input['stage_id'],
            'remarks'        => trim((string) ($input['remarks'] ?? '')) ?: null,
            'updated_by'     => Auth::id(),
        ];

        if (!empty($_FILES['quotation_file']['name'])) {
            try {
                $quotation = FileUploadService::upload($_FILES['quotation_file'], UPLOAD_QUOTATION_DIR);
                $data['quotation_file_path'] = UPLOAD_QUOTATION_REL . '/' . $quotation['stored_name'];
                $data['quotation_file_original_name'] = $quotation['original_name'];
            } catch (Throwable $e) {
                flash_input($input);
                flash_errors(['quotation_file' => 'Quotation upload failed: ' . $e->getMessage()]);
                $this->redirect("/job-orders/{$id}/edit");
            }
        }

        if (!empty($_FILES['po_file']['name'])) {
            try {
                $po = FileUploadService::upload($_FILES['po_file'], UPLOAD_PO_DIR);
                $data['po_file_path'] = UPLOAD_PO_REL . '/' . $po['stored_name'];
                $data['po_file_original_name'] = $po['original_name'];
            } catch (Throwable $e) {
                flash_input($input);
                flash_errors(['po_file' => 'PO upload failed: ' . $e->getMessage()]);
                $this->redirect("/job-orders/{$id}/edit");
            }
        }

        JobOrder::update($id, $data);

        ActivityLog::record(Auth::id(), 'job_order.update', 'job_order', $id);
        flash('success', 'Job order updated successfully.');
        $this->redirect("/job-orders/{$id}/edit");
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
        $this->assertTeamAccess($jobOrder);
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

        if (empty($input['assigned_to']) || !User::findById((int) $input['assigned_to'])) {
            $errors['assigned_to'] = 'Please select a valid user to assign.';
        }

        if (empty($input['stage_id']) || !JobStage::findById((int) $input['stage_id'])) {
            $errors['stage_id'] = 'Please select a valid job stage.';
        }

        return $errors;
    }

    /**
     * Enforces the same team scoping as the list view on direct access by ID
     * (edit/update/delete) — without this, a Sales user could bypass the
     * list filter entirely just by guessing/typing another team's job order URL.
     * 404s rather than 403s so team membership isn't leaked either way.
     */
    private function assertTeamAccess(array $jobOrder): void
    {
        if (Auth::can('job_order.view_all')) {
            return;
        }

        $assignee = User::findById((int) $jobOrder['assigned_to']);
        $assigneeTeamId = $assignee['team_id'] ?? null;
        $myTeamId = Auth::user()['team_id'] ?? null;

        if ($myTeamId === null || $assigneeTeamId === null || (int) $assigneeTeamId !== (int) $myTeamId) {
            $this->notFound();
        }
    }

    /**
     * Completed (stage 7) / Cancelled (stage 8) job orders are Admin-only.
     * Same reasoning as assertTeamAccess: the list already filters these
     * out, but direct access by ID must be blocked too, or the filter is
     * cosmetic. Checked against the job order's CURRENT stage — a non-admin
     * still may move a job order INTO stage 7/8 (see it disappear from their
     * own view afterwards), they just can't view/edit/delete one already there.
     */
    private function assertStageAccess(array $jobOrder): void
    {
        if (Auth::can('job_order.view_completed')) {
            return;
        }

        $stage = JobStage::findById((int) $jobOrder['stage_id']);
        if ($stage && in_array($stage['stage_code'], ['7', '8'], true)) {
            $this->notFound();
        }
    }

    private function notFound(): never
    {
        http_response_code(404);
        require ROOT_PATH . '/views/errors/404.php';
        exit;
    }
}
