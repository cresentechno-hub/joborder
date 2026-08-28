<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\JobOrder;

final class DashboardController extends Controller
{
    public function index(array $params = []): void
    {
        $alertDays = max(1, (int) setting('stage_pending_alert_days', '7'));

        // null = unrestricted (sees every team). 0 = restricted with no team
        // assigned yet (sees nothing) — never confused with "unrestricted".
        $teamId = Auth::can('job_order.view_all') ? null : (int) (Auth::user()['team_id'] ?? 0);
        $hideCompleted = !Auth::can('job_order.view_completed');

        $this->view('dashboard/index', [
            'user'               => Auth::user(),
            'quotationsThisYear' => JobOrder::countThisYear($teamId),
            'stageCounts'        => JobOrder::countByStage($teamId, $hideCompleted),
            'stuckJobs'          => JobOrder::stuckJobs($alertDays, $teamId),
            'alertDays'          => $alertDays,
        ]);
    }
}
