<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Notification;

final class NotificationController extends Controller
{
    /** Marks one notification read (only if it belongs to the current user) and redirects to its link. */
    public function open(array $params): void
    {
        $id = (int) $params['id'];
        $notification = Notification::findById($id);

        if ($notification && (int) $notification['user_id'] === (int) Auth::id()) {
            Notification::markRead($id, (int) Auth::id());
            $this->redirect($notification['link_url'] ?: '/');
        }

        $this->redirect('/');
    }

    public function markAllRead(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/');
        }

        Notification::markAllRead((int) Auth::id());

        // Only trust a same-origin referer, to avoid an open-redirect via a spoofed header.
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $this->redirect(str_starts_with($referer, rtrim(APP_URL, '/') . '/') ? $referer : '/');
    }
}
