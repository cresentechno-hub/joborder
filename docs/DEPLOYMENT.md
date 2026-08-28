# Deployment Notes

## Requirements
- PHP 8.1+ with `pdo_mysql` and `fileinfo` extensions enabled
- MySQL 8.0+ (or MariaDB 10.6+)
- Apache with `mod_rewrite` and `mod_headers` enabled
- `poppler-utils` (for the `pdftotext` command) — powers the "Read Quotation & Auto-Fill" button on the New Job Order form. Install with `apt install poppler-utils` (Debian/Ubuntu) or `yum install poppler-utils` (RHEL/CentOS), then set `PDFTOTEXT_BINARY` in `config/config.php` to `'pdftotext'` (it'll be on the system PATH). Without this, the auto-fill button simply does nothing useful — manual entry still works fine.

## First-time setup
1. Copy the project to the server. The web root **must** point at the `public/` folder — never the project root, or the `src/`, `config/`, and `database/` folders become downloadable.
2. Create the database and import the schema:
   ```
   mysql -u <user> -p < database/schema.sql
   ```
3. Edit `config/database.php` with the production DB credentials.
4. Edit `config/config.php`:
   - Set `APP_DEBUG` to `false` (stops stack traces from being shown to visitors)
   - Set `APP_ENV` to `production`
   - Set `APP_URL` to the real domain
5. Create the first Admin login:
   ```
   php database/seed_admin.php
   ```
6. Make `storage/logs/` and `public/uploads/` writable by the web server user.
7. Log in and set the real system settings under **Settings** (app name, timezone, upload limits) — these are stored in the database and take effect immediately, no redeploy needed.

## What's already handled
- **HTTPS**: session cookies automatically get the `Secure` flag when the request is served over HTTPS — no config change needed, just put the site behind TLS.
- **Errors**: uncaught exceptions are logged to `storage/logs/app.log` and show a generic 500 page instead of a stack trace, as long as `APP_DEBUG` is `false`.
- **Brute-force login protection**: an account locks for 15 minutes after 5 failed password attempts.
- **File upload validation**: uploaded quotation/PO files are checked against their actual content (not just filename), so a renamed `.php` can't pass as a `.pdf`.
- **Security headers**: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` are set via `public/.htaccess`.
- **PHP execution is blocked** inside the upload folders (`public/uploads/quotations`, `public/uploads/po`) via their own `.htaccess`.

## Backups
Two things need backing up: the `job_order_system` database, and the `public/uploads/` folder (the actual quotation/PO files — only their paths live in the database). Back up both together so they stay in sync.

## Rolling back a bad Settings change
All Settings values live in the `settings` table. If a bad value locks something up (e.g. an invalid timezone was somehow saved), fix it directly with SQL rather than through the UI:
```sql
UPDATE settings SET setting_value = '<value>' WHERE setting_key = '<key>';
```
