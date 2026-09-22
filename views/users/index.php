<?php $success = flash('success'); $error = flash('error'); ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
  <h2 style="margin:0;">Users</h2>
  <a href="/users/create" class="btn btn-primary">+ New User</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="padding:0; overflow-x:auto;">
  <table class="data-table client-sortable">
    <thead>
      <tr>
        <th class="actions-cell"></th>
        <th>Full Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Last Login</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td class="actions-cell">
            <a href="/users/<?= (int) $u['id'] ?>/edit">Edit</a>
            &nbsp;|&nbsp;
            <form method="POST" action="/users/<?= (int) $u['id'] ?>/toggle-active" style="display:inline;"
                  onsubmit="return confirm('<?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this user?');">
              <?= csrf_field() ?>
              <button type="submit" class="btn-link-danger" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">
                <?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
            <?php if ($isAdmin && (int) $u['id'] !== (int) \App\Core\Auth::id()): ?>
              &nbsp;|&nbsp;
              <form method="POST" action="/users/<?= (int) $u['id'] ?>/delete" style="display:inline;"
                    onsubmit="return confirm('Delete this user? This cannot be undone.');">
                <?= csrf_field() ?>
                <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">Delete</button>
              </form>
            <?php endif; ?>
          </td>
          <td><?= e($u['full_name']) ?></td>
          <td><?= e($u['username']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="badge-outline"><?= e($u['role_name']) ?></span></td>
          <td>
            <?php if ((int) $u['is_active'] === 1): ?>
              <span class="badge" style="background: var(--color-success);">Active</span>
            <?php else: ?>
              <span class="badge badge-muted">Inactive</span>
            <?php endif; ?>
          </td>
          <td><?= $u['last_login_at'] ? e($u['last_login_at']) : '-' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
