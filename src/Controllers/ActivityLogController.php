<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\User;

final class ActivityLogController extends Controller
{
    public function index(array $params = []): void
    {
        $filters = [
            'action'  => trim((string) $this->input('action', '')) ?: null,
            'user_id' => (int) $this->input('user_id', 0) ?: null,
            'sort'    => trim((string) $this->input('sort', '')) ?: null,
            'dir'     => trim((string) $this->input('dir', '')) ?: null,
        ];
        $page = max(1, (int) $this->input('page', 1));

        $result = ActivityLog::paginate($filters, $page, 30);

        $this->view('activity_log/index', [
            'logs'    => $result['data'],
            'total'   => $result['total'],
            'page'    => $result['page'],
            'perPage' => $result['per_page'],
            'actions' => ActivityLog::distinctActions(),
            'users'   => User::allWithRole(),
            'filters' => $filters,
        ]);
    }
}
