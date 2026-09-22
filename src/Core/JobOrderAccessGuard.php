<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\JobStage;
use App\Models\Notification;
use App\Models\User;
use App\Services\Mailer;

/**
 * Shared by JobOrderController and JobOrderCommentController — any place
 * that accesses a specific job order by ID needs both checks, not just the
 * list-query filters, or a Sales/Manager user could bypass branch/stage
 * restrictions just by guessing the URL.
 */
trait JobOrderAccessGuard
{
    /**
     * Branch scoping: a Sales-role user (no data.view_all_branches) may
     * only act on a job order that belongs to their own branch. 404s
     * rather than 403s so branch membership isn't leaked either way.
     */
    private function assertBranchAccess(array $jobOrder): void
    {
        if (Auth::can('data.view_all_branches')) {
            return;
        }

        $myBranchIds = Auth::user()['branch_ids'] ?? [];

        if (!in_array((int) $jobOrder['branch_id'], $myBranchIds, true)) {
            $this->notFound();
        }
    }

    /**
     * Hard server-side enforcement of the assignment rule: a branch-
     * restricted user can assign a job order to a colleague sharing at
     * least one of their branches, or to anyone with no branch, but never
     * to someone whose branches are entirely disjoint from theirs. Checks
     * a single candidate; call once per selected assignee. Returns an
     * error message, or null if the assignment is fine.
     */
    private function assertOwnBranchAssignment(int $assignedTo): ?string
    {
        if (Auth::can('data.view_all_branches')) {
            return null;
        }

        $myBranchIds = Auth::user()['branch_ids'] ?? [];
        $assigneeBranchIds = User::branchIds($assignedTo);

        // Blocked only when the assignee has branches of their own that
        // share none with mine — unaffiliated (no branch) assignees are
        // always allowed.
        if (!empty($assigneeBranchIds) && empty(array_intersect($myBranchIds, $assigneeBranchIds))) {
            return 'You can only assign job orders to a colleague sharing one of your branches, or to a user with no branch.';
        }

        return null;
    }

    /**
     * Completed (stage 7) / Cancelled (stage 8) job orders are Admin-only.
     * Checked against the job order's CURRENT stage — a non-admin may still
     * move a job order INTO stage 7/8 (it disappears from their own view
     * afterwards), they just can't view/edit one already there.
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

    /**
     * Notifies each newly-added assignee that they've been put on this job
     * order — never the acting user themselves (they already know), and
     * never a resend to someone who was already an assignee. Two
     * independent channels: an email (best-effort — a failed/unconfigured
     * send never blocks the save that triggered it, see Mailer::send())
     * and an in-app notification (always created regardless of the email's
     * own outcome, so it isn't email-only). Shared by JobOrderController
     * (create/update) and JobOrderCommentController (comment-driven
     * reassignment).
     * @param int[] $newAssigneeIds
     */
    private function notifyNewAssignees(array $jobOrder, array $newAssigneeIds): void
    {
        $newAssigneeIds = array_values(array_unique(array_filter(
            array_map('intval', $newAssigneeIds),
            static fn (int $id): bool => $id !== (int) Auth::id()
        )));
        if (empty($newAssigneeIds)) {
            return;
        }

        $assignedByName = Auth::user()['full_name'] ?? 'A colleague';

        foreach ($newAssigneeIds as $userId) {
            $assignee = User::findById($userId);
            if (!$assignee) {
                continue;
            }

            if (!empty($assignee['email'])) {
                Mailer::send(
                    $assignee['email'],
                    $assignee['full_name'],
                    'You\'ve been assigned: ' . $jobOrder['quotation_no'],
                    'assigned_job_order',
                    ['jobOrder' => $jobOrder, 'assignedByName' => $assignedByName]
                );
            }

            Notification::create(
                $userId,
                'job_order_assigned',
                'You\'ve been assigned: ' . $jobOrder['quotation_no'],
                $assignedByName . ' assigned you to ' . $jobOrder['customer_name'] . ' — ' . $jobOrder['subject'],
                '/job-orders/' . (int) $jobOrder['id'] . '/edit'
            );
        }
    }

    private function notFound(): never
    {
        http_response_code(404);
        require ROOT_PATH . '/views/errors/404.php';
        exit;
    }
}
