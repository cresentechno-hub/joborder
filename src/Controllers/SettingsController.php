<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Settings;
use App\Models\ActivityLog;

final class SettingsController extends Controller
{
    private const KEYS = [
        'app_name',
        'timezone',
        'date_format',
        'items_per_page',
        'stage_pending_alert_days',
        'upload_max_size_mb',
        'allowed_upload_types',
    ];

    public function edit(array $params = []): void
    {
        $this->view('settings/edit', [
            'settings' => Settings::all(),
        ]);
    }

    public function update(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/settings');
        }

        $errors = [];
        $values = [];

        foreach (self::KEYS as $key) {
            $values[$key] = trim((string) ($_POST[$key] ?? ''));
        }

        if ($values['app_name'] === '') {
            $errors['app_name'] = 'Application name is required.';
        }
        if (!ctype_digit($values['items_per_page']) || (int) $values['items_per_page'] < 1) {
            $errors['items_per_page'] = 'Items per page must be a positive whole number.';
        }
        if (!ctype_digit($values['stage_pending_alert_days']) || (int) $values['stage_pending_alert_days'] < 1) {
            $errors['stage_pending_alert_days'] = 'Pending-alert threshold must be a positive whole number of days.';
        }
        if (!is_numeric($values['upload_max_size_mb']) || (float) $values['upload_max_size_mb'] <= 0) {
            $errors['upload_max_size_mb'] = 'Max upload size must be a positive number.';
        }
        if ($values['allowed_upload_types'] === '') {
            $errors['allowed_upload_types'] = 'List at least one allowed file extension.';
        }
        try {
            new \DateTimeZone($values['timezone']);
        } catch (\Exception $e) {
            $errors['timezone'] = 'Not a valid PHP timezone identifier (e.g. Asia/Kuala_Lumpur).';
        }

        if (!empty($errors)) {
            flash_input($values);
            flash_errors($errors);
            $this->redirect('/settings');
        }

        // Normalise the extension list: lowercase, no dots, no spaces.
        $types = array_filter(array_map(
            static fn (string $t): string => strtolower(ltrim(trim($t), '.')),
            explode(',', $values['allowed_upload_types'])
        ));
        $values['allowed_upload_types'] = implode(',', $types);

        Settings::setMany($values);

        ActivityLog::record(Auth::id(), 'settings.update');
        flash('success', 'Settings saved.');
        $this->redirect('/settings');
    }
}
