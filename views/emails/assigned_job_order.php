<?php
/**
 * "You've been assigned" notification.
 * Expects: $jobOrder (array: id, quotation_no, customer_name, subject),
 * $assignedByName.
 */
$editUrl = rtrim(APP_URL, '/') . '/job-orders/' . (int) $jobOrder['id'] . '/edit';

ob_start();
?>
<h2 style="margin:0 0 12px; font-size:18px; color:#1E293B;">You've been assigned a job order</h2>
<p style="margin:0 0 18px; font-size:14px; line-height:1.6; color:#334155;">
  <strong><?= e($assignedByName) ?></strong> assigned you to a job order.
</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; margin-bottom:20px;">
  <tr>
    <td style="padding:16px 18px;">
      <div style="font-size:12px; color:#64748B; text-transform:uppercase; letter-spacing:.04em; margin-bottom:2px;">Quotation No</div>
      <div style="font-size:15px; font-weight:600; color:#1E293B; margin-bottom:12px;"><?= e($jobOrder['quotation_no']) ?></div>
      <div style="font-size:12px; color:#64748B; text-transform:uppercase; letter-spacing:.04em; margin-bottom:2px;">Customer</div>
      <div style="font-size:15px; color:#1E293B; margin-bottom:12px;"><?= e($jobOrder['customer_name']) ?></div>
      <div style="font-size:12px; color:#64748B; text-transform:uppercase; letter-spacing:.04em; margin-bottom:2px;">Subject</div>
      <div style="font-size:15px; color:#1E293B;"><?= e($jobOrder['subject']) ?></div>
    </td>
  </tr>
</table>
<a href="<?= e($editUrl) ?>" style="display:inline-block; background:#D97706; color:#FFFFFF; font-size:14px; font-weight:600; text-decoration:none; padding:10px 20px; border-radius:6px;">
  View Job Order
</a>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/emails/_layout.php';
