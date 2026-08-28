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

        $this->view('dashboard/index', [
            'user'           => Auth::user(),
            'quotationsThisYear' => JobOrder::countThisYear(),
            'stageCounts'    => JobOrder::countByStage(),
            'stuckJobs'      => JobOrder::stuckJobs($alertDays),
            'alertDays'      => $alertDays,
        ]);
    }
}
