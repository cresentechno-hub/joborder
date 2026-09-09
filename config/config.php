<?php
declare(strict_types=1);

// ---------------------------------------------------------------------
// Application configuration. Plain PHP constants — no Composer, no .env
// parser. Edit directly per environment (local / production).
// ---------------------------------------------------------------------

define('APP_ENV', 'local');              // local | production
define('APP_DEBUG', true);               // set to false in production
define('APP_NAME', 'Job Order Management System');
define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');

// Matches the Laragon auto-vhost for this project folder
define('APP_URL', 'http://job-order-system');

define('UPLOAD_MAX_SIZE_MB', 10);
define('UPLOAD_ALLOWED_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);
// Every job order gets its own folder here, named after its quotation no
// (e.g. uploads/QF0106-2026/{quotation,po,invoices,do,other}/...) — see
// job_order_upload_dir()/job_order_upload_rel() in Helpers/helpers.php.
define('UPLOAD_BASE_DIR', ROOT_PATH . '/public/uploads');
// Relative to /public — used to build browser-facing links to uploaded files
define('UPLOAD_BASE_REL', 'uploads');

define('STAGE_PENDING_ALERT_DAYS', 7);

// Path to the pdftotext binary (poppler-utils), used to auto-fill the
// quotation no / customer name / total cost from an uploaded quotation PDF.
// On this Laragon dev machine it ships bundled with Git for Windows.
// On a Linux production server: `apt install poppler-utils` (Debian/Ubuntu)
// or `yum install poppler-utils` (RHEL/CentOS), then this can just be
// 'pdftotext' since it lands on the system PATH.
define('PDFTOTEXT_BINARY', 'C:\\laragon\\bin\\git\\mingw64\\bin\\pdftotext.exe');

// Claude-based quotation extraction (src/Services/AnthropicClient.php) —
// currently unused (the extractors call Gemini below instead), left
// configured in case of a future switch back.
define('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001');
define('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages');

// Gemini-based quotation/invoice extraction (src/Services/GoogleAiClient.php,
// AiQuotationExtractor.php, AiInvoiceExtractor.php) — copy
// config/secrets.example.php to config/secrets.php and fill in a real
// GOOGLE_AI_API_KEY (from https://aistudio.google.com/apikey) to enable it.
// Without a key, quotation auto-fill falls back to the pdftotext-only path
// (src/Services/QuotationExtractor.php); invoice auto-fill falls back to a
// filename-derived guess.
define('GOOGLE_AI_MODEL', 'gemini-3.6-flash');
define('GOOGLE_AI_API_URL', 'https://generativelanguage.googleapis.com/v1beta');

// Outbound email (src/Services/Mailer.php) — job order assignment
// notifications + the LPR/SMC renewal reminder cron script. Host/port/etc.
// aren't sensitive; the username/password are, so those two live in
// secrets.php only (see below).
define('SMTP_HOST', 'mail.cresentech.com.my');
define('SMTP_PORT', 465);
define('SMTP_ENCRYPTION', 'ssl'); // 'tls' or 'ssl' — cPanel's "Secure SSL/TLS (Recommended)" preset uses SSL on 465
define('SMTP_FROM_EMAIL', 'noreply@cresentech.com.my');
define('SMTP_FROM_NAME', 'Job Order Management System');

if (file_exists(__DIR__ . '/secrets.php')) {
    require __DIR__ . '/secrets.php';
}
// Each secret defaults independently — an older secrets.php that only
// defines ANTHROPIC_API_KEY (from before email support was added) shouldn't
// leave SMTP_USERNAME/SMTP_PASSWORD undefined and fatal the app.
if (!defined('ANTHROPIC_API_KEY')) {
    define('ANTHROPIC_API_KEY', '');
}
if (!defined('GOOGLE_AI_API_KEY')) {
    define('GOOGLE_AI_API_KEY', '');
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', '');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', '');
}

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

define('DB_CONFIG', require __DIR__ . '/database.php');
