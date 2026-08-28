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
use DateTime;
use Throwable;

final class JobOrderController extends Controller
{
    public function index(array $params = []): void
    {
        $filters = [
            'q'        => trim((string) $this->input('q', '')),
            'stage_id' => (int) $this->input('stage_id', 0) ?: null,
        ];
        $page = max(1, (int) $this->input('page', 1));
        $perPage = max(1, (int) setting('items_per_page', '20'));

        $result = JobOrder::paginate($filters, $page, $perPage);

        $this->view('job_orders/index', [
            'jobOrders' => $result['data'],
            'total'     => $result['total'],
            'page'      => $result['page'],
            'perPage'   => $result['per_page'],
            'stages'    => JobStage::allActive(),
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

    private function notFound(): never
    {
        http_response_code(404);
        require ROOT_PATH . '/views/errors/404.php';
        exit;
    }
}
