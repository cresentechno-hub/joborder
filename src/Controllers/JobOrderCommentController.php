<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\JobOrderAccessGuard;
use App\Models\ActivityLog;
use App\Models\JobOrder;
use App\Models\JobOrderComment;
use App\Models\JobStage;
use App\Models\User;
use App\Services\FileUploadService;
use Throwable;

final class JobOrderCommentController extends Controller
{
    use JobOrderAccessGuard;

    public function store(array $params): void
    {
        $jobOrderId = (int) $params['id'];

        if (!verify_csrf()) {
            flash('comment_error', 'Your session expired, please try again.');
            $this->redirect("/job-orders/{$jobOrderId}/edit");
        }

        $jobOrder = JobOrder::findById($jobOrderId);
        if (!$jobOrder) {
            $this->notFound();
        }
        $this->assertTeamAccess($jobOrder);
        $this->assertStageAccess($jobOrder);

        $input = $_POST;
        $hasInvoiceFile = !empty($_FILES['comment_invoice_file']['name']);
        $hasDoFile = !empty($_FILES['comment_do_file']['name']);

        // Invoice No is normally auto-filled client-side from the invoice
        // file's own name, but derive it server-side too as a fallback in
        // case JS didn't run — the naming scheme depends on it either way.
        $invoiceNo = trim((string) ($input['comment_invoice_no'] ?? ''));
        if ($invoiceNo === '' && $hasInvoiceFile) {
            $invoiceNo = pathinfo($_FILES['comment_invoice_file']['name'], PATHINFO_FILENAME);
        }

        $errors = $this->validate($input, $hasDoFile, $invoiceNo);

        if (!empty($errors)) {
            flash_input($input);
            flash('comment_error', implode(' ', $errors));
            $this->redirect("/job-orders/{$jobOrderId}/edit#add-comment");
        }

        $invoiceUpload = null;
        if ($hasInvoiceFile) {
            try {
                $invoiceUpload = FileUploadService::upload(
                    $_FILES['comment_invoice_file'],
                    UPLOAD_INVOICE_DIR . '/' . $jobOrderId,
                    'INV-' . $invoiceNo
                );
            } catch (Throwable $e) {
                flash_input($input);
                flash('comment_error', 'Invoice upload failed: ' . $e->getMessage());
                $this->redirect("/job-orders/{$jobOrderId}/edit#add-comment");
            }
        }

        $doUpload = null;
        if ($hasDoFile) {
            try {
                $doUpload = FileUploadService::upload(
                    $_FILES['comment_do_file'],
                    UPLOAD_DO_DIR . '/' . $jobOrderId,
                    'DO-' . $invoiceNo
                );
            } catch (Throwable $e) {
                flash_input($input);
                flash('comment_error', 'DO document upload failed: ' . $e->getMessage());
                $this->redirect("/job-orders/{$jobOrderId}/edit#add-comment");
            }
        }

        $commentId = JobOrderComment::create([
            'job_order_id' => $jobOrderId,
            'stage_id'     => (int) $input['comment_stage_id'],
            'invoice_no'   => $invoiceNo !== '' ? $invoiceNo : null,
            'assigned_to'  => (int) $input['comment_assigned_to'],
            'remark'       => trim((string) ($input['comment_remark'] ?? '')) ?: null,
            'created_by'   => Auth::id(),
        ]);

        if ($invoiceUpload) {
            JobOrderComment::updateInvoiceFile(
                $commentId,
                UPLOAD_INVOICE_REL . '/' . $jobOrderId . '/' . $invoiceUpload['stored_name'],
                $invoiceUpload['original_name']
            );
        }

        if ($doUpload) {
            JobOrderComment::updateDoFile(
                $commentId,
                UPLOAD_DO_REL . '/' . $jobOrderId . '/' . $doUpload['stored_name'],
                $doUpload['original_name']
            );
        }

        $ccUserIds = array_filter(array_map('intval', (array) ($input['comment_cc_users'] ?? [])));
        JobOrderComment::attachCcUsers($commentId, $ccUserIds);

        // The comment's Stage/Assign To fields actually update the job
        // order itself — reusing JobOrder::update() (and its stage-change
        // trigger, current_stage_since, history log) rather than a
        // parallel code path. All other fields carry over unchanged.
        JobOrder::update($jobOrderId, [
            'quotation_no'   => $jobOrder['quotation_no'],
            'customer_name'  => $jobOrder['customer_name'],
            'subject'        => $jobOrder['subject'],
            'total_cost'     => $jobOrder['total_cost'],
            'job_start_date' => $jobOrder['job_start_date'],
            'assigned_to'    => (int) $input['comment_assigned_to'],
            'stage_id'       => (int) $input['comment_stage_id'],
            'remarks'        => $jobOrder['remarks'],
            'updated_by'     => Auth::id(),
        ]);

        ActivityLog::record(Auth::id(), 'job_order.comment', 'job_order', $jobOrderId);
        flash('success', 'Comment added.');
        $this->redirect("/job-orders/{$jobOrderId}/edit#comments");
    }

    private function validate(array $input, bool $hasDoFile, string $invoiceNo): array
    {
        $errors = [];

        if (empty($input['comment_stage_id']) || !JobStage::findById((int) $input['comment_stage_id'])) {
            $errors[] = 'Please select a valid stage.';
        }

        if (empty($input['comment_assigned_to']) || !User::findById((int) $input['comment_assigned_to'])) {
            $errors[] = 'Please select a valid user to assign.';
        }

        foreach ((array) ($input['comment_cc_users'] ?? []) as $ccId) {
            if (!User::findById((int) $ccId)) {
                $errors[] = 'One of the selected CC users is not valid.';
                break;
            }
        }

        if ($hasDoFile && $invoiceNo === '') {
            $errors[] = 'Enter or confirm the Invoice No before uploading the DO document.';
        }

        return $errors;
    }
}
