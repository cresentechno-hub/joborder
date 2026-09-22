<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Shared by LprRentalController and SmcController — a branch-restricted
 * user (no data.view_all_branches) may only act on a record belonging to
 * their own branch. 404s rather than 403s so branch membership isn't
 * leaked either way. The composing class must provide notFound().
 */
trait BranchAccessGuard
{
    private function assertBranchAccess(array $record): void
    {
        if (Auth::can('data.view_all_branches')) {
            return;
        }

        $myBranchIds = Auth::user()['branch_ids'] ?? [];

        if (!in_array((int) $record['branch_id'], $myBranchIds, true)) {
            $this->notFound();
        }
    }
}
