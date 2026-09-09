<?php $success = flash('success'); $error = flash('error'); ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
  <h2 style="margin:0;">LPR Partners</h2>
  <a href="/lpr-partners/create" class="btn btn-primary">+ New Partner</a>
</div>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  Partner names used by the LPR Rental module's Partner dropdown — the rental listing is grouped by partner.
</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="padding:0; overflow-x:auto;">
  <table class="data-table client-sortable">
    <thead>
      <tr>
        <th class="actions-cell"></th>
        <th>Partner Name</th>
        <th style="text-align:center;">LPR Rentals</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($partners)): ?>
        <tr><td colspan="4" style="padding:20px; text-align:center; color:var(--color-text-muted);">No partners yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($partners as $p): ?>
        <tr>
          <td class="actions-cell">
            <a href="/lpr-partners/<?= (int) $p['id'] ?>/edit">Edit</a>
            &nbsp;|&nbsp;
            <form method="POST" action="/lpr-partners/<?= (int) $p['id'] ?>/toggle-active" style="display:inline;"
                  onsubmit="return confirm('<?= (int) $p['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this partner?');">
              <?= csrf_field() ?>
              <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">
                <?= (int) $p['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
          </td>
          <td><?= e($p['name']) ?></td>
          <td style="text-align:center;"><?= (int) $p['rental_count'] ?></td>
          <td>
            <?php if ((int) $p['is_active'] === 1): ?>
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
