<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    /** Result codes returned by attempt(). */
    public const OK = 'ok';
    public const INVALID = 'invalid';
    public const LOCKED = 'locked';
    public const INACTIVE = 'inactive';

    private static ?array $userCache = null;

    public static function attempt(string $username, string $password): string
    {
        $user = User::findByUsername($username);

        if (!$user) {
            return self::INVALID;
        }

        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            return self::LOCKED;
        }

        if (!(int) $user['is_active']) {
            return self::INACTIVE;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $justLocked = User::registerFailedLogin((int) $user['id']);
            return $justLocked ? self::LOCKED : self::INVALID;
        }

        User::resetFailedLogins((int) $user['id']);
        session_regenerate_id(true);
        Session::put('user_id', (int) $user['id']);
        User::touchLastLogin((int) $user['id']);
        self::$userCache = null;

        return self::OK;
    }

    public static function logout(): void
    {
        self::$userCache = null;
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    /** Current user row plus 'role_name' and a flat 'permissions' code array. */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        if (self::$userCache === null) {
            self::$userCache = User::findWithRoleById((int) self::id());
        }

        return self::$userCache;
    }

    public static function can(string $permissionCode): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        return in_array($permissionCode, $user['permissions'] ?? [], true);
    }
}
