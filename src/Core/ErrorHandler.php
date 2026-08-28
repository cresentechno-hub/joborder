<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Catches anything that would otherwise be a blank white page in
 * production: PHP warnings/notices, uncaught exceptions, and fatal
 * errors. Logs everything to storage/logs/app.log; shows a friendly
 * 500 page unless APP_DEBUG is on.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        self::log("PHP error [{$severity}]: {$message} in {$file}:{$line}");
        return !APP_DEBUG;
    }

    public static function handleException(Throwable $e): void
    {
        self::log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (APP_DEBUG) {
            echo '<pre style="padding:20px; white-space:pre-wrap; font-size:13px;">' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }

        require ROOT_PATH . '/views/errors/500.php';
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        self::log("Fatal error: {$error['message']} in {$error['file']}:{$error['line']}");

        if (!APP_DEBUG && !headers_sent()) {
            http_response_code(500);
            require ROOT_PATH . '/views/errors/500.php';
        }
    }

    private static function log(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents(ROOT_PATH . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
