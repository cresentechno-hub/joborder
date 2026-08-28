<?php $success = flash('success'); $error = flash('error'); ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
  <h2 style="margin:0;">Sales Teams</h2>
  <a href="/teams/create" class="btn btn-primary">+ New Team</a>
</div>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  Sales-role users only see job orders assigned to a member of their own team. Other roles (Admin, Manager, Viewer)
  see every job order regardless of team.
</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="padding:0; overflow-x:auto;">
  <table class="data-table">
    <thead>
      <tr>
        <th>Team Name</th>
        <th>Description</th>
        <th style="text-align:center;">Members</th>
        <th>Status</th>
        <th class="actions-cell"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($teams)): ?>
        <tr><td colspan="5" style="padding:20px; text-align:center; color:var(--color-text-muted);">No sales teams yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($teams as $t): ?>
        <tr>
          <td><?= e($t['name']) ?></td>
          <td><?= e($t['description'] ?? '') ?></td>
          <td style="text-align:center;"><?= (int) $t['member_count'] ?></td>
          <td>
            <?php if ((int) $t['is_active'] === 1): ?>
              <span class="badge" style="background: var(--color-success);">Active</span>
            <?php else: ?>
              <span class="badge badge-muted">Inactive</span>
            <?php endif; ?>
          </td>
          <td class="actions-cell">
            <a href="/teams/<?= (int) $t['id'] ?>/edit">Edit</a>
            &nbsp;|&nbsp;
            <form method="POST" action="/teams/<?= (int) $t['id'] ?>/toggle-active" style="display:inline;"
                  onsubmit="return confirm('<?= (int) $t['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this team?');">
              <?= csrf_field() ?>
              <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">
                <?= (int) $t['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
