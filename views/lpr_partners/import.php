<?php $error = flash('error'); ?>

<h2 style="margin-top:0;">Import LPR Partners</h2>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <p style="color: var(--color-text-muted); font-size:13px;">
    Upload a CSV in the same format as <a href="/lpr-partners/export">Export CSV</a>: two columns,
    <code>Partner Name</code> and <code>Active</code> (<code>1</code>/<code>0</code>, or Yes/No — defaults to
    active if left blank).
  </p>
  <ul style="color: var(--color-text-muted); font-size:13px; padding-left:18px;">
    <li>A row is matched to an existing partner by exact name; if none matches, a new partner is added.</li>
    <li>Matching an existing partner updates its Active status to whatever the row says.</li>
  </ul>

  <form method="POST" action="/lpr-partners/import" enctype="multipart/form-data" style="margin-top:16px;">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label" for="import_file">CSV File</label>
      <input class="form-control" type="file" id="import_file" name="import_file" accept=".csv,text/csv" required>
    </div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn btn-primary">Import</button>
      <a href="/lpr-partners" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
