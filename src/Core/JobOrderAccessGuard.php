<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\JobStage;
use App\Models\User;

/**
 * Shared by JobOrderController and JobOrderCommentController — any place
 * that accesses a specific job order by ID needs both checks, not just the
 * list-query filters, or a Sales/Manager user could bypass team/stage
 * restrictions just by guessing the URL.
 */
trait JobOrderAccessGuard
{
    /**
     * Team scoping: a Sales-role user (no job_order.view_all) may only act
     * on a job order assigned to a member of their own team. 404s rather
     * than 403s so team membership isn't leaked either way.
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

    private function notFound(): never
    {
        http_response_code(404);
        require ROOT_PATH . '/views/errors/404.php';
        exit;
    }
}
