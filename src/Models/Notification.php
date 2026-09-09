<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * In-app copy of every email this app sends (job order assignment,
 * LPR/SMC renewal reminders) — created independently of whether the
 * matching Mailer::send() call actually succeeds. This is a second,
 * more reliable channel, not a delivery receipt for the email.
 */
final class Notification
{
    public static function create(int $userId, string $type, string $title, ?string $message, ?string $linkUrl): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link_url)
             VALUES (:user_id, :type, :title, :message, :link_url)'
        );
        $stmt->execute([
            'user_id'  => $userId,
            'type'     => $type,
            'title'    => $title,
            'message'  => $message,
            'link_url' => $linkUrl,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function unreadCountForUser(int $userId): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function recentForUser(int $userId, int $limit = 15): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Scoped to the owning user — a notification can only ever be marked read by its own recipient. */
    public static function markRead(int $id, int $userId): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
        $stmt->execute(['user_id' => $userId]);
    }
}
