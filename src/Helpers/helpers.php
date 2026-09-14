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
 * Same shape as job_order_upload_dir()/job_order_upload_rel(), but scoped
 * by numeric id instead of a quotation-no-style text key — neither
 * lpr_rentals nor smc_contracts has a natural unique text field to fold
 * into the folder name, so the row's own id is used once it exists
 * (meaning a contract file upload at create time is necessarily a
 * two-step sequence: insert the row, then upload/update the path).
 */
function lpr_rental_upload_dir(int $id, string $category): string
{
    return UPLOAD_BASE_DIR . '/lpr-rentals/' . $id . '/' . $category;
}

function lpr_rental_upload_rel(int $id, string $category): string
{
    return UPLOAD_BASE_REL . '/lpr-rentals/' . $id . '/' . $category;
}

function smc_contract_upload_dir(int $id, string $category): string
{
    return UPLOAD_BASE_DIR . '/smc-contracts/' . $id . '/' . $category;
}

function smc_contract_upload_rel(int $id, string $category): string
{
    return UPLOAD_BASE_REL . '/smc-contracts/' . $id . '/' . $category;
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

/**
 * Standard-kit icon set — Feather-style 24x24 stroke icons rendered inline so
 * app.css never depends on an icon-font CDN. Usage: <?= icon('plus') ?>
 */
function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'plus'              => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'edit'              => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'eye'               => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'trash-2'           => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'check'             => '<polyline points="20 6 9 17 4 12"/>',
        'search'            => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'download'          => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'log-out'           => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu'              => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'bell'              => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'layout-dashboard'  => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
        'users'             => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'              => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'briefcase'         => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'map-pin'           => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'building'          => '<rect x="4" y="2" width="16" height="20" rx="1"/><line x1="9" y1="6" x2="9" y2="6.01"/><line x1="15" y1="6" x2="15" y2="6.01"/><line x1="9" y1="10" x2="9" y2="10.01"/><line x1="15" y1="10" x2="15" y2="10.01"/><line x1="9" y1="14" x2="9" y2="14.01"/><line x1="15" y1="14" x2="15" y2="14.01"/><line x1="9" y1="18" x2="15" y2="18"/>',
        'handshake'         => '<path d="M11 17l-2 2a2.828 2.828 0 1 1-4-4l4.5-4.5"/><path d="M13 17l2 2a2.828 2.828 0 1 0 4-4l-6-6-3 3-2-2 3.5-3.5a3 3 0 0 1 4.24 0L22 8"/>',
        'shield'            => '<path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/>',
        'settings'          => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'clock'             => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'help-circle'       => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'x'                 => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'chevron-left'      => '<polyline points="15 18 9 12 15 6"/>',
        'chevron-right'     => '<polyline points="9 18 15 12 9 6"/>',
        'arrow-left'        => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'alert-circle'      => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'alert-triangle'    => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'info'              => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'mail'              => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22 6 12 13 2 6"/>',
        'lock'              => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'file-text'         => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'car'               => '<path d="M5 17H3v-5l2-5h12l3 5v5h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/><line x1="10" y1="17.5" x2="15" y2="17.5"/>',
    ];

    $body = $paths[$name] ?? $paths['alert-circle'];

    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}
