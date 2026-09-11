<?php
declare(strict_types=1);

// Copy this file to env.php (gitignored — never commit) and adjust for
// this environment. Without env.php at all, config.php falls back to
// safe local-dev defaults (APP_ENV=local, APP_DEBUG=true).

define('APP_ENV', 'production');         // local | production
define('APP_DEBUG', false);              // true only on local dev — never on a real server
define('APP_URL', 'https://joborder.cresentech.com.my');
