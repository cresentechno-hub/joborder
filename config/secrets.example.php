<?php
declare(strict_types=1);

// Copy this file to secrets.php (gitignored — never commit real keys).

// Unused unless the extractors are switched back to Claude — see
// GOOGLE_AI_API_KEY below for the key that currently powers extraction.
define('ANTHROPIC_API_KEY', '');

// Fill in a real key from https://aistudio.google.com/apikey. Powers the
// Gemini-based "Read Quotation & Auto-Fill" and "Read Invoice & Auto-Fill"
// extraction (src/Services/AiQuotationExtractor.php,
// src/Services/AiInvoiceExtractor.php). Leave blank / don't create
// secrets.php at all to run without it — quotation auto-fill falls back to
// the pdftotext-only path (src/Services/QuotationExtractor.php), invoice
// auto-fill falls back to a filename-derived guess.
define('GOOGLE_AI_API_KEY', '');

// SMTP credentials for outbound email (src/Services/Mailer.php) — job order
// assignment notifications + the LPR/SMC renewal reminder cron script.
// Host/port/encryption/from-address live in config/config.php since they
// aren't sensitive; only the login itself goes here. Leave blank to run
// without email — sends are best-effort and never block the app.
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
