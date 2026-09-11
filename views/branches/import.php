<?php $error = flash('error'); ?>

<h2 style="margin-top:0;">Import Branches</h2>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <p style="color: var(--color-text-muted); font-size:13px;">
    Upload a CSV in the same format as <a href="/branches/export">Export CSV</a>: two columns,
    <code>Branch Name</code> and <code>Active</code> (<code>1</code>/<code>0</code>, or Yes/No — defaults to
    active if left blank).
  </p>
  <ul style="color: var(--color-text-muted); font-size:13px; padding-left:18px;">
    <li>A row is matched to an existing branch by exact name; if none matches, a new branch is added.</li>
    <li>Matching an existing branch updates its Active status to whatever the row says.</li>
  </ul>

  <form method="POST" action="/branches/import" enctype="multipart/form-data" style="margin-top:16px;">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label" for="import_file">CSV File</label>
      <input class="form-control" type="file" id="import_file" name="import_file" accept=".csv,text/csv" required>
    </div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn btn-primary">Import</button>
      <a href="/branches" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
