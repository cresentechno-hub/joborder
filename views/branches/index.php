<?php $success = flash('success'); $error = flash('error'); ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
  <h2 style="margin:0;">Branches</h2>
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a href="/branches/export" class="btn btn-outline">Export CSV</a>
    <a href="/branches/import" class="btn btn-outline">Import</a>
    <a href="/branches/create" class="btn btn-primary">+ New Branch</a>
  </div>
</div>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  Sales-role users only see job orders/LPR rentals/SMC contracts belonging to their own branch. Other roles
  (Admin, Manager, Viewer) see every branch.
</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="padding:0; overflow-x:auto;">
  <table class="data-table client-sortable">
    <thead>
      <tr>
        <th class="actions-cell"></th>
        <th>Branch Name</th>
        <th style="text-align:center;">Members</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($branches)): ?>
        <tr><td colspan="4" style="padding:20px; text-align:center; color:var(--color-text-muted);">No branches yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($branches as $b): ?>
        <tr>
          <td class="actions-cell">
            <a href="/branches/<?= (int) $b['id'] ?>/edit">Edit</a>
            &nbsp;|&nbsp;
            <form method="POST" action="/branches/<?= (int) $b['id'] ?>/toggle-active" style="display:inline;"
                  onsubmit="return confirm('<?= (int) $b['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this branch?');">
              <?= csrf_field() ?>
              <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">
                <?= (int) $b['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
            &nbsp;|&nbsp;
            <form method="POST" action="/branches/<?= (int) $b['id'] ?>/delete" style="display:inline;"
                  onsubmit="return confirm('Permanently delete this branch? This cannot be undone, and will fail if it still has users, job orders, or contracts assigned.');">
              <?= csrf_field() ?>
              <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">Delete</button>
            </form>
          </td>
          <td><?= e($b['name']) ?></td>
          <td style="text-align:center;"><?= (int) $b['member_count'] ?></td>
          <td>
            <?php if ((int) $b['is_active'] === 1): ?>
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
