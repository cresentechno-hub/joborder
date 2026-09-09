<?php $error = flash('error'); ?>

<h2 style="margin-top:0;">Import SMC Contracts</h2>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <p style="color: var(--color-text-muted); font-size:13px;">
    Upload a CSV in the same format as <a href="/smc/export">Export CSV</a>: the first four columns are
    <code>Customer</code>, <code>Start Date</code> (YYYY-MM-DD), <code>Coverage Months</code> (12/24/36/48) and
    <code>Customer Email</code>, followed by one column per month (header <code>YYYY-MM</code>) with
    <code>BLANK</code>/<code>SCH</code>/<code>DONE</code> marking that month's status.
  </p>
  <ul style="color: var(--color-text-muted); font-size:13px; padding-left:18px;">
    <li>A row is matched to an existing contract by Customer + Start Date; if none matches, a new contract is created.</li>
    <li>A Customer name not already in the system is added automatically.</li>
    <li>Month columns outside a row's own coverage window are ignored.</li>
  </ul>

  <form method="POST" action="/smc/import" enctype="multipart/form-data" style="margin-top:16px;">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label" for="import_file">CSV File</label>
      <input class="form-control" type="file" id="import_file" name="import_file" accept=".csv,text/csv" required>
    </div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn btn-primary">Import</button>
      <a href="/smc" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
