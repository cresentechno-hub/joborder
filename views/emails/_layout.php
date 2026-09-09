<?php
/**
 * Shared HTML wrapper for all emails. Expects $content (a string of
 * already-rendered inner HTML) in scope — set by whichever template
 * includes this. Styles are inline throughout; email clients don't
 * reliably support external stylesheets or even <style> blocks.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(APP_NAME) ?></title>
</head>
<body style="margin:0; padding:0; background:#F8FAFC; font-family:'Segoe UI', system-ui, -apple-system, Roboto, Arial, sans-serif; color:#1E293B;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8FAFC; padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%; background:#FFFFFF; border:1px solid #E2E8F0; border-radius:8px; overflow:hidden;">
          <tr>
            <td style="background:#1E293B; padding:18px 28px;">
              <span style="color:#FFFFFF; font-size:16px; font-weight:700; letter-spacing:.02em;">CRESENTECH</span>
              <span style="color:#94A3B8; font-size:12px; margin-left:8px;">Job Order Management System</span>
            </td>
          </tr>
          <tr>
            <td style="padding:28px;">
              <?= $content ?>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 28px; border-top:1px solid #E2E8F0; color:#64748B; font-size:12px;">
              This is an automated message from <?= e(APP_NAME) ?>. Please don't reply to this email.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
