<?php
declare(strict_types=1);

// Copy this file to secrets.php (gitignored — never commit real keys) and
// fill in a real key from https://console.anthropic.com/settings/keys.
// Powers the Claude-based "Read Quotation & Auto-Fill" extraction
// (src/Services/AiQuotationExtractor.php). Leave blank / don't create
// secrets.php at all to run without it — auto-fill just falls back to the
// pdftotext-only path (src/Services/QuotationExtractor.php).

define('ANTHROPIC_API_KEY', '');

// SMTP credentials for outbound email (src/Services/Mailer.php) — job order
// assignment notifications + the LPR/SMC renewal reminder cron script.
// Host/port/encryption/from-address live in config/config.php since they
// aren't sensitive; only the login itself goes here. Leave blank to run
// without email — sends are best-effort and never block the app.
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
