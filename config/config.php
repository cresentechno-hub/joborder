<?php
declare(strict_types=1);

// ---------------------------------------------------------------------
// Application configuration. Plain PHP constants — no Composer, no .env
// parser.
// ---------------------------------------------------------------------

// APP_ENV / APP_DEBUG / APP_URL are environment-specific (differ between
// local dev and production) so they live in config/env.php — gitignored,
// same pattern as config/secrets.php — instead of here. This file is meant
// to be identical on every environment so `git pull` on a production
// server can never silently revert it to another environment's settings.
// Copy config/env.example.php to config/env.php and fill in real values.
if (file_exists(__DIR__ . '/env.php')) {
    require __DIR__ . '/env.php';
}
if (!defined('APP_ENV')) {
    define('APP_ENV', 'local');
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', true);
}
if (!defined('APP_URL')) {
    define('APP_URL', 'http://job-order-system');
}

define('APP_NAME', 'Job Order Management System');
define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');

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
// Using the "lite" tier deliberately: it sits on its own, separate free-tier
// quota from the full flash models (gemini-3.6-flash's free quota is a very
// tight 20 requests/day — the lite tier has proven far more usable for this
// kind of simple field extraction without paying for it).
define('GOOGLE_AI_MODEL', 'gemini-3.5-flash-lite');

// Help/FAQ chat assistant (src/Services/HelpChatService.php) — deliberately
// a DIFFERENT model from GOOGLE_AI_MODEL above. Free-tier quota is scoped
// per model, not per feature, so if chat shared the extraction model, a
// busy chat day could starve quotation/invoice auto-fill of its own quota
// (and vice versa). Same GOOGLE_AI_API_KEY as extraction.
define('GOOGLE_AI_CHAT_MODEL', 'gemini-3.1-flash-lite');
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
