<?php
declare(strict_types=1);

// ---------------------------------------------------------------------
// Single entry point every request/script goes through: constants,
// manual PSR-4-ish autoloader (no Composer), helpers, session, timezone.
// ---------------------------------------------------------------------

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');

require ROOT_PATH . '/config/config.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = SRC_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Vendored PHPMailer (no Composer in this project — see src/Vendor/PHPMailer/VERSION.txt).
spl_autoload_register(function (string $class): void {
    $prefix = 'PHPMailer\\PHPMailer\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = SRC_PATH . '/Vendor/PHPMailer/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require SRC_PATH . '/Helpers/helpers.php';

if (PHP_SAPI !== 'cli') {
    \App\Core\ErrorHandler::register();
}

\App\Core\Settings::boot();

date_default_timezone_set(setting('timezone', APP_TIMEZONE));

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? null) === '443';

    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => $isHttps,
    ]);
}
