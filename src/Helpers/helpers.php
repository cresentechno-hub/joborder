<?php

declare(strict_types=1);

use App\Core\Session;
use App\Core\Settings;

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * Re-populate a field after a failed form submission. $default should
 * already be pre-escaped by the caller (e.g. e($model['field'] ?? '')) —
 * it is only used when there is no flashed input, so it is never
 * re-escaped here.
 */
function old(string $key, string $default = ''): string
{
    if (isset($_SESSION['_old'][$key])) {
        $value = (string) $_SESSION['_old'][$key];
        unset($_SESSION['_old'][$key]);
        return e($value);
    }
    return $default;
}

/** Like old(), but for multi-value fields (e.g. a multi-select) — returns string[] instead of casting to a scalar. */
function old_array(string $key): array
{
    $value = $_SESSION['_old'][$key] ?? [];
    unset($_SESSION['_old'][$key]);
    return is_array($value) ? array_map('strval', $value) : [];
}

/** Stash the submitted form data so old() can re-populate the form on redirect. */
function flash_input(array $data): void
{
    $_SESSION['_old'] = $data;
}

/** Stash validation errors (field => message) for the next request. */
function flash_errors(array $errors): void
{
    $_SESSION['_errors'] = $errors;
}

/** Consume the validation errors stashed by flash_errors(). */
function form_errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return $errors;
}

/** Set a flash message ($message given) or consume one (key only). */
function flash(string $key, ?string $message = null): ?string
{
    return Session::flash($key, $message);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['_csrf'] ?? '';
    return is_string($token) && $token !== '' && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function format_money(float $amount): string
{
    return number_format($amount, 2);
}

/** Read a system setting (Settings screen), falling back to $default if unset. */
function setting(string $key, ?string $default = null): string
{
    return Settings::get($key, $default) ?? '';
}

/** Format a Y-m-d date string using the configured date_format setting. */
function format_date(?string $ymd): string
{
    if (!$ymd) {
        return '';
    }
    $date = DateTime::createFromFormat('Y-m-d', $ymd);
    return $date ? $date->format(setting('date_format', 'd-m-Y')) : $ymd;
}

/** The <input accept> attribute value derived from the allowed_upload_types setting. */
function upload_accept_attr(): string
{
    $types = array_filter(array_map('trim', explode(',', setting('allowed_upload_types', 'pdf,jpg,jpeg,png'))));
    return implode(',', array_map(static fn (string $t): string => '.' . $t, $types));
}
