<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Permission
{
    /** Permission code prefix (before the first '.') -> display name + sort order for the Roles/Permissions screen. */
    private const MODULES = [
        'job_order'   => ['label' => 'Job Orders',          'order' => 10],
        'lpr_rental'  => ['label' => 'LPR Rental',           'order' => 20],
        'lpr_partner' => ['label' => 'LPR Partners',         'order' => 30],
        'smc'         => ['label' => 'SMC',                  'order' => 40],
        'customer'    => ['label' => 'Customers',            'order' => 50],
        'user'        => ['label' => 'Users & Branches',     'order' => 60],
        'data'        => ['label' => 'Cross-Module Access',  'order' => 15],
        'role'        => ['label' => 'Roles & Permissions',  'order' => 70],
        'settings'    => ['label' => 'Settings',             'order' => 80],
        'report'      => ['label' => 'Dashboard & Reports',  'order' => 90],
        'activity_log' => ['label' => 'Activity Log',        'order' => 100],
    ];

    public static function all(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query('SELECT * FROM permissions ORDER BY code')->fetchAll();
    }

    /**
     * All permissions grouped by the module they control, for the Roles &
     * Permissions screen — lets an admin see/toggle module-level access at
     * a glance instead of reading a flat list of permission codes.
     * @return array<int, array{label: string, permissions: array}> ordered by module
     */
    public static function allGroupedByModule(): array
    {
        $groups = [];
        foreach (self::all() as $p) {
            $prefix = strstr($p['code'], '.', true) ?: $p['code'];
            $module = self::MODULES[$prefix] ?? ['label' => ucfirst(str_replace('_', ' ', $prefix)), 'order' => 999];
            $groups[$module['label']]['order'] ??= $module['order'];
            $groups[$module['label']]['permissions'][] = $p;
        }

        uasort($groups, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        $result = [];
        foreach ($groups as $label => $group) {
            $result[] = ['label' => $label, 'permissions' => $group['permissions']];
        }
        return $result;
    }
}
