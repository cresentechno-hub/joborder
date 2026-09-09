<?php
$errors = form_errors();
$success = flash('success');
$s = static fn (string $key): string => old($key, e($settings[$key] ?? ''));
?>

<h2 style="margin-top:0;">System Settings</h2>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <ul style="margin:0; padding-left:18px;">
      <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="/settings">
    <?= csrf_field() ?>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="app_name">Application Name</label>
        <input class="form-control" type="text" id="app_name" name="app_name" value="<?= $s('app_name') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="timezone">Timezone</label>
        <input class="form-control" type="text" id="timezone" name="timezone" value="<?= $s('timezone') ?>" placeholder="e.g. Asia/Kuala_Lumpur" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="date_format">Date Format</label>
        <input class="form-control" type="text" id="date_format" name="date_format" value="<?= $s('date_format') ?>" placeholder="e.g. d-m-Y">
        <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">PHP date() format, used on the Job Orders list.</div>
      </div>
      <div class="form-group">
        <label class="form-label" for="items_per_page">Items Per Page</label>
        <input class="form-control" type="number" min="1" id="items_per_page" name="items_per_page" value="<?= $s('items_per_page') ?>" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="stage_pending_alert_days">Stage Pending Alert (days)</label>
        <input class="form-control" type="number" min="1" id="stage_pending_alert_days" name="stage_pending_alert_days" value="<?= $s('stage_pending_alert_days') ?>" required>
        <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">A job stuck in one stage past this many days is flagged on the Dashboard.</div>
      </div>
      <div class="form-group">
        <label class="form-label" for="upload_max_size_mb">Max Upload Size (MB)</label>
        <input class="form-control" type="number" min="1" step="0.5" id="upload_max_size_mb" name="upload_max_size_mb" value="<?= $s('upload_max_size_mb') ?>" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="renewal_reminder_months">Renewal Reminder Window (months)</label>
        <input class="form-control" type="number" min="1" id="renewal_reminder_months" name="renewal_reminder_months" value="<?= $s('renewal_reminder_months') ?>" required>
        <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">LPR/SMC contracts within this many months of their coverage end date are flagged on the Dashboard and emailed to staff by the renewal reminder cron job.</div>
      </div>
    </div>

    <div style="margin-bottom:20px;">
      <a href="/settings/lpr-renewal-recipients">Manage LPR Renewal Recipients &rarr;</a>
      <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">Pick exactly who receives LPR renewal reminders, independent of role. SMC renewal reminders always go to everyone with SMC management permission.</div>
    </div>

    <div class="form-group">
      <label class="form-label" for="allowed_upload_types">Allowed Upload File Types</label>
      <input class="form-control" type="text" id="allowed_upload_types" name="allowed_upload_types" value="<?= $s('allowed_upload_types') ?>" placeholder="pdf,jpg,jpeg,png" required>
      <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">Comma-separated extensions, no dots (applies to the Quotation and PO uploads).</div>
    </div>

    <button type="submit" class="btn btn-primary">Save Settings</button>
  </form>
</div>
