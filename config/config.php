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
define('UPLOAD_QUOTATION_DIR', ROOT_PATH . '/public/uploads/quotations');
define('UPLOAD_PO_DIR', ROOT_PATH . '/public/uploads/po');
// Relative to /public — used to build browser-facing links to uploaded files
define('UPLOAD_QUOTATION_REL', 'uploads/quotations');
define('UPLOAD_PO_REL', 'uploads/po');

define('STAGE_PENDING_ALERT_DAYS', 7);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

define('DB_CONFIG', require __DIR__ . '/database.php');
