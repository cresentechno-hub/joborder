<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\JobOrder;
use App\Models\LprRental;
use App\Models\SmcContract;

final class DashboardController extends Controller
{
    public function index(array $params = []): void
    {
        $alertDays = max(1, (int) setting('stage_pending_alert_days', '7'));
        $renewalMonths = max(1, (int) setting('renewal_reminder_months', '3'));

        // null = unrestricted (sees every branch). A restricted user with no
        // branch yet gets [] (never any real branch id) — sees nothing —
        // never confused with "unrestricted".
        $branchIds = Auth::can('data.view_all_branches') ? null : (Auth::user()['branch_ids'] ?? []);
        $hideCompleted = !Auth::can('job_order.view_completed');

        $renewals = [];
        if (Auth::can('lpr_rental.view')) {
            foreach (LprRental::expiringSoon($renewalMonths, $branchIds) as $r) {
                $renewals[] = [
                    'module'   => 'LPR',
                    'url'      => "/lpr-rentals/{$r['id']}/edit",
                    'customer' => $r['customer_name'],
                    'partner'  => $r['partner_name'],
                    'end_date' => $r['end_date'],
                ];
            }
        }
        if (Auth::can('smc.view')) {
            foreach (SmcContract::expiringSoon($renewalMonths, $branchIds) as $c) {
                $renewals[] = [
                    'module'   => 'SMC',
                    'url'      => "/smc/{$c['id']}/edit",
                    'customer' => $c['customer_name'],
                    'partner'  => null,
                    'end_date' => $c['end_date'],
                ];
            }
        }
        usort($renewals, static fn (array $a, array $b): int => $a['end_date'] <=> $b['end_date']);

        $this->view('dashboard/index', [
            'user'               => Auth::user(),
            'quotationsThisYear' => JobOrder::countThisYear($branchIds),
            'stageCounts'        => JobOrder::countByStage($branchIds, $hideCompleted),
            'stuckJobs'          => JobOrder::stuckJobs($alertDays, $branchIds),
            'alertDays'          => $alertDays,
            'renewals'           => $renewals,
            'renewalMonths'      => $renewalMonths,
        ]);
    }
}
