<?php $success = flash('success'); ?>

<h2 style="margin-top:0;">Roles &amp; Permissions</h2>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  Controls what each role can do across the system. Assign users to a role from the
  <a href="/users">Users</a> page.
</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
  <?php foreach ($roles as $role): ?>
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:start;">
        <div>
          <h3 style="margin:0 0 4px;"><?= e($role['name']) ?></h3>
          <p style="margin:0; color: var(--color-text-muted); font-size:13px;"><?= e($role['description'] ?? '') ?></p>
        </div>
        <a href="/roles/<?= (int) $role['id'] ?>/permissions" class="btn btn-outline" style="padding:6px 12px; white-space:nowrap;">Edit</a>
      </div>
      <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:6px;">
        <?php if (empty($role['permissions'])): ?>
          <span style="color: var(--color-text-muted); font-size:13px;">No permissions granted.</span>
        <?php endif; ?>
        <?php foreach ($role['permissions'] as $code): ?>
          <span class="badge-outline"><?= e($code) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
