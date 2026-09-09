<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Exactly who receives the LPR renewal reminder digest — admin-curated via
 * the Settings > Manage LPR Renewal Recipients screen, independent of
 * role/permission (unlike SMC, which stays permission-based; see
 * database/send_renewal_reminders.php). Each selected user's own branch
 * scoping still applies when the reminder script resolves what THEY
 * specifically see — this table only controls the candidate pool.
 */
final class LprRenewalRecipient
{
    /** Active users currently selected, for display on the picker page. */
    public static function all(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query(
            'SELECT u.id, u.full_name, u.email, u.branch_id
             FROM lpr_renewal_recipients lrr
             JOIN users u ON u.id = lrr.user_id
             WHERE u.is_active = 1
             ORDER BY u.full_name'
        )->fetchAll();
    }

    /**
     * The selected, active users with a fresh permissions[] array attached
     * to each — same shape as User::activeWithPermission(), so
     * database/send_renewal_reminders.php can resolve each recipient's own
     * data.view_all_branches/branch scoping exactly the same way for LPR
     * as it does for SMC, just from an explicit list instead of a
     * permission query.
     */
    public static function activeWithPermissions(): array
    {
        $users = self::all();
        foreach ($users as &$user) {
            $user['permissions'] = User::permissionCodesForRole((int) $user['id']);
        }
        unset($user);
        return $users;
    }

    /** @return int[] */
    public static function userIds(): array
    {
        $pdo = Database::getInstance();
        return array_map('intval', $pdo->query('SELECT user_id FROM lpr_renewal_recipients')->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param int[] $userIds replaces the whole set */
    public static function replace(array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM lpr_renewal_recipients');
            if (!empty($userIds)) {
                $stmt = $pdo->prepare('INSERT IGNORE INTO lpr_renewal_recipients (user_id) VALUES (:user_id)');
                foreach ($userIds as $userId) {
                    $stmt->execute(['user_id' => $userId]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
