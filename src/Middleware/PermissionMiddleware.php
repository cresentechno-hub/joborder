<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

final class PermissionMiddleware
{
    /** $permissionCode is the required permission code, e.g. 'job_order.create'. */
    public static function handle(mixed $permissionCode = null): void
    {
        if ($permissionCode === null) {
            return;
        }

        if (!Auth::can((string) $permissionCode)) {
            http_response_code(403);
            require ROOT_PATH . '/views/errors/403.php';
            exit;
        }
    }
}
