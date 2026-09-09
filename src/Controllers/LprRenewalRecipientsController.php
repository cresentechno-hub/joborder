<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\LprRenewalRecipient;
use App\Models\User;

final class LprRenewalRecipientsController extends Controller
{
    public function edit(array $params = []): void
    {
        $this->view('settings/lpr_renewal_recipients', [
            'users'             => User::allActive(),
            'selectedUserIds'   => LprRenewalRecipient::userIds(),
        ]);
    }

    public function update(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/settings/lpr-renewal-recipients');
        }

        $userIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['user_ids'] ?? []))
        )));

        LprRenewalRecipient::replace($userIds);

        ActivityLog::record(Auth::id(), 'lpr_renewal_recipients.update', null, null, ['count' => count($userIds)]);
        flash('success', 'LPR renewal recipients saved.');
        $this->redirect('/settings/lpr-renewal-recipients');
    }
}
