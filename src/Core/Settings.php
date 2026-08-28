<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thin cache over the `settings` table. Booted once per request in
 * bootstrap.php so every setting (timezone, app name, pagination size,
 * upload limits, stage-pending alert threshold) is live-editable from
 * the Settings screen without a redeploy.
 */
final class Settings
{
    private static ?array $cache = null;

    public static function boot(): void
    {
        $pdo = Database::getInstance();
        $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();

        self::$cache = [];
        foreach ($rows as $row) {
            self::$cache[$row['setting_key']] = $row['setting_value'];
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            self::boot();
        }
        return self::$cache[$key] ?? $default;
    }

    public static function all(): array
    {
        if (self::$cache === null) {
            self::boot();
        }
        return self::$cache;
    }

    public static function set(string $key, string $value): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute(['key' => $key, 'value' => $value]);

        if (self::$cache === null) {
            self::boot();
        }
        self::$cache[$key] = $value;
    }

    /** @param array<string,string> $pairs */
    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            self::set($key, (string) $value);
        }
    }
}
