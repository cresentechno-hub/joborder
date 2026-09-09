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
    $rel = ltrim($path, '/');
    $full = __DIR__ . '/../../public/assets/' . $rel;
    $v = is_file($full) ? filemtime($full) : time();
    return '/assets/' . $rel . '?v=' . $v;
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

/** Formats a 'YYYY-MM' string as a short label, e.g. '2026-01' -> 'Jan 2026'. */
function lpr_month_label(string $yearMonth): string
{
    $date = DateTime::createFromFormat('Y-m-d', $yearMonth . '-01');
    return $date ? $date->format('M Y') : $yearMonth;
}

/** Turns a quotation no (which may contain '/', e.g. "Q23619/2026") into a safe folder name. */
function job_order_folder_name(string $quotationNo): string
{
    $clean = preg_replace('/[\/\\\\:*?"<>|]/', '-', trim($quotationNo)) ?? $quotationNo;
    $clean = trim($clean, ' .-');
    return $clean !== '' ? $clean : 'unknown';
}

/** Absolute path to a job order's per-category upload folder (created on demand by FileUploadService::upload()). */
function job_order_upload_dir(string $quotationNo, string $category): string
{
    return UPLOAD_BASE_DIR . '/' . job_order_folder_name($quotationNo) . '/' . $category;
}

/** Same as job_order_upload_dir(), but the /public-relative path used to build browser-facing links. */
function job_order_upload_rel(string $quotationNo, string $category): string
{
    return UPLOAD_BASE_REL . '/' . job_order_folder_name($quotationNo) . '/' . $category;
}

/**
 * Renders a sortable column header link for a paginated table: clicking it
 * sets sort=$column (toggling dir=asc/desc on repeat clicks of the same
 * column) while preserving every other filter already in $currentQuery,
 * and resetting to page 1 since the result order changed.
 */
function sortable_th(string $baseUrl, string $column, string $label, array $currentQuery): string
{
    $currentSort = (string) ($currentQuery['sort'] ?? '');
    $currentDir = (string) ($currentQuery['dir'] ?? 'asc');
    $newDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';

    $qs = array_merge($currentQuery, ['sort' => $column, 'dir' => $newDir, 'page' => 1]);
    $qs = array_filter($qs, static fn ($v): bool => $v !== null && $v !== '');
    $url = $baseUrl . '?' . http_build_query($qs);

    $arrow = '';
    if ($currentSort === $column) {
        $arrow = ' <span class="sort-arrow">' . ($currentDir === 'asc' ? '&#9650;' : '&#9660;') . '</span>';
    }

    return '<a href="' . e($url) . '" class="sort-link">' . e($label) . $arrow . '</a>';
}

/** The <input accept> attribute value derived from the allowed_upload_types setting. */
function upload_accept_attr(): string
{
    $types = array_filter(array_map('trim', explode(',', setting('allowed_upload_types', 'pdf,jpg,jpeg,png'))));
    return implode(',', array_map(static fn (string $t): string => '.' . $t, $types));
}
