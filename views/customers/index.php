<?php $success = flash('success'); $error = flash('error'); ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
  <h2 style="margin:0;">Customers</h2>
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a href="/customers/export" class="btn btn-outline">Export CSV</a>
    <a href="/customers/import" class="btn btn-outline">Import</a>
    <a href="/customers/create" class="btn btn-primary">+ New Customer</a>
  </div>
</div>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  Shared customer master list — used by the LPR Rental module's Customer dropdown (and available to any other module going forward).
</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="padding:0; overflow-x:auto;">
  <table class="data-table client-sortable">
    <thead>
      <tr>
        <th class="actions-cell"></th>
        <th>Customer Name</th>
        <th style="text-align:center;">LPR Rentals</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($customers)): ?>
        <tr><td colspan="4" style="padding:20px; text-align:center; color:var(--color-text-muted);">No customers yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td class="actions-cell">
            <a href="/customers/<?= (int) $c['id'] ?>/edit">Edit</a>
            &nbsp;|&nbsp;
            <form method="POST" action="/customers/<?= (int) $c['id'] ?>/toggle-active" style="display:inline;"
                  onsubmit="return confirm('<?= (int) $c['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this customer?');">
              <?= csrf_field() ?>
              <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">
                <?= (int) $c['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
            &nbsp;|&nbsp;
            <form method="POST" action="/customers/<?= (int) $c['id'] ?>/delete" style="display:inline;"
                  onsubmit="return confirm('Permanently delete this customer? This cannot be undone, and will fail if it still has LPR rentals or SMC contracts.');">
              <?= csrf_field() ?>
              <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">Delete</button>
            </form>
          </td>
          <td><?= e($c['name']) ?></td>
          <td style="text-align:center;"><?= (int) $c['rental_count'] ?></td>
          <td>
            <?php if ((int) $c['is_active'] === 1): ?>
              <span class="badge" style="background: var(--color-success);">Active</span>
            <?php else: ?>
              <span class="badge badge-muted">Inactive</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
