<?php

declare(strict_types=1);

// Run periodically via cron (e.g. daily) — on cPanel, wire this into the
// Cron Jobs page as:
//   php /home/<user>/job-order-system/database/send_renewal_reminders.php
// No SSH needed to run it, only to have set it up once. See docs/DEPLOYMENT.md.
//
// Emails staff (never the customer) a digest of their own LPR/SMC contracts
// entering the renewal window (Settings > Renewal Reminder Window), scoped
// to each recipient's own data.view_all_branches / branch permission exactly
// like the Dashboard's on-page alert. Each contract is only ever included
// once (LprRental/SmcContract.renewal_reminder_sent_at) — editing a
// contract resets that flag, so a renewal becomes eligible for a fresh
// reminder later.

if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

require __DIR__ . '/../config/bootstrap.php';

use App\Models\LprRenewalRecipient;
use App\Models\LprRental;
use App\Models\Notification;
use App\Models\SmcContract;
use App\Models\User;
use App\Services\Mailer;

$months = max(1, (int) setting('renewal_reminder_months', '3'));
$appUrl = rtrim(APP_URL, '/');

// LPR's candidate pool is the admin-curated list (Settings > Manage LPR
// Renewal Recipients) — SMC's stays permission-based. A user who's both an
// LPR recipient AND holds smc.manage gets one digest covering both modules.
$recipients = [];
foreach (LprRenewalRecipient::activeWithPermissions() as $user) {
    $id = (int) $user['id'];
    $recipients[$id] ??= $user + ['modules' => []];
    $recipients[$id]['modules'][] = 'lpr';
}
foreach (User::activeWithPermission('smc.manage') as $user) {
    $id = (int) $user['id'];
    $recipients[$id] ??= $user + ['modules' => []];
    $recipients[$id]['modules'][] = 'smc';
}

// Resolve every recipient's list against the CURRENT unnotified state
// before sending anything, and only mark contracts as reminded once every
// qualifying recipient in this run has already been considered — otherwise
// whoever happens to be processed first would silently "use up" the single
// reminder before a second, equally-relevant recipient (e.g. the branch's
// own staff, processed after an unrestricted Admin) ever saw it.
$digests = [];
$lprIdsToMark = [];
$smcIdsToMark = [];

foreach ($recipients as $user) {
    $branchId = in_array('data.view_all_branches', $user['permissions'], true)
        ? null
        : (int) ($user['branch_id'] ?? 0);

    $lprItems = [];
    if (in_array('lpr', $user['modules'], true)) {
        foreach (LprRental::expiringSoon($months, $branchId) as $r) {
            if ($r['renewal_reminder_sent_at'] !== null) {
                continue;
            }
            $lprItems[] = [
                'customer_name' => $r['customer_name'],
                'partner_name'  => $r['partner_name'],
                'end_date'      => $r['end_date'],
                'edit_url'      => "{$appUrl}/lpr-rentals/{$r['id']}/edit",
            ];
            $lprIdsToMark[(int) $r['id']] = true;
        }
    }

    $smcItems = [];
    if (in_array('smc', $user['modules'], true)) {
        foreach (SmcContract::expiringSoon($months, $branchId) as $c) {
            if ($c['renewal_reminder_sent_at'] !== null) {
                continue;
            }
            $smcItems[] = [
                'customer_name' => $c['customer_name'],
                'end_date'      => $c['end_date'],
                'edit_url'      => "{$appUrl}/smc/{$c['id']}/edit",
            ];
            $smcIdsToMark[(int) $c['id']] = true;
        }
    }

    if (empty($lprItems) && empty($smcItems)) {
        continue;
    }

    $digests[] = [
        'user'     => $user,
        'lprItems' => $lprItems,
        'smcItems' => $smcItems,
    ];
}

$sentCount = 0;
foreach ($digests as $digest) {
    $user = $digest['user'];
    $total = count($digest['lprItems']) + count($digest['smcItems']);
    $subject = "Renewal reminder — {$total} contract" . ($total === 1 ? '' : 's') . ' due soon';

    $ok = Mailer::send(
        $user['email'],
        $user['full_name'],
        $subject,
        'renewal_digest',
        [
            'recipientName' => $user['full_name'],
            'lprItems'      => $digest['lprItems'],
            'smcItems'      => $digest['smcItems'],
        ]
    );

    if ($ok) {
        $sentCount++;
    }

    // In-app copy, independent of whether the email itself succeeded — a
    // second, more reliable channel, not a receipt for the email. Renewal
    // digests cover multiple contracts, so this links to the Dashboard,
    // which already surfaces the same renewal list.
    Notification::create((int) $user['id'], 'renewal_reminder', $subject, null, '/');
}

// Mark every contract that appeared in at least one digest this run —
// regardless of whether every individual send succeeded, so a one-off SMTP
// hiccup for a single recipient doesn't keep re-including that contract
// forever; the Dashboard's own alert is unaffected either way.
foreach (array_keys($lprIdsToMark) as $id) {
    LprRental::markReminderSent($id);
}
foreach (array_keys($smcIdsToMark) as $id) {
    SmcContract::markReminderSent($id);
}

echo sprintf(
    "[%s] Renewal reminders: sent to %d/%d eligible recipient(s), covering %d LPR + %d SMC contract(s).\n",
    date('Y-m-d H:i:s'),
    $sentCount,
    count($digests),
    count($lprIdsToMark),
    count($smcIdsToMark)
);
