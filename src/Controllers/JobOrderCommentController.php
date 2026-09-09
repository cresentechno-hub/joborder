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
        $this->assertBranchAccess($jobOrder);
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

        $assigneeIds = $this->assigneeIdsFromInput($input);
        $errors = $this->validate($input, $hasDoFile, $invoiceNo, $assigneeIds);

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
                    job_order_upload_dir($jobOrder['quotation_no'], 'invoices'),
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
                    job_order_upload_dir($jobOrder['quotation_no'], 'do'),
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
            'assigned_to'  => $assigneeIds,
            'remark'       => trim((string) ($input['comment_remark'] ?? '')) ?: null,
            'created_by'   => Auth::id(),
        ]);

        if ($invoiceUpload) {
            JobOrderComment::updateInvoiceFile(
                $commentId,
                job_order_upload_rel($jobOrder['quotation_no'], 'invoices') . '/' . $invoiceUpload['stored_name'],
                $invoiceUpload['original_name']
            );
        }

        if ($doUpload) {
            JobOrderComment::updateDoFile(
                $commentId,
                job_order_upload_rel($jobOrder['quotation_no'], 'do') . '/' . $doUpload['stored_name'],
                $doUpload['original_name']
            );
        }

        $ccUserIds = array_filter(array_map('intval', (array) ($input['comment_cc_users'] ?? [])));
        JobOrderComment::attachCcUsers($commentId, $ccUserIds);

        // The comment's Stage/Assign To fields actually update the job
        // order itself — reusing JobOrder::update() (and its stage-change
        // trigger, current_stage_since, history log) rather than a
        // parallel code path. All other fields carry over unchanged.
        $oldAssigneeIds = JobOrder::getAssigneeIds($jobOrderId);
        JobOrder::update($jobOrderId, [
            'quotation_no'   => $jobOrder['quotation_no'],
            'customer_name'  => $jobOrder['customer_name'],
            'subject'        => $jobOrder['subject'],
            'total_cost'     => $jobOrder['total_cost'],
            'job_start_date' => $jobOrder['job_start_date'],
            'assigned_to'    => $assigneeIds,
            'branch_id'      => $jobOrder['branch_id'],
            'stage_id'       => (int) $input['comment_stage_id'],
            'remarks'        => $jobOrder['remarks'],
            'updated_by'     => Auth::id(),
        ]);
        $this->notifyNewAssignees($jobOrder, array_diff($assigneeIds, $oldAssigneeIds));

        ActivityLog::record(Auth::id(), 'job_order.comment', 'job_order', $jobOrderId);
        flash('success', 'Comment added.');
        $this->redirect("/job-orders/{$jobOrderId}/edit#comments");
    }

    public function edit(array $params): void
    {
        [$jobOrder, $comment] = $this->loadOwned($params);

        $this->view('job_orders/comment_edit', [
            'jobOrder'          => $jobOrder,
            'comment'           => $comment,
            'stages'            => JobStage::allActive(),
            'users'             => User::allActive(),
            'selectedCc'        => JobOrderComment::getCcUserIds((int) $comment['id']),
            'selectedAssignees' => JobOrderComment::getAssigneeIds((int) $comment['id']),
        ]);
    }

    public function update(array $params): void
    {
        $jobOrderId = (int) $params['id'];
        $commentId = (int) $params['commentId'];

        if (!verify_csrf()) {
            flash('comment_error', 'Your session expired, please try again.');
            $this->redirect("/job-orders/{$jobOrderId}/comments/{$commentId}/edit");
        }

        [$jobOrder, $comment] = $this->loadOwned($params);

        $input = $_POST;
        $hasInvoiceFile = !empty($_FILES['comment_invoice_file']['name']);
        $hasDoFile = !empty($_FILES['comment_do_file']['name']);

        $invoiceNo = trim((string) ($input['comment_invoice_no'] ?? ''));
        if ($invoiceNo === '' && $hasInvoiceFile) {
            $invoiceNo = pathinfo($_FILES['comment_invoice_file']['name'], PATHINFO_FILENAME);
        }
        // Keep the comment's existing invoice_no if the field was left blank
        // and no new invoice file was uploaded (e.g. only editing the remark).
        if ($invoiceNo === '') {
            $invoiceNo = (string) ($comment['invoice_no'] ?? '');
        }

        $assigneeIds = $this->assigneeIdsFromInput($input);
        $errors = $this->validate($input, $hasDoFile, $invoiceNo, $assigneeIds);
        if (!empty($errors)) {
            flash_input($input);
            flash('comment_error', implode(' ', $errors));
            $this->redirect("/job-orders/{$jobOrderId}/comments/{$commentId}/edit");
        }

        if ($hasInvoiceFile) {
            try {
                $up = FileUploadService::upload(
                    $_FILES['comment_invoice_file'],
                    job_order_upload_dir($jobOrder['quotation_no'], 'invoices'),
                    'INV-' . $invoiceNo
                );
                JobOrderComment::updateInvoiceFile(
                    $commentId,
                    job_order_upload_rel($jobOrder['quotation_no'], 'invoices') . '/' . $up['stored_name'],
                    $up['original_name']
                );
            } catch (Throwable $e) {
                flash_input($input);
                flash('comment_error', 'Invoice upload failed: ' . $e->getMessage());
                $this->redirect("/job-orders/{$jobOrderId}/comments/{$commentId}/edit");
            }
        }

        if ($hasDoFile) {
            try {
                $up = FileUploadService::upload(
                    $_FILES['comment_do_file'],
                    job_order_upload_dir($jobOrder['quotation_no'], 'do'),
                    'DO-' . $invoiceNo
                );
                JobOrderComment::updateDoFile(
                    $commentId,
                    job_order_upload_rel($jobOrder['quotation_no'], 'do') . '/' . $up['stored_name'],
                    $up['original_name']
                );
            } catch (Throwable $e) {
                flash_input($input);
                flash('comment_error', 'DO document upload failed: ' . $e->getMessage());
                $this->redirect("/job-orders/{$jobOrderId}/comments/{$commentId}/edit");
            }
        }

        JobOrderComment::update($commentId, [
            'stage_id'    => (int) $input['comment_stage_id'],
            'invoice_no'  => $invoiceNo !== '' ? $invoiceNo : null,
            'assigned_to' => $assigneeIds,
            'remark'      => trim((string) ($input['comment_remark'] ?? '')) ?: null,
        ]);

        $ccUserIds = array_filter(array_map('intval', (array) ($input['comment_cc_users'] ?? [])));
        JobOrderComment::replaceCcUsers($commentId, $ccUserIds);

        // Same rule as create: whatever Stage/Assign To this form holds
        // becomes the job order's current stage/assignee.
        $oldAssigneeIds = JobOrder::getAssigneeIds($jobOrderId);
        JobOrder::update($jobOrderId, [
            'quotation_no'   => $jobOrder['quotation_no'],
            'customer_name'  => $jobOrder['customer_name'],
            'subject'        => $jobOrder['subject'],
            'total_cost'     => $jobOrder['total_cost'],
            'job_start_date' => $jobOrder['job_start_date'],
            'assigned_to'    => $assigneeIds,
            'branch_id'      => $jobOrder['branch_id'],
            'stage_id'       => (int) $input['comment_stage_id'],
            'remarks'        => $jobOrder['remarks'],
            'updated_by'     => Auth::id(),
        ]);
        $this->notifyNewAssignees($jobOrder, array_diff($assigneeIds, $oldAssigneeIds));

        ActivityLog::record(Auth::id(), 'job_order.comment_update', 'job_order_comment', $commentId);
        flash('success', 'Comment updated.');
        $this->redirect("/job-orders/{$jobOrderId}/edit#comments");
    }

    public function destroy(array $params): void
    {
        $jobOrderId = (int) $params['id'];
        $commentId = (int) $params['commentId'];

        if (!verify_csrf()) {
            flash('comment_error', 'Your session expired, please try again.');
            $this->redirect("/job-orders/{$jobOrderId}/edit#comments");
        }

        [, $comment] = $this->loadOwned($params);

        foreach (['invoice_file_path', 'do_file_path'] as $pathKey) {
            if (!empty($comment[$pathKey])) {
                $absolute = ROOT_PATH . '/public/' . $comment[$pathKey];
                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }
        }

        JobOrderComment::delete($commentId);
        ActivityLog::record(Auth::id(), 'job_order.comment_delete', 'job_order_comment', $commentId);
        flash('success', 'Comment deleted.');
        $this->redirect("/job-orders/{$jobOrderId}/edit#comments");
    }

    /**
     * Loads the job order + comment for {id}/{commentId}, enforcing the
     * same branch/stage access as the job order itself, and confirming the
     * comment actually belongs to that job order (not just any valid ID).
     * @return array{0: array, 1: array}
     */
    private function loadOwned(array $params): array
    {
        $jobOrderId = (int) $params['id'];
        $commentId = (int) $params['commentId'];

        $jobOrder = JobOrder::findById($jobOrderId);
        if (!$jobOrder) {
            $this->notFound();
        }
        $this->assertBranchAccess($jobOrder);
        $this->assertStageAccess($jobOrder);

        $comment = JobOrderComment::findById($commentId);
        if (!$comment || (int) $comment['job_order_id'] !== $jobOrderId) {
            $this->notFound();
        }

        return [$jobOrder, $comment];
    }

    /** @return int[] deduped, cast-to-int assignee IDs pulled from `comment_assigned_to[]`. */
    private function assigneeIdsFromInput(array $input): array
    {
        $raw = (array) ($input['comment_assigned_to'] ?? []);
        return array_values(array_unique(array_filter(array_map('intval', $raw))));
    }

    /** @param int[] $assigneeIds */
    private function validate(array $input, bool $hasDoFile, string $invoiceNo, array $assigneeIds): array
    {
        $errors = [];

        if (empty($input['comment_stage_id']) || !JobStage::findById((int) $input['comment_stage_id'])) {
            $errors[] = 'Please select a valid stage.';
        }

        if (empty($assigneeIds)) {
            $errors[] = 'Please select at least one user to assign.';
        } else {
            foreach ($assigneeIds as $aid) {
                if (!User::findById($aid)) {
                    $errors[] = 'One of the selected assignees is not valid.';
                    break;
                }
                $branchError = $this->assertOwnBranchAssignment($aid);
                if ($branchError !== null) {
                    $errors[] = $branchError;
                    break;
                }
            }
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
