<?php
/**
 * Staff-only LPR/SMC renewal reminder digest.
 * Expects: $recipientName, $lprItems, $smcItems — each an array of rows
 * with: customer_name, end_date, edit_url, and (LPR only) partner_name.
 */
ob_start();
?>
<h2 style="margin:0 0 12px; font-size:18px; color:#1E293B;">Contracts due for renewal</h2>
<p style="margin:0 0 18px; font-size:14px; line-height:1.6; color:#334155;">
  Hi <?= e($recipientName) ?> — the following contracts are within their renewal window.
</p>

<?php if (!empty($lprItems)): ?>
  <div style="font-size:12px; color:#64748B; text-transform:uppercase; letter-spacing:.04em; margin:0 0 8px;">LPR Rental</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px; border-collapse:collapse;">
    <?php foreach ($lprItems as $item): ?>
      <tr>
        <td style="padding:10px 0; border-top:1px solid #E2E8F0; font-size:14px;">
          <div style="color:#1E293B; font-weight:600;"><?= e($item['customer_name']) ?> <span style="font-weight:400; color:#64748B;">via <?= e($item['partner_name']) ?></span></div>
          <div style="color:#64748B; font-size:13px; margin-top:2px;">Coverage ends <?= e(format_date($item['end_date'])) ?></div>
        </td>
        <td style="padding:10px 0; border-top:1px solid #E2E8F0; text-align:right; white-space:nowrap;">
          <a href="<?= e($item['edit_url']) ?>" style="color:#B45309; font-size:13px; font-weight:600; text-decoration:none;">View &rarr;</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php if (!empty($smcItems)): ?>
  <div style="font-size:12px; color:#64748B; text-transform:uppercase; letter-spacing:.04em; margin:0 0 8px;">SMC</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px; border-collapse:collapse;">
    <?php foreach ($smcItems as $item): ?>
      <tr>
        <td style="padding:10px 0; border-top:1px solid #E2E8F0; font-size:14px;">
          <div style="color:#1E293B; font-weight:600;"><?= e($item['customer_name']) ?></div>
          <div style="color:#64748B; font-size:13px; margin-top:2px;">Coverage ends <?= e(format_date($item['end_date'])) ?></div>
        </td>
        <td style="padding:10px 0; border-top:1px solid #E2E8F0; text-align:right; white-space:nowrap;">
          <a href="<?= e($item['edit_url']) ?>" style="color:#B45309; font-size:13px; font-weight:600; text-decoration:none;">View &rarr;</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/emails/_layout.php';
